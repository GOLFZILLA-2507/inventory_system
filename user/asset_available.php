<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* =====================================================
   ดึงโครงการของ user ที่ login
===================================================== */

$site = $_SESSION['site'];

/* =====================================================
   โหลดอุปกรณ์ที่ยังไม่มีผู้ใช้งาน
===================================================== */

$stmt = $conn->prepare("
SELECT
asset_id,
user_no_pc,
user_type_equipment,
user_spec,
user_ram,
user_ssd,
user_gpu,
user_monitor1,
user_monitor2,
user_ups,
user_update
FROM IT_user_information
WHERE user_project = ?
AND user_employee IS NULL
ORDER BY user_update DESC
");

$stmt->execute([$site]);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<style>

.card-header{
background:linear-gradient(135deg,#198754,#20c997);
color:white;
}

.empty-data{
display:inline-block;
padding:4px 10px;
font-size:12px;
font-weight:600;
color:#856404;
background:#fff3cd;
border-radius:6px;
border:1px solid #000000;
}

</style>

<div class="container mt-4">

<div class="card shadow">

<div class="card-header">

<h5 class="mb-0">

🖥 อุปกรณ์ที่ยังไม่มีผู้ใช้งาน  
(โครงการ <?= $site ?>)

</h5>

</div>

<div class="card-body">

<table class="table table-bordered table-hover">

<thead class="table-success text-center">

<tr>

<th>#</th>
<th>รหัสอุปกรณ์</th>
<th>ประเภท</th>
<th>Spec</th>
<th>จอ</th>
<th>UPS</th>
<th>วันที่บันทึก</th>

</tr>

</thead>

<tbody>

<?php

if(empty($data)){
?>

<tr>

<td colspan="7" class="text-center text-muted">

ไม่พบอุปกรณ์ที่ยังไม่มีผู้ใช้งาน

</td>

</tr>

<?php
}
else{

$i=1;

foreach($data as $d){

$spec = trim(($d['user_spec'] ?? '').($d['user_ram'] ?? '').($d['user_ssd'] ?? '').($d['user_gpu'] ?? ''));

if($spec==''){
$spec = '<span class="empty-data">ไม่มีข้อมูล</span>';
}
else{
$spec = $d['user_spec']." | ".$d['user_ram']." | ".$d['user_ssd']." | ".$d['user_gpu'];
}

?>

<tr>

<td class="text-center"><?= $i++ ?></td>

<td class="fw-bold text-primary">

<?= $d['user_no_pc'] ?>

</td>

<td>

<?= $d['user_type_equipment'] ?: '-' ?>

</td>

<td>

<?= $spec ?>

</td>

<td>

<?php

if($d['user_monitor1'] || $d['user_monitor2']){

echo $d['user_monitor1'];

if($d['user_monitor2']){
echo "<br>".$d['user_monitor2'];
}

}else{

echo '<span class="empty-data">ไม่มี</span>';

}

?>

</td>

<td>

<?= $d['user_ups'] ?: '<span class="empty-data">ไม่มี</span>' ?>

</td>

<td>

<?= $d['user_update'] ?>

</td>

</tr>

<?php
}

}
?>

</tbody>

</table>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>