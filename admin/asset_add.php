<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';
include 'partials/header.php';
include 'partials/sidebar.php';

// ตรวจสอบสิทธิ์ admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}
?>

<h2>➕ เพิ่มอุปกรณ์ IT</h2>

<form method="post" id="assetForm">

<!-- ===================== -->
<!-- ประเภทอุปกรณ์ -->
<!-- ===================== -->
<label>ประเภทอุปกรณ์</label>
<select name="asset_type" id="asset_type" required onchange="toggleFields()">
    <option value="">-- เลือกประเภท --</option>
    <option value="PC">PC</option>
    <option value="MONITOR">หน้าจอ</option>
    <option value="NOTEBOOK">โน้ตบุ๊ค</option>
    <option value="UPS">UPS</option>
    <option value="PRINTER">Printer</option>
    <option value="CCTV">CCTV</option>
    <option value="PROJECTOR">โปรเจ็คเตอร์</option>
</select>
<br><br>

<!-- ===================== -->
<!-- ผู้ใช้งาน -->
<!-- ===================== -->
<label>ผู้ใช้งาน</label>
<select name="employee_id" required>
<?php
// ดึงรายชื่อพนักงาน
$stmt = $conn->query("SELECT id, fullname, EmployeeID FROM Employee WHERE active = 1");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<option value='{$row['id']}'>
        {$row['fullname']} ({$row['EmployeeID']})
    </option>";
}
?>
</select>
<br><br>

<!-- ===================== -->
<!-- โครงการ -->
<!-- ===================== -->
<label>โครงการ</label>
<select name="project_id" required>
<?php
// ดึงโครงการทั้งหมด
$stmt = $conn->query("SELECT project_id, project_name FROM IT_Projects");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<option value='{$row['project_id']}'>
        {$row['project_name']}
    </option>";
}
?>
</select>
<br><br>

<!-- ===================== -->
<!-- กลุ่มฟอร์ม PC / Notebook -->
<!-- ===================== -->
<div id="pc_fields" style="display:none; border:1px solid #ccc; padding:10px;">
    <h4>ข้อมูลสเปคเครื่อง</h4>

    CPU:
    <input type="text" name="cpu_model" placeholder="เช่น i5-8500"><br>

    RAM (GB):
    <input type="number" name="ram_gb"><br>

    SSD / Storage (GB):
    <input type="number" name="storage_gb"><br>

    การ์ดจอ:
    <input type="text" name="gpu_model" placeholder="เช่น GTX 1660"><br>

    ปีที่เริ่มใช้งาน:
    <input type="number" name="asset_year" placeholder="เช่น 2020"><br>
</div>

<!-- ===================== -->
<!-- กลุ่มฟอร์ม อุปกรณ์ทั่วไป -->
<!-- ===================== -->
<div id="general_fields" style="display:none; border:1px solid #ccc; padding:10px;">
    <h4>ข้อมูลอุปกรณ์</h4>

    ปีที่ซื้อ:
    <input type="number" name="purchase_year" placeholder="เช่น 2021"><br>
</div>

<br>
<button type="submit" name="save">💾 บันทึกอุปกรณ์</button>
</form>

<!-- ===================== -->
<!-- JavaScript ควบคุมฟอร์ม -->
<!-- ===================== -->
<script>
// ฟังก์ชันแสดง/ซ่อนฟอร์ม
function toggleFields() {

    // อ่านค่าประเภทอุปกรณ์
    const type = document.getElementById('asset_type').value;

    // กล่องฟอร์ม
    const pcFields = document.getElementById('pc_fields');
    const generalFields = document.getElementById('general_fields');

    // ซ่อนทั้งหมดก่อน
    pcFields.style.display = 'none';
    generalFields.style.display = 'none';

    // ถ้าเลือก PC หรือ NOTEBOOK
    if (type === 'PC' || type === 'NOTEBOOK') {
        pcFields.style.display = 'block';
    }
    // อุปกรณ์อื่น
    else if (type !== '') {
        generalFields.style.display = 'block';
    }
}
</script>

<?php
// =====================
// บันทึกข้อมูล
// =====================
if (isset($_POST['save'])) {

    // กำหนดค่าเริ่มต้นเป็น NULL
    $cpu = $ram = $storage = $gpu = $asset_year = $purchase_year = null;

    // ถ้าเป็น PC / NOTEBOOK
    if ($_POST['asset_type'] === 'PC' || $_POST['asset_type'] === 'NOTEBOOK') {
        $cpu = $_POST['cpu_model'];
        $ram = $_POST['ram_gb'];
        $storage = $_POST['storage_gb'];
        $gpu = $_POST['gpu_model'];
        $asset_year = $_POST['asset_year'];

        // คำนวณอายุการใช้งาน
        $usage_years = date('Y') - $asset_year;

        // ตัดเกรด
        if ($usage_years <= 3) {
            $grade = 'A';
            $replace = 0;
        } elseif ($usage_years <= 5) {
            $grade = 'B';
            $replace = 0;
        } else {
            $grade = 'C';
            $replace = 1;
        }

    } else {
        // อุปกรณ์อื่น
        $purchase_year = $_POST['purchase_year'];
        $grade = null;
        $replace = 0;
    }

    // SQL เพิ่มข้อมูล
    $sql = "
        INSERT INTO IT_Assets
        (asset_type, employee_id, project_id,
         cpu_model, ram_gb, storage_gb, gpu_model,
         asset_year, purchase_year,
         spec_grade, recommend_replace, status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $_POST['asset_type'],
        $_POST['employee_id'],
        $_POST['project_id'],
        $cpu,
        $ram,
        $storage,
        $gpu,
        $asset_year,
        $purchase_year,
        $grade,
        $replace,
        'ใช้งาน'
    ]);

    echo "<p style='color:green'>✅ เพิ่มอุปกรณ์เรียบร้อย</p>";
}
?>

<?php include 'partials/footer.php'; ?>