<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];

/* =====================================================
🔥 CANCEL
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
🔥 LOAD DATA
===================================================== */
$stmt = $conn->prepare("
SELECT 
sent_transfer,
from_site,
to_site,
transfer_type,

FORMAT(MIN(transfer_date),'yyyy-MM-dd HH:mm') AS transfer_date,

COUNT(*) AS total_items,

SUM(CASE WHEN receive_status='รับแล้ว' THEN 1 ELSE 0 END) AS received_items,
SUM(CASE WHEN receive_status IS NULL THEN 1 ELSE 0 END) AS waiting_items,
SUM(CASE WHEN receive_status='ไม่พบอุปกรณ์นี้' THEN 1 ELSE 0 END) AS notfound_items

FROM IT_AssetTransfer_Headers
WHERE from_site = ?
AND receive_status != 'ยกเลิก'
GROUP BY sent_transfer,from_site,to_site,transfer_type
ORDER BY sent_transfer DESC
");
$stmt->execute([$site]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
🔥 DASHBOARD
===================================================== */
$total = count($data);
$done = 0;
$waiting = 0;

foreach($data as $d){
    if($d['received_items'] == $d['total_items']) $done++;
    else $waiting++;
}

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
.card-header{
background:linear-gradient(135deg,#198754,#20c997);
color:white;
}

.stat-card{
border-radius:12px;
padding:15px;
color:white;
text-align:center;
}

.bg-green{ background:#198754; }
.bg-blue{ background:#0d6efd; }
.bg-orange{ background:#ffc107; color:#000; }

.table-hover tbody tr:hover{
background:#f1fdf6;
}

.badge-status{
padding:6px 10px;
font-size:12px;
}
</style>

<div class="container mt-4">

<!-- ================= DASHBOARD ================= -->
<div class="row mb-3">

<div class="col-md-4">
<div class="stat-card bg-blue">
<h6>ทั้งหมด</h6>
<h3><?= $total ?></h3>
</div>
</div>

<div class="col-md-4">
<div class="stat-card bg-green">
<h6>เสร็จแล้ว</h6>
<h3><?= $done ?></h3>
</div>
</div>

<div class="col-md-4">
<div class="stat-card bg-orange">
<h6>รอดำเนินการ</h6>
<h3><?= $waiting ?></h3>
</div>
</div>

</div>

<!-- ================= TABLE ================= -->
<div class="card shadow">

<div class="card-header">
📦 รายการที่ฉันส่ง
</div>

<div class="card-body">

<table class="table table-bordered table-hover text-center align-middle">

<thead class="table-success">
<tr>
<th>#</th>
<th>ปลายทาง</th>
<th>ประเภท</th>
<th>จำนวน</th>
<th>วันที่ส่ง</th>
<th>สถานะ</th>
<th>ปริ้น</th>
<th>เช็ค</th>
<th>ยกเลิก</th>
</tr>
</thead>

<tbody>

<?php $i=1; foreach($data as $d): ?>

<tr>

<td><?= $i++ ?></td>

<td>
<b><?= $d['to_site'] ?></b>
</td>

<td><?= $d['transfer_type'] ?></td>

<td>
<span class="badge bg-success">
<?= $d['total_items'] ?> รายการ
</span>
</td>

<td><?= $d['transfer_date'] ?></td>

<td>
<?php

$received = $d['received_items'];
$notfound = $d['notfound_items'];
$total    = $d['total_items'];

/* =====================================================
🔥 LOGIC ใหม่
===================================================== */

// 🟢 สำเร็จ (รวมไม่พบ = จบรอบ)
if(($received + $notfound) == $total){

    if($notfound > 0){
        echo "<span class='badge bg-danger badge-status'>❌ ปลายทางไม่พบ</span>";
    }else{
        echo "<span class='badge bg-success badge-status'>✅ สำเร็จ</span>";
    }

}

// 🟡 บางส่วน
elseif($received > 0 || $notfound > 0){

    echo "<span class='badge bg-warning text-dark badge-status'>📦 บางส่วน</span>";

}

// ⚪ ยังไม่รับ
else{

    echo "<span class='badge bg-secondary badge-status'>⏳ รอรับ</span>";

}
?>
</td>

<td>
<a href="transfer_detail.php?round=<?= $d['sent_transfer'] ?>" 
class="btn btn-sm btn-success">
🖨️
</a>
</td>

<td>
<a href="transfer_shipping_check.php?round=<?= $d['sent_transfer'] ?>" 
class="btn btn-sm btn-info">
📋
</a>
</td>

<td>
<?php if($d['received_items'] == 0): ?>

<button class="btn btn-sm btn-danger btnCancel"
data-id="<?= $d['sent_transfer'] ?>">
❌
</button>

<?php endif; ?>
</td>
</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>
</div>
</div>

<script>
/* =====================================================
🔥 CANCEL MODAL
===================================================== */
document.querySelectorAll('.btnCancel').forEach(btn=>{

btn.addEventListener('click', function(){

let id = this.dataset.id;

Swal.fire({
title:'ยืนยัน?',
text:'ต้องการยกเลิกรายการนี้ใช่หรือไม่',
icon:'warning',
showCancelButton:true,
confirmButtonText:'ยกเลิก',
cancelButtonText:'ปิด'
}).then((res)=>{

if(res.isConfirmed){
window.location='?cancel='+id;
}

});

});

});
</script>

<?php include 'partials/footer.php'; ?>