<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];

function badge($s){
switch($s){
case 'pending': return '<span class="badge bg-secondary">รอส่ง</span>';
case 'in_transit': return '<span class="badge bg-warning">กำลังส่ง</span>';
case 'arrived': return '<span class="badge bg-info">ถึงปลายทาง</span>';
case 'received_complete': return '<span class="badge bg-success">รับครบ</span>';
case 'received_incomplete': return '<span class="badge bg-danger">ไม่ครบ</span>';
default:return $s;
}
}

$stmt = $conn->prepare("
SELECT * FROM IT_AssetTransfer_Headers
WHERE from_site=?
ORDER BY transfer_id DESC
");
$stmt->execute([$site]);
$data=$stmt->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="container mt-4">
<div class="card shadow">
<div class="card-header bg-success text-white">📦 รายการที่ฉันส่ง</div>
<div class="card-body">

<table class="table table-bordered">
<tr>
<th>#</th>
<th>ประเภท</th>
<th>จาก</th>
<th>ไป</th>
<th>สถานะ</th>
<th>วันที่</th>
<th>จัดการ</th>
</tr>

<?php $i=1; foreach($data as $d): ?>
<tr>
<td><?= $i++ ?></td>
<td><?= $d['transfer_type'] ?></td>
<td><?= $d['from_site'] ?></td>
<td><?= $d['to_site'] ?></td>
<td><?= badge($d['transfer_status']) ?></td>
<td><?= $d['transfer_date'] ?></td>
<td>

<a href="transfer_action.php?action=start&id=<?= $d['transfer_id'] ?>" class="btn btn-warning btn-sm">🚚 ส่ง</a>

<a href="transfer_print.php?id=<?= $d['transfer_id'] ?>" class="btn btn-dark btn-sm">🖨️</a>

</td>
</tr>
<?php endforeach; ?>

</table>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>