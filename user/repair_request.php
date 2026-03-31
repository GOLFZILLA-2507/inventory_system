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

/* ===== Upload รูป ===== */

if(!empty($_FILES['images']['name'][0])){

for($i=0;$i<3;$i++){

if(isset($_FILES['images']['name'][$i]) && $_FILES['images']['name'][$i]!=""){

$filename = time()."_".$i."_".basename($_FILES['images']['name'][$i]);

move_uploaded_file($_FILES['images']['tmp_name'][$i],$uploadDir.$filename);

if($i==0) $img1=$filename;
}

}

}

/* ===== บันทึก Ticket ===== */

$stmt = $conn->prepare("
INSERT INTO IT_RepairTickets
(asset_id,user_id,user_name,problem,priority,img1,user_no_pc,user_type_equipment,project)
VALUES (?,?,?,?,?,?,?,?,?)
");

$stmt->execute([
NULL, // asset_id - เก็บ null เพราะ user equipment ไม่ใช่ inventory asset
$_SESSION['EmployeeID'],
$_SESSION['fullname'],
$_POST['problem'],
$_POST['priority'] ?? 'Normal',
$img1,
$_POST['asset_id'], // ส่งค่า equipment code ไปที่ user_no_pc
$_POST['user_type_equipment'] ?? '',
$userProject
]);

header("Location: repair_status.php?success=1");
exit;

}

/* ===== โหลดข้อมูลทั้งโครงการ (ทั้งหมด ไม่ซ้ำ) ===== */

$stmt = $conn->prepare("
SELECT *
FROM IT_user_information
WHERE user_project = ?
");

$stmt->execute([$userProject]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ======================================================
   โหลดรายการอุปกรณ์ที่ถูกโอนออกไปแล้ว (สำคัญมาก)
====================================================== */

$transferStmt = $conn->prepare("
SELECT no_pc
FROM IT_AssetTransfer_Headers
WHERE from_site = ?
AND receive_status = 'รับแล้ว'
");

$transferStmt->execute([$userProject]);

$transferList = $transferStmt->fetchAll(PDO::FETCH_COLUMN);

// แปลงเป็น array
$transfered = array_map('trim', $transferList);

$assets = [];

// ฟังก์ชันแยก comma-separated values
function parseCommaSeparatedAssets($ids, $equipmentType) {
    if(empty($ids)) return [];
    
    $result = [];
    $idArray = array_filter(array_map('trim', explode(',', $ids)));
    
    foreach($idArray as $id) {
        $result[] = [
            'asset_id' => $id,
            'user_no_pc' => $id,
            'user_equipment_details' => $equipmentType
        ];
    }
    
    return $result;
}

if($rows) {
    // ฟิลด์ที่ต้องแสดง
    $fieldsToCheck = [
        ['col' => 'user_no_pc', 'type' => 'PC'],
        ['col' => 'user_monitor1', 'type' => 'Monitor'],
        ['col' => 'user_brand_1', 'type' => 'Monitor Brand'],
        ['col' => 'user_monitor2', 'type' => 'Monitor'],
        ['col' => 'user_brand_2', 'type' => 'Monitor Brand'],
        ['col' => 'user_ups', 'type' => 'UPS'],
        ['col' => 'user_cctv', 'type' => 'CCTV'],
        ['col' => 'user_nvr', 'type' => 'NVR'],
        ['col' => 'user_projector', 'type' => 'Projector'],
        ['col' => 'user_printer', 'type' => 'Printer'],
        ['col' => 'user_audio_set', 'type' => 'Audio'],
        ['col' => 'user_plotter', 'type' => 'Plotter'],
        ['col' => 'user_Accessories_IT', 'type' => 'Accessories'],
        ['col' => 'user_Drone', 'type' => 'Drone'],
        ['col' => 'user_Optical_Fiber', 'type' => 'Fiber'],
        ['col' => 'user_Server', 'type' => 'Server'],
    ];
    
    $addedIds = []; // เก็บ ID ที่เพิ่มไปแล้ว เพื่อไม่ให้ซ้ำ
    
foreach($rows as $row) {

    foreach($fieldsToCheck as $field) {

        $colName = $field['col'];
        $type = $field['type'];

        // 🔥 ถ้า field ว่าง → ข้าม
        if(empty($row[$colName])) continue;

        // 🔥 แยก comma เช่น CCTV01,CCTV02
        $ids = array_filter(array_map('trim', explode(',', $row[$colName])));

        foreach($ids as $id){

            $id = trim($id);

            // 🔥 ถ้าอุปกรณ์ถูกโอนออกแล้ว → ไม่ต้องแสดง
            if(in_array($id, $transfered)){
                continue;
            }

            // 🔥 กันซ้ำ
            if(!in_array($id, $addedIds)){

                $assets[] = [
                    'asset_id' => $id,
                    'user_no_pc' => $id,
                    'user_equipment_details' => $type,
                    'user_spec' => $row['user_spec'] ?? '',
                    'user_ram' => $row['user_ram'] ?? '',
                    'user_gpu' => $row['user_gpu'] ?? '',
                    'user_ssd' => $row['user_ssd'] ?? ''
                ];

                $addedIds[] = $id;
            }

        }

    }

}
}


include 'partials/header.php';
include 'partials/sidebar.php';
?>


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


<div class="col-md-6">

<label class="label">เลือกเครื่องที่ต้องการแจ้งซ่อม</label>

<select name="asset_id" id="assetSelect" class="form-control mb-3" required>

<option value="">-- เลือกอุปกรณ์ --</option>

<?php foreach($assets as $a): ?>

<option value="<?= $a['asset_id'] ?>"
data-new="<?= $a['user_new_no'] ?? '' ?>"
data-spec="<?= ($a['user_spec'] ?? '').' | '.($a['user_ram'] ?? '').' | '.($a['user_gpu'] ?? '').' | '.($a['user_ssd'] ?? '') ?>"
data-equipment="<?= $a['user_equipment_details'] ?? '' ?>"
>
<?= $a['user_no_pc'] ?> | <?= $a['user_equipment_details'] ?>
</option>

<?php endforeach; ?>

</select>

<input type="hidden" name="user_new_no" id="user_new_no" />
<input type="hidden" name="user_no_pc" id="user_no_pc" />
<input type="hidden" name="user_equipment_details" id="user_equipment_details" />
<input type="hidden" name="user_type_equipment" id="user_type_equipment" />


<label class="label">รหัสยาว (new_no)</label>

<input type="text" id="new_no" class="form-control readonly mb-3" readonly>


<label class="label">Spec เครื่อง</label>

<textarea id="spec" class="form-control readonly mb-3" rows="3" readonly></textarea>


</div>


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
document.getElementById('user_new_no').value=opt.getAttribute('data-new')||'';

document.getElementById('spec').value=opt.getAttribute('data-spec')||'';

document.getElementById('user_no_pc').value=this.value||'';
document.getElementById('user_equipment_details').value=opt.getAttribute('data-equipment')||'';
document.getElementById('user_type_equipment').value=opt.getAttribute('data-equipment')||'';

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