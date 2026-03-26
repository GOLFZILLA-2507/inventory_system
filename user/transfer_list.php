<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* =====================================================
🔥 ดึงโครงการของ user
===================================================== */
$site = $_SESSION['site'];


/* =====================================================
🔥 ยกเลิกรายการ (ทั้งรอบ)
===================================================== */
if(isset($_GET['cancel'])){

    $round = $_GET['cancel'];

    $stmt = $conn->prepare("
    UPDATE IT_AssetTransfer_Headers
    SET receive_status = 'ยกเลิก'
    WHERE sent_transfer = ?
    AND from_site = ?
    ");

    $stmt->execute([$round,$site]);

    header("Location: transfer_list.php");
    exit;
}


/* =====================================================
🔥 โหลดรายการ (ตัดที่ยกเลิกแล้วออก)
===================================================== */
$stmt = $conn->prepare("
SELECT 
sent_transfer,
from_site,
to_site,
transfer_type,

FORMAT(MIN(transfer_date),'yyyy-MM-dd HH:mm') AS transfer_date,
FORMAT(MAX(arrived_date),'yyyy-MM-dd HH:mm') AS arrived_date,

COUNT(*) AS total_items,

SUM(CASE WHEN receive_status='รับแล้ว' THEN 1 ELSE 0 END) AS received_items,

SUM(CASE WHEN receive_status='ไม่พบอุปกรณ์นี้' THEN 1 ELSE 0 END) AS missing_items,

SUM(CASE WHEN receive_status IS NULL THEN 1 ELSE 0 END) AS waiting_items

FROM IT_AssetTransfer_Headers

WHERE from_site = ?
AND receive_status != 'ยกเลิก'

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

<style>
/* =====================================================
🔥 ธีมเขียว
===================================================== */
.card-header{
    background:linear-gradient(135deg,#198754,#20c997);
    color:white;
}

.badge-round{
    background:#198754;
    color:white;
}

.btn-green{
    background:#198754;
    color:white;
}

.btn-green:hover{
    background:#157347;
}
</style>

<div class="container mt-4">

<div class="card shadow">

<div class="card-header">
📦 รายการที่ฉันส่ง
</div>

<div class="card-body">

<table class="table table-bordered table-hover">

<tr>
<th>#</th>
<th>จาก</th>
<th>ปลายทาง</th>
<th>ประเภท</th>
<th>จำนวน</th>
<th>วันที่ส่ง</th>
<th>วันที่รับ</th>
<th>สถานะ</th>
<th>Print</th>
<th>จัดการ</th>
<th>ตรวจเช็ค</th>
</tr>

<?php $i=1; foreach($data as $d): ?>

<tr>

<td><?= $i++ ?></td>

<td><?= htmlspecialchars($d['from_site']) ?></td>

<td>
<?= htmlspecialchars($d['to_site']) ?>
</td>

<td><?= htmlspecialchars($d['transfer_type']) ?></td>

<td>
<span class="badge bg-green badge-round">
<?= $d['total_items'] ?> รายการ
</span>
</td>

<td><?= $d['transfer_date'] ?></td>
<td><?= $d['transfer_date'] ?></td>


<!-- 🔥 สถานะ -->
<td>
<?php
if($d['received_items'] == $d['total_items']){
    echo "<span class='badge bg-success'>✅ รับเสร็จสิ้น</span>";
}
elseif($d['received_items'] > 0){
    echo "<span class='badge bg-warning text-dark'>📦 รับบางส่วน</span>";
}
else{
    echo "<span class='badge bg-secondary'>⏳ รอรับ</span>";
}
?>
</td>

<!-- 🔥 จัดการ -->
<td>

<a href="transfer_detail.php?round=<?= $d['sent_transfer'] ?>" 
class="btn btn-success btn-sm">
🖨️ 
</a>

</td>


<td>

<?php if($d['received_items'] == 0){ ?>

<a href="?cancel=<?= $d['sent_transfer'] ?>" 
class="btn btn-danger btn-sm"
onclick="return confirm('ยืนยันยกเลิกรายการนี้ทั้งรอบ ?')">
❌ ยกเลิก
</a>

<?php } else { ?>

<span class="badge bg-success"></span>

<?php } ?>

</td>

<!-- 🔥 ตรวจเช็ค -->
<td>
<a href="transfer_shipping_check.php?round=<?= $d['sent_transfer'] ?>">
<span class="badge bg-info">📋 ดูรายละเอียด</span>
</a>
</td>

</tr>

<?php endforeach; ?>

</table>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>