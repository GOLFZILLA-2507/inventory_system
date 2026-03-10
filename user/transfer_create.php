<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];
$user = $_SESSION['fullname'];

/* โหลดรายชื่อโครงการจากฐาน Employee */

$stmt = $conn->prepare("
SELECT DISTINCT site AS project_name
FROM Employee
WHERE site IS NOT NULL
ORDER BY site
");

$stmt->execute();

$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
   โหลดข้อมูล asset ของโครงการ
===================================================== */

$stmt = $conn->prepare("
SELECT *
FROM IT_user_information
WHERE user_project = ?
");

$stmt->execute([$site]);
$row = $stmt->fetchAll(PDO::FETCH_ASSOC);

$assets = [];
foreach($row as $row)
{

/* PC */
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

/* monitor */
if(!empty($row['user_monitor1'])){
$assets[$row['user_monitor1']] = [
'asset_id'=>$row['user_monitor1'],
'no_pc'=>$row['user_monitor1']
];
}

if(!empty($row['user_monitor2'])){
$assets[$row['user_monitor2']] = [
'asset_id'=>$row['user_monitor2'],
'no_pc'=>$row['user_monitor2']
];
}

/* CCTV */
if(!empty($row['user_cctv'])){
$assets[$row['user_cctv']] = [
'asset_id'=>$row['user_cctv'],
'no_pc'=>$row['user_cctv']
];
}

/* NVR */
if(!empty($row['user_nvr'])){
$assets[$row['user_nvr']] = [
'asset_id'=>$row['user_nvr'],
'no_pc'=>$row['user_nvr']
];
}

/* Projector */
if(!empty($row['user_projector'])){
$assets[$row['user_projector']] = [
'asset_id'=>$row['user_projector'],
'no_pc'=>$row['user_projector']
];
}

/* Printer */
if(!empty($row['user_printer'])){
$assets[$row['user_printer']] = [
'asset_id'=>$row['user_printer'],
'no_pc'=>$row['user_printer']
];
}

/* Audio */
if(!empty($row['user_audio_set'])){
$assets[$row['user_audio_set']] = [
'asset_id'=>$row['user_audio_set'],
'no_pc'=>$row['user_audio_set']
];
}

/* Plotter */
if(!empty($row['user_plotter'])){
$assets[$row['user_plotter']] = [
'asset_id'=>$row['user_plotter'],
'no_pc'=>$row['user_plotter']
];
}

/* Accessories */
if(!empty($row['user_Accessories_IT'])){
$assets[$row['user_Accessories_IT']] = [
'asset_id'=>$row['user_Accessories_IT'],
'no_pc'=>$row['user_Accessories_IT']
];
}

/* Drone */
if(!empty($row['user_Drone'])){
$assets[$row['user_Drone']] = [
'asset_id'=>$row['user_Drone'],
'no_pc'=>$row['user_Drone']
];
}

/* Fiber */
if(!empty($row['user_Optical_Fiber'])){
$assets[$row['user_Optical_Fiber']] = [
'asset_id'=>$row['user_Optical_Fiber'],
'no_pc'=>$row['user_Optical_Fiber']
];
}

/* Server */
if(!empty($row['user_Server'])){
$assets[$row['user_Server']] = [
'asset_id'=>$row['user_Server'],
'no_pc'=>$row['user_Server']
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

$stmt = $conn->prepare("
INSERT INTO IT_AssetTransfer_Headers
(transfer_type,from_site,to_site,created_by,transfer_status,new_no,no_pc,details,spec,ssd,ram,gpu,type)
VALUES (?,?,?,?, 'รอตรวจรับ',?,?,?,?,?,?,?,?)
");

foreach($items as $aid){

if(isset($assets[$aid])){

$a = $assets[$aid];

$stmt->execute([

$type,
$site,
$to,
$user,

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
<th>#</th>
<th>รหัสเครื่อง</th>
<th>Spec</th>
</tr>

<?php foreach($assets as $a): ?>

<tr>

<td>
<input type="checkbox" name="asset_ids[]" value="<?= $a['asset_id'] ?>">
</td>

<td>
<?= $a['no_pc'] ?? '' ?>
</td>

<td>
<?= ($a['spec'] ?? '')." | ".($a['ram'] ?? '')." | ".($a['ssd'] ?? '')." | ".($a['gpu'] ?? '') ?>
</td>

</tr>

<?php endforeach; ?>

</table>

<button class="btn btn-success" name="submit">
📨 ส่งรายการ
</button>

</form>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>