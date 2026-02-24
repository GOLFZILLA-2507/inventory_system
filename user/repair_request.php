<?php
require_once '../config/connect.php';
include 'partials/header.php';
include 'partials/sidebar.php';

/* ================= โหลด asset ================= */
$assets = $conn->query("
SELECT asset_id, no_pc, new_no, spec, ram, gpu, ssd
FROM IT_assets
ORDER BY no_pc
")->fetchAll(PDO::FETCH_ASSOC);

/* ================= SUBMIT ================= */
if(isset($_POST['submit'])){

    $uploadDir = "../uploads/repair/";
    $img1=""; $img2=""; $img3="";

    if(!empty($_FILES['images']['name'][0])){
        for($i=0;$i<3;$i++){
            if(isset($_FILES['images']['name'][$i]) && $_FILES['images']['name'][$i]!=""){
                $filename = time()."_".$i."_".basename($_FILES['images']['name'][$i]);
                move_uploaded_file($_FILES['images']['tmp_name'][$i], $uploadDir.$filename);

                if($i==0) $img1=$filename;
                if($i==1) $img2=$filename;
                if($i==2) $img3=$filename;
            }
        }
    }

    $stmt = $conn->prepare("
        INSERT INTO IT_RepairTickets
        (asset_id,user_id,user_name,problem,priority,img1,img2,img3)
        VALUES (?,?,?,?,?,?,?,?)
    ");

    $stmt->execute([
        $_POST['asset_id'],
        $_SESSION['EmployeeID'],
        $_SESSION['fullname'],
        $_POST['problem'],
        $_POST['priority'],
        $img1,$img2,$img3
    ]);

    header("Location: repair_request.php?success=1");
    exit;
}
?>

<style>
.card-box{
    max-width:1000px;
    margin:auto;
    border-radius:14px;
}
.card-header{
    background: linear-gradient(135deg,#198754,#20c997);
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

<!-- LEFT -->
<div class="col-md-6">

<label class="label">เลือกเครื่อง</label>
<select name="asset_id" id="assetSelect" class="form-control mb-3" required>
<option value="">-- เลือกอุปกรณ์ --</option>
<?php foreach($assets as $a): ?>
<option value="<?= $a['asset_id'] ?>"
data-new="<?= $a['new_no'] ?>"
data-spec="<?= $a['spec'].' | '.$a['ram'].' | '.$a['gpu'].' | '.$a['ssd'] ?>">
<?= $a['no_pc'] ?>
</option>
<?php endforeach; ?>
</select>

<label class="label">รหัสยาว (new_no)</label>
<input type="text" id="new_no" class="form-control readonly mb-3" readonly>

<label class="label">Spec เครื่อง</label>
<textarea id="spec" class="form-control readonly mb-3" rows="3" readonly></textarea>

</div>

<!-- RIGHT -->
<div class="col-md-6">

<label class="label">อาการเสีย</label>
<textarea name="problem" class="form-control mb-3" rows="4" required></textarea>

<label class="label">ความสำคัญ</label>
<select name="priority" class="form-control mb-3">
<option value="Low">🔵 ตามรอบ</option>
<option value="Normal" selected>🟡 ปกติ</option>
<option value="High">🔴 เร่งด่วน</option>
</select>

<label class="label">แนบรูป (เลือกได้สูงสุด 3 รูป)</label>
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
// auto fill
document.getElementById('assetSelect').addEventListener('change',function(){
let opt=this.options[this.selectedIndex];
document.getElementById('new_no').value=opt.getAttribute('data-new')||'';
document.getElementById('spec').value=opt.getAttribute('data-spec')||'';
});

// preview image
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