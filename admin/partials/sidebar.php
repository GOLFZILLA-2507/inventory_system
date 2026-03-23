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

                <li class="nav-item">
                    <a class="nav-link" href="index.php">🏠 Dashboard</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#">➕ เพิ่มอุปกรณ์</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="service_life_view.php">📋 รายการอุปกรณ์</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="admin_transfer_create.php">ส่งมอบอุปกรณ์</a>
                </li>

                <!-- 🔹 แยกเมนู Import ออกเป็น 2 หัวข้อ -->

                <li class="nav-item">
                    <a class="nav-link" href="import_employee.php">
                        👨 Import พนักงาน
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="import_assets.php">
                        💻 Import อุปกรณ์
                    </a>
                </li>

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

<div class="container mt-4">