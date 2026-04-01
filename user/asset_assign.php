<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$site = $_SESSION['site'];
$user = $_SESSION['fullname'];

/* =====================================================
🔥 ตรวจซ้ำ
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
🔥 นับจำนวน
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
🔥 insert
===================================================== */
function insertDevice($conn,$emp,$site,$type,$role,$code,$user){
    $conn->prepare("
        INSERT INTO IT_user_devices
        (user_employee,user_project,device_type,device_role,device_code,created_by,user_record)
        VALUES (?,?,?,?,?,?,?)
    ")->execute([$emp,$site,$type,$role,$code,$user,$user]);
}

/* =====================================================
🔥 history
===================================================== */
function saveHistory($conn,$emp,$site,$code,$type,$user){
    $conn->prepare("
        INSERT INTO IT_user_history
        (user_employee,user_project,user_no_pc,action_type,created_at,created_by,history_type,start_date)
        VALUES (?,?,?,'assign',GETDATE(),?,?,GETDATE())
    ")->execute([$emp,$site,$code,$user,$type]);
}

/* =====================================================
🔥 โหลดข้อมูล
===================================================== */
$employees = $conn->prepare("SELECT fullname FROM Employee WHERE site=? ORDER BY fullname");
$employees->execute([$site]);
$employees = $employees->fetchAll(PDO::FETCH_ASSOC);

$assets = $conn->query("
    SELECT no_pc,type_equipment,spec,ram,ssd,gpu
    FROM IT_assets
    WHERE (use_it IS NULL OR use_it='')
")->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
🔥 SUBMIT (สำคัญ: ใช้ REQUEST_METHOD แทน)
===================================================== */
$msg=""; $typeAlert="";

if($_SERVER['REQUEST_METHOD']=='POST'){

    $emp = $_POST['employee'] ?? '';
    $pc  = $_POST['pc'] ?? '';
    $m1  = $_POST['monitor1'] ?? '';
    $m2  = $_POST['monitor2'] ?? '';
    $ups = $_POST['ups'] ?? '';

    if(!$emp){
        $msg="กรุณาเลือกพนักงาน";
        $typeAlert="error";
    }

    /* ================= PC ================= */
    if($pc && !$msg){

        $dup = checkDuplicate($conn,$pc);
        if($dup){
            $msg="❌ PC ซ้ำกับ {$dup['user_employee']} ({$dup['user_project']})";
            $typeAlert="error";
        }elseif(countDevice($conn,$emp,'PC')>0){
            $msg="❌ ผู้ใช้นี้มี PC แล้ว";
            $typeAlert="error";
        }else{
            insertDevice($conn,$emp,$site,'PC','main',$pc,$user);
            saveHistory($conn,$emp,$site,$pc,'PC',$user);
        }
    }

    /* ================= MONITOR ================= */
    $count = countDevice($conn,$emp,'Monitor');

    if($m1 && !$msg){
        if($count>=2){
            $msg="❌ จอครบแล้ว (2 จอ)";
            $typeAlert="error";
        }elseif($dup=checkDuplicate($conn,$m1)){
            $msg="❌ Monitor ซ้ำกับ {$dup['user_employee']} ({$dup['user_project']})";
            $typeAlert="error";
        }else{
            insertDevice($conn,$emp,$site,'Monitor','monitor1',$m1,$user);
            saveHistory($conn,$emp,$site,$m1,'Monitor',$user);
            $count++;
        }
    }

    if($m2 && !$msg){
        if($count>=2){
            $msg="❌ จอครบแล้ว (2 จอ)";
            $typeAlert="error";
        }elseif($dup=checkDuplicate($conn,$m2)){
            $msg="❌ Monitor ซ้ำกับ {$dup['user_employee']} ({$dup['user_project']})";
            $typeAlert="error";
        }else{
            insertDevice($conn,$emp,$site,'Monitor','monitor2',$m2,$user);
            saveHistory($conn,$emp,$site,$m2,'Monitor',$user);
        }
    }

    /* ================= UPS ================= */
    if($ups && !$msg){
        if(countDevice($conn,$emp,'UPS')>0){
            $msg="❌ มี UPS แล้ว";
            $typeAlert="error";
        }elseif($dup=checkDuplicate($conn,$ups)){
            $msg="❌ UPS ซ้ำกับ {$dup['user_employee']} ({$dup['user_project']})";
            $typeAlert="error";
        }else{
            insertDevice($conn,$emp,$site,'UPS','ups',$ups,$user);
            saveHistory($conn,$emp,$site,$ups,'UPS',$user);
        }
    }

    if(!$msg){
        $msg="✅ บันทึกสำเร็จ";
        $typeAlert="success";
    }
}
?>

<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
.card-header{
background:linear-gradient(135deg,#198754,#20c997);
color:white;
}
</style>

<div class="container mt-4">
<div class="card shadow">

<div class="card-header">
🖥️ จัดการอุปกรณ์ให้พนักงาน
</div>

<div class="card-body">

<form method="post" id="formAssign">

<div class="row">
<div class="col-md-6">
<label>พนักงาน</label>
<select name="employee" class="form-control" required>
<option value="">-- เลือกพนักงาน --</option>
<?php foreach($employees as $e): ?>
<option value="<?= $e['fullname'] ?>"><?= $e['fullname'] ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-6">
<label>PC</label>
<select name="pc" class="form-control" onchange="showSpec(this.value)">
<option value="">-- เลือกเครื่อง --</option>
<?php foreach($assets as $a): if($a['type_equipment']=='PC'): ?>
<option value="<?= $a['no_pc'] ?>"><?= $a['no_pc'] ?></option>
<?php endif; endforeach; ?>
</select>
</div>
</div>

<div id="specBox" class="alert alert-info mt-3" style="display:none;"></div>

<hr>

<div class="row">
<div class="col-md-4">
<label>Monitor 1</label>
<select name="monitor1" class="form-control">
<option value="">-- เลือกจอ --</option>
<?php foreach($assets as $a): if($a['type_equipment']=='Monitor'): ?>
<option value="<?= $a['no_pc'] ?>"><?= $a['no_pc'] ?></option>
<?php endif; endforeach; ?>
</select>
</div>

<div class="col-md-4">
<label>Monitor 2</label>
<select name="monitor2" class="form-control">
<option value="">-- เลือกจอ --</option>
<?php foreach($assets as $a): if($a['type_equipment']=='Monitor'): ?>
<option value="<?= $a['no_pc'] ?>"><?= $a['no_pc'] ?></option>
<?php endif; endforeach; ?>
</select>
</div>

<div class="col-md-4">
<label>UPS</label>
<select name="ups" class="form-control">
<option value="">-- เลือก UPS --</option>
<?php foreach($assets as $a): if($a['type_equipment']=='UPS'): ?>
<option value="<?= $a['no_pc'] ?>"><?= $a['no_pc'] ?></option>
<?php endif; endforeach; ?>
</select>
</div>
</div>

<div class="text-end mt-4">
<button type="submit" id="btnSubmit" class="btn btn-success">
💾 บันทึก
</button>
</div>

</form>

</div>
</div>
</div>

<script>
const assets = <?= json_encode($assets) ?>;

function showSpec(code){
    let a = assets.find(x => x.no_pc === code);
    if(!a){
        document.getElementById('specBox').style.display='none';
        return;
    }

    document.getElementById('specBox').innerHTML =
        `<b>รายละเอียดเครื่อง</b><br>
        ${a.spec || '-'} | RAM: ${a.ram || '-'} | SSD: ${a.ssd || '-'} | GPU: ${a.gpu || '-'}`;

    document.getElementById('specBox').style.display='block';
}
</script>

<?php if($msg): ?>
<script>
Swal.fire({
    icon:'<?= $typeAlert ?>',
    title:'<?= $msg ?>'
});


</script>
<?php endif; ?>
<script>
let isConfirmed = false;

document.getElementById('formAssign').addEventListener('submit', function(e){

    if(isConfirmed) return;

    e.preventDefault();

    Swal.fire({
        title: 'ยืนยันการบันทึก?',
        text: 'คุณต้องการมอบอุปกรณ์ให้พนักงานใช่หรือไม่',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '💾 บันทึก',
        cancelButtonText: '❌ ยกเลิก',
        confirmButtonColor: '#198754',
        cancelButtonColor: '#dc3545'
    }).then((result)=>{
        if(result.isConfirmed){
            isConfirmed = true;
            e.target.submit();
        }
    });

});
</script>

<?php include 'partials/footer.php'; ?>