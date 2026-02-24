<?php
session_start();

$timeout_duration = 1800; // 30 นาที
// ตรวจสอบว่ามีการเข้าสู่ระบบหรือไม่
if (!isset($_SESSION['EmployeeID'])) {
    header("Location: ../index.php");
    exit();
}

// ตรวจสอบ session timeout
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: ../index.php?timeout=1");
    exit();
}

// อัปเดตเวลาการใช้งานล่าสุด
$_SESSION['LAST_ACTIVITY'] = time();
?>