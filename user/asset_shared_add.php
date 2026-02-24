<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];

/* ================= LOAD ASSETS BY TYPE ================= */
function getByType($conn,$type){
    $stmt = $conn->prepare("
    SELECT asset_id,no_pc 
    FROM IT_assets
    WHERE type_equipment = ?
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

    // multi
    $cctvArr = $_POST['cctv'] ?? [];
    $nvrArr  = $_POST['nvr'] ?? [];

    // single
    $audio = $_POST['audio_set'] ?? null;
    $printer = $_POST['printer'] ?? null;
    $plotter = $_POST['plotter'] ?? null;
    $projector = $_POST['projector'] ?? null;

    // ====== map result ======
    $map = [
        'cctv'=>[],
        'nvr'=>[],
        'audio_set'=>null,
        'printer'=>null,
        'plotter'=>null,
        'projector'=>null
    ];

    // ===== loop CCTV =====
    foreach($cctvArr as $id){
        if(!$id) continue;

        $q = $conn->prepare("SELECT no_pc FROM IT_assets WHERE asset_id=?");
        $q->execute([$id]);
        $row = $q->fetch(PDO::FETCH_ASSOC);

        if($row){
            $map['cctv'][] = $row['no_pc'];

            // update project
            $upd = $conn->prepare("UPDATE IT_assets SET project=?, [update]=GETDATE() WHERE asset_id=?");
            $upd->execute([$site,$id]);
        }
    }

    // ===== loop NVR =====
    foreach($nvrArr as $id){
        if(!$id) continue;

        $q = $conn->prepare("SELECT no_pc FROM IT_assets WHERE asset_id=?");
        $q->execute([$id]);
        $row = $q->fetch(PDO::FETCH_ASSOC);

        if($row){
            $map['nvr'][] = $row['no_pc'];

            $upd = $conn->prepare("UPDATE IT_assets SET project=?, [update]=GETDATE() WHERE asset_id=?");
            $upd->execute([$site,$id]);
        }
    }

    // ===== function single =====
    function setSingle($conn,$id,$site){
        if(!$id) return null;
        $q = $conn->prepare("SELECT no_pc FROM IT_assets WHERE asset_id=?");
        $q->execute([$id]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if($row){
            $upd = $conn->prepare("UPDATE IT_assets SET project=?, [update]=GETDATE() WHERE asset_id=?");
            $upd->execute([$site,$id]);
            return $row['no_pc'];
        }
        return null;
    }

    $map['audio_set'] = setSingle($conn,$audio,$site);
    $map['printer']   = setSingle($conn,$printer,$site);
    $map['plotter']   = setSingle($conn,$plotter,$site);
    $map['projector'] = setSingle($conn,$projector,$site);

    // ===== convert array to string =====
    $cctv_str = implode(',', $map['cctv']);
    $nvr_str  = implode(',', $map['nvr']);

    // ===== check exist project =====
    $chk = $conn->prepare("SELECT COUNT(*) FROM IT_user_information WHERE user_project=?");
    $chk->execute([$site]);

    if($chk->fetchColumn()>0){
        $stmt = $conn->prepare("
        UPDATE IT_user_information SET
            user_cctv=?,
            user_nvr=?,
            user_audio_set=?,
            user_printer=?,
            user_plotter=?,
            user_projector=?,
            user_update=GETDATE()
        WHERE user_project=?
        ");
        $stmt->execute([
            $cctv_str,
            $nvr_str,
            $map['audio_set'],
            $map['printer'],
            $map['plotter'],
            $map['projector'],
            $site
        ]);
    }else{
        $stmt = $conn->prepare("
        INSERT INTO IT_user_information
        (user_project,user_cctv,user_nvr,user_audio_set,user_printer,user_plotter,user_projector,user_update)
        VALUES (?,?,?,?,?,?,?,GETDATE())
        ");
        $stmt->execute([
            $site,
            $cctv_str,
            $nvr_str,
            $map['audio_set'],
            $map['printer'],
            $map['plotter'],
            $map['projector']
        ]);
    }

    header("Location: asset_shared_view.php?success=1");
    exit;
}

include 'partials/header.php';
include 'partials/sidebar.php';
?>

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
<select name="cctv[]" class="form-control mb-2">
<option value="">-- เลือก CCTV --</option>
<?php foreach($cctv as $a): ?>
<option value="<?= $a['asset_id'] ?>"><?= $a['no_pc'] ?></option>
<?php endforeach; ?>
</select>
</div>
<button type="button" onclick="addCCTV()" class="btn btn-sm btn-success">+ เพิ่ม</button>
</div>

<!-- NVR -->
<div>
<label>NVR</label>
<div id="nvrWrap">
<select name="nvr[]" class="form-control mb-2">
<option value="">-- เลือก NVR --</option>
<?php foreach($nvr as $a): ?>
<option value="<?= $a['asset_id'] ?>"><?= $a['no_pc'] ?></option>
<?php endforeach; ?>
</select>
</div>
<button type="button" onclick="addNVR()" class="btn btn-sm btn-success">+ เพิ่ม</button>
</div>

<!-- AUDIO -->
<div>
<label>Audio Set</label>
<select name="audio_set" class="form-control">
<option value="">-- ไม่เลือก --</option>
<?php foreach($audio as $a): ?>
<option value="<?= $a['asset_id'] ?>"><?= $a['no_pc'] ?></option>
<?php endforeach; ?>
</select>
</div>

<!-- PRINTER -->
<div>
<label>Printer</label>
<select name="printer" class="form-control">
<option value="">-- ไม่เลือก --</option>
<?php foreach($printer as $a): ?>
<option value="<?= $a['asset_id'] ?>"><?= $a['no_pc'] ?></option>
<?php endforeach; ?>
</select>
</div>

<!-- PLOTTER -->
<div>
<label>Plotter</label>
<select name="plotter" class="form-control">
<option value="">-- ไม่เลือก --</option>
<?php foreach($plotter as $a): ?>
<option value="<?= $a['asset_id'] ?>"><?= $a['no_pc'] ?></option>
<?php endforeach; ?>
</select>
</div>

<!-- PROJECTOR -->
<div>
<label>Projector</label>
<select name="projector" class="form-control">
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
function addCCTV(){
    let el = document.querySelector('#cctvWrap select').cloneNode(true);
    document.getElementById('cctvWrap').appendChild(el);
}
function addNVR(){
    let el = document.querySelector('#nvrWrap select').cloneNode(true);
    document.getElementById('nvrWrap').appendChild(el);
}
</script>

<?php include 'partials/footer.php'; ?>