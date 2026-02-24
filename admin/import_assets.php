<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

// ป้องกัน timeout / memory เต็ม
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="card shadow-lg border-0">
    <div class="card-header bg-primary text-white">
        <h4 class="mb-0">📥 Import อุปกรณ์ (CSV)</h4>
    </div>

    <div class="card-body">

        <!-- ฟอร์ม upload -->
        <form method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">เลือกไฟล์ CSV</label>
                <input type="file" name="csv_file" accept=".csv" required class="form-control">
            </div>

            <button type="submit" name="import" class="btn btn-success">
                🚀 เริ่ม Import
            </button>
        </form>

        <hr>

<?php
// =============================
// เริ่ม Import
// =============================
if (isset($_POST['import'])) {

    if ($_FILES['csv_file']['error'] != 0) {
        echo "<div class='alert alert-danger'>❌ Upload file ไม่สำเร็จ</div>";
        exit;
    }

    $file = fopen($_FILES['csv_file']['tmp_name'], 'r');

    if (!$file) {
        echo "<div class='alert alert-danger'>❌ ไม่สามารถเปิดไฟล์ได้</div>";
        exit;
    }

    // นับจำนวน
    $success = 0;
    $error   = 0;
    $rowNumber = 0;

    // 🔥 เริ่ม Transaction (เร็วขึ้นมาก)
    $conn->beginTransaction();

    while (($row = fgetcsv($file, 2000, ",")) !== FALSE) {

        $rowNumber++;

        // ข้าม header แถวแรก
        if ($rowNumber == 1) continue;

        // ป้องกัน column ไม่ครบ
        if (count($row) < 4) {
            $error++;
            continue;
        }

        // trim ทุกช่อง
        $new_code            = trim($row[0] ?? '');
        $asset_code          = trim($row[1] ?? '');
        $asset_name          = trim($row[2] ?? '');
        $category            = trim($row[3] ?? '');
        $no_projects         = trim($row[4] ?? '');
        $project_name        = trim($row[5] ?? '');
        $use_employee_name   = trim($row[6] ?? '');

        try {

            $stmt = $conn->prepare("
                INSERT INTO IT_Assets
                (new_code, asset_code, asset_name, category,
                 no_projects, project_name, use_employee_name)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $new_code,
                $asset_code,
                $asset_name,
                $category,
                $no_projects ?: null,
                $project_name ?: null,
                $use_employee_name ?: null
            ]);

            $success++;

        } catch (Exception $e) {
            $error++;

            // แสดง row ที่ error
            echo "<div style='color:red;font-size:13px'>
                    ❌ Row $rowNumber ผิดพลาด : " . htmlspecialchars($e->getMessage()) . "
                  </div>";
        }
    }

    // commit ทั้งก้อน
    $conn->commit();

    fclose($file);

    echo "<hr>";
    echo "<div class='alert alert-success'>
            ✅ Import สำเร็จ : $success รายการ <br>
            ❌ ผิดพลาด : $error รายการ
          </div>";
}
?>

    </div>
</div>

<?php include 'partials/footer.php'; ?>