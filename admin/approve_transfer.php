<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* =====================================================
🔥 ดึงข้อมูล "รายรอบ" + สถานะอนุมัติ
===================================================== */
$data = $conn->query("
SELECT 
    sent_transfer,

    COUNT(*) AS total_items,

    -- 🔥 นับรายการที่อนุมัติแล้ว
    SUM(CASE WHEN admin_status='อนุมัติ' THEN 1 ELSE 0 END) AS approved_items,

    MIN(from_site) as from_site,
    MIN(to_site) as to_site,
    MIN(transfer_date) as transfer_date

FROM IT_AssetTransfer_Headers

WHERE admin_status IS NOT NULL

GROUP BY sent_transfer
ORDER BY sent_transfer DESC
")->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<style>
/* =====================================================
🔥 ธีม น้ำเงิน-ฟ้า (ดู modern ขึ้น)
===================================================== */

.card-header{
    background: linear-gradient(135deg,#0d6efd,#4dabf7);
    color:white;
}

/* 🔥 card hover */
.table-hover tbody tr:hover{
    background:#e7f1ff;
}

/* 🔥 badge */
.badge-round{
    background:#0d6efd;
    color:white;
    padding:6px 10px;
    border-radius:8px;
}

.badge-success{
    background:#198754;
}

.badge-warning{
    background:#ffc107;
    color:black;
}

.badge-secondary{
    background:#6c757d;
}

/* 🔥 ปุ่ม */
.btn-view{
    background:#0d6efd;
    color:white;
}
.btn-view:hover{
    background:#0b5ed7;
}

/* 🔥 glow */
.card{
    border-radius:12px;
    box-shadow:0 4px 15px rgba(0,0,0,0.1);
}

</style>

<div class="container mt-4">

<div class="card">

<div class="card-header">
<h5 class="mb-0">📋 อนุมัติรายการโอนย้าย</h5>
</div>

<div class="card-body">

<table class="table table-bordered table-hover text-center align-middle">

<thead class="table-primary">
<tr>
<th>#</th>
<th>จำนวน</th>
<th>วันที่</th>
<th>จาก</th>
<th>ไป</th>
<th>สถานะ</th>
<th>จัดการ</th>
</tr>
</thead>

<tbody>

<?php $i=1; foreach($data as $d){ 

    /* =====================================================
    🔥 คำนวณสถานะ
    ===================================================== */
    if($d['approved_items'] == $d['total_items']){
        $status = "<span class='badge badge-success'>✅ อนุมัติครบแล้ว</span>";
    }
    elseif($d['approved_items'] > 0){
        $status = "<span class='badge badge-warning'>⚠️ อนุมัติบางส่วน</span>";
    }
    else{
        $status = "<span class='badge badge-secondary'>⏳ รออนุมัติ</span>";
    }

?>

<tr>

<td class="fw-bold text-primary"><?= $i++ ?></td>

<td>
<?= $d['approved_items'] ?>/<?= $d['total_items'] ?>
</td>

<td>
<?= date('d/m/Y H:i', strtotime($d['transfer_date'])) ?>
</td>

<td><?= htmlspecialchars($d['from_site']) ?></td>

<td>

<?= htmlspecialchars($d['to_site']) ?>

</td>

<td>
<?= $status ?>
</td>

<td>

<a href="approve_transfer_detail.php?round=<?= $d['sent_transfer'] ?>" 
class="btn btn-view btn-sm">
🔍 ดูรายการ
</a>

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>