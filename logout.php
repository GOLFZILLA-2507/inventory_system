<?php
session_start(); // เริ่มต้น session
session_unset(); // ล้างข้อมูลทั้งหมดใน session
session_destroy(); // ทำลาย session
header("Location: login.php"); // เปลี่ยนเส้นทางไปยังหน้าเข้าสู่ระบบ
exit();
?>
