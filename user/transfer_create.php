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
📦 1. โหลดอุปกรณ์ที่มีผู้ใช้
===================================================== */
$stmt = $conn->prepare("
SELECT 
d.device_code,
d.device_type,
a.spec,a.ram,a.ssd,a.gpu
FROM IT_user_devices d
LEFT JOIN IT_assets a ON a.no_pc = d.device_code
WHERE d.user_project = ?
");
$stmt->execute([$site]);
$userDevices = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
📦 2. โหลดอุปกรณ์ที่รับมาแล้ว (ไม่มีผู้ใช้)
🔥 แก้ตรงนี้: ไม่เอาที่ "รับแล้ว" ของตัวเอง
===================================================== */
$stmt = $conn->prepare("
SELECT DISTINCT
t.no_pc,
t.type,
a.spec,a.ram,a.ssd,a.gpu
FROM IT_AssetTransfer_Headers t
LEFT JOIN IT_assets a ON a.no_pc = t.no_pc
WHERE t.to_site = ?
AND t.receive_status != 'รับแล้ว'

-- 🔥 เพิ่ม: ถ้ามี record ล่าสุดอยู่ที่ site ตัวเองแล้ว → ไม่ต้องแสดง
AND NOT EXISTS (
    SELECT 1 FROM IT_AssetTransfer_Headers t2
    WHERE t2.no_pc = t.no_pc
    AND t2.to_site = ?
    AND t2.receive_status = 'รับแล้ว'
    AND t2.transfer_id > t.transfer_id
)
");
$stmt->execute([$site,$site]);
$receivedDevices = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
🔍 เช็คสถานะโอนล่าสุด
===================================================== */
function getTransferStatus($conn,$code,$site){
    $stmt = $conn->prepare("
    SELECT TOP 1 to_site, receive_status
    FROM IT_AssetTransfer_Headers
    WHERE no_pc=? AND from_site=?
    ORDER BY transfer_id DESC
    ");
    $stmt->execute([$code,$site]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/* =====================================================
🔥 รวมข้อมูลทั้งหมด
===================================================== */
$assets = [];

foreach($userDevices as $r){
    $code = trim($r['device_code']);

    $assets[$code] = [
        'no_pc'=>$code,
        'type'=>$r['device_type'],
        'spec'=>$r['spec'],
        'ram'=>$r['ram'],
        'ssd'=>$r['ssd'],
        'gpu'=>$r['gpu'],
        'source'=>'user'
    ];
}

foreach($receivedDevices as $r){
    $code = trim($r['no_pc']);

    if(isset($assets[$code])) continue;

    $assets[$code] = [
        'no_pc'=>$code,
        'type'=>$r['type'] ?? 'UNKNOWN',
        'spec'=>$r['spec'],
        'ram'=>$r['ram'],
        'ssd'=>$r['ssd'],
        'gpu'=>$r['gpu'],
        'source'=>'transfer'
    ];
}

/* =====================================================
🔥 ใส่สถานะโอน
===================================================== */
foreach($assets as $code => $a){
    $assets[$code]['transfer'] = getTransferStatus($conn,$code,$site);
}

/* =====================================================
📨 SUBMIT
🔥 แก้: กัน F5 (PRG)
===================================================== */
$msg=""; $status="";

if($_SERVER['REQUEST_METHOD']=='POST'){

    $items = $_POST['asset_ids'] ?? [];
    $type  = $_POST['transfer_type'];
    $to    = $_POST['to_site'];

    if(empty($items)){
        header("Location: transfer_create.php?error=empty");
        exit;
    }else{

        try{
            $conn->beginTransaction();

            $sent_transfer = $conn->query("
            SELECT ISNULL(MAX(sent_transfer),0)+1 
            FROM IT_AssetTransfer_Headers
            ")->fetchColumn();

            $stmt = $conn->prepare("
            INSERT INTO IT_AssetTransfer_Headers
            (sent_transfer,transfer_type,from_site,to_site,created_by,admin_status,no_pc,type)
            VALUES (?,?,?,?,?,?,?,?)
            ");

            foreach($items as $aid){

                if(!isset($assets[$aid])) continue;

                $t = $assets[$aid]['transfer'];

                if($t && $t['receive_status']!='รับแล้ว' && $t['receive_status']!='ยกเลิก'){
                    throw new Exception("dup");
                }

                $stmt->execute([
                    $sent_transfer,
                    $type,
                    $site,
                    $to,
                    $user,
                    'รออนุมัติ',
                    $aid,
                    $assets[$aid]['type']
                ]);
            }

            $conn->commit();

            // 🔥 PRG กัน F5
            header("Location: transfer_create.php?success=1");
            exit;

        }catch(Exception $e){
            $conn->rollBack();

            header("Location: transfer_create.php?error=fail");
            exit;
        }
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

<!-- 🔥 TOP FORM -->
<div class="row mb-3">

<div class="col-md-4">
<label>ประเภท</label>
<select name="transfer_type" class="form-control" required>
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
<select name="to_site" class="form-control" required>
<?php foreach($projects as $p): ?>
<option><?= $p ?></option>
<?php endforeach; ?>
</select>
</div>

</div>

<!-- 🔥 TABLE -->
<table class="table table-bordered text-center">

<tr>
<th>#</th>
<th>เลือก</th>
<th>สถานะ</th>
<th>ประเภท</th>
<th>แหล่ง</th>
<th>รหัส</th>
<th>Spec</th>
</tr>

<?php $i=1; foreach($assets as $a): 

$isTransferring = ($a['transfer'] && 
    $a['transfer']['receive_status']!='รับแล้ว' && 
    $a['transfer']['receive_status']!='ยกเลิก');

?>

<tr class="<?= $isTransferring ? 'table-warning' : '' ?>">

<td><?= $i++ ?></td>

<td>
<input type="checkbox"
name="asset_ids[]"
value="<?= $a['no_pc'] ?>"
<?= $isTransferring ? 'disabled class="locked-item" data-site="'.$a['transfer']['to_site'].'"' : '' ?>
>
</td>

<td>
<?php if($isTransferring): ?>
    <span class="badge bg-warning">🚚 ไป <?= $a['transfer']['to_site'] ?></span>
<?php elseif($a['transfer'] && $a['transfer']['receive_status']=='รับแล้ว'): ?>
    <span class="badge bg-success">รับแล้ว</span>
<?php elseif($a['transfer'] && $a['transfer']['receive_status']=='ยกเลิก'): ?>
    <span class="badge bg-danger">ยกเลิก</span>
<?php else: ?>
    <span class="badge bg-secondary">ปกติ</span>
<?php endif; ?>
</td>

<td><span class="badge bg-primary"><?= $a['type'] ?></span></td>

<td>
<?= $a['source']=='transfer'
? '<span class="badge bg-info">ไม่มีผู้ใช้</span>'
: '<span class="badge bg-dark">มีผู้ใช้</span>' ?>
</td>

<td><?= $a['no_pc'] ?></td>

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
// confirm
document.getElementById('btnConfirm').onclick = function(){
Swal.fire({
title:'ยืนยัน?',
icon:'question',
showCancelButton:true
}).then(res=>{
if(res.isConfirmed){
document.getElementById('formTransfer').submit();
}
});
};

// แจ้งเตือน item lock
document.querySelectorAll('.locked-item').forEach(el=>{
el.closest('tr').onclick=function(e){
if(e.target.tagName==='INPUT') return;
Swal.fire({
icon:'warning',
title:'ไม่สามารถเลือกได้',
text:'กำลังโอนไปที่ '+el.dataset.site
});
};
});
</script>

<?php if($msg): ?>
<script>
Swal.fire({
icon:'<?= $status ?>',
title:'<?= $msg ?>'
});
</script>
<?php endif; ?>
</script>  <!-- script เดิม -->



<?php if(isset($_GET['error'])): ?>
<script>
Swal.fire({icon:'error',title:'เกิดข้อผิดพลาด'});
</script>
<?php endif; ?>

<?php include 'partials/footer.php'; ?>
<?php include 'partials/footer.php'; ?>