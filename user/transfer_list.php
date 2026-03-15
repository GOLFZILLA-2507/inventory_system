<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* =====================================================
ดึงโครงการของ user ที่ login
===================================================== */

$site = $_SESSION['site'];


/* =====================================================
โหลดรายการโอนย้ายแบบ "รอบการส่ง"
พร้อมตรวจเช็คสถานะปลายทาง
===================================================== */

$stmt = $conn->prepare("
SELECT 
sent_transfer,
from_site,
to_site,
transfer_type,

MIN(transfer_date) AS transfer_date,

COUNT(*) AS total_items,

SUM(CASE WHEN receive_status='รับแล้ว' THEN 1 ELSE 0 END) AS received_items,

SUM(CASE WHEN receive_status='ไม่พบอุปกรณ์นี้' THEN 1 ELSE 0 END) AS missing_items,

SUM(CASE WHEN receive_status IS NULL THEN 1 ELSE 0 END) AS waiting_items

FROM IT_AssetTransfer_Headers

WHERE from_site = ?

GROUP BY 
sent_transfer,
from_site,
to_site,
transfer_type

ORDER BY sent_transfer DESC
");

$stmt->execute([$site]);
$data=$stmt->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="container mt-4">

<div class="card shadow">

<div class="card-header bg-success text-white">
📦 รายการที่ฉันส่ง
</div>

<div class="card-body">

<table class="table table-bordered table-hover">

<tr>
<th>#</th>
<th>รอบการส่ง</th>
<th>จาก</th>
<th>ปลายทาง</th>
<th>ประเภท</th>
<th>จำนวน</th>
<th>วันที่</th>
<th>จัดการ</th>
<th>ตรวจเช็คการจัดส่ง</th>
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
<span class="badge bg-info">
<?= $d['to_site'] ?>
</span>
</td>

<td><?= $d['transfer_type'] ?></td>

<td>
<span class="badge bg-dark">
<?= $d['total_items'] ?> รายการ
</span>
</td>

<td><?= $d['transfer_date'] ?></td>

<td>

<a href="transfer_detail.php?round=<?= $d['sent_transfer'] ?>" 
class="btn btn-info btn-sm">

🖨️ ปริ้น

</a>

</td>
<td>

<a 
href="transfer_shipping_check.php?round=<?= $d['sent_transfer'] ?>">
<?php echo '<span class="badge bg-info">📋 ดูรายละเอียด</span>';?>



</a>

</td>



</tr>

<?php endforeach; ?>

</table>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>