<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];

/* =====================================================
📦 ดึงรายการที่ "ส่งมาหาเรา"
===================================================== */
$stmt = $conn->prepare("
SELECT 
    sent_transfer,
    from_site,
    MIN(transfer_date) AS send_date,
    MAX(receive_date) AS receive_date,
    COUNT(*) total,
    SUM(CASE WHEN receive_status='รับแล้ว' THEN 1 ELSE 0 END) received
FROM IT_AssetTransfer_Headers
WHERE to_site = ?
GROUP BY sent_transfer, from_site
ORDER BY sent_transfer DESC
");
$stmt->execute([$site]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container mt-4">
<div class="card shadow">

<div class="card-header bg-primary text-white">
📥 รายการที่ส่งมาหาฉัน
</div>

<div class="card-body">

<table class="table table-bordered text-center align-middle">

<tr>
<th>#</th>
<th>รอบ</th>
<th>จากโครงการ</th>
<th>จำนวน</th>
<th>รับแล้ว</th>
<th>วันที่ส่ง</th>
<th>วันที่รับ</th>
<th>สถานะ</th>
<th>จัดการ</th>
</tr>

<?php $i=1; foreach($data as $d): 

$status = ($d['received'] == $d['total'])
? '<span class="badge bg-success">ครบแล้ว</span>'
: '<span class="badge bg-warning">รอรับ</span>';

?>

<tr>
<td><?= $i++ ?></td>
<td><?= $d['sent_transfer'] ?></td>
<td><?= $d['from_site'] ?></td>
<td><?= $d['total'] ?></td>
<td><?= $d['received'] ?></td>
<td><?= $d['send_date'] ?></td>
<td><?= $d['receive_date'] ?? '-' ?></td>
<td><?= $status ?></td>

<td>
<a href="transfer_receive_check.php?round=<?= $d['sent_transfer'] ?>" 
class="btn btn-primary btn-sm">
🔍 ตรวจเช็ค
</a>
</td>

</tr>

<?php endforeach; ?>

</table>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>