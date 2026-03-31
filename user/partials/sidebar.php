<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container-fluid">

<?php
/* =====================================================
   ตรวจสอบ role ของ user ที่ login
   ถ้าไม่มี role_ivt ให้กำหนดเป็น user
===================================================== */
$role = $_SESSION['role_ivt'] ?? 'user';
?>

        <!-- =====================================================
             LOGO ระบบ
        ====================================================== -->
        <a class="navbar-brand" href="asset_shared_view.php">
            🏢 Inventory System
        </a>

        <!-- =====================================================
             ปุ่มสำหรับมือถือ
        ====================================================== -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#userSidebar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- =====================================================
             เมนูหลักของระบบ
        ====================================================== -->
        <div class="collapse navbar-collapse" id="userSidebar">

            <ul class="navbar-nav me-auto">

                <!-- =====================================================
                     หน้าแรก (ทุก role เห็น)
                ====================================================== -->
                <li class="nav-item">
                    <a class="nav-link" href="asset_shared_view.php">
                        🏠 หน้าหลัก
                    </a>
                </li>

<?php
/* =====================================================
   ถ้าเป็น HR ให้เห็นเมนูระบบทั้งหมด
===================================================== */
if($role == 'hr'):
?>

                <!-- =====================================================
                     ระบบอุปกรณ์
                ====================================================== -->
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
                            <a class="dropdown-item" href="asset_available.php">
                                📡 อุปกรณ์ที่ยังไม่มีผู้ใช้งาน
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item" href="asset_shared_add.php">
                                ➕ เพิ่มอุปกรณ์ใช้ร่วม
                            </a>
                        </li>

                         <li>
                            <a class="dropdown-item" href="asset_assign_no_code.php">
                                ➕ เพิ่มอุปกรณ์ (ไม่มีรหัส อุปกรณ์หลัก)
                            </a>    
                        </li>
                        <li>
                            <a class="dropdown-item" href="asset_assign_shared.php">
                                ➕ เพิ่มอุปกรณ์ (ไม่มีรหัส อุปกรณ์ใช้ร่วม)
                            </a>
                        </li>

                    </ul>

                </li>


                <!-- =====================================================
                     ระบบงานซ่อม
                ====================================================== -->
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


                <!-- =====================================================
                     ระบบโอนย้าย / ส่งมอบ
                ====================================================== -->
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
                                ✔️ ตรวจรับอุปกรณ์
                            </a>
                        </li>

                    </ul>

                </li>

<?php
/* =====================================================
   ปิดเงื่อนไข HR
===================================================== */
endif;
?>


                <!-- =====================================================
                     รายงาน (ทุก role เห็น)
                ====================================================== -->
                <li class="nav-item dropdown">

                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        📊 รายงาน
                    </a>

                    <ul class="dropdown-menu">

                        <li>
                            <a class="dropdown-item" href="asset_available.php">
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


            <!-- =====================================================
                 แสดงข้อมูลผู้ใช้ที่ login
            ====================================================== -->
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