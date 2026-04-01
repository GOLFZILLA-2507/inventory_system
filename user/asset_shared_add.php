<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];
$user = $_SESSION['fullname'];

/* =====================================================
🔥 FUNCTION: เช็คซ้ำ
===================================================== */
function checkDuplicate($conn,$code){
    $stmt = $conn->prepare("
        SELECT TOP 1 user_employee,user_project
        FROM IT_user_devices
        WHERE device_code=?
    ");
    $stmt->execute([$code]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/* =====================================================
🔥 FUNCTION: insert device (shared)
===================================================== */
function insertDevice($conn,$site,$type,$code,$user){
    $conn->prepare("
        INSERT INTO IT_user_devices
        (user_employee,user_project,device_type,device_role,device_code,created_by,user_record)
        VALUES (?,?,?,?,?,?,?)
    ")->execute([
        $site,
        $site,
        $type,
        'shared',
        $code,
        $user,
        $user
    ]);
}

/* =====================================================
🔥 FUNCTION: save history
===================================================== */
function saveHistory($conn,$site,$code,$type,$user){
    $conn->prepare("
        INSERT INTO IT_user_history
        (user_employee,user_project,user_no_pc,action_type,created_at,created_by,history_type,start_date)
        VALUES (?,?,?,'shared_assign',GETDATE(),?,?,GETDATE())
    ")->execute([
        $site,
        $site,
        $code,
        $user,
        $type
    ]);
}

/* =====================================================
🔥 LOAD ASSET BY TYPE (เหมือนเดิม)
===================================================== */
function getByType($conn,$type){
    $stmt=$conn->prepare("
    SELECT asset_id,no_pc
    FROM IT_assets
    WHERE type_equipment=?
    AND (use_it IS NULL OR use_it='')
    ORDER BY no_pc
    ");
    $stmt->execute([$type]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* =====================================================
🔥 LOAD DATA
===================================================== */
$cctv       = getByType($conn,'CCTV');
$nvr        = getByType($conn,'NVR');
$printer    = getByType($conn,'Printer');
$audio      = getByType($conn,'audio_set');
$plotter    = getByType($conn,'Plotter');
$projector  = getByType($conn,'Projector');
$accessories= getByType($conn,'Accessories_IT');
$drone      = getByType($conn,'Drone');
$fiber      = getByType($conn,'Optical_Fiber');
$server     = getByType($conn,'Server');

/* =====================================================
🔥 SUBMIT
===================================================== */
$msg=""; $status="";

if($_SERVER['REQUEST_METHOD']=='POST'){

    $all = array_merge(
        $_POST['cctv'] ?? [],
        $_POST['nvr'] ?? [],
        $_POST['printer'] ?? [],
        $_POST['audio_set'] ?? [],
        $_POST['plotter'] ?? [],
        $_POST['projector'] ?? [],
        $_POST['accessories'] ?? [],
        $_POST['drone'] ?? [],
        $_POST['fiber'] ?? [],
        $_POST['server'] ?? []
    );

    $all = array_filter($all);

    if(empty($all)){
        $msg="❌ กรุณาเลือกอุปกรณ์";
        $status="error";
    }

    foreach($all as $id){

        $q = $conn->prepare("
        SELECT no_pc,type_equipment 
        FROM IT_assets WHERE asset_id=?
        ");
        $q->execute([$id]);
        $a = $q->fetch(PDO::FETCH_ASSOC);

        if(!$a) continue;

        $code = $a['no_pc'];
        $type = $a['type_equipment'];

        /* 🔥 DUP */
        $dup = checkDuplicate($conn,$code);
        if($dup){
            $msg="❌ $code ซ้ำกับ {$dup['user_employee']} ({$dup['user_project']})";
            $status="error";
            break;
        }

        /* 🔥 INSERT */
        insertDevice($conn,$site,$type,$code,$user);
        saveHistory($conn,$site,$code,$type,$user);

        /* 🔥 UPDATE ASSET */
        $conn->prepare("
        UPDATE IT_assets
        SET use_it=?, project=?, [update]=GETDATE()
        WHERE asset_id=?
        ")->execute([$site,$site,$id]);
    }

    if(!$msg){
        $msg="✅ บันทึกสำเร็จ";
        $status="success";
    }
}
?>

<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container mt-4">
<div class="card shadow">

<div class="card-header bg-success text-white">
➕ เพิ่มอุปกรณ์ใช้ร่วม (<?= $site ?>)
</div>

<div class="card-body">

<form method="post" id="mainForm">

<div class="row">

<!-- LEFT -->
<div class="col-md-6">

<!-- CCTV -->
<div class="mb-4">
<label class="fw-bold">CCTV</label>

<div id="cctvBox">
<select name="cctv[]" class="form-control mb-2">
<option value="">-- เลือก CCTV --</option>
<?php foreach($cctv as $a): ?>
<option value="<?= $a['asset_id'] ?>"><?= $a['no_pc'] ?></option>
<?php endforeach; ?>
</select>
</div>

<button type="button" class="btn btn-success btn-sm"
onclick="addField('cctvBox','cctv')">
+ เพิ่ม CCTV
</button>
</div>

<!-- Audio -->
<div class="mb-4">
<label class="fw-bold">Audio Set</label>
<select name="audio_set[]" class="form-control">
<option value="">-- ไม่เลือก --</option>
<?php foreach($audio as $a): ?>
<option value="<?= $a['asset_id'] ?>"><?= $a['no_pc'] ?></option>
<?php endforeach; ?>
</select>
</div>

<!-- Plotter -->
<div class="mb-4">
<label class="fw-bold">Plotter</label>
<select name="plotter[]" class="form-control">
<option value="">-- ไม่เลือก --</option>
<?php foreach($plotter as $a): ?>
<option value="<?= $a['asset_id'] ?>"><?= $a['no_pc'] ?></option>
<?php endforeach; ?>
</select>
</div>

<!-- Optical Fiber -->
<div class="mb-4">
<label class="fw-bold">Optical Fiber</label>
<select name="fiber[]" class="form-control">
<option value="">-- ไม่เลือก --</option>
<?php foreach($fiber as $a): ?>
<option value="<?= $a['asset_id'] ?>"><?= $a['no_pc'] ?></option>
<?php endforeach; ?>
</select>
</div>

</div>

<!-- RIGHT -->
<div class="col-md-6">

<!-- NVR -->
<div class="mb-4">
<label class="fw-bold">NVR</label>

<div id="nvrBox">
<select name="nvr[]" class="form-control mb-2">
<option value="">-- เลือก NVR --</option>
<?php foreach($nvr as $a): ?>
<option value="<?= $a['asset_id'] ?>"><?= $a['no_pc'] ?></option>
<?php endforeach; ?>
</select>
</div>

<button type="button" class="btn btn-success btn-sm"
onclick="addField('nvrBox','nvr')">
+ เพิ่ม NVR
</button>
</div>

<!-- Printer -->
<div class="mb-4">
<label class="fw-bold">Printer</label>

<div id="printerBox">
<select name="printer[]" class="form-control mb-2">
<option value="">-- เลือก Printer --</option>
<?php foreach($printer as $a): ?>
<option value="<?= $a['asset_id'] ?>"><?= $a['no_pc'] ?></option>
<?php endforeach; ?>
</select>
</div>

<button type="button" class="btn btn-success btn-sm"
onclick="addField('printerBox','printer')">
+ เพิ่ม Printer
</button>
</div>

<!-- Projector -->
<div class="mb-4">
<label class="fw-bold">Projector</label>
<select name="projector[]" class="form-control">
<option value="">-- ไม่เลือก --</option>
<?php foreach($projector as $a): ?>
<option value="<?= $a['asset_id'] ?>"><?= $a['no_pc'] ?></option>
<?php endforeach; ?>
</select>
</div>

<!-- Server -->
<div class="mb-4">
<label class="fw-bold">Server</label>
<select name="server[]" class="form-control">
<option value="">-- ไม่เลือก --</option>
<?php foreach($server as $a): ?>
<option value="<?= $a['asset_id'] ?>"><?= $a['no_pc'] ?></option>
<?php endforeach; ?>
</select>
</div>

</div>

</div>

<div class="text-end mt-4">
<button type="button" id="btnConfirm" class="btn btn-success">
💾 บันทึก
</button>
</div>

</form>

</div>
</div>
</div>

<script>
document.getElementById('btnConfirm').onclick = function(){

    Swal.fire({
        title:'ยืนยัน?',
        text:'ต้องการเพิ่มอุปกรณ์ใช่หรือไม่',
        icon:'question',
        showCancelButton:true,
        confirmButtonText:'บันทึก',
        cancelButtonText:'ยกเลิก'
    }).then((res)=>{
        if(res.isConfirmed){
            document.getElementById('mainForm').submit();
        }
    });

};
</script>

<?php if($msg): ?>
<script>
Swal.fire({
    icon:'<?= $status ?>',
    title:'<?= $msg ?>'
});
</script>
<?php endif; ?>


<script>
function addField(containerId,type){

    let html = '';

    if(type === 'cctv'){
        html = `<?= str_replace("\n","", addslashes('
        <select name="cctv[]" class="form-control mb-2">
        <option value="">-- เลือก CCTV --</option>
        '.implode('', array_map(fn($a)=>"<option value=\"{$a['asset_id']}\">{$a['no_pc']}</option>", $cctv)).'
        </select>
        ')); ?>`;
    }

    if(type === 'nvr'){
        html = `<?= str_replace("\n","", addslashes('
        <select name="nvr[]" class="form-control mb-2">
        <option value="">-- เลือก NVR --</option>
        '.implode('', array_map(fn($a)=>"<option value=\"{$a['asset_id']}\">{$a['no_pc']}</option>", $nvr)).'
        </select>
        ')); ?>`;
    }

    if(type === 'printer'){
        html = `<?= str_replace("\n","", addslashes('
        <select name="printer[]" class="form-control mb-2">
        <option value="">-- เลือก Printer --</option>
        '.implode('', array_map(fn($a)=>"<option value=\"{$a['asset_id']}\">{$a['no_pc']}</option>", $printer)).'
        </select>
        ')); ?>`;
    }

    document.getElementById(containerId).insertAdjacentHTML('beforeend', html);
}
function addField(containerId,type){

    let selectHTML = document.querySelector(`#${containerId} select`).outerHTML;

    let newField = `
    <div class="d-flex mb-2 gap-2">
        ${selectHTML}
        <button type="button" class="btn btn-danger btn-sm"
        onclick="this.parentElement.remove()">✖</button>
    </div>
    `;

    document.getElementById(containerId).insertAdjacentHTML('beforeend', newField);
}
</script>

<?php include 'partials/footer.php'; ?>