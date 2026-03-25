<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];

/* =====================================================
🔥 โหลดรายการที่ "โอนออกแล้ว + รับแล้ว"
ใช้ตัวนี้ filter ทั้งหน้า
===================================================== */
$stmtT = $conn->prepare("
SELECT no_pc
FROM IT_AssetTransfer_Headers
WHERE from_site = ?
AND receive_status = 'รับแล้ว'
");
$stmtT->execute([$site]);

$transfered = array_map('trim',$stmtT->fetchAll(PDO::FETCH_COLUMN));

/* =====================================================
🔥 Dashboard
===================================================== */

// 🔴 ไม่มีผู้ใช้ (ดูจาก asset จริง)
$stmt1 = $conn->prepare("
SELECT COUNT(*) 
FROM IT_assets
WHERE project = ?
AND (use_it IS NULL OR use_it = '')
");
$stmt1->execute([$site]);
$count_no_user = $stmt1->fetchColumn();

// 🔴 ส่งออก
$stmt2 = $conn->prepare("
SELECT COUNT(*) FROM IT_AssetTransfer_Headers
WHERE from_site = ?
");
$stmt2->execute([$site]);
$count_sent = $stmt2->fetchColumn();

// 🔴 รอตรวจรับ
$stmt3 = $conn->prepare("
SELECT COUNT(*) FROM IT_AssetTransfer_Headers
WHERE to_site = ?
AND receive_status = 'รอตรวจรับ'
");
$stmt3->execute([$site]);
$count_receive = $stmt3->fetchColumn();

// 🔴 ซ่อม
$stmt4 = $conn->prepare("
SELECT COUNT(*) FROM IT_RepairTickets
WHERE project = ?
AND status != 'เสร็จแล้ว'
");
$stmt4->execute([$site]);
$count_repair = $stmt4->fetchColumn();

/* =====================================================
🔥 โหลด user + JOIN asset
===================================================== */
$stmt = $conn->prepare("
SELECT 
    u.user_employee,
    e.position, -- 🔥 เพิ่มตรงนี้

    u.user_no_pc,
    u.user_monitor1,
    u.user_monitor2,
    u.user_ups,

    a.type_equipment,
    a.spec,
    a.ram,
    a.ssd,
    a.gpu

FROM IT_user_information u

LEFT JOIN IT_assets a 
ON a.no_pc = u.user_no_pc

LEFT JOIN Employee e
ON e.fullname = u.user_employee

WHERE u.user_project = ?
AND u.user_employee IS NOT NULL
AND LTRIM(RTRIM(u.user_employee)) <> ''
AND (u.user_type_equipment IS NULL OR u.user_type_equipment <> 'SHARED')

ORDER BY u.user_employee
");
$stmt->execute([$site]);
$userData = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
🔥 โหลด shared
===================================================== */
$stmtS = $conn->prepare("
SELECT 
user_cctv,user_nvr,user_projector,user_printer,
user_audio_set,user_plotter,user_Accessories_IT,
user_Drone,user_Optical_Fiber,user_Server
FROM IT_user_information
WHERE user_project = ?
");
$stmtS->execute([$site]);
$sharedRows = $stmtS->fetchAll(PDO::FETCH_ASSOC);

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

<div class="row mb-4">

<!-- ไม่มีผู้ใช้ -->
<div class="col-md-3">
<a href="asset_available.php" style="text-decoration:none;">
<div class="card text-white bg-danger shadow">
<div class="card-body text-center">
<h6>🖥 อุปกรณ์ไม่มีผู้ใช้</h6>
<h2><?= $count_no_user ?></h2>
</div>
</div>
</a>
</div>

<!-- รายการที่ส่ง -->
<div class="col-md-3">
<a href="transfer_list.php" style="text-decoration:none;">
<div class="card text-white bg-primary shadow">
<div class="card-body text-center">
<h6>📦 รายการที่ส่ง</h6>
<h2><?= $count_sent ?> รายการ </h2>
</div>
</div>
</a>
</div>

<!-- ตรวจรับ -->
<div class="col-md-3">
<a href="transfer_receive.php" style="text-decoration:none;">
<div class="card text-dark bg-warning shadow">
<div class="card-body text-center">
<h6>📥 รอตรวจรับ</h6>
<h2><?= $count_receive ?></h2>
</div>
</div>
</a>
</div>

<!-- ซ่อม -->
<div class="col-md-3">
<a href="repair_status.php" style="text-decoration:none;">
<div class="card text-white bg-success shadow">
<div class="card-body text-center">
<h6>🛠 งานซ่อม</h6>
<h2><?= $count_repair ?></h2>
</div>
</div>
</a>
</div>

</div>

<div class="card shadow">
<div class="card-header">
📡 อุปกรณ์ภายในโครงการ <?= $site ?>
</div>

<div class="card-body">

<h6>👨‍💼 อุปกรณ์พนักงาน</h6>

<table class="table table-bordered text-center">
<tr>
<th>#</th>
<th>ชื่อ</th>
<th>ตำแหน่ง</th>
<th>PC</th>
<th>ประเภท</th>
<th>Spec</th>
<th>Monitor1</th>
<th>Monitor2</th>
<th>UPS</th>
</tr>

<?php
$i=1;

foreach($userData as $u){

// ❌ ตัดของที่โอนแล้ว
if(in_array(trim($u['user_no_pc']),$transfered)){
    continue;
}

$spec = trim(($u['spec'] ?? '').($u['ram'] ?? '').($u['ssd'] ?? '').($u['gpu'] ?? ''));

if(!$spec){
    $spec = '<span class="empty-data">ไม่มีข้อมูล</span>';
}else{
    $spec = "{$u['spec']} | {$u['ram']} | {$u['ssd']} | {$u['gpu']}";
}
?>

<tr>
<td><?= $i++ ?></td>
<td  class="text-start"><?= $u['user_employee'] ?></td>

<td>
<?= $u['position'] ?: '<span class="empty-data">ไม่มีข้อมูล</span>' ?>
</td>

<td><?= $u['user_no_pc'] ?></td>
<td><?= $u['type_equipment'] ?></td>
<td><?= $spec ?></td>
<td><?= $u['user_monitor1'] ?: '-' ?></td>
<td><?= $u['user_monitor2'] ?: '-' ?></td>
<td><?= $u['user_ups'] ?: '-' ?></td>
</tr>

<?php } ?>

</table>

<hr>

<h6>📡 อุปกรณ์ใช้ร่วม</h6>

<table class="table table-bordered text-center">
<tr>
<th>#</th>
<th>ประเภท</th>
<th>รหัส</th>
</tr>

<?php
$map = [
'user_cctv'=>'CCTV',
'user_nvr'=>'NVR',
'user_projector'=>'Projector',
'user_printer'=>'Printer',
'user_audio_set'=>'Audio Set',
'user_plotter'=>'Plotter',
'user_Accessories_IT'=>'Accessories IT',
'user_Drone'=>'Drone',
'user_Optical_Fiber'=>'Optical Fiber',
'user_Server'=>'Server'
];

$j=1;
$unique=[];

foreach($sharedRows as $row){

foreach($map as $field=>$type){

if(empty($row[$field])) continue;

foreach(explode(',',$row[$field]) as $code){

$code = trim($code);

// ❌ ตัดของที่โอนแล้ว
if(in_array($code,$transfered)) continue;

$key = $type.$code;
if(isset($unique[$key])) continue;

$unique[$key]=1;
?>

<tr>
<td><?= $j++ ?></td>
<td><?= $type ?></td>
<td><?= $code ?></td>
</tr>

<?php
}
}
}
?>

</table>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>