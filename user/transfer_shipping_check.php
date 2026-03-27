<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* =====================================================
🔥 เมื่อกดยกเลิก
===================================================== */
if(isset($_POST['cancel_id'])){

    $id = $_POST['cancel_id'];

    $stmt = $conn->prepare("
    UPDATE IT_AssetTransfer_Headers
    SET receive_status = 'ยกเลิก'
    WHERE transfer_id = ?
    ");
    $stmt->execute([$id]);

    header("Location: transfer_shipping_check.php?round=".$round);
    exit;
}


$round = $_GET['round'] ?? 0;

/* โหลดรายการอุปกรณ์ในรอบนั้น */

$stmt = $conn->prepare("
SELECT 
    t.*,

    a.type_equipment,
    a.spec,
    a.ram,
    a.ssd,
    a.gpu

FROM IT_AssetTransfer_Headers t

LEFT JOIN IT_assets a
ON a.no_pc = t.no_pc

WHERE t.sent_transfer = ?
");

$stmt->execute([$round]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="container mt-4">

<div class="card shadow">

<div class="card-header bg-success text-white">

ตรวจเช็คการจัดส่ง รอบที่ <?= $round ?>

</div>

<div class="card-body">

<table class="table table-bordered table-hover">

<tr>
<th width="60">#</th>
<th>ส่งไปยัง</th>
<th>รหัสอุปกรณ์</th>
<th>ประเภท</th>
<th>Spec</th>
<th>สถานะปลายทาง</th>
<th>จัดการ</t่h>
</tr>

<?php $i=1; foreach($data as $d): ?>

<tr>

<td><?= $i++ ?></td>
<td><?= $d['to_site'] ?></td>
<td><?= $d['no_pc'] ?></td>
<td><?= $d['type_equipment'] ?></td>
<td>

<?php

$specParts = array_filter([
$d['spec'],
$d['ram'],
$d['ssd'],
$d['gpu']
]);

echo empty($specParts)
? 'ยังไม่ได้บันทึกข้อมูล'
: implode(' | ',$specParts);

?>

</td>

<td>

<?php

if($d['receive_status']=='รับแล้ว'){

echo '<span class="badge bg-success">รับแล้ว</span>';

}
elseif($d['receive_status']=='ไม่พบอุปกรณ์นี้'){

echo '<span class="badge bg-danger">ไม่พบ</span>';

}
else{
echo '<span class="badge bg-warning text-dark">ยังไม่ตรวจรับ</span>';
}
?>
</td>

<td class="text-center">

<?php if($d['receive_status'] != 'ยกเลิก'): ?>

<form method="post" onsubmit="return confirm('ยืนยันยกเลิกรายการนี้?');">
    <input type="hidden" name="cancel_id" value="<?= $d['transfer_id'] ?>">
    <button class="btn btn-danger btn-sm">
        ❌ ยกเลิก
    </button>
</form>

<?php else: ?>

<span class="badge bg-secondary">ยกเลิกแล้ว</span>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</table>

<!-- 🔥 ปุ่มย้อนกลับ (อยู่ล่าง) -->
<div class="mt-3 text-start">
    <button onclick="history.back()" class="btn btn-secondary">
        ⬅️ ย้อนกลับ
    </button>
</div>

</div>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>