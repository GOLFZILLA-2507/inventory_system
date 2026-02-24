<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container-fluid">

        <!-- LOGO -->
        <a class="navbar-brand" href="index.php">
            🏢 Inventory System
        </a>

        <!-- MOBILE BTN -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#userSidebar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- MENU -->
        <div class="collapse navbar-collapse" id="userSidebar">

            <ul class="navbar-nav me-auto">

                <!-- ================= HOME ================= -->
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        🏠 หน้าหลัก
                    </a>
                </li>

                <!-- ================= ASSET ================= -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        💻 ระบบอุปกรณ์
                    </a>
                    <ul class="dropdown-menu">

                        <li>
                            <a class="dropdown-item" href="asset_assign.php">
                                🖥️ จัดการอุปกรณ์พนักงาน
                            </a>
                        </li>
                         <li>
                            <a class="dropdown-item" href="asset_assign.php">
                                🖥️ จัดการอุปกรณ์พนักงานtest
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item" href="asset_shared_view.php">
                                📡 อุปกรณ์ทั้งหมดในโครงการ
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item" href="asset_shared_add.php">
                                ➕ เพิ่มอุปกรณ์ใช้ร่วม
                            </a>
                        </li>

                    </ul>
                </li>

                <!-- ================= REPAIR ================= -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        🛠 ระบบงานซ่อม
                    </a>
                    <ul class="dropdown-menu">

                        <li>
                            <a class="dropdown-item" href="repair_request.php">
                                ➕ แจ้งซ่อม
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item" href="repair_status.php">
                                📋 ติดตามสถานะซ่อม
                            </a>
                        </li>

                    </ul>
                </li>

                <!-- ================= TRANSFER ================= -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        🚚 ระบบโอนย้าย / ส่งมอบ
                    </a>

                    <ul class="dropdown-menu">

                        <li>
                            <a class="dropdown-item" href="transfer_create.php">
                                ➕ สร้างรายการส่ง
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item" href="transfer_list.php">
                                📦 รายการที่ฉันส่ง
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item" href="transfer_receive.php">
                                📥 รายการรอรับ
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item" href="transfer_receive.php">
                                ✔️ ตรวจรับอุปกรณ์
                            </a>
                        </li>

                    </ul>
                </li>

                <!-- ================= REPORT ================= -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        📊 รายงาน
                    </a>
                    <ul class="dropdown-menu">

                        <li>
                            <a class="dropdown-item" href="asset_shared_view.php">
                                📡 รายงานอุปกรณ์โครงการ
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item" href="transfer_list.php">
                                🚚 รายงานโอนย้าย
                            </a>
                        </li>

                    </ul>
                </li>

            </ul>

            <!-- ================= USER INFO ================= -->
            <span class="navbar-text text-white">

                👤 <?= htmlspecialchars($_SESSION['fullname']) ?>

                <span class="mx-2">|</span>

                🏢 <?= htmlspecialchars($_SESSION['site']) ?>

                <span class="mx-2">|</span>

                <a href="../" class="text-white text-decoration-underline">
                    ออกจากระบบ
                </a>

            </span>

        </div>
    </div>
</nav>