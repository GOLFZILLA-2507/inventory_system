<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$user = $_SESSION['fullname'];

/* =====================================================
🔐 TOKEN กัน F5
===================================================== */
if(empty($_SESSION['form_token'])){
    $_SESSION['form_token'] = bin2hex(random_bytes(32));
}

/* =====================================================
📍 โหลดโครงการ
===================================================== */
$projects = $conn->query("
SELECT DISTINCT site FROM Employee 
WHERE site IS NOT NULL 
ORDER BY site
")->fetchAll(PDO::FETCH_COLUMN);

/* =====================================================
📦 โหลดอุปกรณ์ทั้งหมด
===================================================== */
$stmt = $conn->query("
SELECT no_pc,type_equipment,spec,ram,ssd,gpu
FROM IT_assets
WHERE no_pc IS NOT NULL
");

/* =====================================================
📊 GROUP TYPE
===================================================== */
$grouped = [];

while($r = $stmt->fetch(PDO::FETCH_ASSOC)){
    $type = strtoupper(trim($r['type_equipment'] ?: 'อื่นๆ'));
    $grouped[$type][] = $r;
}

/* =====================================================
📨 SUBMIT
===================================================== */
if($_SERVER['REQUEST_METHOD']=='POST'){

    // 🔐 check token
    if(!isset($_POST['form_token']) || $_POST['form_token'] !== $_SESSION['form_token']){
        header("Location: transfer_return.php?error=duplicate"); exit;
    }

    unset($_SESSION['form_token']);

    $items = $_POST['asset_ids'] ?? [];
    $from  = trim($_POST['from_site'] ?? '');

    if($from==''){
        header("Location: transfer_return.php?error=nosite"); exit;
    }

    if(empty($items)){
        header("Location: transfer_return.php?error=empty"); exit;
    }

    try{
        $conn->beginTransaction();

        // 🔥 รอบส่ง
        $round = $conn->query("
        SELECT ISNULL(MAX(sent_transfer),0)+1 
        FROM IT_AssetTransfer_Headers
        ")->fetchColumn();

        $stmt = $conn->prepare("
        INSERT INTO IT_AssetTransfer_Headers
        (sent_transfer,transfer_type,from_site,to_site,created_by,admin_status,no_pc,type)
        VALUES (?,?,?,?,?,?,?,?)
        ");

        foreach($items as $pc){

            /* =====================================================
            🔥 เช็คสถานะล่าสุด
            ===================================================== */
            $chk = $conn->prepare("
            SELECT TOP 1 receive_status, to_site
            FROM IT_AssetTransfer_Headers
            WHERE no_pc = ?
            ORDER BY transfer_id DESC
            ");
            $chk->execute([$pc]);
            $last = $chk->fetch(PDO::FETCH_ASSOC);

            $lastStatus = $last['receive_status'] ?? null;

            // ❌ ถ้ายังโอนอยู่
            if($lastStatus && $lastStatus != 'รับแล้ว' && $lastStatus != 'ยกเลิก'){
                throw new Exception("BLOCK_$pc");
            }

            /* =====================================================
            🔥 type จริง
            ===================================================== */
            $typeStmt = $conn->prepare("SELECT type_equipment FROM IT_assets WHERE no_pc=?");
            $typeStmt->execute([$pc]);
            $realType = $typeStmt->fetchColumn() ?: 'UNKNOWN';

            $stmt->execute([
                $round,
                'ส่งคืน',
                $from,
                'HQ', // 🔥 ปลายทาง = คลัง
                $user,
                'อนุมัติ',
                $pc,
                $realType
            ]);
        }

        $conn->commit();
        header("Location: transfer_return.php?success=1");
        exit;

    }catch(Exception $e){

        $conn->rollBack();

        if(strpos($e->getMessage(),'BLOCK_')===0){
            $pc = str_replace('BLOCK_','',$e->getMessage());
            header("Location: transfer_return.php?error=block&pc=".$pc);
        }else{
            header("Location: transfer_return.php?error=fail");
        }

        exit;
    }
}
?>

<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container mt-4">
<div class="card shadow">

<div class="card-header bg-danger text-white">
🔄 รับคืนอุปกรณ์ (จากโครงการ → HQ)
</div>

<div class="card-body">

<form method="post" id="formReturn">
<input type="hidden" name="form_token" value="<?= $_SESSION['form_token'] ?>">

<div class="row mb-3">

<div class="col-md-6">
<select name="from_site" id="fromSite" class="form-control">
<option value="">-- เลือกโครงการต้นทาง --</option>
<?php foreach($projects as $p): ?>
<option><?= $p ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-6">
<button type="button" id="btnSend" class="btn btn-danger w-100">
📥 รับคืน
</button>
</div>

</div>

<div class="row">
<?php foreach($grouped as $type => $items): ?>
<div class="col-md-6 mb-2">
<div class="border p-2">

<b><?= $type ?></b>

<select class="form-control mt-2 selectItem">
<option value="">-- เลือก --</option>
<?php foreach($items as $a): ?>
<option value="<?= $a['no_pc'] ?>">
<?= $a['no_pc'] ?> | <?= $a['spec'] ?>
</option>
<?php endforeach; ?>
</select>

<button type="button" class="btn btn-sm btn-danger mt-2 btnAdd">+ เพิ่ม</button>

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
/* ================= ADD ================= */
document.querySelectorAll('.btnAdd').forEach(btn=>{
btn.onclick=function(){
let box = btn.closest('div');
let select = box.querySelector('.selectItem');
let list = box.querySelector('.selectedList');

let val = select.value;
let text = select.options[select.selectedIndex].text;

if(!val){ Swal.fire('เลือกก่อน'); return; }

let tag = document.createElement('div');
tag.innerHTML = text + ' ❌';
tag.style.cursor='pointer';
tag.onclick=()=>tag.remove();
tag.dataset.id=val;

list.appendChild(tag);
};
});

/* ================= SUBMIT ================= */
document.getElementById('btnSend').onclick=function(){

let from = document.getElementById('fromSite').value;
if(!from){ Swal.fire('เลือกโครงการ'); return; }

let items=[];

document.querySelectorAll('[data-id]').forEach(t=>{
items.push(t.dataset.id);
});

if(items.length==0){
Swal.fire('ไม่มีรายการ'); return;
}

Swal.fire({
title:'ยืนยันรับคืน?',
text:'จำนวน '+items.length+' รายการ',
showCancelButton:true
}).then(res=>{
if(res.isConfirmed){

let form=document.getElementById('formReturn');

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

/* ================= RESULT ================= */
<?php if(isset($_GET['success'])): ?>
Swal.fire({icon:'success',title:'รับคืนสำเร็จ'});
<?php endif; ?>

<?php if(isset($_GET['error']) && $_GET['error']=='block'): ?>
Swal.fire({
icon:'warning',
title:'รายการถูกใช้งานอยู่',
text:'<?= $_GET['pc'] ?> ยังโอนอยู่'
});
<?php endif; ?>
</script>

<?php include 'partials/footer.php'; ?>