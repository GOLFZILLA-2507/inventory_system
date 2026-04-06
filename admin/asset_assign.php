<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$admin = $_SESSION['fullname'];

/* =====================================================
🔥 AJAX: โหลดพนักงาน (เหมือนเดิม)
===================================================== */
if(isset($_GET['action']) && $_GET['action']=='get_employee'){

    $site = $_GET['site'] ?? '';

    $stmt = $conn->prepare("
        SELECT fullname FROM Employee 
        WHERE site=? ORDER BY fullname
    ");
    $stmt->execute([$site]);

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

/* =====================================================
🔥 โหลดข้อมูล (เหมือนเดิม)
===================================================== */
$projects = $conn->query("
SELECT DISTINCT site FROM Employee ORDER BY site
")->fetchAll(PDO::FETCH_ASSOC);

$assets = $conn->query("
SELECT no_pc,type_equipment,spec,ram,ssd,gpu
FROM IT_assets
WHERE (use_it IS NULL OR use_it='')
")->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
🔥 แยกประเภท (เพิ่ม UI เท่านั้น ไม่กระทบ logic)
===================================================== */
$pcList = [];
$monitorList = [];
$upsList = [];

foreach($assets as $a){
    $type = strtoupper(trim($a['type_equipment']));

    // ✅ แก้ตรงนี้ (เพิ่ม 3 ประเภท)
    if(in_array($type, ['PC','NOTEBOOK','ALL_IN_ONE'])){
        $pcList[] = $a['no_pc'];
    }

    if($type == 'MONITOR') $monitorList[] = $a['no_pc'];
    if($type == 'UPS') $upsList[] = $a['no_pc'];
}

/* =====================================================
🔥 SAVE (เหมือนเดิม 100%)
===================================================== */
if($_SERVER['REQUEST_METHOD']=='POST'){

    $project = $_POST['project'];
    $emp     = $_POST['employee'];

    $pc  = $_POST['pc'];
    $m1  = $_POST['monitor1'];
    $m2  = $_POST['monitor2'];
    $ups = $_POST['ups'];

    function insert($conn,$emp,$project,$type,$role,$code,$admin){

        $conn->prepare("
        INSERT INTO IT_user_devices
        (user_employee,user_project,device_type,device_role,device_code,created_by,user_record)
        VALUES (?,?,?,?,?,?,?)
        ")->execute([$emp,$project,$type,$role,$code,$admin,$admin]);

        $conn->prepare("
        UPDATE IT_assets SET use_it=? WHERE no_pc=?
        ")->execute([$project,$code]);

        $conn->prepare("
        INSERT INTO IT_user_history
        (user_employee,user_project,user_no_pc,action_type,created_at,created_by,history_type,start_date)
        VALUES (?,?,?,'assign',GETDATE(),?,?,GETDATE())
        ")->execute([$emp,$project,$code,$admin,$type]);
    }

    if($pc)  insert($conn,$emp,$project,'PC','main',$pc,$admin);
    if($m1)  insert($conn,$emp,$project,'Monitor','m1',$m1,$admin);
    if($m2)  insert($conn,$emp,$project,'Monitor','m2',$m2,$admin);
    if($ups) insert($conn,$emp,$project,'UPS','ups',$ups,$admin);

    echo "<script>
    Swal.fire({
        icon:'success',
        title:'บันทึกสำเร็จ'
    }).then(()=>{window.location='index.php';});
    </script>";
}
?>

<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
.card-header{
background:linear-gradient(135deg,#0d6efd,#74c0fc);
color:#fff;
}
.form-control{
border-radius:10px;
}
</style>

<div class="container mt-4">
<div class="card shadow">

<div class="card-header">
🖥️ เพิ่มอุปกรณ์ให้พนักงาน (Admin)
</div>

<div class="card-body">

<form method="post" id="formAssign">

<!-- 🔥 PROJECT -->
<div class="row mb-3">
<div class="col-md-6">
<label>โครงการ</label>
<select name="project" id="project" class="form-control" required>
<option value="">-- เลือกโครงการ --</option>
<?php foreach($projects as $p): ?>
<option value="<?= $p['site'] ?>"><?= $p['site'] ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-6">
<label>พนักงาน</label>
<select name="employee" id="employee" class="form-control" required>
<option value="">-- เลือกพนักงาน --</option>
</select>
</div>
</div>

<hr>

<!-- 🔥 PC -->
<div class="row mb-3">
<div class="col-md-6">
<label>PC</label>

<input list="pcList"
name="pc"
class="form-control"
placeholder="พิมพ์ค้นหา PC..."
onchange="validateInput(this,'pcList')">

</div>
</div>

<!-- 🔥 MONITOR -->
<div class="row mb-3">
<div class="col-md-4">
<label>Monitor 1</label>

<input list="monitorList"
name="monitor1"
class="form-control"
placeholder="พิมพ์ค้นหา..."
onchange="validateInput(this,'monitorList')">

</div>

<div class="col-md-4">
<label>Monitor 2</label>

<input list="monitorList"
name="monitor2"
class="form-control"
placeholder="พิมพ์ค้นหา..."
onchange="validateInput(this,'monitorList')">

</div>

<div class="col-md-4">
<label>UPS</label>

<input list="upsList"
name="ups"
class="form-control"
placeholder="พิมพ์ค้นหา..."
onchange="validateInput(this,'upsList')">

</div>
</div>

<div class="text-end">
<button type="submit" class="btn btn-primary">
💾 บันทึก
</button>
</div>

</form>

</div>
</div>
</div>

<!-- 🔥 DATALIST -->
<datalist id="pcList">
<?php foreach($pcList as $p): ?>
<option value="<?= $p ?>">
<?php endforeach; ?>
</datalist>

<datalist id="monitorList">
<?php foreach($monitorList as $m): ?>
<option value="<?= $m ?>">
<?php endforeach; ?>
</datalist>

<datalist id="upsList">
<?php foreach($upsList as $u): ?>
<option value="<?= $u ?>">
<?php endforeach; ?>
</datalist>

<script>

/* ================= 🔥 โหลดพนักงาน ================= */
document.getElementById('project').addEventListener('change', function(){

    let project = this.value;

    fetch('?action=get_employee&site='+project)
    .then(res=>res.json())
    .then(data=>{
        let emp = document.getElementById('employee');
        emp.innerHTML = '<option value="">-- เลือกพนักงาน --</option>';

        data.forEach(e=>{
            emp.innerHTML += `<option value="${e.fullname}">${e.fullname}</option>`;
        });
    });
});

/* ================= 🔥 VALIDATE ================= */
function validateInput(input, listId){
    let options = document.querySelectorAll(`#${listId} option`);
    let valid = false;

    options.forEach(o=>{
        if(o.value === input.value) valid = true;
    });

    if(!valid && input.value !== ''){
        input.value='';
        Swal.fire('❌ กรุณาเลือกจากรายการ');
    }
}

/* ================= 🔥 CONFIRM ================= */
let confirmed = false;

document.getElementById('formAssign').addEventListener('submit', function(e){

    if(confirmed) return;

    e.preventDefault();

    let emp = document.querySelector('[name=employee]').value;
    let project = document.querySelector('[name=project]').value;

    let pc = document.querySelector('[name=pc]').value;
    let m1 = document.querySelector('[name=monitor1]').value;
    let m2 = document.querySelector('[name=monitor2]').value;
    let ups = document.querySelector('[name=ups]').value;

    let list = `
พนักงาน: ${emp}
โครงการ: ${project}

PC: ${pc||'-'}
Monitor1: ${m1||'-'}
Monitor2: ${m2||'-'}
UPS: ${ups||'-'}
`;

    Swal.fire({
        title:'ยืนยันการบันทึก',
        text:list,
        icon:'question',
        showCancelButton:true,
        confirmButtonText:'ยืนยัน',
        cancelButtonText:'ยกเลิก'
    }).then(res=>{
        if(res.isConfirmed){
            confirmed = true;
            e.target.submit();
        }
    });

});
</script>

<?php include 'partials/footer.php'; ?>