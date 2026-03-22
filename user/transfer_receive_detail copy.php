<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];
$user = $_SESSION['fullname'];

$round = $_GET['round'] ?? ($_POST['round'] ?? 0);

/* =====================================================
   FUNCTION: ต่อค่า shared asset (ต่อท้าย + กันซ้ำ)
===================================================== */
function updateSharedAsset($conn,$site,$field,$code,$user){

    $stmt = $conn->prepare("
        SELECT *
        FROM IT_user_information
        WHERE user_project = ?
        AND user_type_equipment = 'SHARED'
    ");
    $stmt->execute([$site]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // ถ้ายังไม่มี row → สร้างใหม่

       if(!$row){

    // 🔥 generate asset_id
    $stmtMax = $conn->prepare("SELECT MAX(asset_id) as max_id FROM IT_user_information");
    $stmtMax->execute();
    $max = $stmtMax->fetch(PDO::FETCH_ASSOC);

    $new_id = ($max['max_id'] ?? 0) + 1;

    // 🔥 insert พร้อม asset_id
    $conn->prepare("
        INSERT INTO IT_user_information 
        (asset_id, user_project, user_type_equipment, user_record, user_update)
        VALUES (?, ?, 'SHARED', ?, GETDATE())
    ")->execute([$new_id, $site, $user]);

    $stmt->execute([$site]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
}

        $stmt->execute([$site]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    

    // ค่าเดิม
    $old = $row[$field] ?? '';

    $arr = !empty($old) ? explode(',',$old) : [];

    // กันซ้ำ
    if(!in_array($code,$arr)){
        $arr[] = $code;
    }

    $new = implode(',',$arr);

    // update
    $sql = "UPDATE IT_user_information SET $field=?, user_update=GETDATE() WHERE user_project=? AND user_type_equipment='SHARED'";
    $conn->prepare($sql)->execute([$new,$site]);
}

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

$type = $row['type'];
$code = trim($row['no_pc']);

/* ===============================
MAP shared asset
=============================== */
$sharedMap = [
    'CCTV' => 'user_cctv',
    'NVR' => 'user_nvr',
    'Projector' => 'user_projector',
    'Printer' => 'user_printer',
    'Audio Set' => 'user_audio_set',
    'Plotter' => 'user_plotter',
    'Accessories IT' => 'user_Accessories_IT',
    'Drone' => 'user_Drone',
    'Optical Fiber' => 'user_Optical_Fiber',
    'Server' => 'user_Server'
];

/* ===============================
เช็คว่าเป็น shared หรือไม่
=============================== */
if(isset($sharedMap[$type])){

    // ✅ UPDATE ต่อท้าย (แทน INSERT)
    $field = $sharedMap[$type];
    updateSharedAsset($conn,$site,$field,$code,$user);

}
else{

    /* =====================================================
       🔥 MAP TYPE → FIELD (หัวใจของระบบ)
    ===================================================== */

    $fieldMap = [

        // 🖥 กลุ่มเครื่องหลัก
        'PC' => 'user_no_pc',
        'Notebook' => 'user_no_pc',
        'All In One' => 'user_no_pc',

        // 🖥 อุปกรณ์เสริม
        'Monitor' => 'monitor_auto', // 🔥 ใช้ logic เลือกช่องเอง
        'UPS' => 'user_ups'
    ];

    /* =====================================================
       🔥 หา field ที่ต้องลง
    ===================================================== */

    $field = $fieldMap[$type] ?? 'user_no_pc';


    /* =====================================================
       🔥 generate asset_id (กัน NULL error)
    ===================================================== */

    $stmtMax = $conn->prepare("SELECT MAX(asset_id) as max_id FROM IT_user_information");
    $stmtMax->execute();
    $max = $stmtMax->fetch(PDO::FETCH_ASSOC);

    $new_asset_id = ($max['max_id'] ?? 0) + 1;


    /* =====================================================
       🔥 กรณี Monitor → เลือกช่องอัตโนมัติ
    ===================================================== */

    if($field === 'monitor_auto'){

        // 🔍 หา row ล่าสุดของ project นี้ (ที่ยังไม่มี employee)
        $stmtCheck = $conn->prepare("
            SELECT TOP 1 user_monitor1, user_monitor2
            FROM IT_user_information
            WHERE user_project = ?
            AND user_employee IS NULL
            ORDER BY asset_id DESC
        ");
        $stmtCheck->execute([$site]);
        $rowCheck = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        // 🔥 เลือกช่อง
        if(empty($rowCheck['user_monitor1'])){
            $field = 'user_monitor1';
        }else{
            $field = 'user_monitor2';
        }
    }


    /* =====================================================
       🔥 เตรียมข้อมูลพื้นฐาน
    ===================================================== */

    $dataInsert = [
        'asset_id' => $new_asset_id,
        'user_employee' => NULL,
        'user_project' => $site,
        'user_type_equipment' => $type,
        'user_record' => $user
    ];

    /* =====================================================
       🔥 ใส่ค่า field ตามประเภท
    ===================================================== */

    $dataInsert[$field] = $code;


    /* =====================================================
       🔥 ถ้าเป็น PC → ใส่ SPEC เพิ่ม
    ===================================================== */

    if(in_array($type, ['PC','Notebook','All In One'])){

        $dataInsert['user_spec'] = $row['spec'] ?? null;
        $dataInsert['user_ram']  = $row['ram'] ?? null;
        $dataInsert['user_ssd']  = $row['ssd'] ?? null;
        $dataInsert['user_gpu']  = $row['gpu'] ?? null;
        $dataInsert['user_equipment_details'] = $row['details'] ?? null;
        $dataInsert['user_new_no'] = $row['new_no'] ?? null;
    }


    /* =====================================================
       🔥 สร้าง SQL แบบ dynamic
    ===================================================== */

    $columns = array_keys($dataInsert);
    $placeholders = implode(',', array_fill(0, count($columns), '?'));

    $sql = "
        INSERT INTO IT_user_information
        (" . implode(',', $columns) . ", user_update)
        VALUES ($placeholders, GETDATE())
    ";

    $stmtInsert = $conn->prepare($sql);
    $stmtInsert->execute(array_values($dataInsert));

}

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