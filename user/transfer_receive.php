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
$new_asset_id = $row['max_id'] + 1;


/* เตรียมคำสั่ง INSERT แถวใหม่ */
$stmt = $conn->prepare("
INSERT INTO IT_user_information
(
asset_id,              -- รหัส asset ใหม่
user_employee,         -- ผู้ใช้เครื่อง
user_project,          -- โครงการใหม่
user_new_no,           -- รหัสยาว
user_no_pc,            -- รหัสเครื่อง
user_equipment_details,-- รายละเอียด
user_spec,             -- spec
user_ssd,              -- ssd
user_ram,              -- ram
user_gpu,              -- gpu
user_type_equipment,   -- ประเภท
user_record,           -- ผู้บันทึก
user_update            -- วันที่บันทึก
)
VALUES
(?,?,?,?,?,?,?,?,?,?,?, ?,GETDATE())
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
$user            // ผู้บันทึก

]);
}

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