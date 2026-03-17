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

/* ถ้ารายการนี้รับแล้ว ให้ข้าม */
if($row['receive_status']=='รับแล้ว'){
continue;
}

/* =========================================
ถ้าติ๊กรับอุปกรณ์
========================================= */

if(in_array($id,$checked)){

/* update header */

$stmt = $conn->prepare("
UPDATE IT_AssetTransfer_Headers
SET
status='รับของแล้ว',
receive_status='รับแล้ว',
arrived_date = GETDATE()
WHERE transfer_id = ?
");

$stmt->execute([$id]);

/* =========================================
เพิ่มอุปกรณ์เข้าโครงการปลายทาง
========================================= */

/* หา asset_id ล่าสุด */

$stmt2 = $conn->prepare("SELECT MAX(asset_id) AS max_id FROM IT_user_information");
$stmt2->execute();
$row2 = $stmt2->fetch(PDO::FETCH_ASSOC);

$new_asset_id = ($row2['max_id'] ?? 0) + 1; 

/* =========================================
แยกตามประเภทอุปกรณ์
========================================= */

$type = $row['type'];
$code = $row['no_pc'];

$dataInsert = [
    'asset_id' => $new_asset_id,
    'user_employee' => NULL,
    'user_project' => $site,
    'user_new_no' => $row['new_no'],
    'user_no_pc' => NULL,
    'user_equipment_details' => $row['details'],
    'user_spec' => $row['spec'],
    'user_ssd' => $row['ssd'],
    'user_ram' => $row['ram'],
    'user_gpu' => $row['gpu'],
    'user_type_equipment' => $type,
    'user_record' => $user
];

/* ===============================
กำหนด field ตาม type
=============================== */

switch($type){

    case 'Computer':
        $dataInsert['user_no_pc'] = $code;
        break;

    case 'Monitor':
        $dataInsert['user_monitor1'] = $code;
        break;

    case 'UPS':
        $dataInsert['user_ups'] = $code;
        break;

    case 'CCTV':
        $dataInsert['user_cctv'] = $code;
        break;

    case 'NVR':
        $dataInsert['user_nvr'] = $code;
        break;

    case 'Printer':
        $dataInsert['user_printer'] = $code;
        break;

    case 'Projector':
        $dataInsert['user_projector'] = $code;
        break;

    case 'Audio Set':
        $dataInsert['user_audio_set'] = $code;
        break;

    case 'Plotter':
        $dataInsert['user_plotter'] = $code;
        break;

    case 'Accessories IT':
        $dataInsert['user_Accessories_IT'] = $code;
        break;

    case 'Drone':
        $dataInsert['user_Drone'] = $code;
        break;

    case 'Optical Fiber':
        $dataInsert['user_Optical_Fiber'] = $code;
        break;

    case 'Server':
        $dataInsert['user_Server'] = $code;
        break;
}

/* =========================================
สร้าง SQL แบบ dynamic
========================================= */

$columns = array_keys($dataInsert);
$placeholders = implode(',', array_fill(0, count($columns), '?'));

$sql = "INSERT INTO IT_user_information (" . implode(',', $columns) . ",user_update)
        VALUES ($placeholders, GETDATE())";

$stmt2 = $conn->prepare($sql);
$stmt2->execute(array_values($dataInsert));

}

/* =========================================
ถ้าไม่ได้ติ๊ก
========================================= */

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
/* =========================================
กันกด F5 แล้วบันทึกซ้ำ (สำคัญมาก)
========================================= */
header("Location: transfer_receive_detail.php?round=".$round);
exit;
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

/* =========================================
เช็คว่าทั้งรอบรับครบหรือยัง
========================================= */

$allReceived = true;

foreach($data as $chk){

    if($chk['receive_status'] != 'รับแล้ว'){
        $allReceived = false;
        break;
    }

}


include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="container mt-4">

<div class="card shadow">

<div class="card-header bg-success text-white">

ตรวจรับอุปกรณ์

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

<?php if(!$allReceived): ?>

<button class="btn btn-success" name="confirm">
ยืนยันการตรวจรับ
</button>

<?php else: ?>

<div class="alert alert-success text-center">
✅ รอบนี้ตรวจรับครบแล้ว
</div>

<?php endif; ?>

</form>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>