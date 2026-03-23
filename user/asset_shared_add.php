<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];
$user = $_SESSION['fullname'];

/* =====================================================
   FUNCTION โหลด asset ตาม type
===================================================== */
function getByType($conn,$type){
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

/* =====================================================
   FUNCTION รวมค่า (หัวใจของระบบนี้)
   👉 เอาค่าเก่า + ค่าใหม่ → ต่อท้าย + กันซ้ำ
===================================================== */
function mergeValue($old, $newArr){

    // แปลงค่าเก่าเป็น array
    $oldArr = !empty($old) ? explode(',', $old) : [];

    // trim กันช่องว่าง
    $oldArr = array_map('trim', $oldArr);
    $newArr = array_map('trim', $newArr);

    // รวม + กันซ้ำ
    $final = array_unique(array_merge($oldArr, $newArr));

    // ลบค่าว่าง
    $final = array_filter($final);

    return !empty($final) ? implode(',', $final) : null;
}

/* =====================================================
   FUNCTION สำหรับ multi select
===================================================== */
function setMulti($conn, $ids, $site, $old){

    $new = [];

    foreach($ids as $id){

        if(!$id) continue;

        // หา no_pc
        $q=$conn->prepare("SELECT no_pc FROM IT_assets WHERE asset_id=?");
        $q->execute([$id]);
        $row=$q->fetch(PDO::FETCH_ASSOC);

        if($row){

            $new[] = $row['no_pc'];

            // update asset → mark ว่าใช้งานแล้ว
            $conn->prepare("
            UPDATE IT_assets
            SET project=?, use_it=?, [update]=GETDATE()
            WHERE asset_id=?
            ")->execute([$site,$site,$id]);
        }
    }

    // 🔥 รวมค่า
    return mergeValue($old, $new);
}

/* =====================================================
   LOAD ASSETS
===================================================== */
$audio      = getByType($conn,'audio_set');
$cctv       = getByType($conn,'CCTV');
$nvr        = getByType($conn,'NVR');
$printer    = getByType($conn,'Printer');
$plotter    = getByType($conn,'Plotter');
$projector  = getByType($conn,'Projector');
$accessories= getByType($conn,'Accessories_IT');
$drone      = getByType($conn,'Drone');
$fiber      = getByType($conn,'Optical_Fiber');
$server     = getByType($conn,'Server');

/* =====================================================
   SUBMIT
===================================================== */
if(isset($_POST['submit'])){

    // รับค่าเป็น array
    $cctvArr        = (array)($_POST['cctv'] ?? []);
    $nvrArr         = (array)($_POST['nvr'] ?? []);
    $projectorArr   = (array)($_POST['projector'] ?? []);
    $printerArr     = (array)($_POST['printer'] ?? []);
    $audioArr       = (array)($_POST['audio_set'] ?? []);
    $plotterArr     = (array)($_POST['plotter'] ?? []);
    $accessoriesArr = (array)($_POST['accessories'] ?? []);
    $droneArr       = (array)($_POST['drone'] ?? []);
    $fiberArr       = (array)($_POST['fiber'] ?? []);
    $serverArr      = (array)($_POST['server'] ?? []);

    /* ================= กันเลือกซ้ำ ================= */
    $allIds = array_filter(array_merge(
        $cctvArr,$nvrArr,$projectorArr,$printerArr,
        $audioArr,$plotterArr,$accessoriesArr,
        $droneArr,$fiberArr,$serverArr
    ));

    if(count($allIds) !== count(array_unique($allIds))){
        header("Location: asset_shared_add.php?error=เลือกอุปกรณ์ซ้ำ");
        exit;
    }

    /* ================= โหลดข้อมูลเดิม ================= */
  $stmt=$conn->prepare("
    SELECT *
    FROM IT_user_information
    WHERE user_project=? 
    AND user_type_equipment='SHARED'
    ");
    $stmt->execute([$site]);
    $current=$stmt->fetch(PDO::FETCH_ASSOC);

    /* ================= ถ้ายังไม่มี → สร้างแถวเดียว ================= */
    if(!$current){

        $stmtMax = $conn->prepare("SELECT MAX(asset_id) as max_id FROM IT_user_information");
        $stmtMax->execute();
        $max = $stmtMax->fetch(PDO::FETCH_ASSOC);

        $new_id = ($max['max_id'] ?? 0) + 1;

        $conn->prepare("
        INSERT INTO IT_user_information 
        (asset_id, user_project, user_type_equipment, user_record, user_update)
        VALUES (?, ?, 'SHARED', ?, GETDATE())
        ")->execute([$new_id, $site, $user]);
        // reload
        $stmt->execute([$site]);
        $current=$stmt->fetch(PDO::FETCH_ASSOC);
    }

    /* =====================================================
       CCTV
    ===================================================== */
    $new_cctv = [];

    foreach($cctvArr as $id){

        if(!$id) continue;

        $q=$conn->prepare("SELECT no_pc FROM IT_assets WHERE asset_id=?");
        $q->execute([$id]);
        $row=$q->fetch(PDO::FETCH_ASSOC);

        if($row){
            $new_cctv[] = $row['no_pc'];

            $conn->prepare("
            UPDATE IT_assets
            SET project=?, use_it=?, [update]=GETDATE()
            WHERE asset_id=?
            ")->execute([$site,$site,$id]);
        }
    }

    $cctv_str = mergeValue($current['user_cctv'], $new_cctv);

    /* =====================================================
       NVR
    ===================================================== */
    $new_nvr = [];

    foreach($nvrArr as $id){

        if(!$id) continue;

        $q=$conn->prepare("SELECT no_pc FROM IT_assets WHERE asset_id=?");
        $q->execute([$id]);
        $row=$q->fetch(PDO::FETCH_ASSOC);

        if($row){
            $new_nvr[] = $row['no_pc'];

            $conn->prepare("
            UPDATE IT_assets
            SET project=?, use_it=?, [update]=GETDATE()
            WHERE asset_id=?
            ")->execute([$site,$site,$id]);
        }
    }

    $nvr_str = mergeValue($current['user_nvr'], $new_nvr);

    /* =====================================================
       MULTI TYPE
    ===================================================== */
    $projector_pc   = setMulti($conn,$projectorArr,$site,$current['user_projector']);
    $printer_pc     = setMulti($conn,$printerArr,$site,$current['user_printer']);
    $audio_pc       = setMulti($conn,$audioArr,$site,$current['user_audio_set']);
    $plotter_pc     = setMulti($conn,$plotterArr,$site,$current['user_plotter']);
    $accessories_pc = setMulti($conn,$accessoriesArr,$site,$current['user_Accessories_IT']);
    $drone_pc       = setMulti($conn,$droneArr,$site,$current['user_Drone']);
    $fiber_pc       = setMulti($conn,$fiberArr,$site,$current['user_Optical_Fiber']);
    $server_pc      = setMulti($conn,$serverArr,$site,$current['user_Server']);

    /* =====================================================
       UPDATE (อัปเดตแถวเดียวเท่านั้น)
    ===================================================== */
    $stmt=$conn->prepare("
    UPDATE IT_user_information SET
    user_employee=?,
    user_cctv=?,
    user_nvr=?,
    user_projector=?,
    user_printer=?,
    user_audio_set=?,
    user_plotter=?,
    user_Accessories_IT=?,
    user_Drone=?,
    user_Optical_Fiber=?,
    user_Server=?,
    user_record=?,
    user_update=GETDATE()
    WHERE user_project=? AND user_type_equipment='SHARED'
    ");

    $stmt->execute([
        $site, // user_employee ใช้ชื่อโครงการแทน
        $cctv_str,
        $nvr_str,
        $projector_pc,
        $printer_pc,
        $audio_pc,
        $plotter_pc,
        $accessories_pc,
        $drone_pc,
        $fiber_pc,
        $server_pc,
        $user,
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

<!-- เราท์เตอร์อินเตอร์เน็ต IT -->
<div>
<label>เราท์เตอร์อินเตอร์เน็ต</label>
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