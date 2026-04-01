<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];
$user = $_SESSION['fullname'];

/* =====================================================
🔥 โหลดพนักงาน
===================================================== */
$employees = $conn->prepare("
SELECT fullname FROM Employee
WHERE site = ?
ORDER BY fullname
");
$employees->execute([$site]);
$employees = $employees->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
🔥 ประเภทที่อนุญาต
===================================================== */
$types = ['PC','Notebook','All_In_One','Monitor','UPS'];

/* =====================================================
🔥 FUNCTION: นับจำนวนอุปกรณ์ของ user
===================================================== */
function countDevice($conn,$emp,$type){
    $stmt = $conn->prepare("
    SELECT COUNT(*) FROM IT_user_devices
    WHERE user_employee=? AND device_type=?
    ");
    $stmt->execute([$emp,$type]);
    return $stmt->fetchColumn();
}

/* =====================================================
🔥 FUNCTION: insert device (1 row)
===================================================== */
function insertDevice($conn,$emp,$site,$type,$role,$code,$user){
    $conn->prepare("
    INSERT INTO IT_user_devices
    (user_employee,user_project,device_type,device_role,device_code,created_by,user_record)
    VALUES (?,?,?,?,?,?,?)
    ")->execute([
        $emp,
        $site,
        $type,
        $role,
        $code,
        $user,
        $user
    ]);
}

/* =====================================================
🔥 FUNCTION: save history
===================================================== */
function saveHistory($conn,$emp,$site,$code,$type,$user){
    $conn->prepare("
    INSERT INTO IT_user_history
    (user_employee,user_project,user_no_pc,action_type,created_at,created_by,history_type,start_date)
    VALUES (?,?,?,'assign_no_code',GETDATE(),?,?,GETDATE())
    ")->execute([
        $emp,
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

    $emp  = $_POST['employee'] ?? '';
    $type = $_POST['type'] ?? '';

    // 🔥 ใช้ชื่อคงที่
    $code = "ไม่มีรหัสอุปกรณ์";

    if(!$emp || !$type){
        $msg = "กรุณาเลือกข้อมูลให้ครบ";
        $status = "error";
    }

    try{

        if(!$msg){

            $conn->beginTransaction();

            /* ===============================
            🔵 เครื่องหลัก
            =============================== */
            if(in_array($type,['PC','Notebook','All_In_One'])){

                if(countDevice($conn,$emp,'PC') > 0){
                    throw new Exception("มีเครื่องหลักอยู่แล้ว");
                }

                insertDevice($conn,$emp,$site,'PC','main',$code,$user);
                saveHistory($conn,$emp,$site,$code,'PC',$user);
            }

            /* ===============================
            🟡 Monitor
            =============================== */
            elseif($type == 'Monitor'){

                $count = countDevice($conn,$emp,'Monitor');

                if($count >= 2){
                    throw new Exception("จอครบแล้ว (2 จอ)");
                }

                $role = ($count == 0) ? 'monitor1' : 'monitor2';

                insertDevice($conn,$emp,$site,'Monitor',$role,$code,$user);
                saveHistory($conn,$emp,$site,$code,'Monitor',$user);
            }

            /* ===============================
            🟣 UPS
            =============================== */
            elseif($type == 'UPS'){

                if(countDevice($conn,$emp,'UPS') > 0){
                    throw new Exception("มี UPS แล้ว");
                }

                insertDevice($conn,$emp,$site,'UPS','ups',$code,$user);
                saveHistory($conn,$emp,$site,$code,'UPS',$user);
            }

            else{
                throw new Exception("ประเภทนี้ไม่รองรับ");
            }

            $conn->commit();

            $msg = "บันทึกสำเร็จ";
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
เพิ่มอุปกรณ์ (ไม่มีรหัส)
</div>

<div class="card-body">

<form method="post" id="mainForm">

<label>พนักงาน</label>
<select name="employee" class="form-control mb-3" required>
<option value="">-- เลือกพนักงาน --</option>
<?php foreach($employees as $e): ?>
<option value="<?= $e['fullname'] ?>"><?= $e['fullname'] ?></option>
<?php endforeach; ?>
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
<button type="button" id="btnConfirm" class="btn btn-success">
💾 บันทึก
</button>
</div>

</form>

</div>
</div>
</div>

<!-- =====================================================
🔥 CONFIRM MODAL
===================================================== -->
<script>
document.getElementById('btnConfirm').addEventListener('click', function(){

    Swal.fire({
        title: 'ยืนยัน?',
        text: 'ต้องการเพิ่มอุปกรณ์นี้ใช่หรือไม่',
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