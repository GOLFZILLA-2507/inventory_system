<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* =========================================
โหลด site (โครงการ) ไม่ซ้ำ
========================================= */
$sites = $conn->query("
SELECT DISTINCT site 
FROM Employee 
WHERE site IS NOT NULL AND site <> ''
ORDER BY site
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================================
เมื่อกดยืนยัน
========================================= */
if(isset($_POST['confirm'])){

    $emp = $_POST['emp_id'] ?? '';
    $site = $_POST['site'] ?? '';
    $role = $_POST['role_ivt'] ?? '';

    if($emp && $site && in_array($role,['user','hr'])){

        $stmt = $conn->prepare("
        UPDATE Employee
        SET site = ?, 
            role_ivt = ?
        WHERE EmployeeID = ?
        ");
        $stmt->execute([$site, $role, $emp]);

        header("Location: admin_assign_project.php?success=1");
        exit;
    }
}

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="container mt-4">

<div class="card shadow">
<div class="card-header header-blue text-white">
กำหนดพนักงานเข้าโครงการ
</div>

<div class="card-body">

<form method="post" id="mainForm">

<!-- 🔵 เลือกโครงการ -->
<div class="mb-3">
<label>เลือกโครงการ</label>
<select name="site" id="site" class="form-select" required>
<option value="">-- เลือกโครงการ --</option>
<?php foreach($sites as $s): ?>
<option value="<?= $s['site'] ?>">
<?= $s['site'] ?>
</option>
<?php endforeach; ?>
</select>
</div>

<!-- 🔍 ค้นหา -->
<div class="mb-3">
<label>ค้นหาพนักงาน</label>
<input type="text" id="search" class="form-control" placeholder="พิมพ์ชื่อ...">
</div>

<!-- 📋 ตาราง -->
<table class="table table-bordered" id="empTable">
<thead>
<tr>
<th>เลือก</th>
<th>รหัส</th>
<th>ชื่อ</th>
<th>ตำแหน่ง</th>
</tr>
</thead>
<tbody>

<?php
$emps = $conn->query("
SELECT EmployeeID, fullname, position 
FROM Employee 
WHERE active = 1
")->fetchAll(PDO::FETCH_ASSOC);

foreach($emps as $e):
?>
<tr>
<td>
<input type="radio" name="emp_id" value="<?= $e['EmployeeID'] ?>">
</td>
<td><?= $e['EmployeeID'] ?></td>
<td><?= $e['fullname'] ?></td>
<td><?= $e['position'] ?></td>
</tr>
<?php endforeach; ?>

</tbody>
</table>

<!-- 🔵 role -->
<div class="mb-3">
<label>สิทธิ์ในโครงการ</label>
<select name="role_ivt" class="form-select" required>
<option value="">-- เลือก --</option>
<option value="user">user</option>
<option value="hr">hr</option>
</select>
</div>

<!-- 🔥 ปุ่ม -->
<button type="button" id="openConfirm" class="btn btn-blue">
กำหนด
</button>

</form>

</div>
</div>
</div>

<!-- ======================
MODAL CONFIRM
====================== -->
<div class="modal fade" id="confirmModal">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">

<div class="modal-header header-blue text-white">
<h5>ยืนยันการกำหนด</h5>
</div>

<div class="modal-body text-center">
<div id="confirmText"></div>
</div>

<div class="modal-footer">
<button class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>

<button type="submit" name="confirm" form="mainForm" class="btn btn-blue">
ยืนยัน
</button>
</div>

</div>
</div>
</div>

<!-- ======================
MODAL SUCCESS
====================== -->
<div class="modal fade" id="successModal">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content text-center p-4">

<h4 class="text-primary fw-bold">สำเร็จ 🎉</h4>
<p>กำหนดพนักงานเรียบร้อย</p>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>

<!-- ======================
JS
====================== -->
<script>

// 🔍 search filter
document.getElementById("search").addEventListener("keyup", function(){

let val = this.value.toLowerCase();

document.querySelectorAll("#empTable tbody tr").forEach(tr=>{
    tr.style.display = tr.innerText.toLowerCase().includes(val) ? "" : "none";
});

});

// 🔥 confirm modal
document.getElementById("openConfirm").onclick = function(){

let emp = document.querySelector("input[name='emp_id']:checked");
let site = document.getElementById("site").value;
let role = document.querySelector("[name='role_ivt']").value;

if(!emp || !site || !role){
    alert("กรุณาเลือกข้อมูลให้ครบ");
    return;
}

// 🔥 ดึงข้อมูล row
let row = emp.closest("tr");
let name = row.children[2].innerText;

document.getElementById("confirmText").innerHTML =
"<b>"+name+"</b><br>ไปยังโครงการ <b>"+site+"</b><br>สิทธิ์: "+role;

new bootstrap.Modal(document.getElementById('confirmModal')).show();

};

// 🔥 success
<?php if(isset($_GET['success'])): ?>
new bootstrap.Modal(document.getElementById('successModal')).show();
<?php endif; ?>

</script>

<style>

/* 🔵 ธีมเดียวกับหน้าอื่น */
.header-blue{
    background: linear-gradient(135deg,#0ea5e9,#2563eb);
}

.btn-blue{
    background: linear-gradient(135deg,#38bdf8,#2563eb);
    color:#fff;
    border:none;
}

</style>