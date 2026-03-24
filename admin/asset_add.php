<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$admin = $_SESSION['fullname'] ?? 'admin';

/* =====================================================
   SUBMIT
===================================================== */
if(isset($_POST['save'])){

    $no_pc = trim($_POST['no_pc'] ?? '');
    $type  = $_POST['type_equipment'] ?? '';
    $project = 'สำนักงานใหญ่'; // default value

    $new_no = $_POST['new_no'] ?? null;
    $details = $_POST['Equipment_details'] ?? null;
    $spec = $_POST['spec'] ?? null;
    $ssd  = $_POST['ssd'] ?? null;
    $ram  = $_POST['ram'] ?? null;
    $gpu  = $_POST['gpu'] ?? null;

    /* ================= VALIDATE ================= */

    if(empty($no_pc)){
        echo "<script>alert('กรุณากรอกรหัสอุปกรณ์');</script>";
    }
    elseif(empty($type)){
        echo "<script>alert('กรุณาเลือกประเภท');</script>";
    }
    else{

        /* 🔥 กันรหัสซ้ำ */
        $check = $conn->prepare("SELECT COUNT(*) FROM IT_assets WHERE no_pc=?");
        $check->execute([$no_pc]);

        if($check->fetchColumn() > 0){
            echo "<script>alert('รหัสอุปกรณ์นี้มีในระบบแล้ว');</script>";
        }else{

            /* ================= INSERT ================= */
            $stmt = $conn->prepare("
            INSERT INTO IT_assets
            (new_no,no_pc,Equipment_details,type_equipment,project,spec,ssd,ram,gpu,[update])
            VALUES (?,?,?,?,?,?,?,?,?,GETDATE())
            ");

            $stmt->execute([
                $new_no,
                $no_pc,
                $details,
                $type,
                $project,
                $spec,
                $ssd,
                $ram,
                $gpu
            ]);

            echo "<script>
            alert('✅ บันทึกสำเร็จ');
            window.location='asset_add.php';
            </script>";
        }
    }
}
?>

<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>

<style>
.card-header{
background: linear-gradient(135deg,#0d6efd,#4dabf7);
color:white;
}
.btn-primary{
background: linear-gradient(135deg,#0d6efd,#4dabf7);
border:none;
}
</style>

<div class="container mt-4">

<div class="card shadow">

<div class="card-header">
<h5 class="mb-0">➕ เพิ่มอุปกรณ์ (Admin)</h5>
</div>

<div class="card-body">

<form method="post" onsubmit="return confirmSave()">

<div class="row">

<!-- 🔥 รหัส -->
<div class="col-md-4 mb-3">
<label>รหัสอุปกรณ์ *</label>
<input type="text" name="no_pc" class="form-control" required>
</div>

<!-- 🔥 ประเภท -->
<div class="col-md-4 mb-3">
<label>ประเภท *</label>
<select name="type_equipment" class="form-control" required>
<option value="">-- เลือกประเภท --</option>
<option>PC</option>
<option>Notebook</option>
<option>All_In_One</option>
<option>Monitor</option>
<option>UPS</option>
<option>CCTV</option>
<option>NVR</option>
<option>Printer</option>
<option>Projector</option>
<option>Audio Set</option>
<option>Plotter</option>
<option>Accessories IT</option>
<option>Drone</option>
<option>Optical Fiber</option>
<option>Server</option>
</select>
</div>

<!-- 🔥 โครงการ -->
<div class="col-md-4 mb-3">
<label>โครงการ *</label>
<input type="text" name="project" class="form-control" value="สำนักงานใหญ่" readonly>
</div>

<!-- optional -->
<div class="col-md-4 mb-3">
<label>รหัสใหม่</label>
<input type="text" name="new_no" class="form-control">
</div>

<div class="col-md-8 mb-3">
<label>รายละเอียด</label>
<input type="text" name="Equipment_details" class="form-control">
</div>

<div class="col-md-3 mb-3">
<label>Spec</label>
<input type="text" name="spec" class="form-control">
</div>

<div class="col-md-3 mb-3">
<label>SSD</label>
<input type="text" name="ssd" class="form-control">
</div>

<div class="col-md-3 mb-3">
<label>RAM</label>
<input type="text" name="ram" class="form-control">
</div>

<div class="col-md-3 mb-3">
<label>GPU</label>
<input type="text" name="gpu" class="form-control">
</div>

</div>

<div class="text-end">
<button class="btn btn-primary px-4" name="save">
💾 บันทึก
</button>
</div>

</form>

</div>
</div>
</div>

<script>
function confirmSave(){
    return confirm("ยืนยันการเพิ่มอุปกรณ์นี้ ?");
}
</script>

<?php include '../user/partials/footer.php'; ?>