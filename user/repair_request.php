<?php
session_start();

require_once '../config/connect.php';

$userProject = $_SESSION['site'];


/* ======================================================
   SUBMIT แจ้งซ่อม (ต้องอยู่ก่อน include header)
====================================================== */

if(isset($_POST['submit'])){

$uploadDir = "../uploads/repair/";

$img1="";
$img2="";
$img3="";

/* ===== Upload รูป ===== */

if(!empty($_FILES['images']['name'][0])){

for($i=0;$i<3;$i++){

if(isset($_FILES['images']['name'][$i]) && $_FILES['images']['name'][$i]!=""){

$filename = time()."_".$i."_".basename($_FILES['images']['name'][$i]);

move_uploaded_file($_FILES['images']['tmp_name'][$i],$uploadDir.$filename);

if($i==0) $img1=$filename;
if($i==1) $img2=$filename;
if($i==2) $img3=$filename;

}

}

}

/* ===== บันทึก Ticket ===== */

$stmt = $conn->prepare("
INSERT INTO IT_RepairTickets
(asset_id,user_id,user_name,problem,priority,img1,img2,img3,user_new_no,user_no_pc,user_equipment_details,user_type_equipment,project)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
");

$stmt->execute([

$_POST['asset_id'],
$_SESSION['EmployeeID'],
$_SESSION['fullname'],
$_POST['problem'],
$_POST['priority'] ?? 'Normal',
$img1,
$img2,
$img3,
$_POST['user_new_no'] ?? '',
$_POST['user_no_pc'] ?? '',
$_POST['user_equipment_details'] ?? '',
$_POST['user_type_equipment'] ?? '',
$userProject
]);

header("Location: repair_request.php?success=1");
exit;

}

/* ===== โหลดข้อมูล project ===== */

$stmt = $conn->prepare("
SELECT *
FROM IT_user_information
WHERE user_project = ?
");

$stmt->execute([$userProject]);

$row = $stmt->fetch(PDO::FETCH_ASSOC);

$assets = [];

if($row){

/* เครื่องหลัก */

if(!empty($row['user_no_pc'])){
$assets[]=[
'asset_id'=>$row['asset_id'],
'user_no_pc'=>$row['user_no_pc'],
'user_new_no'=>$row['user_new_no'],
'user_equipment_details'=>$row['user_equipment_details'],
'user_spec'=>$row['user_spec'],
'user_ram'=>$row['user_ram'],
'user_gpu'=>$row['user_gpu'],
'user_ssd'=>$row['user_ssd']
];
}

/* monitor */

if(!empty($row['user_monitor1'])){
$assets[]=['asset_id'=>$row['user_monitor1'],'user_no_pc'=>$row['user_monitor1'],'user_equipment_details'=>'Monitor'];
}

if(!empty($row['user_monitor2'])){
$assets[]=['asset_id'=>$row['user_monitor2'],'user_no_pc'=>$row['user_monitor2'],'user_equipment_details'=>'Monitor'];
}

/* CCTV */

if(!empty($row['user_cctv'])){
$assets[]=['asset_id'=>$row['user_cctv'],'user_no_pc'=>$row['user_cctv'],'user_equipment_details'=>'CCTV'];
}

/* NVR */

if(!empty($row['user_nvr'])){
$assets[]=['asset_id'=>$row['user_nvr'],'user_no_pc'=>$row['user_nvr'],'user_equipment_details'=>'NVR'];
}

/* Projector */

if(!empty($row['user_projector'])){
$assets[]=['asset_id'=>$row['user_projector'],'user_no_pc'=>$row['user_projector'],'user_equipment_details'=>'Projector'];
}

/* Printer */

if(!empty($row['user_printer'])){
$assets[]=['asset_id'=>$row['user_printer'],'user_no_pc'=>$row['user_printer'],'user_equipment_details'=>'Printer'];
}

/* Audio */

if(!empty($row['user_audio_set'])){
$assets[]=['asset_id'=>$row['user_audio_set'],'user_no_pc'=>$row['user_audio_set'],'user_equipment_details'=>'Audio'];
}

/* Plotter */

if(!empty($row['user_plotter'])){
$assets[]=['asset_id'=>$row['user_plotter'],'user_no_pc'=>$row['user_plotter'],'user_equipment_details'=>'Plotter'];
}

/* Accessories */

if(!empty($row['user_Accessories_IT'])){
$assets[]=['asset_id'=>$row['user_Accessories_IT'],'user_no_pc'=>$row['user_Accessories_IT'],'user_equipment_details'=>'Accessories'];
}

/* Drone */

if(!empty($row['user_Drone'])){
$assets[]=['asset_id'=>$row['user_Drone'],'user_no_pc'=>$row['user_Drone'],'user_equipment_details'=>'Drone'];
}

/* Fiber */

if(!empty($row['user_Optical_Fiber'])){
$assets[]=['asset_id'=>$row['user_Optical_Fiber'],'user_no_pc'=>$row['user_Optical_Fiber'],'user_equipment_details'=>'Fiber'];
}

/* Server */

if(!empty($row['user_Server'])){
$assets[]=['asset_id'=>$row['user_Server'],'user_no_pc'=>$row['user_Server'],'user_equipment_details'=>'Server'];
}

}

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<!-- ======================================================
     STYLE
====================================================== -->

<style>

.card-box{
max-width:1000px;
margin:auto;
border-radius:14px;
}

.card-header{
background:linear-gradient(135deg,#198754,#20c997);
color:white;
border-radius:14px 14px 0 0;
}

.label{
font-weight:600;
margin-bottom:4px;
}

.readonly{
background:#f1f5f4;
}

.preview img{
height:80px;
margin-right:5px;
border-radius:8px;
border:1px solid #ddd;
}

</style>

<div class="container mt-4">

<div class="card shadow card-box">

<div class="card-header">
<h5 class="mb-0">🛠 แจ้งซ่อมอุปกรณ์</h5>
</div>

<div class="card-body">

<?php if(isset($_GET['success'])): ?>
<div class="alert alert-success">✅ แจ้งซ่อมเรียบร้อยแล้ว</div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">

<div class="row">

<!-- ======================================
     LEFT
====================================== -->

<div class="col-md-6">

<label class="label">เลือกเครื่องที่ต้องการแจ้งซ่อม</label>

<select name="asset_id" id="assetSelect" class="form-control mb-3" required>

<option value="">-- เลือกอุปกรณ์ --</option>

<?php foreach($assets as $a): ?>

<option value="<?= $a['asset_id'] ?>"
data-new="<?= $a['user_new_no'] ?? '' ?>"
data-spec="<?= ($a['user_spec'] ?? '').' | '.($a['user_ram'] ?? '').' | '.($a['user_gpu'] ?? '').' | '.($a['user_ssd'] ?? '') ?>"
>
<?= $a['user_no_pc'] ?> | <?= $a['user_equipment_details'] ?>
</option>

<?php endforeach; ?>

</select>


<label class="label">รหัสยาว (new_no)</label>

<input type="text" id="new_no" class="form-control readonly mb-3" readonly>


<label class="label">Spec เครื่อง</label>

<textarea id="spec" class="form-control readonly mb-3" rows="3" readonly></textarea>


<label class="label">ระดับความเร่งด่วน</label>

<select name="priority" class="form-control mb-3">

<option value="Low">Low</option>
<option value="Normal">Normal</option>
<option value="High">High</option>
<option value="Urgent">Urgent</option>

</select>

</div>


<!-- ======================================
     RIGHT
====================================== -->

<div class="col-md-6">

<label class="label">อาการเสีย</label>

<textarea name="problem" class="form-control mb-3" rows="4" required></textarea>


<label class="label">แนบรูป (สูงสุด 3 รูป)</label>

<input type="file" name="images[]" id="imgInput" multiple class="form-control mb-2" accept="image/*">

<div class="preview" id="preview"></div>

</div>

</div>

<div class="text-end mt-3">

<button class="btn btn-success px-4" name="submit">
📨 ส่งคำขอแจ้งซ่อม
</button>

</div>

</form>

</div>
</div>
</div>

<script>

/* ======================================================
   auto fill spec
====================================================== */

document.getElementById('assetSelect').addEventListener('change',function(){

let opt=this.options[this.selectedIndex];

document.getElementById('new_no').value=opt.getAttribute('data-new')||'';

document.getElementById('spec').value=opt.getAttribute('data-spec')||'';

});


/* ======================================================
   preview รูป
====================================================== */

document.getElementById('imgInput').addEventListener('change',function(){

let preview=document.getElementById('preview');

preview.innerHTML="";

let files=this.files;

for(let i=0;i<files.length && i<3;i++){

let reader=new FileReader();

reader.onload=function(e){

let img=document.createElement("img");

img.src=e.target.result;

preview.appendChild(img);

}

reader.readAsDataURL(files[i]);

}

});

</script>

<?php include 'partials/footer.php'; ?>