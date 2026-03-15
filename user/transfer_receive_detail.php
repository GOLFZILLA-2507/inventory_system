<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];
$user = $_SESSION['fullname'];

$round = $_GET['round'] ?? ($_POST['round'] ?? 0);

/* =========================================
เมื่อกดยืนยันตรวจรับ
========================================= */

if(isset($_POST['confirm'])){

$checked = $_POST['check_item'] ?? [];

/* โหลดรายการทั้งหมดในรอบ */
$stmt = $conn->prepare("
SELECT *
FROM IT_AssetTransfer_Headers
WHERE sent_transfer = ?
");
$stmt->execute([$round]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($items as $row){

$id = $row['transfer_id'];

/* ถ้ารายการนี้ตรวจรับแล้ว ไม่ต้องแก้ไข */
if($row['receive_status']=='รับแล้ว'){
continue;
}

/* ถ้าติ๊ก checkbox */
if(in_array($id,$checked)){

$stmt = $conn->prepare("
UPDATE IT_AssetTransfer_Headers
SET 
status='รับของแล้ว',
receive_status='รับแล้ว',
arrived_date = GETDATE()
WHERE transfer_id = ?
");

$stmt->execute([$id]);

}

/* ถ้าไม่ได้ติ๊ก และยังไม่เคยตรวจรับ */
else{

$stmt = $conn->prepare("
UPDATE IT_AssetTransfer_Headers
SET receive_status='ไม่พบอุปกรณ์นี้'
WHERE transfer_id = ?
AND receive_status IS NULL
");

$stmt->execute([$id]);

}

}

}

/* =========================================
โหลดข้อมูลใหม่หลัง update
========================================= */

$stmt = $conn->prepare("
SELECT *
FROM IT_AssetTransfer_Headers
WHERE sent_transfer = ?
");

$stmt->execute([$round]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="container mt-4">

<div class="card shadow">

<div class="card-header bg-success text-white">

ตรวจรับอุปกรณ์ รอบที่ <?= $round ?>

</div>

<div class="card-body">

<form method="post">

<input type="hidden" name="round" value="<?= $round ?>">

<table class="table table-bordered">

<tr>
<th width="120">ตรวจรับ</th>
<th>รหัสอุปกรณ์</th>
<th>ประเภท</th>
<th>Spec</th>
</tr>

<?php foreach($data as $d): ?>

<tr>

<td class="text-center">

<?php
/* =================================================
ถ้ารับแล้ว → ไม่แสดง checkbox
================================================= */

if($d['receive_status']=='รับแล้ว'){
echo '<span class="badge bg-success">รับแล้ว</span>';
}

/* =================================================
ถ้าไม่พบอุปกรณ์
================================================= */

elseif($d['receive_status']=='ไม่พบอุปกรณ์นี้'){
echo '<span class="badge bg-danger">ไม่พบ</span>';
}

/* =================================================
ถ้ายังไม่ตรวจรับ → แสดง checkbox
================================================= */

else{
?>

<input 
type="checkbox"
name="check_item[]"
value="<?= $d['transfer_id'] ?>"

<?php
/* ถ้ารับแล้วให้คงติ๊ก */
if($d['receive_status']=='รับแล้ว'){
echo "checked";
}
?>

>

<?php } ?>

</td>

<td><?= $d['no_pc'] ?></td>

<td><?= $d['type'] ?></td>

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


</tr>

<?php endforeach; ?>

</table>

<button class="btn btn-success" name="confirm">

ยืนยันการตรวจรับ

</button>

</form>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>