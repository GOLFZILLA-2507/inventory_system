<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];

/* ================= LOAD ASSETS ================= */

function getByType($conn,$type){
$stmt=$conn->prepare("
SELECT asset_id,no_pc
FROM IT_assets
WHERE type_equipment=?
ORDER BY no_pc
");
$stmt->execute([$type]);
return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$audio      = getByType($conn,'audio_set');
$cctv       = getByType($conn,'CCTV');
$nvr        = getByType($conn,'NVR');
$printer    = getByType($conn,'Printer');
$plotter    = getByType($conn,'Plotter');
$projector  = getByType($conn,'Projector');


/* ================= SUBMIT ================= */

if(isset($_POST['submit'])){

$cctvArr = $_POST['cctv'] ?? [];
$nvrArr  = $_POST['nvr'] ?? [];

$audio_id     = $_POST['audio_set'] ?? null;
$printer_id   = $_POST['printer'] ?? null;
$plotter_id   = $_POST['plotter'] ?? null;
$projector_id = $_POST['projector'] ?? null;


/* ================= โหลดค่าปัจจุบัน ================= */

$old=$conn->prepare("
SELECT *
FROM IT_user_information
WHERE user_project=?
");
$old->execute([$site]);
$current=$old->fetch(PDO::FETCH_ASSOC);


/* ================= ค่าเดิม ================= */

$old_cctv = !empty($current['user_cctv']) ? explode(',',$current['user_cctv']) : [];
$old_nvr  = !empty($current['user_nvr'])  ? explode(',',$current['user_nvr'])  : [];

$old_audio   = $current['user_audio_set'] ?? null;
$old_printer = $current['user_printer'] ?? null;
$old_plotter = $current['user_plotter'] ?? null;
$old_projector = $current['user_projector'] ?? null;

$old_accessories = $current['user_Accessories_IT'] ?? null;
$old_drone       = $current['user_Drone'] ?? null;
$old_fiber       = $current['user_Optical_Fiber'] ?? null;
$old_server      = $current['user_Server'] ?? null;
$old_service     = $current['user_Service_life'] ?? null;


/* ================= NEW ARRAY ================= */

$new_cctv=[];
$new_nvr=[];


/* ================= CCTV ================= */

foreach($cctvArr as $id){

if(!$id) continue;

$q=$conn->prepare("SELECT no_pc FROM IT_assets WHERE asset_id=?");
$q->execute([$id]);
$row=$q->fetch(PDO::FETCH_ASSOC);

if($row){

$new_cctv[]=$row['no_pc'];

$conn->prepare("
UPDATE IT_assets
SET project=?, [update]=GETDATE()
WHERE asset_id=?
")->execute([$site,$id]);

}

}


/* ================= NVR ================= */

foreach($nvrArr as $id){

if(!$id) continue;

$q=$conn->prepare("SELECT no_pc FROM IT_assets WHERE asset_id=?");
$q->execute([$id]);
$row=$q->fetch(PDO::FETCH_ASSOC);

if($row){

$new_nvr[]=$row['no_pc'];

$conn->prepare("
UPDATE IT_assets
SET project=?, [update]=GETDATE()
WHERE asset_id=?
")->execute([$site,$id]);

}

}


/* ================= MERGE ================= */

$final_cctv=array_unique(array_merge($old_cctv,$new_cctv));
$final_nvr=array_unique(array_merge($old_nvr,$new_nvr));

$cctv_str=implode(',',$final_cctv);
$nvr_str=implode(',',$final_nvr);


/* ================= SINGLE FUNCTION ================= */

function setSingle($conn,$id,$site,$old){

if(!$id) return $old;

$q=$conn->prepare("SELECT no_pc FROM IT_assets WHERE asset_id=?");
$q->execute([$id]);
$row=$q->fetch(PDO::FETCH_ASSOC);

if($row){

$conn->prepare("
UPDATE IT_assets
SET project=?, [update]=GETDATE()
WHERE asset_id=?
")->execute([$site,$id]);

return $row['no_pc'];

}

return $old;

}


$audio_pc=setSingle($conn,$audio_id,$site,$old_audio);
$printer_pc=setSingle($conn,$printer_id,$site,$old_printer);
$plotter_pc=setSingle($conn,$plotter_id,$site,$old_plotter);
$projector_pc=setSingle($conn,$projector_id,$site,$old_projector);


/* ================= UPDATE ================= */

$stmt=$conn->prepare("
UPDATE IT_user_information SET

user_cctv=?,
user_nvr=?,
user_projector=?,
user_printer=?,
user_Service_life=?,
user_audio_set=?,
user_plotter=?,
user_Accessories_IT=?,
user_Drone=?,
user_Optical_Fiber=?,
user_Server=?,
user_update=GETDATE()

WHERE user_project=?
");

$stmt->execute([

$cctv_str,
$nvr_str,
$projector_pc,
$printer_pc,
$old_service,
$audio_pc,
$plotter_pc,
$old_accessories,
$old_drone,
$old_fiber,
$old_server,
$site

]);

header("Location: asset_shared_view.php?success=1");
exit;

}

include 'partials/header.php';
include 'partials/sidebar.php';
?>


<!-- SELECT2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css" rel="stylesheet"/>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js"></script>

<style>
.card-header{background:linear-gradient(135deg,#198754,#20c997);color:white;}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:15px;}
</style>


<div class="container mt-4">
<div class="card shadow">

<div class="card-header">
<h5 class="mb-0">➕ เพิ่มอุปกรณ์ใช้ร่วม (<?= $site ?>)</h5>
</div>

<div class="card-body">

<form method="post" onsubmit="return confirm('ยืนยันการบันทึกอุปกรณ์ใช้ร่วม ?')">

<div class="two-col">

<!-- CCTV -->
<div>
<label>CCTV</label>

<div id="cctvWrap">

<select name="cctv[]" class="form-control select2 mb-2">

<option value="">-- เลือก CCTV --</option>

<?php foreach($cctv as $a): ?>

<option value="<?= $a['asset_id'] ?>">
<?= $a['no_pc'] ?>
</option>

<?php endforeach; ?>

</select>

</div>

<button type="button" onclick="addCCTV()" class="btn btn-sm btn-success">+ เพิ่ม</button>

</div>


<!-- NVR -->
<div>

<label>NVR</label>

<div id="nvrWrap">

<select name="nvr[]" class="form-control select2 mb-2">

<option value="">-- เลือก NVR --</option>

<?php foreach($nvr as $a): ?>

<option value="<?= $a['asset_id'] ?>">
<?= $a['no_pc'] ?>
</option>

<?php endforeach; ?>

</select>

</div>

<button type="button" onclick="addNVR()" class="btn btn-sm btn-success">+ เพิ่ม</button>

</div>


<!-- AUDIO -->
<div>
<label>Audio Set</label>
<select name="audio_set" class="form-control select2">
<option value="">-- ไม่เลือก --</option>
<?php foreach($audio as $a): ?>
<option value="<?= $a['asset_id'] ?>"><?= $a['no_pc'] ?></option>
<?php endforeach; ?>
</select>
</div>


<!-- PRINTER -->
<div>
<label>Printer</label>
<select name="printer" class="form-control select2">
<option value="">-- ไม่เลือก --</option>
<?php foreach($printer as $a): ?>
<option value="<?= $a['asset_id'] ?>"><?= $a['no_pc'] ?></option>
<?php endforeach; ?>
</select>
</div>


<!-- PLOTTER -->
<div>
<label>Plotter</label>
<select name="plotter" class="form-control select2">
<option value="">-- ไม่เลือก --</option>
<?php foreach($plotter as $a): ?>
<option value="<?= $a['asset_id'] ?>"><?= $a['no_pc'] ?></option>
<?php endforeach; ?>
</select>
</div>


<!-- PROJECTOR -->
<div>
<label>Projector</label>
<select name="projector" class="form-control select2">
<option value="">-- ไม่เลือก --</option>
<?php foreach($projector as $a): ?>
<option value="<?= $a['asset_id'] ?>"><?= $a['no_pc'] ?></option>
<?php endforeach; ?>
</select>
</div>


</div>

<div class="text-end mt-4">
<button class="btn btn-success px-4" name="submit">💾 บันทึก</button>
</div>

</form>

</div>
</div>
</div>


<script>

$('.select2').select2({
width:'100%',
placeholder:'พิมพ์ค้นหา...',
allowClear:true
});

function addCCTV(){
let el=document.querySelector('#cctvWrap select').cloneNode(true);
document.getElementById('cctvWrap').appendChild(el);
$('.select2').select2();
}

function addNVR(){
let el=document.querySelector('#nvrWrap select').cloneNode(true);
document.getElementById('nvrWrap').appendChild(el);
$('.select2').select2();
}

</script>

<?php include 'partials/footer.php'; ?>