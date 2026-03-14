<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];


/* ================= LOAD ASSETS ================= */

function getByType($conn,$type){
    // exclude items already marked as in use
    $stmt=$conn->prepare("
SELECT asset_id,no_pc
FROM IT_assets
WHERE type_equipment=?
  AND (use_it IS NULL OR use_it = '')
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
$accessories  = getByType($conn,'Accessories_IT');
$drone        = getByType($conn,'Drone');
$fiber        = getByType($conn,'Optical_Fiber');
$server       = getByType($conn,'Server');

/* ================= SUBMIT ================= */

if(isset($_POST['submit'])){

    // collect submitted asset ids
    $cctvArr = $_POST['cctv'] ?? [];
    $nvrArr  = $_POST['nvr'] ?? [];

    $audio_id     = $_POST['audio_set'] ?? null;
    $printer_id   = $_POST['printer'] ?? null;
    $plotter_id   = $_POST['plotter'] ?? null;
    $projector_id = $_POST['projector'] ?? null;
    $accessories_id = $_POST['accessories'] ?? null;
    $drone_id       = $_POST['drone'] ?? null;
    $fiber_id       = $_POST['fiber'] ?? null;
    $server_id      = $_POST['server'] ?? null;

    // === VALIDATION: avoid duplicate or already-used assets ===
    $allIds = array_filter(array_merge(
        $cctvArr,
        $nvrArr,
        [$audio_id, $printer_id, $plotter_id, $projector_id, $accessories_id, $drone_id, $fiber_id, $server_id]
    ));

    // check for repeated selections in the form
    if(count($allIds) !== count(array_unique($allIds))){
        header("Location: asset_shared_add.php?error=".urlencode('มีอุปกรณ์ถูกเลือกซ้ำ'));
        exit;
    }

    // check each asset to ensure it's not already assigned to a user other than the current site
    foreach($allIds as $aid){
        // first resolve the asset id to its no_pc value
        $q=$conn->prepare("SELECT no_pc FROM IT_assets WHERE asset_id=?");
        $q->execute([$aid]);
        $row=$q->fetch(PDO::FETCH_ASSOC);
        if($row){
            $no = $row['no_pc'];
            // look for any record in the user information table that contains this no_pc
            // exclude the current site since re-using within the same project is allowed
            $uq = $conn->prepare(
                "SELECT user_project FROM IT_user_information
                 WHERE user_project <> ?
                   AND (
                         user_cctv LIKE ?
                      OR user_nvr  LIKE ?
                      OR user_projector = ?
                      OR user_printer   = ?
                      OR user_audio_set = ?
                      OR user_plotter   = ?
                      OR user_Accessories_IT = ?
                      OR user_Drone     = ?
                      OR user_Optical_Fiber = ?
                      OR user_Server    = ?
                   )");
            $like = '%' . $no . '%';
            $uq->execute([$site, $like, $like, $no, $no, $no, $no, $no, $no, $no, $no]);
            if($urow = $uq->fetch(PDO::FETCH_ASSOC)){
                header("Location: asset_shared_add.php?error=" . urlencode('อุปกรณ์ '.htmlspecialchars($no).' ถูกใช้งานโดยโครงการ '.htmlspecialchars($urow['user_project']).''));
                exit;
            }
        }
    }

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

    // convert old assigned equipment (stored as no_pc) back to asset_id for duplicate checks
    $oldIds = [];
    $convertStmt = $conn->prepare("SELECT asset_id FROM IT_assets WHERE no_pc=?");
    foreach($old_cctv as $no){
        if($no){
            $convertStmt->execute([$no]);
            if($r=$convertStmt->fetch(PDO::FETCH_ASSOC)){
                $oldIds[] = $r['asset_id'];
            }
        }
    }
    foreach($old_nvr as $no){
        if($no){
            $convertStmt->execute([$no]);
            if($r=$convertStmt->fetch(PDO::FETCH_ASSOC)){
                $oldIds[] = $r['asset_id'];
            }
        }
    }
    $singleOld = [$old_audio, $old_printer, $old_plotter, $old_projector, $old_accessories, $old_drone, $old_fiber, $old_server];
    foreach($singleOld as $no){
        if($no){
            $convertStmt->execute([$no]);
            if($r=$convertStmt->fetch(PDO::FETCH_ASSOC)){
                $oldIds[] = $r['asset_id'];
            }
        }
    }

    // validation against previously assigned assets for this site
    if(!empty($allIds)){
        foreach($allIds as $aid){
            if(in_array($aid, $oldIds)){
                header("Location: asset_shared_add.php?error=".urlencode('อุปกรณ์ ID:'.$aid.' มีการบันทึกไว้ก่อนหน้าแล้ว'));
                exit;
            }
        }
    }

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
SET project=?, use_it=?, [update]=GETDATE()
WHERE asset_id=?
")->execute([$site,$site,$id]);

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
SET project=?, use_it=?, [update]=GETDATE()
WHERE asset_id=?
")->execute([$site,$site,$id]);

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
SET project=?, use_it=?, [update]=GETDATE()
WHERE asset_id=?
")->execute([$site,$site,$id]);

        return $row['no_pc'];
    }

    return $old;

}


$audio_pc=setSingle($conn,$audio_id,$site,$old_audio);
$printer_pc=setSingle($conn,$printer_id,$site,$old_printer);
$plotter_pc=setSingle($conn,$plotter_id,$site,$old_plotter);
$projector_pc=setSingle($conn,$projector_id,$site,$old_projector);
$accessories_pc=setSingle($conn,$accessories_id,$site,$old_accessories);
$drone_pc=setSingle($conn,$drone_id,$site,$old_drone);
$fiber_pc=setSingle($conn,$fiber_id,$site,$old_fiber);
$server_pc=setSingle($conn,$server_id,$site,$old_server);


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
$accessories_pc,
$drone_pc,
$fiber_pc,
$server_pc,
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

<?php if(isset($_GET['error'])): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
<?php endif; ?>

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
<div>
<label>Printer</label>
<select name="printer" class="form-control select2">
<option value="">-- ไม่เลือก --</option>
<?php foreach($printer as $a): ?>
<option value="<?= $a['asset_id'] ?>"><?= $a['no_pc'] ?></option>
<?php endforeach; ?>
</select>
</div>
<button type="button" onclick="addPrinter()" class="btn btn-sm btn-success">+ เพิ่ม</button>
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

<!-- Accessories IT -->
<div>
<label>Accessories IT</label>
<select name="accessories" class="form-control select2">
<option value="">-- ไม่เลือก --</option>
<?php foreach($accessories as $a): ?>
<option value="<?= $a['asset_id'] ?>"><?= $a['no_pc'] ?></option>
<?php endforeach; ?>
</select>
</div>

<!-- Drone -->
<div>
<label>Drone</label>
<select name="drone" class="form-control select2">
<option value="">-- ไม่เลือก --</option>
<?php foreach($drone as $a): ?>
<option value="<?= $a['asset_id'] ?>"><?= $a['no_pc'] ?></option>
<?php endforeach; ?>
</select>
</div>

<!-- Optical Fiber -->
<div>
<label>Optical Fiber</label>
<select name="fiber" class="form-control select2">
<option value="">-- ไม่เลือก --</option>
<?php foreach($fiber as $a): ?>
<option value="<?= $a['asset_id'] ?>"><?= $a['no_pc'] ?></option>
<?php endforeach; ?>
</select>
</div>

<!-- Server -->
<div>
<label>Server</label>
<select name="server" class="form-control select2">
<option value="">-- ไม่เลือก --</option>
<?php foreach($server as $a): ?>
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
function addCCTV(){
let wrap=document.getElementById('cctvWrap');
let select=wrap.querySelector('select');
let newSelect=select.cloneNode(true);
newSelect.value='';
wrap.appendChild(newSelect);
$('.select2').select2();
}

function addNVR(){
let wrap=document.getElementById('nvrWrap');
let select=wrap.querySelector('select');
let newSelect=select.cloneNode(true);
newSelect.value='';
wrap.appendChild(newSelect);
$('.select2').select2();
}

function addPrinter(){
let wrap=document.querySelector('select[name="printer"]').parentElement;
let select=wrap.querySelector('select');
let newSelect=select.cloneNode(true);
newSelect.value='';
wrap.appendChild(newSelect);
$('.select2').select2();
}

</script>

<?php include 'partials/footer.php'; ?>