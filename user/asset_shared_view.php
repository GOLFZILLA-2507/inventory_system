<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* =====================================================
   ดึงชื่อโครงการของ user ที่ login
===================================================== */

$site = $_SESSION['site'];

/* =====================================================
   โหลดข้อมูลอุปกรณ์พนักงาน
===================================================== */

$userAssets = $conn->prepare("
SELECT 
    u.user_employee,
    u.user_no_pc,
    u.user_type_equipment,
    u.user_spec,
    u.user_ram,
    u.user_ssd,
    u.user_gpu,
    u.user_monitor1,
    u.user_monitor2,
    u.user_ups
FROM IT_user_information u
WHERE 
LTRIM(RTRIM(u.user_project)) = LTRIM(RTRIM(?))

AND NOT EXISTS (
    SELECT 1
    FROM IT_AssetTransfer_Headers t
    WHERE t.no_pc = u.user_no_pc
    AND t.status = 'รับของแล้ว'
)

ORDER BY u.user_employee
");

$userAssets->execute([$site]);
$userData = $userAssets->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<style>
.card-header{
background:linear-gradient(135deg,#198754,#20c997);
color:white;
}

/* badge สำหรับข้อมูลที่ยังไม่ได้บันทึก */

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
<h5 class="mb-0">📡 อุปกรณ์ภายในโครงการ <?= $site ?></h5>
</div>

<div class="card-body">


<!-- =====================================================
     ตารางอุปกรณ์พนักงาน
===================================================== -->

<h6 class="text-success">👨‍💼 อุปกรณ์พนักงาน</h6>

<table class="table table-bordered table-hover">

<thead class="table-success text-center">
<tr>
<th style="width:60px">ลำดับ</th>
<th>ชื่อผู้ใช้</th>
<th>รหัสเครื่อง</th>
<th>ประเภท</th>
<th>Spec</th>
<th>จอที่ 1</th>
<th>จอที่ 2</th>
<th>เครื่องสำรองไฟ</th>
</tr>
</thead>

<tbody>

<?php

if(empty($userData)){
?>

<tr>
<td colspan="8" class="text-center text-muted">
ยังไม่บันทึกข้อมูล
</td>
</tr>

<?php
}
else{

$i=1;

foreach($userData as $u){

$spec = trim(($u['user_spec'] ?? '').($u['user_ram'] ?? '').($u['user_ssd'] ?? '').($u['user_gpu'] ?? ''));

if($spec==''){
$spec = '<span class="empty-data">ยังไม่ได้บันทึกข้อมูล</span>';
}
else{
$spec = $u['user_spec']." | ".$u['user_ram']." | ".$u['user_ssd']." | ".$u['user_gpu'];
}
?>

<tr>

<td class="text-center"><?= $i++ ?></td>

<td><?= $u['user_employee'] ?></td>

<td class="fw-bold text-primary"><?= $u['user_no_pc'] ?></td>

<td><?= $u['user_type_equipment'] ?: '-' ?></td>

<td><?= $spec ?></td>

<td><?= $u['user_monitor1'] ? $u['user_monitor1'] : '<span class="empty-data">ไม่มีข้อมูล</span>' ?></td>
<td><?= $u['user_monitor2'] ? $u['user_monitor2'] : '<span class="empty-data">ไม่มีข้อมูล</span>' ?></td>
<td><?= $u['user_ups'] ? $u['user_ups'] : '<span class="empty-data">ไม่มีข้อมูล</span>' ?></td>

</tr>

<?php
}

}
?>

</tbody>
</table>

<hr>


<!-- =====================================================
     ตารางอุปกรณ์ใช้ร่วม
===================================================== -->

<h6 class="text-success">📡 อุปกรณ์ใช้ร่วม</h6>

<table class="table table-bordered table-hover">

<thead class="table-success text-center">

<tr>
<th style="width:60px">ลำดับ</th>
<th>ประเภท</th>
<th>รหัส</th>
</tr>

</thead>

<tbody>

<?php

/* =====================================================
   โหลดข้อมูล shared asset
===================================================== */

$sqlShared = "
SELECT
    user_cctv,
    user_nvr,
    user_projector,
    user_printer,
    user_audio_set,
    user_plotter,
    user_Accessories_IT,
    user_Drone,
    user_Optical_Fiber,
    user_Server
FROM IT_user_information
WHERE user_project = ?
";

$stmt = $conn->prepare($sqlShared);
$stmt->execute([$site]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
   map ประเภทอุปกรณ์
===================================================== */

$map = [

'user_cctv' => 'CCTV',
'user_nvr' => 'NVR',
'user_projector' => 'Projector',
'user_printer' => 'Printer',
'user_audio_set' => 'Audio Set',
'user_plotter' => 'Plotter',
'user_Accessories_IT' => 'Accessories IT',
'user_Drone' => 'Drone',
'user_Optical_Fiber' => 'Optical Fiber',
'user_Server' => 'Server'

];

$sharedData = [];
$unique = [];

/* =====================================================
   loop ข้อมูล
===================================================== */

foreach($rows as $row){

foreach($map as $field => $type){

if(empty($row[$field])) continue;

$items = explode(',', $row[$field]);

foreach($items as $code){

$code = trim($code);

$key = $type.'-'.$code;

if(isset($unique[$key])) continue;

$sharedData[] = [

'type'=>$type,
'code'=>$code

];

$unique[$key] = true;

}

}

}

/* =====================================================
   แสดงผล
===================================================== */

$j=1;

if(empty($sharedData)){
?>

<tr>
<td colspan="3" class="text-center text-muted">
ยังไม่การบันทึกข้อมูล
</td>
</tr>

<?php
}
else{

foreach($sharedData as $s){
?>

<tr>

<td class="text-center"><?= $j++ ?></td>

<td><?= $s['type'] ?></td>

<td class="fw-bold text-primary"><?= $s['code'] ?></td>

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