<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$user = $_SESSION['fullname'];

/* =====================================================
🔥 AJAX: โหลดพนักงานตามโครงการ
===================================================== */
if(isset($_GET['action']) && $_GET['action']=='get_emp'){

    $site = $_GET['site'] ?? '';

    $stmt = $conn->prepare("
    SELECT fullname FROM Employee WHERE site=? ORDER BY fullname
    ");
    $stmt->execute([$site]);

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

/* =====================================================
🔥 LOAD PROJECT
===================================================== */
$projects = $conn->query("
SELECT DISTINCT site FROM Employee ORDER BY site
")->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
🔥 TYPE
===================================================== */
$types = ['PC','Notebook','All_In_One','Monitor','UPS'];

/* =====================================================
🔥 FUNCTION
===================================================== */
function countDevice($conn,$emp,$type){
    $stmt = $conn->prepare("
    SELECT COUNT(*) FROM IT_user_devices
    WHERE user_employee=? AND device_type=?
    ");
    $stmt->execute([$emp,$type]);
    return $stmt->fetchColumn();
}

function insertDevice($conn,$emp,$site,$type,$role,$code,$user){
    $conn->prepare("
    INSERT INTO IT_user_devices
    (user_employee,user_project,device_type,device_role,device_code,created_by,user_record)
    VALUES (?,?,?,?,?,?,?)
    ")->execute([$emp,$site,$type,$role,$code,$user,$user]);
}

function saveHistory($conn,$emp,$site,$code,$type,$user){
    $conn->prepare("
    INSERT INTO IT_user_history
    (user_employee,user_project,user_no_pc,action_type,created_at,created_by,history_type,start_date)
    VALUES (?,?,?,'assign_no_code',GETDATE(),?,?,GETDATE())
    ")->execute([$emp,$site,$code,$user,$type]);
}

/* =====================================================
🔥 SUBMIT
===================================================== */
$msg=""; $status="";

if($_SERVER['REQUEST_METHOD']=='POST'){

    $site = $_POST['project'] ?? '';
    $emp  = $_POST['employee'] ?? '';
    $type = $_POST['type'] ?? '';

    $code = "ไม่มีรหัสอุปกรณ์";

    try{

        if(!$site || !$emp || !$type){
            throw new Exception("กรุณาเลือกข้อมูลให้ครบ");
        }

        $conn->beginTransaction();

        if(in_array($type,['PC','Notebook','All_In_One'])){

            if(countDevice($conn,$emp,'PC') > 0){
                throw new Exception("มีเครื่องหลักอยู่แล้ว");
            }

            insertDevice($conn,$emp,$site,'PC','main',$code,$user);
            saveHistory($conn,$emp,$site,$code,'PC',$user);
        }

        elseif($type=='Monitor'){

            $count = countDevice($conn,$emp,'Monitor');

            if($count>=2){
                throw new Exception("จอครบแล้ว (2 จอ)");
            }

            $role = ($count==0)?'monitor1':'monitor2';

            insertDevice($conn,$emp,$site,'Monitor',$role,$code,$user);
            saveHistory($conn,$emp,$site,$code,'Monitor',$user);
        }

        elseif($type=='UPS'){

            if(countDevice($conn,$emp,'UPS')>0){
                throw new Exception("มี UPS แล้ว");
            }

            insertDevice($conn,$emp,$site,'UPS','ups',$code,$user);
            saveHistory($conn,$emp,$site,$code,'UPS',$user);
        }

        else{
            throw new Exception("ประเภทไม่รองรับ");
        }

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
body{background:#f4f9ff;}

.card{
border-radius:16px;
box-shadow:0 10px 30px rgba(13,110,253,0.1);
border:none;
}

.card-header{
background:linear-gradient(135deg,#0d6efd,#74c0fc);
color:#fff;
font-weight:600;
}

.form-control{
border-radius:10px;
}

</style>

<div class="container mt-4">
<div class="card">

<div class="card-header">
➕ เพิ่มอุปกรณ์ (ไม่มีรหัส)
</div>

<div class="card-body">

<form method="post" id="mainForm">

<!-- 🔥 PROJECT -->
<label>โครงการ</label>
<select name="project" id="project" class="form-control mb-3" required>
<option value="">-- เลือกโครงการ --</option>
<?php foreach($projects as $p): ?>
<option value="<?= $p['site'] ?>"><?= $p['site'] ?></option>
<?php endforeach; ?>
</select>

<!-- 🔥 EMP -->
<label>พนักงาน</label>
<select name="employee" id="employee" class="form-control mb-3" required>
<option value="">-- เลือกพนักงาน --</option>
</select>

<label>ประเภทอุปกรณ์</label>
<select name="type" class="form-control mb-3" required>
<option value="">-- เลือกประเภท --</option>
<?php foreach($types as $t): ?>
<option><?= $t ?></option>
<?php endforeach; ?>
</select>

<div class="alert alert-info">
ระบบจะบันทึกเป็น: <b>ไม่มีรหัสอุปกรณ์</b>
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

/* ================= 🔥 LOAD EMP ================= */
document.getElementById('project').addEventListener('change', function(){

    let site = this.value;

    fetch('?action=get_emp&site='+site)
    .then(res=>res.json())
    .then(data=>{
        let emp = document.getElementById('employee');
        emp.innerHTML = '<option value="">-- เลือกพนักงาน --</option>';

        data.forEach(e=>{
            emp.innerHTML += `<option value="${e.fullname}">${e.fullname}</option>`;
        });
    });
});

/* ================= 🔥 CONFIRM ================= */
document.getElementById('btnConfirm').onclick = function(){

    let emp = document.querySelector('[name=employee]').value;
    let project = document.querySelector('[name=project]').value;
    let type = document.querySelector('[name=type]').value;

    Swal.fire({
        title:'ยืนยันการบันทึก',
        html:`
        <b>พนักงาน:</b> ${emp}<br>
        <b>โครงการ:</b> ${project}<br>
        <b>ประเภท:</b> ${type}
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