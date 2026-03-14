<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];
$user = $_SESSION['fullname'];
$emp  = $_SESSION['EmployeeID'];


/* =========================================
   เมื่อกดรับอุปกรณ์
========================================= */

if(isset($_POST['receive'])){


$tid = $_POST['transfer_id'];

/* ดึงข้อมูล transfer */
$stmt = $conn->prepare("
SELECT *
FROM IT_AssetTransfer_Headers
WHERE transfer_id = ?
");
$stmt->execute([$tid]);
$t = $stmt->fetch(PDO::FETCH_ASSOC);


if($t){

/* 1 อัพเดทสถานะรับของ */

$stmt = $conn->prepare("
UPDATE IT_AssetTransfer_Headers
SET status='รับของแล้ว',
receive_status='รับแล้ว',
arrived_date = GETDATE()
WHERE transfer_id = ?
");
$stmt->execute([$tid]);


/* 2 อัพเดท site ของ user */

$stmt = $conn->prepare("
UPDATE Employee
SET site = ?
WHERE EmployeeID = ?
");
$stmt->execute([$site,$emp]);

/* =========================================
   3 บันทึกประวัติอุปกรณ์ (สร้างแถวใหม่)
========================================= */

/* หา asset_id ล่าสุดในระบบ */
$stmt = $conn->prepare("
SELECT MAX(asset_id) AS max_id
FROM IT_user_information
");

/* execute query */
$stmt->execute();

/* ดึงค่า max asset_id */
$row = $stmt->fetch(PDO::FETCH_ASSOC);

/* สร้าง asset_id ใหม่ */
$new_asset_id = ($row['max_id'] ?? 0) + 1;


/* เตรียมคำสั่ง INSERT แถวใหม่ */
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

/* execute insert */
$stmt->execute([

$new_asset_id,   // asset_id ใหม่
$user,           // ผู้ใช้เครื่อง
$site,           // โครงการปลายทาง
$t['new_no'],    // รหัสยาว
$t['no_pc'],     // รหัสเครื่อง
$t['details'],   // รายละเอียด
$t['spec'],      // spec
$t['ssd'],       // ssd
$t['ram'],       // ram
$t['gpu'],       // gpu
$t['type'],      // ประเภท
$user,            // ผู้บันทึก

]);
}

/* =========================================
   4 อัพเดทเครื่องต้นทาง
   หาเครื่องจากทุก field ที่อาจเก็บรหัสอุปกรณ์
========================================= */

$stmt = $conn->prepare("
UPDATE IT_user_information
SET 
status_tranfer = ?,   -- ประเภทการโอน เช่น โอนย้าย
no_transfer = ?       -- เก็บรหัสเครื่องที่ถูกโอน
WHERE
(
user_no_pc = ?
OR user_monitor1 = ?
OR user_monitor2 = ?
OR user_ups = ?
OR user_cctv = ?
OR user_nvr = ?
OR user_projector = ?
OR user_printer = ?
)
AND user_project <> ?
");

$stmt->execute([
$t['transfer_type'],
$t['no_pc'],

$t['no_pc'],
$t['no_pc'],
$t['no_pc'],
$t['no_pc'],
$t['no_pc'],
$t['no_pc'],
$t['no_pc'],
$t['no_pc'],

$site
]);

header("Location: transfer_receive.php");
exit;

}


/* =========================================
   โหลดรายการรอรับ
========================================= */

$stmt = $conn->prepare("
SELECT *
FROM IT_AssetTransfer_Headers
WHERE to_site = ?
ORDER BY transfer_id DESC
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
<th>#</th>
<th>ประเภท</th>
<th>รหัสเครื่อง</th>
<th>จาก</th>
<th>วันที่โอน</th>
<th>สถานะโอน</th>
<th>สถานะ</th>
<th>ตรวจรับ</th>
</tr>

<?php $i=1; foreach($data as $d): ?>

<tr>

<td><?= $i++ ?></td>

<td><?= $d['type'] ?></td>

<td><?= $d['no_pc'] ?></td>

<td><?= $d['from_site'] ?></td>

<td><?= $d['transfer_date'] ?></td>

<td><?= $d['transfer_status'] ?></td>

<td><?= $d['status'] ?></td>

<td>

<?php if($d['status']!='รับของแล้ว'): ?>

<form method="post">

<input type="hidden" name="transfer_id" value="<?= $d['transfer_id'] ?>">

<button class="btn btn-success btn-sm" name="receive">
ยืนยันรับอุปกรณ์
</button>

</form>

<?php else: ?>

<span class="badge bg-success">รับแล้ว</span>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</table>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>