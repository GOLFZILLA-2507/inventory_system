<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];       // โครงการ
$user = $_SESSION['fullname'];   // ผู้บันทึก

/* =====================================================
🔥 ประเภทที่ใช้ร่วม
===================================================== */
$types = [
    'CCTV','NVR','Printer','Projector','Server',
    'Audio','Plotter','Accessories_IT','Drone','Optical_Fiber'
];

/* =====================================================
🔥 FUNCTION: insert device (shared)
===================================================== */
function insertDevice($conn,$site,$type,$code,$user){
    $conn->prepare("
        INSERT INTO IT_user_devices
        (user_employee,user_project,device_type,device_role,device_code,created_by,user_record)
        VALUES (?,?,?,?,?,?,?)
    ")->execute([
        $site,      // shared = project
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
🔥 SUBMIT
===================================================== */
$msg = "";
$status = "";

if($_SERVER['REQUEST_METHOD']=='POST'){

    $type = $_POST['type'] ?? '';
    $code = "อุปกรณ์ไม่มีรหัส";

    if(!$type || !in_array($type,$types)){
        $msg = "ประเภทไม่ถูกต้อง";
        $status = "error";
    }

    try{

        if(!$msg){

            $conn->beginTransaction();

            // 🔥 insert (ไม่มีเช็คซ้ำ)
            insertDevice($conn,$site,$type,$code,$user);

            // 🔥 history
            saveHistory($conn,$site,$code,$type,$user);

            $conn->commit();

            $msg = "เพิ่มอุปกรณ์ใช้ร่วมสำเร็จ";
            $status = "success";
        }

    }catch(Exception $e){

        if($conn->inTransaction()){
            $conn->rollBack();
        }

        $msg = $e->getMessage();
        $status = "error";
    }
}
?>

<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container mt-4">
<div class="card shadow">

<div class="card-header bg-success text-white">
📡 เพิ่มอุปกรณ์ใช้ร่วม (ไม่มีรหัส)
</div>

<div class="card-body">

<form method="post" id="mainForm">

<!-- 🔥 โครงการ -->
<div class="mb-3">
<label>โครงการ</label>
<input class="form-control" value="<?= $site ?>" readonly>
</div>

<!-- 🔥 ประเภท -->
<div class="mb-3">
<label>ประเภทอุปกรณ์</label>
<select name="type" class="form-control" required>
<option value="">-- เลือกประเภท --</option>
<?php foreach($types as $t): ?>
<option><?= $t ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="alert alert-info">
ระบบจะบันทึกเป็น: <b>อุปกรณ์ไม่มีรหัส</b>
</div>

<div class="text-end">
<button type="button" id="btnConfirm" class="btn btn-success">
💾 บันทึก
</button>
</div>

</form>

</div>
</div>
</div>

<!-- =====================================================
🔥 MODAL CONFIRM
===================================================== -->
<script>
document.getElementById('btnConfirm').addEventListener('click', function(){

    Swal.fire({
        title: 'ยืนยัน?',
        text: 'ต้องการเพิ่มอุปกรณ์ใช้ร่วมใช่หรือไม่',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '💾 บันทึก',
        cancelButtonText: '❌ ยกเลิก',
        confirmButtonColor: '#198754',
        cancelButtonColor: '#dc3545'
    }).then((result)=>{
        if(result.isConfirmed){
            document.getElementById('mainForm').submit();
        }
    });

});
</script>

<!-- =====================================================
🔥 RESULT MODAL
===================================================== -->
<?php if($msg): ?>
<script>
window.onload = function(){
Swal.fire({
    icon:'<?= $status ?>',
    title:'<?= $status == "success" ? "สำเร็จ" : "ผิดพลาด" ?>',
    text:'<?= $msg ?>'
}).then(()=>{
    <?php if($status == "success"): ?>
    window.location='asset_shared_view.php';
    <?php endif; ?>
});
}
</script>
<?php endif; ?>

<?php include 'partials/footer.php'; ?>