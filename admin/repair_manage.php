<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

// 🔥 ดึง role จาก session
$role = $_SESSION['role_ivt'] ?? '';
/* ======================================================
   UPDATE STATUS (อัปเดตสถานะซ่อม)
====================================================== */
if(isset($_POST['update_status'])){

    // 🔥 ถ้าเป็น MD → อัปเดตเฉพาะ comment
    if($role == 'MD'){

        $stmt = $conn->prepare("
            UPDATE IT_RepairTickets
            SET 
                requipment_details = ?, -- 🔥 comment ของ MD
                updated_at = GETDATE()
            WHERE ticket_id = ?
        ");

        $stmt->execute([
            $_POST['md_comment'],
            $_POST['ticket_id']
        ]);

    }else{

        // 🔥 Admin ปกติ
        $stmt = $conn->prepare("
            UPDATE IT_RepairTickets
            SET 
                status = ?,
                responsible_person = ?,
                repair_details = ?,
                cost = ?,
                updated_at = GETDATE(),
                closed_at = CASE 
                    WHEN ? = N'เสร็จแล้ว' THEN GETDATE() 
                    ELSE closed_at 
                END
            WHERE ticket_id = ?
        ");

        $stmt->execute([
            $_POST['status'],
            $_POST['assigned_to'],
            $_POST['solution'],
            $_POST['cost'],
            $_POST['status'],
            $_POST['ticket_id']
        ]);
    }

    header("Location: repair_manage.php");
    exit;
}

/* ======================================================
   DASHBOARD TOP 5 (เครื่องเสียบ่อย)
====================================================== */
$topAssets = $conn->query("
    SELECT TOP 5 
        r.user_no_pc AS no_pc,
        COUNT(*) total_repairs
    FROM IT_RepairTickets r
    GROUP BY r.user_no_pc
    ORDER BY total_repairs DESC
")->fetchAll(PDO::FETCH_ASSOC);

/* ======================================================
   LOAD DATA + FILTER
====================================================== */

$sql = "
SELECT 
    r.*,
    a.type_equipment,
    a.new_no,a.spec,a.ram,a.ssd,a.gpu,

    -- 🔥 นับจำนวนครั้งซ่อม (แยก project)
    (
        SELECT COUNT(*) 
        FROM IT_RepairTickets 
        WHERE user_no_pc = r.user_no_pc
        AND project = r.project
    ) AS repair_count

FROM IT_RepairTickets r
LEFT JOIN IT_assets a ON a.no_pc = r.user_no_pc

WHERE 1=1
";

$params = [];

/* ================= FILTER ================= */

// 🔍 ค้นหา
if(!empty($_GET['search'])){
    $sql .= " AND (r.user_no_pc LIKE ? OR r.user_name LIKE ? OR r.problem LIKE ?)";
    $params[] = "%".$_GET['search']."%";
    $params[] = "%".$_GET['search']."%";
    $params[] = "%".$_GET['search']."%";
}

// 🔍 สถานะ
if(!empty($_GET['status'])){
    $sql .= " AND r.status = ?";
    $params[] = $_GET['status'];
}

// 🔍 โครงการ
if(!empty($_GET['project'])){
    $sql .= " AND r.project = ?";
    $params[] = $_GET['project'];
}

// 🔍 ประเภท
if(!empty($_GET['type'])){
    $sql .= " AND a.type_equipment = ?";
    $params[] = $_GET['type'];
}

$sql .= " ORDER BY r.ticket_id DESC";

/* ================= EXECUTE ================= */
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= LOAD FILTER OPTION ================= */
$projects = $conn->query("SELECT DISTINCT project FROM IT_RepairTickets")->fetchAll(PDO::FETCH_COLUMN);
$types = $conn->query("SELECT DISTINCT type_equipment FROM IT_assets")->fetchAll(PDO::FETCH_COLUMN);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<style>
body{font-family:'Sarabun';}
.card-header{background:linear-gradient(135deg,#0d6efd,#0dcaf0);color:white;}
.badge-open{background:#6c757d;}
.badge-progress{background:#ffc107;color:#000;}
.badge-done{background:#198754;}
.img-thumb{height:80px;margin:4px;border-radius:8px;}
</style>

<div class="container-fluid p-3">

<!-- ================= TOP 5 ================= -->
<div class="card mb-3">
<div class="card-header">🔥 เครื่องเสียบ่อย</div>
<div class="card-body row">
<?php foreach($topAssets as $t): ?>
<div class="col-md-3 text-center">
<b><?= $t['no_pc'] ?></b><br>
<span class="text-danger"><?= $t['total_repairs'] ?> ครั้ง</span>
</div>
<?php endforeach; ?>
</div>
</div>

<!-- ================= FILTER ================= -->
<form method="get" class="row mb-3">

<div class="col-md-3">
<input name="search" class="form-control" placeholder="ค้นหา">
</div>

<div class="col-md-2">
<select name="status" class="form-control">
<option value="">สถานะ</option>
<option>รอรับเรื่อง</option>
<option>กำลังซ่อม</option>
<option>เสร็จแล้ว</option>
</select>
</div>

<div class="col-md-3">
<select name="project" class="form-control">
<option value="">โครงการ</option>
<?php foreach($projects as $p): ?>
<option><?= $p ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-2">
<select name="type" class="form-control">
<option value="">ประเภท</option>
<?php foreach($types as $t): ?>
<option><?= $t ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-2 d-flex gap-1">
<button class="btn btn-primary w-100">ค้นหา</button>
<a href="repair_manage.php" class="btn btn-secondary w-100">ล้าง</a>
</div>

</form>

<!-- ================= TABLE ================= -->
<table class="table table-bordered text-center">
<thead>
<tr>
<th>#</th>
<th>โครงการ</th>
<th>รหัสเครื่อง</th>
<th>ประเภท</th>
<th>ผู้แจ้ง</th>
<th>อาการ</th>
<th>จำนวนครั้งที่ซ่อม</th>
<th>สถานะ</th>
<th>วันที่</th>
<th>จัดการ</th>
</tr>
</thead>

<tbody>
<?php $i=1; foreach($data as $r):

$badge='badge-open';
if($r['status']=='กำลังซ่อม') $badge='badge-progress';
if($r['status']=='เสร็จแล้ว') $badge='badge-done';
?>

<tr>
<td><?= $i++ ?></td>
<td><?= $r['project'] ?></td>
<td><?= $r['user_no_pc'] ?></td>
<td><?= $r['type_equipment'] ?? '-' ?></td>
<td><?= $r['user_name'] ?></td>
<td><?= $r['problem'] ?></td>
<td class="text-danger"><?= $r['repair_count'] ?></td>
<td><span class="badge <?= $badge ?>"><?= $r['status'] ?></span></td>
<td><?= date('d/m/Y H:i',strtotime($r['created_at'])) ?></td>
<td class="text-center">
<button class="btn btn-primary btn-sm"
data-bs-toggle="modal"
data-bs-target="#repairModal<?= $r['ticket_id'] ?>">
ดูรายละเอียด
</button>
</td>
</tr>

<?php endforeach; ?>
</tbody>
</table>

</div>

<!-- ================= MODAL ================= -->
<?php foreach($data as $r): ?>

<div class="modal fade" id="repairModal<?= $r['ticket_id'] ?>">
<div class="modal-dialog modal-lg">
<div class="modal-content">

<form method="post" autocomplete="off">

<div class="modal-header">
<h5 class="modal-title">รายละเอียดงานซ่อม #<?= $r['ticket_id'] ?></h5>
<button class="btn-close" data-bs-dismiss="modal"></button>
</div>

<input type="hidden" name="ticket_id" value="<?= $r['ticket_id'] ?>">

<div class="modal-body">
<div class="row">

<div class="col-md-6 mb-2">
<label>รหัสเครื่อง</label>
<input class="form-control" value="<?= $r['user_no_pc'] ?>" readonly>
</div>

<div class="col-md-6 mb-2">
<label>ผู้แจ้ง</label>
<input class="form-control" value="<?= $r['user_name'] ?>" readonly>
</div>

<div class="col-md-12 mb-2">
<label>Spec</label>
<textarea class="form-control" readonly>
<?= $r['new_no'] ?> | <?= $r['spec'] ?>  <?= $r['ram'] ?>  <?= $r['ssd'] ?>  <?= $r['gpu'] ?>
</textarea>
</div>

<div class="col-md-12 mb-2">
<label>รูปภาพ</label><br>
<?php for($i2=1;$i2<=3;$i2++):
if(!empty($r["img$i2"])): ?>
<img src="../uploads/repair/<?= $r["img$i2"] ?>" class="img-thumb" onclick="zoomImg(this.src)">
<?php endif; endfor; ?>
</div>


<div class="col-md-12 mb-2">
<label>อาการเสีย</label>
<textarea class="form-control" readonly><?= $r['problem'] ?></textarea>
</div>

<div class="col-md-4 mb-2">
<label>สถานะ</label>
<select name="status" class="form-control" <?= $role=='MD'?'disabled':'' ?> <?= $role!='MD'?'required':'' ?>>
<option <?= $r['status']=='รอรับเรื่อง'?'selected':'' ?>>รอรับเรื่อง</option>
<option <?= $r['status']=='กำลังซ่อม'?'selected':'' ?>>กำลังซ่อม</option>
<option <?= $r['status']=='เสร็จแล้ว'?'selected':'' ?>>เสร็จแล้ว</option>
</select>
</div>

<div class="col-md-4 mb-2">
<label>ผู้รับผิดชอบ</label>
<input name="assigned_to" class="form-control" value="<?= $r['responsible_person'] ?>"
<?= $role=='MD'?'disabled':'' ?>
<?= $role!='MD'?'required':'' ?>>
</div>

<div class="col-md-4 mb-2">
<label>ค่าใช้จ่าย</label>
<input name="cost" type="number" class="form-control"value="<?= $r['cost'] ?>"
<?= $role=='MD'?'disabled':'' ?>
<?= $role!='MD'?'required':'' ?>>
</div>

<div class="col-md-12 mb-2">
<label>รายละเอียดการซ่อม</label>
<textarea name="solution" class="form-control"
<?= $role=='MD'?'disabled':'' ?>
<?= $role!='MD'?'required':'' ?>><?= $r['repair_details'] ?></textarea>
</div>

<div class="col-md-6 mb-2">
<label>วันที่แจ้ง</label>
<input class="form-control" value="<?= $r['created_at'] ?>" readonly>
</div>

<div class="col-md-6 mb-2">
<label>วันที่ปิดงาน</label>
<input class="form-control" value="<?= $r['closed_at'] ?? '-' ?>" readonly>
</div>

<?php if($role == 'MD' || $role == 'admin'){ ?>

<div class="col-md-12 mb-2">
<label>💬 ความคิดเห็น MD</label>

<textarea name="md_comment" class="form-control"
placeholder="แสดงความคิดเห็น (ไม่บังคับ)"
<?= $role == 'admin' ? 'readonly' : '' ?>>

<?= $r['requipment_details'] ?>

</textarea>

</div>

<?php } ?>

</div>
</div>


<div class="modal-footer">
<input type="hidden" name="update_status" value="1">
<button type="button" class="btn btn-success"
onclick="confirmSave(<?= $r['ticket_id'] ?>)">
💾 บันทึก
</button>
<button class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
</div>

</form>
</div>
</div>
</div>

<?php endforeach; ?>

<!-- 🔥 MODAL CONFIRM -->
<div class="modal fade" id="confirmModal">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content text-center p-4">
<h5>⚠️ ยืนยันการบันทึกข้อมูล?</h5>

<div class="mt-3">
<button class="btn btn-success" onclick="showSuccess()">ยืนยัน</button>
<button class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
</div>

</div>
</div>
</div>

<!-- 🔥 MODAL SUCCESS -->
<div class="modal fade" id="successModal">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content text-center p-4">
<h5 class="text-success">✅ บันทึกสำเร็จ</h5>
<p>กำลังบันทึกข้อมูล...</p>
</div>
</div>
</div>

<!-- 🔥 IMAGE ZOOM MODAL -->
<div class="modal fade" id="imgModal">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-body text-center">
<img id="imgView" style="max-width:100%;">
</div>
</div>
</div>
</div>

<script>
function zoomImg(src){
    let img = document.getElementById('imgView');
    img.src = src;

    let modal = new bootstrap.Modal(document.getElementById('imgModal'));
    modal.show();
}

// 🔥 กดปุ่มบันทึก → เปิด confirm
function confirmSave(id){

    let form = document.querySelector(`#repairModal${id} form`);

    // 🔥 เช็ค validation ก่อน
    if(!form.checkValidity()){
        form.reportValidity(); // ให้ browser เด้งเตือน
        return;
    }

    currentForm = form;

    let confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
    confirmModal.show();
}

// 🔥 confirm → ไป success
function showSuccess(){

    let confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmModal'));
    confirmModal.hide();

    let successModal = new bootstrap.Modal(document.getElementById('successModal'));
    successModal.show();

    // 🔥 delay แล้ว submit
    setTimeout(()=>{
        currentForm.submit();
    },1000);
}

// 🔥 reset ตอนเปิด modal (แก้ปัญหา cache จริง)
document.querySelectorAll('.modal').forEach(modal=>{
    modal.addEventListener('show.bs.modal', function () {
        let form = modal.querySelector('form');
        if(form) form.reset();
    });
});
</script>

<?php include 'partials/footer.php'; ?>