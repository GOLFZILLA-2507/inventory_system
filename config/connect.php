<?php
$serverName = "WEBVSP\\SQLEXPRESS,51433"; // กำหนด IP และพอร์ต (เซิร์ฟเวอร์\อินสแตนซ์,พอร์ต)
$database = "VsathapatV2"; // ชื่อฐานข้อมูล
$username = "sa"; // ชื่อผู้ใช้
$password = "IT@web117"; // รหัสผ่าน
try {
    $conn = new PDO("sqlsrv:Server=$serverName;Database=$database", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::SQLSRV_ATTR_QUERY_TIMEOUT, 300);
   //echo "เชื่อมต่อแล้ว";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), '08001') !== false) {
        echo "ข้อผิดพลาด: ไม่สามารถเชื่อมต่อกับ SQL Server ตรวจสอบพอร์ตหรือ Firewall.";
    } else {
        echo "ข้อผิดพลาด : " . $e->getMessage();
    }
}
?>