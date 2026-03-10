<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];

$stmt = $conn->prepare("
SELECT * FROM IT_AssetTransfer_Headers
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
<div class="card-header bg-success text-white">📥 รายการรอรับ</div>
<div class="card-body">

<table class="table table-bordered">
<tr>
<th>#</th>
<th>ประเภท</th>
<th>จาก</th>
<th>สถานะ</th>
<th>ตรวจรับ</th>
</tr>

<?php $i=1; foreach($data as $d): ?>
<tr>
<td><?= $i++ ?></td>
<td><?= $d['transfer_type'] ?></td>
<td><?= $d['from_site'] ?></td>
<td><?= $d['transfer_status'] ?></td>
<td>
<a href="transfer_receive_detail.php?id=<?= $d['transfer_id'] ?>" class="btn btn-success btn-sm">ตรวจรับ</a>
</td>
</tr>
<?php endforeach; ?>

</table>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>