<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];

/* ================= USER ASSET ================= */
$userAssets = $conn->prepare("
SELECT 
    u.user_employee,
    u.user_no_pc,
    u.user_spec,
    u.user_ram,
    u.user_ssd,
    u.user_gpu,
    u.user_monitor1,
    u.user_monitor2,
    u.user_ups
FROM IT_user_information u
WHERE u.user_project = ?
ORDER BY u.user_employee
");
$userAssets->execute([$site]);
$userData = $userAssets->fetchAll(PDO::FETCH_ASSOC);

/* ================= SHARED ================= */
$sharedTypes = [
'audio_set','CCTV','Drone','NVR',
'Optical_Fiber','Printer','Plotter','Projector'
];

$in  = str_repeat('?,', count($sharedTypes)-1) . '?';

$sqlShared = "
SELECT no_pc,type_equipment
FROM IT_assets
WHERE project = ?
AND type_equipment IN ($in)
ORDER BY type_equipment,no_pc
";

$stmt = $conn->prepare($sqlShared);
$stmt->execute(array_merge([$site],$sharedTypes));
$sharedData = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<style>
.card-header{background:linear-gradient(135deg,#198754,#20c997);color:white;}
</style>

<div class="container mt-4">

<div class="card shadow">
<div class="card-header">
<h5 class="mb-0">📡 อุปกรณ์ภายในโครงการ <?= $site ?></h5>
</div>

<div class="card-body">

<!-- ================= USER TABLE ================= -->
<h6 class="text-success">👨‍💼 อุปกรณ์พนักงาน</h6>

<table class="table table-bordered table-hover">
<thead class="table-success text-center">
<tr>
<th style="width:60px">ลำดับ</th>
<th>ชื่อผู้ใช้</th>
<th>รหัสเครื่อง</th>
<th>Spec</th>
<th>จอที่ 1</th>
<th>จอที่ 2</th>
<th>UPS</th>
</tr>
</thead>

<tbody>

<?php 
$i=1;
foreach($userData as $u): 
$spec = $u['user_spec']." | ".$u['user_ram']." | ".$u['user_ssd']." | ".$u['user_gpu'];
?>

<tr>
<td class="text-center"><?= $i++ ?></td>
<td><?= $u['user_employee'] ?></td>
<td class="fw-bold text-primary"><?= $u['user_no_pc'] ?></td>
<td><?= $spec ?></td>
<td><?= $u['user_monitor1'] ?: '-' ?></td>
<td><?= $u['user_monitor2'] ?: '-' ?></td>
<td><?= $u['user_ups'] ?: '-' ?></td>
</tr>

<?php endforeach; ?>

</tbody>
</table>

<hr>

<!-- ================= SHARED TABLE ================= -->
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
$j=1;
foreach($sharedData as $s): 
?>

<tr>
<td class="text-center"><?= $j++ ?></td>
<td><?= $s['type_equipment'] ?></td>
<td class="fw-bold text-primary"><?= $s['no_pc'] ?></td>
</tr>

<?php endforeach; ?>

</tbody>
</table>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>