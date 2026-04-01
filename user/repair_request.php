<?php
session_start();
require_once '../config/connect.php';

$userProject = $_SESSION['site'];

/* ======================================================
🔥 SUBMIT
====================================================== */
$msg = "";
$status = "";

if($_SERVER['REQUEST_METHOD']=='POST'){

    $uploadDir = "../uploads/repair/";
    $img1="";

    /* ===== Upload รูป ===== */
    if(!empty($_FILES['images']['name'][0])){

        for($i=0;$i<3;$i++){

            if(!empty($_FILES['images']['name'][$i])){

                $filename = time()."_".$i."_".basename($_FILES['images']['name'][$i]);

                move_uploaded_file($_FILES['images']['tmp_name'][$i],$uploadDir.$filename);

                if($i==0) $img1=$filename;
            }
        }
    }

    try{

        $stmt = $conn->prepare("
        INSERT INTO IT_RepairTickets
        (asset_id,user_id,user_name,problem,priority,img1,user_no_pc,user_type_equipment,project)
        VALUES (?,?,?,?,?,?,?,?,?)
        ");

        $stmt->execute([
            NULL,
            $_SESSION['EmployeeID'],
            $_SESSION['fullname'],
            $_POST['problem'],
            $_POST['priority'] ?? 'Normal',
            $img1,
            $_POST['asset_id'],
            $_POST['user_type_equipment'],
            $userProject
        ]);

        $msg = "แจ้งซ่อมสำเร็จ";
        $status = "success";

    }catch(Exception $e){
        $msg = $e->getMessage();
        $status = "error";
    }
}

/* ======================================================
🔥 โหลดอุปกรณ์จาก IT_user_devices
====================================================== */
$stmt = $conn->prepare("
SELECT 
d.device_code,
d.device_type,
a.spec,
a.ram,
a.ssd,
a.gpu
FROM IT_user_devices d
LEFT JOIN IT_assets a ON a.no_pc = d.device_code
WHERE d.user_project = ?
");
$stmt->execute([$userProject]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ======================================================
🔥 filter ของที่โอนแล้ว
====================================================== */
$stmtT = $conn->prepare("
SELECT no_pc
FROM IT_AssetTransfer_Headers
WHERE from_site = ?
AND receive_status = 'รับแล้ว'
");
$stmtT->execute([$userProject]);

$transfered = array_map('trim',$stmtT->fetchAll(PDO::FETCH_COLUMN));

$assets = [];

foreach($rows as $r){

    if(in_array(trim($r['device_code']),$transfered)) continue;

    $assets[] = [
        'asset_id' => $r['device_code'],
        'type' => $r['device_type'],
        'spec' => trim(($r['spec'] ?? '')." | ".($r['ram'] ?? '')." | ".($r['ssd'] ?? '')." | ".($r['gpu'] ?? ''))
    ];
}

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container mt-4">
<div class="card shadow">

<div class="card-header bg-success text-white">
🛠 แจ้งซ่อมอุปกรณ์
</div>

<div class="card-body">

<form method="post" enctype="multipart/form-data" id="repairForm">

<div class="row">

<div class="col-md-6">

<label>เลือกอุปกรณ์</label>
<select name="asset_id" id="assetSelect" class="form-control mb-3" required>

<option value="">-- เลือก --</option>

<?php foreach($assets as $a): ?>
<option value="<?= $a['asset_id'] ?>"
data-spec="<?= $a['spec'] ?>"
data-type="<?= $a['type'] ?>"
>
<?= $a['asset_id'] ?> | <?= $a['type'] ?>
</option>
<?php endforeach; ?>

</select>

<input type="hidden" name="user_type_equipment" id="type">

<label>Spec</label>
<textarea id="spec" class="form-control mb-3" readonly></textarea>

</div>

<div class="col-md-6">

<label>อาการเสีย</label>
<textarea name="problem" class="form-control mb-3" required></textarea>

<label>แนบรูป</label>
<input type="file" name="images[]" multiple class="form-control">

</div>

</div>

<div class="text-end mt-3">
<button type="button" id="btnConfirm" class="btn btn-success">
📨 แจ้งซ่อม
</button>
</div>

</form>

</div>
</div>
</div>

<script>
/* ===== auto fill ===== */
document.getElementById('assetSelect').addEventListener('change',function(){

let opt=this.options[this.selectedIndex];

document.getElementById('spec').value = opt.getAttribute('data-spec') || '';
document.getElementById('type').value = opt.getAttribute('data-type') || '';

});

/* ===== confirm ===== */
document.getElementById('btnConfirm').addEventListener('click',function(){

Swal.fire({
title:'ยืนยัน?',
text:'ต้องการแจ้งซ่อมใช่หรือไม่',
icon:'question',
showCancelButton:true,
confirmButtonText:'ส่ง',
cancelButtonText:'ยกเลิก'
}).then((res)=>{
if(res.isConfirmed){
document.getElementById('repairForm').submit();
}
});

});
</script>

<?php if($msg): ?>
<script>
Swal.fire({
icon:'<?= $status ?>',
title:'<?= $msg ?>'
}).then(()=>{
<?php if($status=='success'): ?>
window.location='repair_status.php';
<?php endif; ?>
});
</script>
<?php endif; ?>

<?php include 'partials/footer.php'; ?>