<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container-fluid">

<?php
/* =====================================================
   ตรวจสอบ role ของ user
===================================================== */
$role = $_SESSION['role_ivt'] ?? 'user';
?>

        <!-- LOGO -->
        <a class="navbar-brand" href="asset_shared_view.php">
            🏢 Inventory System
        </a>

        <!-- MOBILE BUTTON -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#userSidebar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- MENU -->
        <div class="collapse navbar-collapse" id="userSidebar">

            <!-- LEFT MENU -->
            <ul class="navbar-nav me-auto">

                <!-- หน้าหลัก -->
                <li class="nav-item">
                    <a class="nav-link" href="asset_shared_view.php">
                        🏠 หน้าหลัก
                    </a>
                </li>

<?php if($role == 'hr'): ?>

                <!-- ระบบอุปกรณ์ -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                        💻 ระบบอุปกรณ์
                    </a>
                    <ul class="dropdown-menu">

                        <li><a class="dropdown-item" href="asset_assign.php">🖥️ จัดการอุปกรณ์พนักงาน</a></li>
                        <li><a class="dropdown-item" href="asset_available.php">📡 อุปกรณ์ที่ยังไม่มีผู้ใช้งาน</a></li>
                        <li><a class="dropdown-item" href="asset_shared_add.php">➕ เพิ่มอุปกรณ์ใช้ร่วม</a></li>
                        <li><a class="dropdown-item" href="asset_assign_no_code.php">➕ เพิ่มอุปกรณ์ (ไม่มีรหัส อุปกรณ์หลัก)</a></li>
                        <li><a class="dropdown-item" href="asset_assign_shared.php">➕ เพิ่มอุปกรณ์ (ไม่มีรหัส อุปกรณ์ใช้ร่วม)</a></li>

                    </ul>
                </li>

                <!-- ระบบซ่อม -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                        🛠 ระบบงานซ่อม
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="repair_request.php">➕ แจ้งซ่อม</a></li>
                        <li><a class="dropdown-item" href="repair_status.php">📋 ติดตามสถานะซ่อม</a></li>
                    </ul>
                </li>

                <!-- โอนย้าย -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                        🚚 ระบบโอนย้าย / ส่งมอบ
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="transfer_create.php">➕ สร้างรายการส่ง</a></li>
                        <li><a class="dropdown-item" href="transfer_list.php">📦 รายการที่ฉันส่ง</a></li>
                        <li><a class="dropdown-item" href="transfer_receive.php">✔️ ตรวจรับอุปกรณ์</a></li>
                    </ul>
                </li>

<?php endif; ?>

            </ul>

            <!-- RIGHT MENU 🔥 -->
            <ul class="navbar-nav ms-auto align-items-lg-center">

                <!-- ชื่อ -->
                <li class="nav-item text-white me-3">
                    👤 <?= htmlspecialchars($_SESSION['fullname']) ?>
                </li>
<span class="mx-2">|</span>

                <!-- โครงการ -->
                <li class="nav-item text-white me-3">
                    🏢 <?= htmlspecialchars($_SESSION['site']) ?>
                </li>
<span class="mx-2">|</span>

                <!-- logout -->
                <li class="nav-item">
                    <a href="../" class="nav-link text-white">
                        🚪 ออกจากระบบ
                    </a>
                </li>

            </ul>

        </div>
    </div>
</nav>