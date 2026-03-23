<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* ================= PARAM ================= */
$search = $_GET['search'] ?? '';
$type   = $_GET['type'] ?? '';
$proj   = $_GET['project'] ?? '';
$page   = $_GET['page'] ?? 1;

$limit  = 15;
$offset = ($page-1)*$limit;

/* ================= SAVE ================= */
if(isset($_POST['save'])){
    $id = $_POST['asset_id'];

    $value = $_POST['machine_value'];
    $value = ($value === '' ? null : $value);

    $y1 = $_POST['yfm_1'] ?? null;
    $y2 = $_POST['yfm_2'] ?? null;

    $date1 = !empty($y1) ? date('Y-m-01', strtotime($y1)) : null;
    $date2 = !empty($y2) ? date('Y-m-01', strtotime($y2)) : null;

    $how1 = $date1 ? date('Y') - date('Y', strtotime($date1)) : null;
    $how2 = $date2 ? date('Y') - date('Y', strtotime($date2)) : null;

    $stmt = $conn->prepare("
        UPDATE IT_assets SET
            machine_value=?,
            yfm_1=?,
            How_long=?,
            yfm_2=?,
            How_long2=?
        WHERE asset_id=?
    ");
    $stmt->execute([$value,$date1,$how1,$date2,$how2,$id]);


    /* ================= SAVE USER (เพิ่มใหม่) ================= */
if(isset($_POST['save_user'])){

    $asset_id = $_POST['asset_id'];
    $user_emp = $_POST['user_employee'];

    // 🔥 เช็คว่ามีอยู่แล้วไหม
    $check = $conn->prepare("SELECT COUNT(*) FROM IT_user_information WHERE asset_id=?");
    $check->execute([$asset_id]);
    $exists = $check->fetchColumn();

    if($exists > 0){
        // 🔄 update
        $stmt = $conn->prepare("
            UPDATE IT_user_information 
            SET user_employee=? 
            WHERE asset_id=?
        ");
        $stmt->execute([$user_emp,$asset_id]);
    }else{
        // ➕ insert
        $stmt = $conn->prepare("
            INSERT INTO IT_user_information(asset_id,user_employee)
            VALUES(?,?)
        ");
        $stmt->execute([$asset_id,$user_emp]);
    }

    header("Location: asset_all.php?success=1");
    exit();
}

    // ✅ กัน refresh submit ซ้ำ
    header("Location: service_life_view.php?success=1");
    exit();
}

/* ================= FILTER ================= */
$typeList = $conn->query("SELECT DISTINCT type_equipment FROM IT_assets ORDER BY type_equipment")->fetchAll(PDO::FETCH_ASSOC);
/* ================= โหลดรายชื่อพนักงาน (เพิ่มใหม่) ================= */
$userList = $conn->query("
SELECT fullname 
FROM Employee
ORDER BY fullname
")->fetchAll(PDO::FETCH_COLUMN);

/* ================= MAIN QUERY ================= */
$sql = "
SELECT 
a.asset_id,
a.no_pc,
a.project,
a.type_equipment,
a.spec,a.ram,a.ssd,a.gpu,
a.machine_value,
a.yfm_1,a.How_long,
a.yfm_2,a.How_long2,
u.user_employee
FROM IT_assets a
LEFT JOIN IT_user_information u ON u.asset_id=a.asset_id
WHERE 1=1
";

$params = [];

if($search!=''){
    $sql.=" AND (a.no_pc LIKE ? OR u.user_employee LIKE ? OR a.project LIKE ?)";
    $params[]="%$search%";
    $params[]="%$search%";
    $params[]="%$search%";
}

if($type!=''){
    $sql.=" AND a.type_equipment=?";
    $params[]=$type;
}

if($proj!=''){
    $sql.=" AND a.project=?";
    $params[]=$proj;
}

$sql.=" ORDER BY a.project OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY";

$stmt=$conn->prepare($sql);
$stmt->execute($params);
$data=$stmt->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<style>
body{font-family:'Sarabun';font-size:14px;background:#eef6ff;}
.card-header{background:linear-gradient(135deg,#0d6efd,#0dcaf0);color:white;}
.table thead{background:#0d6efd;color:white;}
.modal-header{background:#0d6efd;color:white;}
.badge-grade{padding:5px 10px;border-radius:8px;font-size:13px;}

.detail-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:12px;
}

.detail-box{
    background:#f1f7ff;
    border-radius:10px;
    padding:12px;
    text-align:left;
    box-shadow:0 2px 6px rgba(0,0,0,0.08);
}

.detail-box b{
    display:block;
    color:#0d6efd;
    margin-bottom:4px;
}
/* ===== NEW REPAIR MODAL STYLE ===== */
.repair-clean{
    background:#ffffff;
    padding:30px;
    border-radius:12px;
}

.repair-head{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
    padding-bottom:10px;
    border-bottom:1px solid #e5e7eb;
}

.repair-grid-clean{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
}

.repair-grid-clean .item{
    background:#f8fafc;
    padding:15px 18px;
    border-radius:10px;
    border:1px solid #e5e7eb;
}

.repair-grid-clean .full{
    grid-column:1 / -1;
}

.repair-grid-clean label{
    font-size:12px;
    color:#6b7280;
    display:block;
}

.repair-grid-clean .item div{
    font-size:16px;
    font-weight:600;
    color:#111827;
}

.price{
    font-size:22px;
    color:#0d6efd;
    font-weight:700;
}

.repair-footer{
    margin-top:25px;
    text-align:right;
}
/* 🔥 modal ฟ้าๆ สวย */
.modal-content{
    border-radius:12px;
    box-shadow:0 10px 25px rgba(0,0,0,0.15);
}

.modal-header{
    border-top-left-radius:12px;
    border-top-right-radius:12px;
}

.btn-primary{
    background:linear-gradient(135deg,#0d6efd,#0dcaf0);
    border:none;
}

.btn-primary:hover{
    opacity:0.9;
}
</style>

<div class="container mt-4">
<div class="card shadow">

<div class="card-header">
📊 รายงาน Service Life อุปกรณ์
</div>

<div class="card-body">

<!-- FILTER -->
<form class="row mb-3">
<div class="col-md-3">
<input name="search" id="searchBox" class="form-control" placeholder="ค้นหา..." value="<?= $search ?>">
</div>

<div class="col-md-3">
<select name="type" class="form-control">
<option value="">-- ทุกประเภท --</option>
<?php foreach($typeList as $t): ?>
<option value="<?= $t['type_equipment'] ?>" <?= $type==$t['type_equipment']?'selected':'' ?>>
<?= $t['type_equipment'] ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-3">
<select name="project" class="form-control">
<option value="">-- ทั้งหมด --</option>
<?php foreach($projList as $p): ?>
<option value="<?= $p['project'] ?>" <?= $proj==$p['project']?'selected':'' ?>>
<?= $p['project'] ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-3">
<button class="btn btn-primary w-100">🔍 ค้นหา</button>
</div>
</form>

<table class="table table-bordered table-hover text-center" id="dataTable">
<thead>
<tr>
<th>#</th>
<th>ผู้ใช้</th>
<th>โครงการ</th>
<th>รหัส</th>
<th>ประเภท</th>
<th>อายุจากปีที่ผลิต</th>    
<th>วันที่เริ่มใช้งาน</th>
<th>อยู่ในเกณฑ์</th>
<th>รายละเอียด</th>
<th>เพิ่มผู้ใช้งาน</th>
</tr>
</thead>

<tbody>

<?php $i= $offset + 1; foreach($data as $row): 

$spec = $row['spec']." | ".$row['ram']." | ".$row['ssd']." | ".$row['gpu'];

$age = $row['How_long2'];

if(empty($row['yfm_2'])){
    $grade = "<span class='badge bg-secondary'>ยังไม่ได้บันทึกข้อมูล</span>";
}
elseif((int)$age < 6){
    $grade = "<span class='badge bg-success'>A - ใช้งานได้ดี</span>";
}
elseif((int)$age <= 7){
    $grade = "<span class='badge bg-warning text-dark'>B - พอใช้</span>";
}
else{
    $grade = "<span class='badge bg-danger'>C - ควรเปลี่ยน</span>";
}
?>

<tr>

<td><?= $i++ ?></td>
<td><?= $row['user_employee'] ?></td>
<td><?= $row['project'] ?></td>
<td><?= $row['no_pc'] ?></td>
<td><?= $row['type_equipment'] ?></td>
<td><?= $row['How_long'] ?> ปี</td>
<td><?= $row['How_long2'] ?> ปี</td>
<td><?= $grade ?></td>
<td>
<button type="button" class="btn btn-info btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#detail<?= $row['asset_id'] ?>">
จัดการ
</button>
</td>
<td>
<?php 
// 🔥 ถ้าเป็นอุปกรณ์ใช้ร่วม = ไม่ให้แสดงปุ่ม
if(empty($row['user_employee']) && $row['type_equipment'] != 'cctv,printer,projector'): 
?>
    <button type="button" class="btn btn-success btn-sm"
            data-bs-toggle="modal"
            data-bs-target="#assignUser<?= $row['asset_id'] ?>">
        เพิ่มผู้ใช้งาน
    </button>
<?php endif; ?>

</tr>

<!-- =====================================================
🔥 MODAL (แก้ใหม่ทั้งหมด)
===================================================== -->
<div class="modal fade" id="detail<?= $row['asset_id'] ?>">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">

<!-- ✅ form อยู่ใน modal -->
<form method="post">

<div class="modal-header">
<h5>📄 แก้ไขข้อมูลอุปกรณ์</h5>
<button class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">

<!-- ✅ ส่ง id -->
<input type="hidden" name="asset_id" value="<?= $row['asset_id'] ?>">

<div class="detail-grid">

<div class="detail-box">
<b>ผู้ใช้งาน</b>
<?= $row['user_employee'] ?>
</div>

<div class="detail-box">
<b>โครงการ</b>
<?= $row['project'] ?>
</div>

<div class="detail-box">
<b>รหัสอุปกรณ์</b>
<?= $row['no_pc'] ?>
</div>

<div class="detail-box">
<b>Spec เครื่อง</b>
<?= $spec ?>
</div>

<div class="detail-box">
<b>📅 (กรุณาเลือก) ปีที่เริ่มผลิต อ้างอิงจากสเปคเครื่อง</b>
<input type="text" name="yfm_1"
class="form-control y1"
value="<?= !empty($row['yfm_1']) ? substr($row['yfm_1'],0,7) : '' ?>">
</div>

<div class="detail-box">
<b>🛒 (กรุณาเลือก) วันที่เริ่มใช้งาน</b>
<input type="text" name="yfm_2"
class="form-control y2"
value="<?= !empty($row['yfm_2']) ? substr($row['yfm_2'],0,7) : '' ?>">
</div>

<div class="detail-box">
<b>⏳ อายุ CPU</b>
<div><?= $row['How_long'] ?> ปี</div>
</div>

<div class="detail-box">
<b>📊 อายุใช้งาน</b>
<div><?= $row['How_long2'] ?> ปี</div>
</div>

<!-- ================= INPUT ================= -->

<div class="detail-box">
<b>💰 มูลค่าเครื่อง (โดยประมานการ)</b>
<input type="number" name="machine_value"
class="form-control"
value="<?= $row['machine_value'] ?>">
</div>

</div>

</div>

<!-- ================= FOOTER ================= -->
<div class="modal-footer">

<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
ปิด
</button>

<!-- ✅ ปุ่มบันทึกอยู่ใน modal -->
<button type="submit" name="save" class="btn btn-primary">
💾 บันทึก
</button>

</div>

</form>
<!-- ✅ จบ form -->

</div>
</div>
</div>

<!-- =====================================================
🔥 MODAL เพิ่มผู้ใช้งาน (ใหม่)
===================================================== -->
<div class="modal fade" id="assignUser<?= $row['asset_id'] ?>">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">

<form method="post">

<!-- HEADER -->
<div class="modal-header" style="background:linear-gradient(135deg,#0d6efd,#0dcaf0);color:white;">
<h5>👤 เพิ่มผู้ใช้งาน</h5>
<button class="btn-close" data-bs-dismiss="modal"></button>
</div>

<!-- BODY -->
<div class="modal-body">

<input type="hidden" name="asset_id" value="<?= $row['asset_id'] ?>">

<div class="mb-3">
<label>รหัสอุปกรณ์</label>
<input type="text" class="form-control" value="<?= $row['no_pc'] ?>" readonly>
</div>

<div class="mb-3">
<label>ผู้ใช้งาน</label>
<!-- 🔥 input + search -->
<input list="userList"
       name="user_employee"
       class="form-control"
       placeholder="พิมพ์ชื่อผู้ใช้งาน..."
       required>

<!-- 🔥 datalist -->
<datalist id="userList">
<?php foreach($userList as $u): ?>
    <option value="<?= $u ?>">
<?php endforeach; ?>
</datalist>
</div>

</div>

<!-- FOOTER -->
<div class="modal-footer">

<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
ยกเลิก
</button>

<button type="submit" name="save_user"
class="btn btn-primary"
onclick="return confirm('ยืนยันเพิ่มผู้ใช้งาน ?')">
💾 บันทึก
</button>

</div>

</form>

</div>
</div>
</div>

<?php endforeach; ?>

</tbody>

</table>

<!-- PAGINATION -->
<div class="text-center mt-3">
<a href="?page=<?= max(1,$page-1) ?>&search=<?= $search ?>&type=<?= $type ?>&project=<?= $proj ?>" class="btn btn-primary">⬅ ก่อนหน้า</a>
<a href="?page=<?= $page+1 ?>&search=<?= $search ?>&type=<?= $type ?>&project=<?= $proj ?>" class="btn btn-primary">ถัดไป ➡</a>
</div>

</div>
</div>
</div>

<script>
flatpickr(".y1",{dateFormat:"Y-m"});
flatpickr(".y2",{dateFormat:"Y-m"});

// realtime search
document.getElementById("searchBox").addEventListener("keyup",function(){
    let v=this.value.toLowerCase();
    document.querySelectorAll("#dataTable tbody tr").forEach(tr=>{
        tr.style.display = tr.innerText.toLowerCase().includes(v)?'':'none';
    });
});

let currentForm = null;

document.querySelectorAll(".btn-save").forEach(btn=>{
    btn.addEventListener("click", function(){

        let formId = this.getAttribute("data-form");
        let form = document.getElementById(formId);
        currentForm = form;

        // ❗ แก้ตรงนี้: input อยู่ข้างนอก form ต้องหาโดยใช้ [form="formID"]
        let newVal = document.querySelector('[name="machine_value"][form="'+formId+'"]').value;
        let newY1  = document.querySelector('[name="yfm_1"][form="'+formId+'"]').value;
        let newY2  = document.querySelector('[name="yfm_2"][form="'+formId+'"]').value;

        // ค่าเก่าอยู่ใน form ปุ่ม save (หาได้ปกติ)
        let oldVal = form.querySelector("[name=old_value]").value;
        let oldY1  = form.querySelector("[name=old_y1]").value;
        let oldY2  = form.querySelector("[name=old_y2]").value;

        let html = `
        <b>Machine Value:</b> ${oldVal} → ${newVal}<br>
        <b>CPU Date:</b> ${oldY1} → ${newY1}<br>
        <b>Purchase Date:</b> ${oldY2} → ${newY2}
        `;

        document.getElementById("changeSummary").innerHTML = html;

        let modal = new bootstrap.Modal(document.getElementById('confirmModal'));
        modal.show();
    });
});

document.getElementById("confirmSaveBtn").addEventListener("click",function(){
    if(currentForm){
        let input = document.createElement("input");
        input.type = "hidden";
        input.name = "save";
        input.value = "1";
        currentForm.appendChild(input);
        currentForm.submit();
    }
});
</script>

<?php include 'partials/footer.php'; ?>