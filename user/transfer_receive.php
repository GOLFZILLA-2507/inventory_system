<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];
$user = $_SESSION['fullname'];
$emp  = $_SESSION['EmployeeID'];

/* =====================================================
   เมื่อกดยืนยันรับอุปกรณ์ (รับทั้งรอบ)
===================================================== */

if(isset($_POST['receive'])){

$round = $_POST['round'];

/* =====================================================
   โหลดรายการทั้งหมดในรอบนั้น
===================================================== */

$stmt = $conn->prepare("
SELECT *
FROM IT_AssetTransfer_Headers
WHERE sent_transfer = ?
");

$stmt->execute([$round]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($items as $t){

/* 1 อัพเดทสถานะรับของ */

$stmt = $conn->prepare("
UPDATE IT_AssetTransfer_Headers
SET status='รับของแล้ว',
receive_status='รับอุปกรณ์เสร็จสิ้น',
arrived_date = GETDATE()
WHERE transfer_id = ?
");

$stmt->execute([$t['transfer_id']]);


/* =====================================================
   2 บันทึกอุปกรณ์เข้าโครงการปลายทาง
===================================================== */

$stmt = $conn->prepare("
SELECT MAX(asset_id) AS max_id
FROM IT_user_information
");

$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$new_asset_id = ($row['max_id'] ?? 0) + 1;

$stmt = $conn->prepare("
INSERT INTO IT_user_information
(
asset_id,
user_employee,
user_project,
user_new_no,
user_no_pc,
user_equipment_details,
user_spec,
user_ssd,
user_ram,
user_gpu,
user_type_equipment,
user_record,
user_update
)
VALUES
(?,?,?,?,?,?,?,?,?,?,?,?,GETDATE())
");

$stmt->execute([

$new_asset_id,
NULL,
$site,
$t['new_no'],
$t['no_pc'],
$t['details'],
$t['spec'],
$t['ssd'],
$t['ram'],
$t['gpu'],
$t['type'],
$user

]);

}

/* =====================================================
   กลับหน้าเดิม
===================================================== */

header("Location: transfer_receive.php");
exit;

}


/* =====================================================
   โหลดรายการรอรับแบบ "รอบการส่ง"
===================================================== */

$stmt = $conn->prepare("
SELECT 
sent_transfer,
from_site,
MIN(transfer_date) AS transfer_date,
COUNT(*) AS total_items,
MAX(status) AS status
FROM IT_AssetTransfer_Headers
WHERE to_site = ?
GROUP BY sent_transfer,from_site
ORDER BY sent_transfer DESC
");

$stmt->execute([$site]);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="container mt-4">
<div class="card shadow">

<div class="card-header bg-success text-white">
📥 รายการรอรับอุปกรณ์
</div>

<div class="card-body">

<table class="table table-bordered table-hover">

<tr>
<th width="60">ลำดับ</th>
<th>รอบการส่ง</th>
<th>จากโครงการ</th>
<th>จำนวนอุปกรณ์</th>
<th>วันที่โอน</th>
<th>ตรวจเช็คอุปกรณ์</th>
<th>ตรวจรับ</th>
</tr>

<?php $i=1; foreach($data as $d): ?>

<tr>

<td><?= $i++ ?></td>

<td>
<span class="badge bg-primary">
ครั้งที่ <?= $d['sent_transfer'] ?>
</span>
</td>

<td><?= $d['from_site'] ?></td>

<td>
<span class="badge bg-dark">
<?= $d['total_items'] ?> รายการ
</span>
</td>

<td><?= $d['transfer_date'] ?></td>
<td>

<a href="transfer_receive_detail.php?round=<?= $d['sent_transfer'] ?>" 
class="btn btn-info btn-sm">

ดูอุปกรณ์

</a>

</td>

<td>

<?php if($d['status']!='รับของแล้ว'): ?>

<form method="post">

<input type="hidden" name="round" value="<?= $d['sent_transfer'] ?>">

<button class="btn btn-success btn-sm" name="receive">

ยืนยันรับ

</button>

</form>

<?php else: ?>

<span class="badge bg-success">
รับแล้ว
</span>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</table>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>