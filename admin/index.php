<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

include 'partials/header.php';
include 'partials/sidebar.php';

// ตรวจสอบสิทธิ์ admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}
?>

<!-- หัวข้อหน้า -->
<h2>Admin Dashboard</h2>

<!-- เมนูสำหรับ admin -->
<ul>
    <li><a href="asset_add.php">➕ เพิ่มอุปกรณ์</a></li>
    <li><a href="assets_list.php">📋 รายการอุปกรณ์</a></li>
    <li><a href="asset_transfer.php">🔁 โอนย้ายอุปกรณ์</a></li>
    <li><a href="repair_manage.php">🛠️ จัดการแจ้งซ่อม</a></li>
</ul>

<?php include 'partials/footer.php'; ?>
