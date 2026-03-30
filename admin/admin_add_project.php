<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* =========================================
เมื่อกด submit
========================================= */
if(isset($_POST['confirm'])){

    $project = $_POST['project_name'];

    if($project != ''){

        $stmt = $conn->prepare("
        INSERT INTO IT_projects (project_name)
        VALUES (?)
        ");
        $stmt->execute([$project]);

        header("Location: admin_add_project.php?success=1");
        exit;
    }
}

include 'partials/header.php';
include 'partials/sidebar.php';
?>
<style>

/* 🔵 Header gradient */
.header-blue{
    background: linear-gradient(135deg, #0ea5e9, #2563eb);
    box-shadow: 0 4px 15px rgba(37,99,235,0.4);
}

/* 🔵 ปุ่มน้ำเงินว้าวๆ */
.btn-blue{
    background: linear-gradient(135deg, #38bdf8, #2563eb);
    border: none;
    color: white;
    font-weight: bold;
    transition: 0.3s;
    box-shadow: 0 4px 12px rgba(37,99,235,0.4);
}

.btn-blue:hover{
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(37,99,235,0.6);
}

/* 🔥 input focus */
.form-control:focus{
    border-color: #0ea5e9;
    box-shadow: 0 0 10px rgba(14,165,233,0.4);
}

/* 🔵 modal animation */
.modal-content{
    border-radius: 15px;
    animation: fadeZoom 0.3s ease;
}

@keyframes fadeZoom{
    from{
        transform: scale(0.9);
        opacity: 0;
    }
    to{
        transform: scale(1);
        opacity: 1;
    }
}

</style>

<div class="container mt-4">

<div class="card shadow">
<div class="card-header text-white header-blue">
เพิ่มโครงการ
</div>

<div class="card-body">

<form method="post" id="mainForm">

<div class="mb-3">
<label>ชื่อโครงการ</label>
<input type="text" name="project_name" id="project_name" class="form-control" required>
</div>

<!-- 🔥 ปุ่มเปิด modal -->
<button type="button" id="openConfirm" class="btn btn-blue">
เพิ่มโครงการ
</button>

</form>

</div>
</div>
</div>

<!-- =======================
MODAL CONFIRM
======================= -->
<div class="modal fade" id="confirmModal">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header text-white header-blue">
        <h5>ยืนยันการเพิ่มโครงการ</h5>
      </div>

      <div class="modal-body text-center">
        คุณต้องการเพิ่มโครงการนี้ใช่หรือไม่?
        <br><br>
        <b id="showProject"></b>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">
            ยกเลิก
        </button>

        <!-- 🔥 submit จริง -->
        <button type="submit" name="confirm" form="mainForm" class="btn btn-blue">
            ยืนยัน
        </button>
      </div>

    </div>
  </div>
</div>

<!-- =======================
MODAL SUCCESS
======================= -->
<div class="modal fade" id="successModal">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-4">

        <h4 class="text-primary fw-bold">สำเร็จ 🎉</h4>
        <p>เพิ่มโครงการเรียบร้อยแล้ว</p>

    </div>
  </div>
</div>

<?php include 'partials/footer.php'; ?>

<script>
// 🔥 เปิด modal confirm
document.getElementById("openConfirm").onclick = function(){

    let project = document.getElementById("project_name").value;

    if(project === ''){
        alert("กรุณากรอกชื่อโครงการ");
        return;
    }

    document.getElementById("showProject").innerText = project;

    new bootstrap.Modal(document.getElementById('confirmModal')).show();
};

// 🔥 success modal
<?php if(isset($_GET['success'])): ?>
new bootstrap.Modal(document.getElementById('successModal')).show();
<?php endif; ?>
</script>