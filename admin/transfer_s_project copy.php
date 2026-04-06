<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$user = $_SESSION['fullname'];
$site = $_SESSION['site'];

/* =====================================================
🔐 TOKEN กันยิงซ้ำ
===================================================== */
if(empty($_SESSION['form_token'])){
    $_SESSION['form_token'] = bin2hex(random_bytes(32));
}

/* =====================================================
📍 โหลดโครงการ
===================================================== */
$projects = $conn->query("
SELECT DISTINCT site FROM Employee WHERE site IS NOT NULL ORDER BY site
")->fetchAll(PDO::FETCH_COLUMN);

/* =====================================================
📦 โหลดอุปกรณ์ (เพิ่ม use_it)
===================================================== */
$stmt = $conn->query("
SELECT no_pc,type_equipment,spec,ram,ssd,gpu,use_it
FROM IT_assets
WHERE no_pc IS NOT NULL
");

/* =====================================================
📊 GROUP TYPE
===================================================== */
$priority = ['PC','CCTV','MONITOR','NOTEBOOK','NVR','PRINTER','UPS'];

$grouped = [];

while($r = $stmt->fetch(PDO::FETCH_ASSOC)){
    $type = strtoupper(trim($r['type_equipment'] ?: 'อื่นๆ'));
    $grouped[$type][] = $r;
}

uksort($grouped,function($a,$b) use ($priority){
    $pa = array_search($a,$priority);
    $pb = array_search($b,$priority);
    $pa = $pa===false?999:$pa;
    $pb = $pb===false?999:$pb;
    return $pa <=> $pb;
});

/* =====================================================
📨 SUBMIT
===================================================== */
if($_SERVER['REQUEST_METHOD']=='POST'){

    if(!isset($_POST['form_token']) || $_POST['form_token'] !== $_SESSION['form_token']){
        header("Location: transfer_s_project.php?error=duplicate"); exit;
    }

    unset($_SESSION['form_token']);

    $items = $_POST['asset_ids'] ?? [];
    $to    = trim($_POST['to_site'] ?? '');

    if($to==''){
        header("Location: transfer_s_project.php?error=nosite"); exit;
    }

    if(empty($items)){
        header("Location: transfer_s_project.php?error=empty"); exit;
    }

    try{
        $conn->beginTransaction();

        $round = $conn->query("
        SELECT ISNULL(MAX(sent_transfer),0)+1 FROM IT_AssetTransfer_Headers
        ")->fetchColumn();

        $stmt = $conn->prepare("
        INSERT INTO IT_AssetTransfer_Headers
        (sent_transfer,transfer_type,from_site,to_site,created_by,admin_status,no_pc,type)
        VALUES (?,?,?,?,?,?,?,?)
        ");

        foreach($items as $pc){

            /* 🔥 CHECK use_it */
            $checkUse = $conn->prepare("SELECT use_it FROM IT_assets WHERE no_pc=?");
            $checkUse->execute([$pc]);
            $useSite = $checkUse->fetchColumn();

            if(!empty($useSite)){
                throw new Exception("USED_{$pc}_{$useSite}");
            }

            /* 🔥 CHECK TRANSFER */
            $chk = $conn->prepare("
            SELECT TOP 1 receive_status, to_site
            FROM IT_AssetTransfer_Headers
            WHERE no_pc = ?
            ORDER BY transfer_id DESC
            ");
            $chk->execute([$pc]);
            $last = $chk->fetch(PDO::FETCH_ASSOC);

            $lastStatus = $last['receive_status'] ?? null;
            $lastSite   = $last['to_site'] ?? '';

            if($lastStatus && $lastStatus != 'รับแล้ว' && $lastStatus != 'ยกเลิก'){
                throw new Exception("BLOCK_{$pc}_{$lastSite}");
            }

            /* 🔥 TYPE */
            $typeStmt = $conn->prepare("SELECT type_equipment FROM IT_assets WHERE no_pc=?");
            $typeStmt->execute([$pc]);
            $realType = $typeStmt->fetchColumn() ?: 'UNKNOWN';

            $stmt->execute([
                $round,
                'ส่งมอบ',
                $site,
                $to,
                $user,
                'อนุมัติ',
                $pc,
                $realType
            ]);
        }

        $conn->commit();

        /* 🔥 redirect ใหม่ */
        header("Location: transfer_s_project_list.php?success=1");
        exit;

    }catch(Exception $e){

        $conn->rollBack();

        if(strpos($e->getMessage(),'USED_') === 0){
            $msg = str_replace('USED_','',$e->getMessage());
            list($pc,$siteBlock) = explode('_',$msg);
            header("Location: transfer_s_project.php?error=used&pc=".$pc."&site=".$siteBlock);
        }
        elseif(strpos($e->getMessage(),'BLOCK_') === 0){
            $msg = str_replace('BLOCK_','',$e->getMessage());
            list($pc,$siteBlock) = explode('_',$msg);
            header("Location: transfer_s_project.php?error=duplicate_item&pc=".$pc."&site=".$siteBlock);
        }
        else{
            header("Location: transfer_s_project.php?error=fail");
        }

        exit;
    }
}
?>

<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
body{
    background:linear-gradient(135deg,#e3f2fd,#ffffff);
}
.card-header{
    background:linear-gradient(135deg,#2196f3,#64b5f6);
    color:white;
}
.group-box{
    background:white;
    border-radius:12px;
    padding:15px;
    margin-bottom:15px;
    box-shadow:0 5px 12px rgba(0,0,0,0.05);
}
.tag{
    display:inline-block;
    background:#42a5f5;
    color:white;
    padding:6px 12px;
    border-radius:20px;
    margin:4px;
}
select option:disabled{
    color:#999;
    background:#eee;
}
</style>

<div class="container mt-4">
<div class="card shadow">

<div class="card-header">
🚚 ส่งอุปกรณ์ (Admin)
</div>

<div class="card-body">

<form method="post" id="formSend">
<input type="hidden" name="form_token" value="<?= $_SESSION['form_token'] ?>">

<div class="row mb-3">
<div class="col-md-6">
<select name="to_site" id="toSite" class="form-control">
<option value="">-- เลือกโครงการปลายทาง --</option>
<?php foreach($projects as $p): ?>
<option><?= $p ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-6">
<button type="button" id="btnSend" class="btn btn-primary w-100">
📨 ส่งมอบ
</button>
</div>
</div>

<div class="row">
<?php foreach($grouped as $type => $items): ?>
<div class="col-md-6">
<div class="group-box">

<b><?= $type ?></b>

<select class="form-control mt-2 selectItem">
<option value="">-- เลือก <?= $type ?> --</option>

<?php foreach($items as $a): 
$disabled = !empty($a['use_it']);
?>

<option value="<?= $a['no_pc'] ?>" <?= $disabled?'disabled':'' ?>>

<?= $a['no_pc'] ?> | <?= $a['spec'] ?>

<?php if($disabled): ?>
(อยู่ที่ <?= $a['use_it'] ?>)
<?php endif; ?>

</option>

<?php endforeach; ?>
</select>

<button type="button" class="btn btn-info btn-sm mt-2 btnAdd">+ เพิ่ม</button>
<div class="selectedList mt-2"></div>

</div>
</div>
<?php endforeach; ?>
</div>

</form>

</div>
</div>
</div>

<script>
let isSubmitting=false;

document.querySelectorAll('.group-box').forEach(box=>{
let select = box.querySelector('.selectItem');
let list   = box.querySelector('.selectedList');

box.querySelector('.btnAdd').onclick=function(){

let val = select.value;
let text = select.options[select.selectedIndex].text;

if(!val){ Swal.fire('กรุณาเลือกก่อน'); return; }

if(select.options[select.selectedIndex].disabled){
Swal.fire('❌ อุปกรณ์นี้ถูกใช้งานอยู่'); return;
}

if(list.querySelector('[data-id="'+val+'"]')){
Swal.fire('เลือกแล้ว'); return;
}

let tag = document.createElement('div');
tag.className='tag';
tag.dataset.id=val;
tag.innerHTML=text+' ✖';
tag.onclick=()=>tag.remove();

list.appendChild(tag);
};
});

document.getElementById('btnSend').onclick=function(){

if(isSubmitting) return;

let to = document.getElementById('toSite').value;
if(!to){ Swal.fire('กรุณาเลือกโครงการ'); return; }

let items=[];
document.querySelectorAll('.tag').forEach(t=>{
items.push(t.dataset.id);
});

if(items.length==0){
Swal.fire('กรุณาเลือกอุปกรณ์'); return;
}

Swal.fire({
title:'ยืนยันส่ง?',
icon:'question',
showCancelButton:true
}).then(res=>{
if(res.isConfirmed){

isSubmitting=true;

let form=document.getElementById('formSend');
form.querySelectorAll('input[name="asset_ids[]"]').forEach(e=>e.remove());

items.forEach(v=>{
let i=document.createElement('input');
i.type='hidden';
i.name='asset_ids[]';
i.value=v;
form.appendChild(i);
});

form.submit();
}
});
};

<?php if(isset($_GET['error']) && $_GET['error']=='used'): ?>
Swal.fire({
icon:'warning',
title:'อุปกรณ์ถูกใช้งาน',
text:'<?= $_GET['pc'] ?> อยู่ที่ <?= $_GET['site'] ?>'
});
<?php endif; ?>
</script>

<?php include 'partials/footer.php'; ?>