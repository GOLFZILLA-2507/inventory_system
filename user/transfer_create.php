<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];
$user = $_SESSION['fullname'];

/* ===============================
โหลดรายชื่อโครงการ
=============================== */

$stmt = $conn->prepare("
SELECT DISTINCT site AS project_name
FROM Employee
WHERE site IS NOT NULL
AND site <> ?
ORDER BY site
");

$stmt->execute([$site]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* ===============================
โหลดอุปกรณ์ของโครงการ
=============================== */

$stmt = $conn->prepare("
SELECT *
FROM IT_user_information u
WHERE u.user_project = ?

/* =========================================
ตัดเฉพาะเครื่องที่โอนออกจากโครงการนี้
========================================= */
AND NOT EXISTS (
    SELECT 1
    FROM IT_AssetTransfer_Headers t
    WHERE t.no_pc = u.user_no_pc
    AND t.from_site = ?
    AND t.status = 'รับของแล้ว'
)
");

$stmt->execute([$site,$site]);
$row = $stmt->fetchAll(PDO::FETCH_ASSOC);

$assets = [];

foreach($row as $row){

/* ================= PC ================= */

if(!empty($row['user_no_pc'])){
$assets[$row['asset_id']] = [
'asset_id'=>$row['asset_id'],
'no_pc'=>$row['user_no_pc'],
'type'=>$row['user_type_equipment'],
'details'=>$row['user_equipment_details'],
'new_no'=>$row['user_new_no'],
'spec'=>$row['user_spec'],
'ram'=>$row['user_ram'],
'ssd'=>$row['user_ssd'],
'gpu'=>$row['user_gpu']
];
}

/* ================= Monitor ================= */

if(!empty($row['user_monitor1'])){
$assets[$row['user_monitor1']] = [
'asset_id'=>$row['user_monitor1'],
'no_pc'=>$row['user_monitor1'],
'type'=>'Monitor'
];
}

if(!empty($row['user_monitor2'])){
$assets[$row['user_monitor2']] = [
'asset_id'=>$row['user_monitor2'],
'no_pc'=>$row['user_monitor2'],
'type'=>'Monitor'
];
}

/* ================= UPS ================= */

if(!empty($row['user_ups'])){
$assets[$row['user_ups']] = [
'asset_id'=>$row['user_ups'],
'no_pc'=>$row['user_ups'],
'type'=>'UPS'
];
}

/* ================= CCTV ตัด , ================= */

if(!empty($row['user_cctv'])){

$cctvList = explode(',', $row['user_cctv']);

foreach($cctvList as $cctv){

$cctv = trim($cctv);

$assets[$cctv] = [
'asset_id'=>$cctv,
'no_pc'=>$cctv,
'type'=>'CCTV'
];

}

}

/* ================= NVR ตัด ,================= */
if(!empty($row['user_nvr'])){

$nvrList = explode(',', $row['user_nvr']);

foreach($nvrList as $nvr){

$nvr = trim($nvr);

$assets[$nvr] = [
'asset_id'=>$nvr,
'no_pc'=>$nvr,
'type'=>'NVR'
];

}

}

/* ================= Projector ================= */

if(!empty($row['user_projector'])){
$assets[$row['user_projector']] = [
'asset_id'=>$row['user_projector'],
'no_pc'=>$row['user_projector'],
'type'=>'Projector'
];
}

/* ================= Printer ตัด================= */

if(!empty($row['user_printer'])){

$printerList = explode(',', $row['user_printer']);

foreach($printerList as $printer){

$printer = trim($printer);

$assets[$printer] = [
'asset_id'=>$printer,
'no_pc'=>$printer,
'type'=>'Printer'
];

}

}

/* ================= Audio ================= */

if(!empty($row['user_audio_set'])){
$assets[$row['user_audio_set']] = [
'asset_id'=>$row['user_audio_set'],
'no_pc'=>$row['user_audio_set'],
'type'=>'Audio Set'
];
}

/* ================= Plotter ================= */

if(!empty($row['user_plotter'])){
$assets[$row['user_plotter']] = [
'asset_id'=>$row['user_plotter'],
'no_pc'=>$row['user_plotter'],
'type'=>'Plotter'
];
}

/* ================= Accessories ================= */

if(!empty($row['user_Accessories_IT'])){
$assets[$row['user_Accessories_IT']] = [
'asset_id'=>$row['user_Accessories_IT'],
'no_pc'=>$row['user_Accessories_IT'],
'type'=>'Accessories IT'
];
}

/* ================= Drone ================= */

if(!empty($row['user_Drone'])){
$assets[$row['user_Drone']] = [
'asset_id'=>$row['user_Drone'],
'no_pc'=>$row['user_Drone'],
'type'=>'Drone'
];
}

/* ================= Fiber ================= */

if(!empty($row['user_Optical_Fiber'])){
$assets[$row['user_Optical_Fiber']] = [
'asset_id'=>$row['user_Optical_Fiber'],
'no_pc'=>$row['user_Optical_Fiber'],
'type'=>'Optical Fiber'
];
}

/* ================= Server ================= */

if(!empty($row['user_Server'])){
$assets[$row['user_Server']] = [
'asset_id'=>$row['user_Server'],
'no_pc'=>$row['user_Server'],
'type'=>'Server'
];
}

}


/* =====================================================
SUBMIT FORM
===================================================== */

if(isset($_POST['submit'])){

$type = $_POST['transfer_type'] ?? '';
$to   = $_POST['to_site'] ?? '';
$items = $_POST['asset_ids'] ?? [];

if(empty($items)){
echo "<script>alert('กรุณาเลือกอุปกรณ์');</script>";
}
else{


/* ===============================
หาลำดับรอบการส่ง
=============================== */

$stmtRound = $conn->prepare("
SELECT ISNULL(MAX(sent_transfer),0)+1 AS round_transfer
FROM IT_AssetTransfer_Headers
");

$stmtRound->execute();
$r = $stmtRound->fetch(PDO::FETCH_ASSOC);

$sent_transfer = $r['round_transfer'];


$stmt = $conn->prepare("
INSERT INTO IT_AssetTransfer_Headers
(sent_transfer,transfer_type,from_site,to_site,created_by,transfer_status,new_no,no_pc,details,spec,ssd,ram,gpu,type)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
");

foreach($items as $aid){

if(isset($assets[$aid])){

$a = $assets[$aid];

$stmt->execute([

$sent_transfer,
$type,
$site,
$to,
$user,
'รอตรวจรับ',   // ✅ ย้ายมาไว้ตรงนี้

$a['new_no'] ?? '',
$a['no_pc'] ?? '',
$a['details'] ?? '',
$a['spec'] ?? '',
$a['ssd'] ?? '',
$a['ram'] ?? '',
$a['gpu'] ?? '',
$a['type'] ?? ''

]);

}

}

header("Location: transfer_list.php?success=1");
exit;

}

}

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="container mt-4">
<div class="card shadow">

<div class="card-header bg-success text-white">
🚚 สร้างรายการโอนย้าย / ส่งมอบ / ส่งคืน
</div>

<div class="card-body">

<form method="post">

<div class="row">

<div class="col-md-4">
<label>ประเภท</label>
<select name="transfer_type" class="form-control">
<option value="โอนย้าย">โอนย้าย</option>
<option value="ส่งคืน">ส่งคืน</option>
</select>
</div>

<div class="col-md-4">
<label>จาก</label>
<input class="form-control" value="<?= $site ?>" readonly>
</div>

<div class="col-md-4">
<label>ไปยัง</label>
<select name="to_site" class="form-control">
<?php foreach($projects as $p): ?>
<option><?= $p['project_name'] ?></option>
<?php endforeach; ?>
</select>
</div>

</div>

<hr>

<table class="table table-bordered">

<tr>
<th>ลำดับ</th>
<th>เลือก</th>
<th>ประเภท</th>
<th>รหัส</th>
<th>Spec</th>
</tr>

<?php $i=1; foreach($assets as $a): 

$type = !empty($a['type'])
? $a['type']
: '<span class="badge bg-success">ยังไม่ได้บันทึกข้อมูล</span>';

$code = !empty($a['no_pc'])
? $a['no_pc']
: '<span class="badge bg-success">ยังไม่ได้บันทึกข้อมูล</span>';

$spec = trim(($a['spec'] ?? '').($a['ram'] ?? '').($a['ssd'] ?? '').($a['gpu'] ?? ''));

if($spec==''){
$spec='<span class="badge bg-success">ยังไม่ได้บันทึกข้อมูล</span>';
}else{
$spec=$a['spec']." | ".$a['ram']." | ".$a['ssd']." | ".$a['gpu'];
}

?>

<tr>

<td><?= $i++ ?></td>

<td>
<input type="checkbox" name="asset_ids[]" value="<?= $a['asset_id'] ?>">
</td>

<td><?= $type ?></td>

<td><?= $code ?></td>

<td><?= $spec ?></td>

</tr>

<?php endforeach; ?>

</table>

<div class="text-end mt-2">
<strong>
จำนวนอุปกรณ์ที่เลือก :
<span id="countSelect">0</span>
</strong>
</div>

<button class="btn btn-success mt-3" name="submit">
📨 ส่งรายการ
</button>

</form>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>

<script>

const checkboxes=document.querySelectorAll("input[name='asset_ids[]']");
const counter=document.getElementById("countSelect");

function updateCount(){

let count=0;

checkboxes.forEach(cb=>{
if(cb.checked) count++;
});

counter.innerText=count;

}

checkboxes.forEach(cb=>{
cb.addEventListener("change",updateCount);
});

</script>