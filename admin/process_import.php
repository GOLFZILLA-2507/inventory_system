<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

if (!isset($_FILES['csv_file'])) {
    die("ไม่พบไฟล์");
}

$file = $_FILES['csv_file']['tmp_name'];

$handle = fopen($file, "r");

// ข้าม header แถวแรก
fgetcsv($handle);

$count = 0;

while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {

    $EmployeeID = trim($data[0]);
    $fullname   = trim($data[1]);
    $position   = trim($data[2]);
    $department = trim($data[3]);
    $site       = trim($data[4]);

    // เช็คซ้ำ
    $check = $conn->prepare("SELECT id FROM Employee WHERE EmployeeID = ?");
    $check->execute([$EmployeeID]);

    if ($check->rowCount() == 0) {

     $tempPassword = ''; // ใส่ค่าว่างแทน NULL

        $stmt = $conn->prepare("
            INSERT INTO Employee
            (EmployeeID, password, fullname, position, department, site, role, active)
            VALUES (?, ?, ?, ?, ?, ?, 'user', 1)
        ");

        $stmt->execute([
            $EmployeeID,
            $tempPassword,
            $fullname,
            $position,
            $department,
            $site
        ]);

        $count++;
    }
}

fclose($handle);

echo "<h3>Import สำเร็จ $count รายการ</h3>";
echo "<a href='import_employee.php'>กลับ</a>";