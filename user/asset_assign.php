<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];
$user = $_SESSION['fullname'];

/* ================= โหลดพนักงาน ================= */
$employees = $conn->prepare("
SELECT fullname, position, department
FROM Employee
WHERE site = ?
ORDER BY fullname
");
$employees->execute([$site]);
$employees = $employees->fetchAll(PDO::FETCH_ASSOC);

/* ================= โหลดอุปกรณ์ ================= */
function getAssets($conn,$types){

    $in  = str_repeat('?,', count($types) - 1) . '?';

    $sql = "
    SELECT asset_id,no_pc,type_equipment,spec,ram,ssd,gpu
    FROM IT_assets
    WHERE type_equipment IN ($in)
    ORDER BY no_pc
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($types);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$computers = getAssets($conn,['PC','Notebook','All_In_One']);
$monitors  = getAssets($conn,['Monitor']);
$upsList   = getAssets($conn,['UPS']);


/* ================= SUBMIT ================= */

if(isset($_POST['submit'])){

$emp  = $_POST['employee'] ?? null;
$pos  = $_POST['position'] ?? null;
$asset_id = $_POST['asset_id'] ?? null;

$pc   = $_POST['no_pc'] ?? null;
$spec = $_POST['spec'] ?? null;
$ram  = $_POST['ram'] ?? null;
$ssd  = $_POST['ssd'] ?? null;
$gpu  = $_POST['gpu'] ?? null;

$m1  = $_POST['monitor1'] ?? null;
$m2  = $_POST['monitor2'] ?? null;
$ups = $_POST['ups'] ?? null;

$type_equipment = $_POST['type_equipment'] ?? null;

$equipment_details = $pc;


/* ===== CHECK EXIST ===== */

$check = $conn->prepare("SELECT COUNT(*) FROM IT_user_information WHERE asset_id=?");
$check->execute([$asset_id]);
$exists = $check->fetchColumn();



/* ================= UPDATE ================= */

if($exists){

$stmt = $conn->prepare("

UPDATE IT_user_information SET

user_employee=?,
user_position=?,
user_project=?,

user_new_no=?,
user_no_pc=?,
user_equipment_details=?,

user_spec=?,
user_ssd=?,
user_ram=?,
user_gpu=?,

user_monitor1=?,
user_brand_1=NULL,
user_monitor2=?,
user_brand_2=NULL,

user_ups=?,
user_cctv=NULL,
user_nvr=NULL,
user_projector=NULL,
user_printer=NULL,

user_Service_life=NULL,
user_update=GETDATE(),

user_audio_set=NULL,
user_plotter=NULL,
user_Accessories_IT=NULL,
user_Drone=NULL,
user_Optical_Fiber=NULL,
user_Server=NULL,

user_type_equipment=?

WHERE asset_id=?

");

$stmt->execute([

$emp,
$pos,
$site,

$pc,
$pc,
$equipment_details,

$spec,
$ssd,
$ram,
$gpu,

$m1,
$m2,
$ups,

$type_equipment,

$asset_id

]);

}


/* ================= INSERT ================= */

else{

$stmt = $conn->prepare("

INSERT INTO IT_user_information(

asset_id,
user_employee,
user_position,
user_project,

user_new_no,
user_no_pc,
user_equipment_details,

user_spec,
user_ssd,
user_ram,
user_gpu,

user_monitor1,
user_brand_1,
user_monitor2,
user_brand_2,

user_ups,
user_cctv,
user_nvr,
user_projector,
user_printer,

user_Service_life,
user_update,

user_audio_set,
user_plotter,
user_Accessories_IT,
user_Drone,
user_Optical_Fiber,
user_Server,

user_type_equipment

)

VALUES(

?, ?, ?, ?,
?, ?, ?,
?, ?, ?, ?,
?, NULL, ?, NULL,
?, NULL, NULL, NULL, NULL,
NULL, GETDATE(),
NULL, NULL, NULL, NULL, NULL, NULL,
?

)

");

$stmt->execute([

$asset_id,
$emp,
$pos,
$site,

$pc,
$pc,
$equipment_details,

$spec,
$ssd,
$ram,
$gpu,

$m1,
$m2,

$ups,

$type_equipment

]);

}



/* ================= UPDATE IT_assets ================= */

if(!empty($asset_id)){

$conn->prepare("
UPDATE IT_assets
SET project=?, [update]=GETDATE()
WHERE asset_id=?
")->execute([$site,$asset_id]);

}

if(!empty($m1)){

$conn->prepare("
UPDATE IT_assets
SET project=?, [update]=GETDATE()
WHERE no_pc=?
")->execute([$site,$m1]);

}

if(!empty($m2)){

$conn->prepare("
UPDATE IT_assets
SET project=?, [update]=GETDATE()
WHERE no_pc=?
")->execute([$site,$m2]);

}

if(!empty($ups)){

$conn->prepare("
UPDATE IT_assets
SET project=?, [update]=GETDATE()
WHERE no_pc=?
")->execute([$site,$ups]);

}


header("Location: asset_shared_view.php?success=1");
exit;

}

include 'partials/header.php';
include 'partials/sidebar.php';

?>

<style>
body{font-family:'Sarabun';font-size:14px;}
.card-header{background:linear-gradient(135deg,#198754,#20c997);color:white;}
</style>

<div class="container mt-4">
<div class="card shadow">
<div class="card-header">
<h5 class="mb-0">🖥️ จัดการอุปกรณ์ให้พนักงาน</h5>
</div>

<div class="card-body">

<?php if(isset($_GET['success'])): ?>
<div class="alert alert-success">บันทึกข้อมูลเรียบร้อย</div>
<?php endif; ?>

<form method="post" onsubmit="return confirm('ยืนยันการมอบอุปกรณ์ให้พนักงาน ?')">

<div class="row">

<div class="col-md-6">
<label>เลือกพนักงาน</label>
<select id="empSelect" name="employee" class="form-control mb-2" required>
<option value="">-- เลือกพนักงาน --</option>
<?php foreach($employees as $e): ?>
<option value="<?= $e['fullname'] ?>"
data-pos="<?= $e['position'] ?>"
data-dep="<?= $e['department'] ?>">
<?= $e['fullname'] ?>
</option>
<?php endforeach; ?>
</select>

<input type="text" id="position" name="position" class="form-control mb-2" readonly>
<input type="text" id="department" class="form-control mb-3" readonly>
</div>

<div class="col-md-6">
<label>เลือกเครื่อง</label>
<select id="pcSelect" name="asset_id" class="form-control mb-2" required>
<option value="">-- เลือกเครื่อง --</option>
<?php foreach($computers as $c): ?>
<option value="<?= $c['asset_id'] ?>"
data-pc="<?= $c['no_pc'] ?>"
data-spec="<?= $c['spec'] ?>"
data-ram="<?= $c['ram'] ?>"
data-ssd="<?= $c['ssd'] ?>"
data-gpu="<?= $c['gpu'] ?>"
data-type="<?= $c['type_equipment'] ?>">
<?= $c['no_pc'] ?>
</option>
<?php endforeach; ?>
</select>

<input type="text" id="no_pc" name="no_pc" class="form-control mb-2" readonly>
<input type="text" id="spec_full" class="form-control mb-2" readonly>
<input type="hidden" name="spec" id="spec">
<input type="hidden" name="ram" id="ram">
<input type="hidden" name="ssd" id="ssd">
<input type="hidden" name="gpu" id="gpu">
<input type="hidden" name="type_equipment" id="type_equipment">
</div>

<hr>

<div class="row">
<div class="col-md-4">
<label>Monitor 1</label>
<select name="monitor1" class="form-control" required>
<option value="">-- เลือกจอ --</option>
<?php foreach($monitors as $m): ?>
<option value="<?= $m['no_pc'] ?>"><?= $m['no_pc'] ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-4">
<label>Monitor 2</label>
<select name="monitor2" class="form-control">
<option value="">-- ไม่มี --</option>
<?php foreach($monitors as $m): ?>
<option value="<?= $m['no_pc'] ?>"><?= $m['no_pc'] ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-4">
<label>UPS</label>
<select name="ups" class="form-control" >
<option value="">-- เลือก UPS --</option>
<?php foreach($upsList as $u): ?>
<option value="<?= $u['no_pc'] ?>"><?= $u['no_pc'] ?></option>
<?php endforeach; ?>
</select>
</div>
</div>

<div class="text-end mt-3">
<button class="btn btn-success px-4" name="submit">💾 บันทึก</button>
</div>

</form>

</div>
</div>
</div>

<script>
document.getElementById('empSelect').addEventListener('change',function(){
let opt=this.options[this.selectedIndex];
document.getElementById('position').value=opt.getAttribute('data-pos')||'';
document.getElementById('department').value=opt.getAttribute('data-dep')||'';
});

document.getElementById('pcSelect').addEventListener('change',function(){
let opt=this.options[this.selectedIndex];

let pc = opt.getAttribute('data-pc')||'';
let spec = opt.getAttribute('data-spec')||'';
let ram = opt.getAttribute('data-ram')||'';
let ssd = opt.getAttribute('data-ssd')||'';
let gpu = opt.getAttribute('data-gpu')||'';
let type = opt.getAttribute('data-type')||'';

document.getElementById('no_pc').value = pc;
document.getElementById('spec_full').value = spec+" | RAM "+ram+" | SSD "+ssd+" | GPU "+gpu;
document.getElementById('spec').value = spec;
document.getElementById('ram').value = ram;
document.getElementById('ssd').value = ssd;
document.getElementById('gpu').value = gpu;
document.getElementById('type_equipment').value = type;
});
</script>

<?php include 'partials/footer.php'; ?>