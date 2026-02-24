<?php
session_start();
require_once '../config/connect.php';
include 'partials/header.php';
include 'partials/sidebar.php';
?>

<h2>แจ้งซ่อม</h2>

<form method="post">

<!-- เลือกอุปกรณ์ของตัวเอง -->
<select name="asset_id">
<?php
// ดึงอุปกรณ์ของ user
$q = sqlsrv_query($conn, "
    SELECT asset_id, serial_no 
    FROM IT_Assets 
    WHERE employee_id = ?
", [$emp]);

// วนลูปแสดงอุปกรณ์
while ($r = sqlsrv_fetch_array($q)) {
    echo "<option value='{$r['asset_id']}'>
            {$r['serial_no']}
          </option>";
}
?>
</select><br>

<!-- ช่องกรอกอาการเสีย -->
<textarea name="problem" placeholder="อาการเสีย"></textarea><br>

<!-- ปุ่มแจ้งซ่อม -->
<button name="save">แจ้งซ่อม</button>
</form>

<?php
// ถ้ากดปุ่มแจ้งซ่อม
if (isset($_POST['save'])) {

    // บันทึกข้อมูลแจ้งซ่อม
    sqlsrv_query($conn, "
        INSERT INTO IT_RepairHistory
        (asset_id, problem, status)
        VALUES (?,?,?)
    ", [
        $_POST['asset_id'],     // อุปกรณ์
        $_POST['problem'],      // ปัญหา
        'แจ้งซ่อม'              // สถานะเริ่มต้น
    ]);

    // แจ้งผลลัพธ์
    echo "✅ แจ้งซ่อมเรียบร้อย";
}
?>
