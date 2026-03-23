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

    // ✅ กัน refresh submit ซ้ำ
    header("Location: service_life_view.php?success=1");
    exit();
}

/* ================= FILTER ================= */
$typeList = $conn->query("SELECT DISTINCT type_equipment FROM IT_assets ORDER BY type_equipment")->fetchAll(PDO::FETCH_ASSOC);
$projList = $conn->query("SELECT DISTINCT project FROM IT_assets ORDER BY project")->fetchAll(PDO::FETCH_ASSOC);

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
<option value="">-- ประเภท --</option>
<?php foreach($typeList as $t): ?>
<option value="<?= $t['type_equipment'] ?>" <?= $type==$t['type_equipment']?'selected':'' ?>>
<?= $t['type_equipment'] ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-3">
<select name="project" class="form-control">
<option value="">-- โครงการ --</option>
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
<th>วัดค่า</th>
<th>รายละเอียด</th>
</tr>
</thead>

<tbody>

<?php $i=1; foreach($data as $row): 

$spec = $row['spec']." | ".$row['ram']." | ".$row['ssd']." | ".$row['gpu'];

$age = $row['How_long2'];

if(empty($row['yfm_2'])){
    $grade = "<span class='badge bg-secondary'>ยังไม่ได้บันทึกข้อมูล</span>";
}
elseif((int)$age < 4){
    $grade = "<span class='badge bg-success'>A - ใช้งานได้ดี</span>";
}
elseif((int)$age <= 7){
    $grade = "<span class='badge bg-warning text-dark'>B - พอใช้</span>";
}
else{
    $grade = "<span class='badge bg-danger'>C - ควรเปลี่ยน</span>";
}
?>

<form method="post">

<!-- 🔥 แก้ตรงนี้: ผูก hidden กับ form ปุ่ม save -->
<input type="hidden" name="asset_id" form="form<?= $row['asset_id'] ?>" value="<?= $row['asset_id'] ?>">
<input type="hidden" name="old_value" form="form<?= $row['asset_id'] ?>" value="<?= $row['machine_value'] ?>">
<input type="hidden" name="old_y1" form="form<?= $row['asset_id'] ?>" value="<?= !empty($row['yfm_1']) ? substr($row['yfm_1'],0,7) : '' ?>">
<input type="hidden" name="old_y2" form="form<?= $row['asset_id'] ?>" value="<?= !empty($row['yfm_2']) ? substr($row['yfm_2'],0,7) : '' ?>">

<tr>

<td><?= $i++ ?></td>
<td><?= $row['user_employee'] ?></td>
<td><?= $row['project'] ?></td>
<td><?= $row['no_pc'] ?></td>



<td><?= $grade ?></td>

<td>
<button type="button" class="btn btn-info btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#detail<?= $i ?>">แก้ไข</button>
</td>
<!-- 
<td>
<button type="button" class="btn btn-warning btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#repair<?= $i ?>">ดู</button>
</td> 
-->

</form>

<td>

<form method="post" id="form<?= $row['asset_id'] ?>">

<input type="hidden" name="asset_id" value="<?= $row['asset_id'] ?>">
<input type="hidden" name="old_value" value="<?= $row['machine_value'] ?>">
<input type="hidden" name="old_y1" value="<?= !empty($row['yfm_1']) ? substr($row['yfm_1'],0,7) : '' ?>">
<input type="hidden" name="old_y2" value="<?= !empty($row['yfm_2']) ? substr($row['yfm_2'],0,7) : '' ?>">

<button type="button"
        class="btn btn-primary btn-sm btn-save"
        data-form="form<?= $row['asset_id'] ?>">
💾
</button>

</form>

</td>

<!-- CONFIRM MODAL -->
<div class="modal fade" id="confirmModal">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">

<div class="modal-header bg-primary text-white">
<h5>ยืนยันการบันทึก</h5>
<button class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">

<div id="changeSummary" style="font-size:14px"></div>

</div>

<div class="modal-footer">
<button class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
<button id="confirmSaveBtn" class="btn btn-primary">ยืนยันบันทึก</button>
</div>

</div>
</div>
</div>

<!-- MODAL DETAIL -->
<div class="modal fade" id="detail<?= $i ?>">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">

<div class="modal-header">
<h5>📄 รายละเอียดอุปกรณ์</h5>
<button class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">

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
<b>วันที่เริ่ม CPU</b>
<?= $row['yfm_1'] ?>
</div>

<div class="detail-box">
<b>วันที่ซื้อเครื่อง</b>
<?= $row['yfm_2'] ?>
</div>

<div class="detail-box">
<b>⏳ อายุ CPU</b>
<div><?= $row['How_long'] ?> ปี</div>
</div>

<div class="detail-box">
<b>📊 อายุการใช้งานจริง</b>
<div><?= $row['How_long2'] ?> ปี</div>
</div>

<div class="detail-box">
<b>💰 มูลค่าเครื่อง (กรอกข้อมูล)</b>
<input type="number" name="machine_value" form="form<?= $row['asset_id'] ?>"
class="form-control" value="<?= $row['machine_value'] ?>">
</div>

<div class="detail-box">
<b>📅 CPU เริ่มใช้งาน (กรอกข้อมูล)</b>
<input type="text" name="yfm_1" form="form<?= $row['asset_id'] ?>"
class="form-control y1"
value="<?= !empty($row['yfm_1']) ? substr($row['yfm_1'],0,7) : '' ?>">
</div>

<div class="detail-box">
<b>🛒 วันที่ซื้อ (กรอกข้อมูล)</b>
<input type="text" name="yfm_2" form="form<?= $row['asset_id'] ?>"
class="form-control y2"
value="<?= !empty($row['yfm_2']) ? substr($row['yfm_2'],0,7) : '' ?>">
</div>

</div>

</div>

</div>
</div>
</div>

<!-- ================= MODAL REPAIR ================= -->
<div class="modal fade" id="repair<?= $i ?>">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">

<div class="modal-header bg-warning">
<h5>🛠 ประวัติการซ่อม</h5>
<button class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body repair-clean">

<div class="repair-head">
    <div>
        <h5 class="mb-0"><?= $row['no_pc'] ?></h5>
        <small class="text-muted"><?= $row['project'] ?></small>
    </div>
    <div class="badge bg-primary px-3 py-2">
        <?= $row['user_employee'] ?>
    </div>
</div>

<div class="repair-grid-clean">

<div class="item">
<label>Spec เครื่อง</label>
<div><?= $spec ?></div>
</div>

<div class="item">
<label>วันที่ซื้อ</label>
<div><?= $row['yfm_2'] ?: '-' ?></div>
</div>

<div class="item">
<label>CPU อายุ</label>
<div><?= $row['How_long'] ?> ปี</div>
</div>

<div class="item">
<label>อายุใช้งาน</label>
<div><?= $row['How_long2'] ?> ปี</div>
</div>

</div>

<div class="repair-footer">
<button class="btn btn-outline-primary">ถัดไป →</button>
</div>

</div>

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