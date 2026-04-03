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

    -- 🔥 นับรับแล้ว
    SUM(CASE WHEN receive_status='รับแล้ว' THEN 1 ELSE 0 END) received,

    -- 🔥 นับยกเลิก
    SUM(CASE WHEN receive_status='ยกเลิก' THEN 1 ELSE 0 END) cancelled

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

/* =====================================================
🔥 ดึงค่าแต่ละรอบ
===================================================== */
$total     = $d['total'];
$received  = $d['received'];
$cancelled = $d['cancelled'];

/* =====================================================
🔥 LOGIC STATUS (ตรง requirement 100%)
===================================================== */

/*
เงื่อนไข:
1. ถ้ามี "ยกเลิก" → แสดง "ถูกยกเลิก"
2. ถ้ายังไม่ครบ → "ตรวจรับบางรายการ"
3. ถ้าครบ → "ตรวจรับแล้ว"
*/

// 🟢 รับครบ (รวมยกเลิกด้วย)
if(($received + $cancelled) == $total){

    if($cancelled > 0){
        // 🔴 มีรายการยกเลิกในรอบนี้
        $status = '<span class="badge bg-danger">ถูกยกเลิก</span>';
    }else{
        // 🟢 รับครบจริง
        $status = '<span class="badge bg-success">ตรวจรับแล้ว</span>';
    }

}

// 🟡 รับบางส่วน (มีทั้งรับแล้ว / ยกเลิก แต่ยังไม่ครบ)
elseif($received > 0 || $cancelled > 0){

    $status = '<span class="badge bg-warning text-dark">ตรวจรับบางรายการ</span>';

}

// ⚪ ยังไม่ทำอะไรเลย
else{
    $status = '<span class="badge bg-secondary">ยังไม่ตรวจรับ</span>';
}

?>

<tr>

<td><?= $i++ ?></td>

<td>
<span class="badge bg-primary">
ครั้งที่ <?= $d['sent_transfer'] ?>
</span>
</td>

<td><?= $d['from_site'] ?></td>

<td>
<span class="badge bg-dark">
<?= $total ?> รายการ
</span>
</td>

<td>
<span class="badge bg-success"><?= $received ?> รับ</span>
<span class="badge bg-danger"><?= $cancelled ?> ยกเลิก</span>
</td>

<td><?= $d['send_date'] ?></td>

<td><?= $d['receive_date'] ?? '-' ?></td>

<td><?= $status ?></td>

<td>
<a href="transfer_tosend_check.php?round=<?= $d['sent_transfer'] ?>" 
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