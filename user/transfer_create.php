<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];
$user = $_SESSION['fullname'];

/* =====================================================
🔥 โหลดโครงการปลายทาง
===================================================== */
$stmt = $conn->prepare("
SELECT DISTINCT site FROM Employee
WHERE site IS NOT NULL AND site <> ?
ORDER BY site
");
$stmt->execute([$site]);
$projects = $stmt->fetchAll(PDO::FETCH_COLUMN);

/* =====================================================
🔥 โหลด asset จาก IT_user_devices (ตัวใหม่)
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
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
🔥 เช็คสถานะโอน
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
🔥 filter asset
===================================================== */
$assets = [];

foreach($rows as $r){

    $code = trim($r['device_code']);

    $t = getTransferStatus($conn,$code,$site);

    // ❌ ถ้ารับแล้ว = ไม่ต้องโชว์
    if($t && $t['receive_status']=='รับแล้ว') continue;

    $assets[$code] = [
        'no_pc'=>$code,
        'type'=>$r['device_type'],
        'spec'=>$r['spec'],
        'ram'=>$r['ram'],
        'ssd'=>$r['ssd'],
        'gpu'=>$r['gpu'],
        'transfer'=>$t
    ];
}

/* =====================================================
🔥 SUBMIT
===================================================== */
$msg=""; $status="";

if($_SERVER['REQUEST_METHOD']=='POST'){

    $items = $_POST['asset_ids'] ?? [];
    $type  = $_POST['transfer_type'];
    $to    = $_POST['to_site'];

    if(empty($items)){
        $msg="กรุณาเลือกอุปกรณ์";
        $status="error";
    }else{

        try{

            $conn->beginTransaction();

            $sent_transfer = $conn->query("
            SELECT ISNULL(MAX(sent_transfer),0)+1 FROM IT_AssetTransfer_Headers
            ")->fetchColumn();

            $stmt = $conn->prepare("
            INSERT INTO IT_AssetTransfer_Headers
            (sent_transfer,transfer_type,from_site,to_site,created_by,admin_status,no_pc,type)
            VALUES (?,?,?,?,?,?,?,?)
            ");

            foreach($items as $aid){

                if(!isset($assets[$aid])) continue;

                $t = getTransferStatus($conn,$aid,$site);

                // ❌ กันโอนซ้ำ
                if($t && $t['receive_status']!='รับแล้ว'){
                    throw new Exception("❌ $aid ถูกโอนไปแล้ว (ยังไม่รับ)");
                }

                // ✅ insert อย่างเดียว (ไม่ลบ)
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

            $msg="ส่งรายการสำเร็จ";
            $status="success";

        }catch(Exception $e){

            $conn->rollBack();
            $msg=$e->getMessage();
            $status="error";
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
<select name="to_site" class="form-control">
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
<th>ประเภท</th>
<th>รหัส</th>
<th>Spec</th>
</tr>

<?php $i=1; foreach($assets as $a): ?>
<tr>
<td><?= $i++ ?></td>

<td>
<input type="checkbox" name="asset_ids[]" value="<?= $a['no_pc'] ?>">
</td>

<td><?= $a['type'] ?></td>
<td><?= $a['no_pc'] ?></td>

<td>
<?= $a['spec']
? $a['spec']." | ".$a['ram']." | ".$a['ssd']." | ".$a['gpu']
: '<span class="badge bg-success">ไม่มีข้อมูล</span>' ?>
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
/* 🔥 CONFIRM */
document.getElementById('btnConfirm').addEventListener('click',function(){

Swal.fire({
title:'ยืนยัน?',
text:'ต้องการโอนอุปกรณ์ใช่หรือไม่',
icon:'question',
showCancelButton:true,
confirmButtonText:'ส่ง',
cancelButtonText:'ยกเลิก'
}).then((res)=>{
if(res.isConfirmed){
document.getElementById('formTransfer').submit();
}
});

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

<?php include 'partials/footer.php'; ?>