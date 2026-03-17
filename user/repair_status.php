<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$user = $_SESSION['fullname'];

$stmt = $conn->prepare("
SELECT r.*, a.no_pc
FROM IT_RepairTickets r
LEFT JOIN IT_assets a ON a.asset_id = r.asset_id
WHERE r.user_name = ?
ORDER BY r.ticket_id DESC
");
$stmt->execute([$user]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="container mt-4">
<div class="card shadow">
<div class="card-header bg-success text-white">
<h5>📦 ติดตามสถานะการแจ้งซ่อมของคุณ</h5>
</div>

<div class="card-body">

<table class="table table-bordered">
<tr>
<th class="text-center">ลำดับ</th>
<th>รหัสอุปกรณ์</th>
<th>รายละเอียด</th>
<th>สถานะ</th>
<th>วันที่แจ้ง</th>
<th>วันที่ซ่อมเสร็จ</th>
</tr>

<?php foreach($data as $r): 
    
$i=1;?>

<tr>
<td class="text-center"><?= $i++ ?></td>
<td><?= $r['user_no_pc'] ?></td>
<td><?= $r['problem'] ?></td>

<td>
<?php
$color="secondary";
if($r['status']=="กำลังซ่อม") $color="primary";
if($r['status']=="เสร็จแล้ว") $color="success";
if($r['status']=="ส่ง Vendor") $color="warning";
if($r['status']=="ยกเลิก") $color="danger";
?>

<span class="badge bg-<?= $color ?>">
<?= $r['status'] ?>
</span>
</td>

<td><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>

</tr>

<?php endforeach; ?>

</table>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>