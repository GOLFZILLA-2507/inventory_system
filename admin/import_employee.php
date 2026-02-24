<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="card shadow">
    <div class="card-header bg-primary text-white">
        <h4>📥 Import รายชื่อพนักงาน</h4>
    </div>
    <div class="card-body">

        <p class="text-muted">
            รูปแบบไฟล์ต้องเป็น CSV และมีหัวคอลัมน์ดังนี้:
            EmployeeID, fullname, position, department, site
        </p>

        <form method="post" action="process_import.php" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">เลือกไฟล์ CSV</label>
                <input type="file" name="csv_file" class="form-control" accept=".csv" required>
            </div>

            <button type="submit" class="btn btn-success">
                ⬆️ Upload และ Import
            </button>
        </form>

    </div>
</div>

<?php include 'partials/footer.php'; ?>