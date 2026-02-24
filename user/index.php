<?php
// เรียกไฟล์ตรวจสอบ login + session + timeout
include('../config/checklogin.php');
// เรียกไฟล์เชื่อมต่อฐานข้อมูล (PDO)
include('../config/connect.php');

include 'partials/header.php';
include 'partials/sidebar.php';

// ตรวจสอบว่ามี session EmployeeID จริง
if (!isset($_SESSION['EmployeeID'])) {
    die('ไม่พบข้อมูลผู้ใช้งาน กรุณา login ใหม่');
}

// เก็บ EmployeeID จาก session
$employeeID = $_SESSION['EmployeeID'];
?>



<?php include 'partials/footer.php'; ?>