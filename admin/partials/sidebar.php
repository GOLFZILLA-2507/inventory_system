<?php $role = $_SESSION['role_ivt'] ?? ''; // กำหนดค่าเริ่มต้นเป็น 'user' หากไม่มี session ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">

        <!-- โลโก้ -->
        <a class="navbar-brand" href="index.php">🛠 IT Inventory System</a>

        <!-- ปุ่ม mobile -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminSidebar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- เมนู -->
        <div class="collapse navbar-collapse" id="adminSidebar">
            <ul class="navbar-nav me-auto">

                <!-- Dashboard (ทุก role เห็น) -->
                <li class="nav-item">
                    <a class="nav-link" href="index.php">🏠 Dashboard</a>
                </li>

                <?php if($role != 'MD'): ?>
                <!-- 🔴 admin เท่านั้น -->
                <li class="nav-item">
                    <a class="nav-link" href="asset_add.php">➕ เพิ่มอุปกรณ์</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="admin_add_project.php">➕ เพิ่มโครงการ</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="admin_assign_userproject.php">🔄 กำหนดโครงการ</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="admin_rental_price_manage.php">💰 จัดการราคาเช่า</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="transfer_s_project.php">🚚 ส่งมอบอุปกรณ์</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="import_assets.php">
                        💻 Import อุปกรณ์
                    </a>
                </li>
                <?php endif; ?>

            </ul>

            <!-- ชื่อผู้ใช้ -->
            <span class="navbar-text text-white">
                <?= htmlspecialchars($_SESSION['fullname']) ?>
                |
                <a href="../" class="text-white text-decoration-underline">ออกจากระบบ</a>
            </span>
        </div>
    </div>
</nav>

<div class="container-fluid mt-4">