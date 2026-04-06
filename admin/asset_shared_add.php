<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$admin = $_SESSION['fullname'];

/* =====================================================
🔥 LOAD PROJECT
===================================================== */
$projects = $conn->query("
SELECT DISTINCT site FROM Employee ORDER BY site
")->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
🔥 FUNCTION
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

function insertDevice($conn,$site,$type,$code,$admin){
    $conn->prepare("
    INSERT INTO IT_user_devices
    (user_employee,user_project,device_type,device_role,device_code,created_by,user_record)
    VALUES (?,?,?,?,?,?,?)
    ")->execute([$site,$site,$type,'shared',$code,$admin,$admin]);
}

function saveHistory($conn,$site,$code,$type,$admin){
    $conn->prepare("
    INSERT INTO IT_user_history
    (user_employee,user_project,user_no_pc,action_type,created_at,created_by,history_type,start_date)
    VALUES (?,?,?,'shared_assign',GETDATE(),?,?,GETDATE())
    ")->execute([$site,$site,$code,$admin,$type]);
}

/* =====================================================
🔥 LOAD DATA
===================================================== */
$types = [
'CCTV','NVR','Printer','audio_set','Plotter',
'Projector','Accessories_IT','Drone','Optical_Fiber','Server'
];

$data = [];
foreach($types as $t){
    $data[$t] = getByType($conn,$t);
}

/* =====================================================
🔥 SUBMIT
===================================================== */
$msg=""; $status="";

if($_SERVER['REQUEST_METHOD']=='POST'){

    $site = $_POST['project'];

    $all = [];
    foreach($types as $t){
        $all = array_merge($all, $_POST[$t] ?? []);
    }

    $all = array_filter($all);

    if(empty($all)){
        $msg="กรุณาเลือกอุปกรณ์";
        $status="error";
    }

    foreach($all as $id){

        $q=$conn->prepare("SELECT no_pc,type_equipment FROM IT_assets WHERE asset_id=?");
        $q->execute([$id]);
        $a=$q->fetch(PDO::FETCH_ASSOC);

        if(!$a) continue;

        insertDevice($conn,$site,$a['type_equipment'],$a['no_pc'],$admin);
        saveHistory($conn,$site,$a['no_pc'],$a['type_equipment'],$admin);

        $conn->prepare("
        UPDATE IT_assets SET use_it=?,project=? WHERE asset_id=?
        ")->execute([$site,$site,$id]);
    }

    if(!$msg){
        echo "<script>
        Swal.fire({
            icon:'success',
            title:'บันทึกสำเร็จ'
        }).then(()=>{window.location='index.php';});
        </script>";
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
font-size:18px;
font-weight:600;
}

.section{
background:#fff;
border-radius:12px;
padding:15px;
margin-bottom:15px;
box-shadow:0 5px 15px rgba(0,0,0,0.05);
}

label{
font-weight:600;
color:#0d6efd;
}

</style>

<div class="container mt-4">
<div class="card">

<div class="card-header">
📡 เพิ่มอุปกรณ์ใช้ร่วม (Shared)
</div>

<div class="card-body">

<form method="post" id="form">

<!-- 🔥 PROJECT -->
<div class="mb-4">
<label>เลือกโครงการ</label>
<select name="project" class="form-control" required>
<option value="">-- เลือกโครงการ --</option>
<?php foreach($projects as $p): ?>
<option value="<?= $p['site'] ?>"><?= $p['site'] ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="row">

<?php foreach($data as $type => $list): ?>
<div class="col-md-6">

<div class="section">
<label><?= $type ?></label>

<div id="<?= $type ?>">
<select name="<?= $type ?>[]" class="form-control mb-2">
<option value="">-- เลือก <?= $type ?> --</option>
<?php foreach($list as $a): ?>
<option value="<?= $a['asset_id'] ?>"><?= $a['no_pc'] ?></option>
<?php endforeach; ?>
</select>
</div>

<button type="button" class="btn btn-primary btn-sm"
onclick="addField('<?= $type ?>')">
+ เพิ่ม
</button>

</div>

</div>
<?php endforeach; ?>

</div>

<div class="text-end mt-3">
<button type="button" class="btn btn-success" id="btnSave">
💾 บันทึก
</button>
</div>

</form>

</div>
</div>
</div>

<script>

/* ================= 🔥 ADD FIELD ================= */
function addField(type){
    let box = document.getElementById(type);
    let select = box.querySelector('select').outerHTML;

    box.insertAdjacentHTML('beforeend', `
    <div class="d-flex gap-2 mb-2">
        ${select}
        <button type="button" class="btn btn-danger btn-sm"
        onclick="this.parentElement.remove()">✖</button>
    </div>
    `);
}

/* ================= 🔥 CONFIRM ================= */
document.getElementById('btnSave').onclick = function(){

    let data = new FormData(document.getElementById('form'));

    let txt = '';
    data.forEach((v,k)=>{
        if(v) txt += `${k}: ${v}\n`;
    });

    Swal.fire({
        title:'ยืนยันการบันทึก',
        text:txt,
        icon:'question',
        showCancelButton:true,
        confirmButtonText:'ยืนยัน',
        cancelButtonText:'ยกเลิก'
    }).then(res=>{
        if(res.isConfirmed){
            document.getElementById('form').submit();
        }
    });

};
</script>

<?php include 'partials/footer.php'; ?>