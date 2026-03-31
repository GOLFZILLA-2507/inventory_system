<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* ================= ADD PROJECT ================= */
if(isset($_POST['confirm'])){
    $project = $_POST['project_name'];

    if($project != ''){
        $stmt = $conn->prepare("
        INSERT INTO IT_projects (project_name, status, created_at)
        VALUES (?, N'อยู่ระหว่างดำเนินการ', GETDATE())
        ");
        $stmt->execute([$project]);

        header("Location: admin_add_project.php?success=1");
        exit;
    }
}

/* ================= EDIT PROJECT ================= */
if(isset($_POST['edit_project'])){
    $stmt = $conn->prepare("
        UPDATE IT_projects
        SET project_name = ?
        WHERE project_id = ?
    ");
    $stmt->execute([
        $_POST['edit_name'],
        $_POST['project_id']
    ]);

    header("Location: admin_add_project.php");
    exit;
}

/* ================= UPDATE STATUS ================= */
if(isset($_POST['update_status'])){
    $stmt = $conn->prepare("
        UPDATE IT_projects
        SET status = ?
        WHERE project_id = ?
    ");
    $stmt->execute([
        $_POST['status'],
        $_POST['project_id']
    ]);

    header("Location: admin_add_project.php");
    exit;
}

/* ================= LOAD DATA ================= */
$projects = $conn->query("
SELECT * FROM IT_projects
ORDER BY project_id DESC
")->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="container mt-4">

<!-- ================= ADD FORM ================= -->
<div class="card mb-4">
<div class="card-header bg-primary text-white">เพิ่มโครงการ</div>
<div class="card-body">

<form method="post" id="mainForm">
<input type="text" name="project_name" id="project_name" class="form-control mb-2" required placeholder="ชื่อโครงการ">

<button type="button" onclick="openConfirm()" class="btn btn-primary">
เพิ่มโครงการ
</button>
</form>

</div>
</div>

<!-- ================= TABLE ================= -->
<div class="card" >
<div class="card-header bg-primary text-white" >จัดการโครงการ</div>
<div class="card-body" >

<table class="table table-bordered text-center" >
<thead>
<tr>
<th>#</th>
<th>ชื่อโครงการ</th>
<th>สถานะ</th>
<th>จัดการ</th>
<th>จัดการโครงการ</th>
</tr>
</thead>

<tbody>
<?php $i=1;  foreach($projects as $p): ?>
<tr>
<td><?= $i++ ?></td>

<td><?= $p['project_name'] ?></td>

<td>
<?php if($p['status']=='ปิดโครงการ'): ?>
<span class="badge bg-danger">ปิดโครงการ</span>
<?php else: ?>
<span class="badge bg-success">ใช้งานอยู่</span>
<?php endif; ?>
</td>

<td>

<!-- ✏️ แก้ไข -->
<button class="btn btn-warning btn-sm"
onclick="openEdit(<?= $p['project_id'] ?>,'<?= $p['project_name'] ?>')">
แก้ไข
</button>
</td>

<td>
<form method="post" style="display:inline-flex; gap:5px;">

<input type="hidden" name="project_id" value="<?= $p['project_id'] ?>">

<select name="status" class="form-control form-control-sm">

<option <?= $p['status']=='อยู่ระหว่างดำเนินการ'?'selected':'' ?>>
อยู่ระหว่างดำเนินการ
</option>

<option <?= $p['status']=='ปิดโครงการ'?'selected':'' ?>>
ปิดโครงการ
</option>

</select>

<button class="btn btn-success btn-sm" name="update_status">
บันทึก
</button>

</form>

</td>
</form>



</td>
</tr>
<?php endforeach; ?>




</tbody>

</table>



</div>
</div>

</div>

<!-- ================= MODAL CONFIRM ================= -->
<div class="modal fade" id="confirmModal">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content text-center p-4">

<h5>ยืนยันเพิ่มโครงการ?</h5>
<p id="showProject"></p>

<button class="btn btn-success" onclick="submitForm()">ยืนยัน</button>
<button class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>

</div>
</div>
</div>

<!-- ================= MODAL EDIT ================= -->
<div class="modal fade" id="editModal">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content p-4">

<form method="post">
<input type="hidden" name="project_id" id="edit_id">

<label>ชื่อโครงการ</label>
<input type="text" name="edit_name" id="edit_name" class="form-control mb-3" required>

<button class="btn btn-warning" name="edit_project">บันทึก</button>
</form>

</div>
</div>
</div>

<!-- ================= SUCCESS ================= -->
<?php if(isset($_GET['success'])): ?>
<script>
alert("เพิ่มโครงการสำเร็จ");
</script>
<?php endif; ?>

<script>
function openConfirm(){
    let val = document.getElementById('project_name').value;

    if(val==''){
        alert('กรุณากรอกชื่อ');
        return;
    }

    document.getElementById('showProject').innerText = val;

    new bootstrap.Modal(document.getElementById('confirmModal')).show();
}

function submitForm(){
    document.getElementById('mainForm').submit();
}

function openEdit(id,name){
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;

    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php include 'partials/footer.php'; ?>