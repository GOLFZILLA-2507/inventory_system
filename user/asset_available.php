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
u.asset_id,
u.user_no_pc,
u.user_monitor1,
u.user_monitor2,
u.user_ups,
u.user_cctv,
u.user_nvr,
u.user_printer,
u.user_projector,
u.user_audio_set,
u.user_plotter,
u.user_Accessories_IT,
u.user_Drone,
u.user_Optical_Fiber,
u.user_Server,
u.user_type_equipment,
u.user_update,

t.from_site,
t.transfer_type

FROM IT_user_information u

/* ===============================
เอา transfer ล่าสุดของแต่ละเครื่อง
=============================== */

LEFT JOIN (
    SELECT no_pc, MAX(transfer_id) AS max_id
    FROM IT_AssetTransfer_Headers
    GROUP BY no_pc
) x 
ON x.no_pc = 
    u.user_no_pc OR
    x.no_pc = u.user_monitor1 OR
    x.no_pc = u.user_monitor2 OR
    x.no_pc = u.user_ups OR
    x.no_pc = u.user_cctv OR
    x.no_pc = u.user_nvr OR
    x.no_pc = u.user_printer OR
    x.no_pc = u.user_projector OR
    x.no_pc = u.user_audio_set OR
    x.no_pc = u.user_plotter OR
    x.no_pc = u.user_Accessories_IT OR
    x.no_pc = u.user_Drone OR
    x.no_pc = u.user_Optical_Fiber OR
    x.no_pc = u.user_Server

LEFT JOIN IT_AssetTransfer_Headers t
ON t.transfer_id = x.max_id

WHERE u.user_project = ?
AND u.user_employee IS NULL

AND t.to_site = ?
AND t.receive_status = 'รับแล้ว'

ORDER BY u.user_update DESC
");

$stmt->execute([$site, $site]);

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

<th>ลำดับ</th>
<th>รหัสอุปกรณ์</th>
<th>ประเภท</th>
<th>หมายเหตุ</th>
<th>วันที่บันทึก</th>
<th>จัดการ</th>

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
<td>
<?php
}
else{
$i=1;
foreach($data as $d){
?>

<td class="text-center"><?= $i++ ?></td>

<td class="fw-bold text-primary">

<?php

$code = 
$d['user_no_pc'] ??
$d['user_monitor1'] ??
$d['user_monitor2'] ??
$d['user_ups'] ??
$d['user_cctv'] ??
$d['user_nvr'] ??
$d['user_printer'] ??
$d['user_projector'] ??
$d['user_audio_set'] ??
$d['user_plotter'] ??
$d['user_Accessories_IT'] ??
$d['user_Drone'] ??
$d['user_Optical_Fiber'] ??
$d['user_Server'];

echo $code ?: '<span class="empty-data">ไม่มีข้อมูล</span>';

?>

</td>

<td>

<?= $d['user_type_equipment'] ?: '-' ?>

</td>

<td>

<?php
if(!empty($d['from_site'])){

echo "โอนจาก : <b>".htmlspecialchars($d['from_site'])."</b><br>";
echo "ประเภท : <span class='badge bg-info'>".htmlspecialchars($d['transfer_type'])."</span>";

}else{

echo '<span class="empty-data">ไม่มีข้อมูล</span>';

}
?>

</td>
<td>
<?= $d['user_update'] ?>

</td>
<td>

<a href="asset_assign_user.php?asset_id=<?= $d['asset_id'] ?>" class="btn btn-sm btn-outline-primary">
    <i class="fas fa-eye"></i> 👤 เพิ่มผู้ใช้
</a>

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