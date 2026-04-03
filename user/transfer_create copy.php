<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* =====================================================
🔐 SESSION
===================================================== */
$site = $_SESSION['site'];
$user = $_SESSION['fullname'];

/* =====================================================
📍 โหลดโครงการปลายทาง
===================================================== */
$stmt = $conn->prepare("
SELECT DISTINCT site 
FROM Employee
WHERE site IS NOT NULL AND site <> ?
ORDER BY site
");
$stmt->execute([$site]);
$projects = $stmt->fetchAll(PDO::FETCH_COLUMN);

/* =====================================================
📦 โหลด "ล่าสุดจริง" ของแต่ละเครื่อง (หัวใจระบบ)
===================================================== */
$stmt = $conn->prepare("
SELECT 
t.transfer_id,
t.no_pc,
t.type,
t.from_site,
t.to_site,
t.receive_status,
a.spec,a.ram,a.ssd,a.gpu

FROM IT_AssetTransfer_Headers t
LEFT JOIN IT_assets a ON a.no_pc = t.no_pc

-- 🔥 เอาเฉพาะ record ล่าสุดของเครื่อง
WHERE NOT EXISTS (
    SELECT 1 FROM IT_AssetTransfer_Headers t2
    WHERE t2.no_pc = t.no_pc
    AND t2.transfer_id > t.transfer_id
)

-- 🔥 เอาเฉพาะของโครงการเรา
AND t.from_site = ?
");
$stmt->execute([$site]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
🔥 FILTER ตาม requirement
===================================================== */
$assets = [];

foreach($rows as $r){

    $status = $r['receive_status'];

    // ❌ 1. รับแล้ว → ไม่แสดง
    if($status === 'รับแล้ว') continue;

    $assets[] = $r;
}

/* =====================================================
📨 SUBMIT (PRG กัน F5)
===================================================== */
if($_SERVER['REQUEST_METHOD']=='POST'){

    $items = $_POST['asset_ids'] ?? [];
    $type  = $_POST['transfer_type'];
    $to    = $_POST['to_site'];

    /* =====================================================
    🔥 AUTO RULE: ส่งสำนักงานใหญ่
    ===================================================== */
    $adminStatus = 'รออนุมัติ';

    if($to === 'สำนักงานใหญ่'){
        $type = 'ส่งคืน';        // 🔥 บังคับ type
        $adminStatus = 'อนุมัติ'; // 🔥 auto approve
    }

    if(empty($items)){
        header("Location: transfer_create.php?error=empty");
        exit;
    }

    try{
        $conn->beginTransaction();

        $round = $conn->query("
        SELECT ISNULL(MAX(sent_transfer),0)+1 
        FROM IT_AssetTransfer_Headers
        ")->fetchColumn();

        $stmt = $conn->prepare("
        INSERT INTO IT_AssetTransfer_Headers
        (sent_transfer,transfer_type,from_site,to_site,created_by,admin_status,no_pc,type)
        VALUES (?,?,?,?,?,?,?,?)
        ");

        foreach($items as $code){

            $stmt->execute([
                $round,
                $type,
                $site,
                $to,
                $user,
                $adminStatus,
                $code,
                $_POST['type_map'][$code] ?? ''
            ]);
        }

        $conn->commit();

        header("Location: transfer_create.php?success=1");
        exit;

    }catch(Exception $e){
        $conn->rollBack();
        header("Location: transfer_create.php?error=fail");
        exit;
    }
}
?>

<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container mt-4">
<div class="card shadow">

<div class="card-header bg-success text-white">
🚚 สร้างรายการโอนย้าย
</div>


<div class="card-body">

<form method="post" id="formTransfer">

<div class="row mb-3">

<div class="col-md-4">
<label>ประเภท</label>
<select name="transfer_type" class="form-control">
<option value="โอนย้าย">โอนย้าย</option>
<option value="ส่งคืน">ส่งคืน</option>
</select>
</div>

<div class="col-md-4">
<label>จาก</label>
<input class="form-control" value="<?= $site ?>" readonly>
</div>

<div class="col-md-4">
<label>ไปยัง</label>
<select name="to_site" id="to_site" class="form-control">
<?php foreach($projects as $p): ?>
<option><?= $p ?></option>
<?php endforeach; ?>
</select>
</div>

</div>

<table class="table table-bordered text-center">

<tr>
<th>#</th>
<th>เลือก</th>
<th>สถานะ</th>
<th>รหัส</th>
<th>ประเภท</th>
<th>Spec</th>
</tr>

<?php $i=1; foreach($assets as $a): 

$isWaiting = ($a['receive_status']=='รอตรวจรับ' || empty($a['receive_status']));
?>

<tr class="<?= $isWaiting ? 'table-warning' : '' ?>">

<td><?= $i++ ?></td>

<td>
<input type="checkbox"
name="asset_ids[]"
value="<?= $a['no_pc'] ?>"
<?= $isWaiting ? 'disabled class="waiting-item" data-site="'.$a['to_site'].'"' : '' ?>
>

<input type="hidden" name="type_map[<?= $a['no_pc'] ?>]" value="<?= $a['type'] ?>">
</td>

<td>
<?php if($isWaiting): ?>
<span class="badge bg-warning text-dark">📥 รอตรวจรับ</span>
<?php elseif($a['receive_status']=='ยกเลิก'): ?>
<span class="badge bg-danger"></span>
<?php else: ?>
<span class="badge bg-secondary"></span>
<?php endif; ?>
</td>

<td><?= $a['no_pc'] ?></td>
<td><?= $a['type'] ?></td>
<td>
<?= $a['spec']
? $a['spec']." | ".$a['ram']." | ".$a['ssd']." | ".$a['gpu']
: '-' ?>
</td>

</tr>

<?php endforeach; ?>

</table>

<button type="button" id="btnConfirm" class="btn btn-success">
📨 ส่งรายการ
</button>

</form>

</div>
</div>
</div>

<script>
// 🔥 modal confirm
document.getElementById('btnConfirm').onclick = function(){

let checked = document.querySelectorAll('input[name="asset_ids[]"]:checked');

if(checked.length === 0){
Swal.fire('กรุณาเลือกอย่างน้อย 1 รายการ');
return;
}

let list = '';
checked.forEach(el=>{
list += el.value + '<br>';
});

let to = document.getElementById('to_site').value;

Swal.fire({
title:'ยืนยันการโอน',
html:`<b>ไปที่:</b> ${to}<br><hr>${list}`,
icon:'question',
showCancelButton:true
}).then(res=>{
if(res.isConfirmed){
document.getElementById('formTransfer').submit();
}
});
};

// 🔥 เตือนรอตรวจรับ
document.querySelectorAll('.waiting-item').forEach(el=>{
el.closest('tr').onclick=function(e){
if(e.target.tagName==='INPUT') return;
Swal.fire({
icon:'warning',
title:'ยังโอนไม่ได้',
text:'กำลังรอตรวจรับที่ '+el.dataset.site
});
};
});

// 🔥 success
<?php if(isset($_GET['success'])): ?>
Swal.fire({
icon:'success',
title:'ส่งรายการเรียบร้อย'
});
<?php endif; ?>

// 🔥 error
<?php if(isset($_GET['error'])): ?>
Swal.fire({icon:'error',title:'เกิดข้อผิดพลาด'});
<?php endif; ?>
</script>

<?php include 'partials/footer.php'; ?>