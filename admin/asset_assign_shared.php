<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$user = $_SESSION['fullname'];

/* =====================================================
🔥 LOAD PROJECT
===================================================== */
$projects = $conn->query("
SELECT DISTINCT site FROM Employee ORDER BY site
")->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
🔥 TYPES
===================================================== */
$types = [
    'CCTV','NVR','Printer','Projector','Server',
    'Audio','Plotter','Accessories_IT','Drone','Optical_Fiber'
];

/* =====================================================
🔥 FUNCTION
===================================================== */
function insertDevice($conn,$site,$type,$code,$user){
    $conn->prepare("
    INSERT INTO IT_user_devices
    (user_employee,user_project,device_type,device_role,device_code,created_by,user_record)
    VALUES (?,?,?,?,?,?,?)
    ")->execute([$site,$site,$type,'shared',$code,$user,$user]);
}

function saveHistory($conn,$site,$code,$type,$user){
    $conn->prepare("
    INSERT INTO IT_user_history
    (user_employee,user_project,user_no_pc,action_type,created_at,created_by,history_type,start_date)
    VALUES (?,?,?,'shared_assign',GETDATE(),?,?,GETDATE())
    ")->execute([$site,$site,$code,$user,$type]);
}

/* =====================================================
🔥 SUBMIT
===================================================== */
$msg=""; $status="";

if($_SERVER['REQUEST_METHOD']=='POST'){

    $site = $_POST['project'] ?? '';
    $type = $_POST['type'] ?? '';
    $code = "อุปกรณ์ไม่มีรหัส";

    try{

        if(!$site || !$type){
            throw new Exception("กรุณาเลือกข้อมูลให้ครบ");
        }

        if(!in_array($type,$types)){
            throw new Exception("ประเภทไม่ถูกต้อง");
        }

        $conn->beginTransaction();

        insertDevice($conn,$site,$type,$code,$user);
        saveHistory($conn,$site,$code,$type,$user);

        $conn->commit();

        echo "<script>
        Swal.fire({
            icon:'success',
            title:'บันทึกสำเร็จ'
        }).then(()=>{window.location='index.php';});
        </script>";

    }catch(Exception $e){

        if($conn->inTransaction()){
            $conn->rollBack();
        }

        $msg=$e->getMessage();
        $status="error";
    }
}
?>

<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
body{
background:#f4f9ff;
}

.card{
border-radius:16px;
border:none;
box-shadow:0 10px 30px rgba(13,110,253,0.1);
}

.card-header{
background:linear-gradient(135deg,#0d6efd,#74c0fc);
color:#fff;
font-weight:600;
font-size:18px;
}

.form-control{
border-radius:10px;
}

</style>

<div class="container mt-4">
<div class="card">

<div class="card-header">
📡 เพิ่มอุปกรณ์ใช้ร่วม (ไม่มีรหัส)
</div>

<div class="card-body">

<form method="post" id="mainForm">

<!-- 🔥 PROJECT -->
<label>โครงการ</label>
<select name="project" class="form-control mb-3" required>
<option value="">-- เลือกโครงการ --</option>
<?php foreach($projects as $p): ?>
<option value="<?= $p['site'] ?>"><?= $p['site'] ?></option>
<?php endforeach; ?>
</select>

<!-- 🔥 TYPE -->
<label>ประเภทอุปกรณ์</label>
<select name="type" class="form-control mb-3" required>
<option value="">-- เลือกประเภท --</option>
<?php foreach($types as $t): ?>
<option><?= $t ?></option>
<?php endforeach; ?>
</select>

<div class="alert alert-info">
ระบบจะบันทึกเป็น: <b>อุปกรณ์ไม่มีรหัส</b>
</div>

<div class="text-end">
<button type="button" id="btnConfirm" class="btn btn-primary">
💾 บันทึก
</button>
</div>

</form>

</div>
</div>
</div>

<script>

/* ================= 🔥 CONFIRM ================= */
document.getElementById('btnConfirm').onclick = function(){

    let project = document.querySelector('[name=project]').value;
    let type = document.querySelector('[name=type]').value;

    if(!project || !type){
        Swal.fire('แจ้งเตือน','กรุณาเลือกข้อมูลให้ครบ','warning');
        return;
    }

    Swal.fire({
        title:'ยืนยันการบันทึก',
        html:`
        <b>โครงการ:</b> ${project}<br>
        <b>ประเภท:</b> ${type}<br>
        <b>รหัส:</b> อุปกรณ์ไม่มีรหัส
        `,
        icon:'question',
        showCancelButton:true,
        confirmButtonText:'ยืนยัน',
        cancelButtonText:'ยกเลิก'
    }).then(res=>{
        if(res.isConfirmed){
            document.getElementById('mainForm').submit();
        }
    });

};

</script>

<?php if($msg): ?>
<script>
Swal.fire({
icon:'error',
title:'ผิดพลาด',
text:'<?= $msg ?>'
});
</script>
<?php endif; ?>

<?php include 'partials/footer.php'; ?>