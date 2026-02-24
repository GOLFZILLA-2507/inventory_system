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
        SELECT asset_id,no_pc,spec,ram,ssd,gpu
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

    $emp = $_POST['employee'];
    $pos = $_POST['position'];

    $asset_id = $_POST['asset_id'];
    $pc       = $_POST['no_pc'];
    $spec     = $_POST['spec'];
    $ram      = $_POST['ram'];
    $ssd      = $_POST['ssd'];
    $gpu      = $_POST['gpu'];

    $m1 = $_POST['monitor1']; // required
    $m2 = $_POST['monitor2'] ?? null;
    $ups= $_POST['ups'] ?? null;

    // ================= USER INFO TABLE =================
    $check = $conn->prepare("SELECT COUNT(*) FROM IT_user_information WHERE asset_id=?");
    $check->execute([$asset_id]);

    if($check->fetchColumn()>0){
        $stmt = $conn->prepare("
        UPDATE IT_user_information SET
            user_employee=?,
            user_position=?,
            user_project=?,
            user_no_pc=?,
            user_spec=?,
            user_ram=?,
            user_ssd=?,
            user_gpu=?,
            user_monitor1=?,
            user_monitor2=?,
            user_ups=?,
            user_update=GETDATE()
        WHERE asset_id=?
        ");
        $stmt->execute([$emp,$pos,$site,$pc,$spec,$ram,$ssd,$gpu,$m1,$m2,$ups,$asset_id]);
    }else{
        $stmt = $conn->prepare("
        INSERT INTO IT_user_information
        (asset_id,user_employee,user_position,user_project,
         user_no_pc,user_spec,user_ram,user_ssd,user_gpu,
         user_monitor1,user_monitor2,user_ups,user_update)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,GETDATE())
        ");
        $stmt->execute([$asset_id,$emp,$pos,$site,$pc,$spec,$ram,$ssd,$gpu,$m1,$m2,$ups]);
    }

    // ================= UPDATE กลับ IT_assets =================
    $updateAsset = $conn->prepare("
    UPDATE IT_assets SET
        project = ?,
        [update] = GETDATE()
    WHERE asset_id = ?
");
    $updateAsset->execute([$site,$asset_id]);

    header("Location: asset_assign.php?success=1");
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

<!-- EMPLOYEE -->
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

<input type="text" id="position" name="position" class="form-control mb-2" placeholder="ตำแหน่ง" readonly>
<input type="text" id="department" class="form-control mb-3" placeholder="แผนก" readonly>
</div>

<!-- COMPUTER -->
<div class="col-md-6">
<label>เลือกเครื่องคอมพิวเตอร์</label>
<select id="pcSelect" name="asset_id" class="form-control mb-2" required>
<option value="">-- เลือกเครื่อง --</option>
<?php foreach($computers as $c): ?>
<option value="<?= $c['asset_id'] ?>"
data-pc="<?= $c['no_pc'] ?>"
data-spec="<?= $c['spec'] ?>"
data-ram="<?= $c['ram'] ?>"
data-ssd="<?= $c['ssd'] ?>"
data-gpu="<?= $c['gpu'] ?>">
<?= $c['no_pc'] ?>
</option>
<?php endforeach; ?>
</select>

<input type="text" id="no_pc" name="no_pc" class="form-control mb-2" placeholder="รหัสเครื่อง..." readonly>

<!-- 🔥 รวม spec -->
<input type="text" id="spec_full" class="form-control mb-2" placeholder="Spec เครื่อง" readonly>

<input type="hidden" name="spec" id="spec">
<input type="hidden" name="ram" id="ram">
<input type="hidden" name="ssd" id="ssd">
<input type="hidden" name="gpu" id="gpu">
</div>

</div>

<hr>

<div class="row">

<!-- MONITOR 1 REQUIRED -->
<div class="col-md-4">
<label>จอที่ 1 *</label>
<select name="monitor1" class="form-control" required>
<option value="">-- เลือกจอ --</option>
<?php foreach($monitors as $m): ?>
<option value="<?= $m['no_pc'] ?>"><?= $m['no_pc'] ?></option>
<?php endforeach; ?>
</select>
</div>

<!-- MONITOR 2 OPTIONAL -->
<div class="col-md-4">
<label>จอที่ 2</label>
<select name="monitor2" class="form-control">
<option value="">-- ไม่มี --</option>
<?php foreach($monitors as $m): ?>
<option value="<?= $m['no_pc'] ?>"><?= $m['no_pc'] ?></option>
<?php endforeach; ?>
</select>
</div>

<!-- UPS -->
<div class="col-md-4">
<label>UPS *</label>
<select name="ups" class="form-control"  required>
<option value="">-- ไม่มี --</option>
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
// fill employee
document.getElementById('empSelect').addEventListener('change',function(){
let opt=this.options[this.selectedIndex];
document.getElementById('position').value=opt.getAttribute('data-pos')||'';
document.getElementById('department').value=opt.getAttribute('data-dep')||'';
});

// fill spec รวมช่องเดียว
document.getElementById('pcSelect').addEventListener('change',function(){
let opt=this.options[this.selectedIndex];

let pc = opt.getAttribute('data-pc')||'';
let spec = opt.getAttribute('data-spec')||'';
let ram = opt.getAttribute('data-ram')||'';
let ssd = opt.getAttribute('data-ssd')||'';
let gpu = opt.getAttribute('data-gpu')||'';

document.getElementById('no_pc').value = pc;
document.getElementById('spec_full').value = spec+" | RAM "+ram+" | SSD "+ssd+" | GPU "+gpu;

document.getElementById('spec').value = spec;
document.getElementById('ram').value = ram;
document.getElementById('ssd').value = ssd;
document.getElementById('gpu').value = gpu;
});
</script>

<?php include 'partials/footer.php'; ?>