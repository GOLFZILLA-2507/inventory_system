<?php $role = $_SESSION['role_ivt'] ?? ''; ?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
<div class="container-fluid">

<a class="navbar-brand" href="index.php">🛠 IT Inventory System</a>

<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminSidebar">
<span class="navbar-toggler-icon"></span>
</button>

<div class="collapse navbar-collapse" id="adminSidebar">
<ul class="navbar-nav me-auto">

<!-- Dashboard -->
<li class="nav-item">
<a class="nav-link" href="index.php">🏠 Dashboard</a>
</li>

<?php if($role != 'MD'): ?>

<!-- 🔥 กลุ่ม 1: เพิ่มข้อมูล -->
<li class="nav-item dropdown">
<a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
➕ เพิ่มข้อมูล
</a>
<ul class="dropdown-menu">

<li><a class="dropdown-item" href="asset_add.php">💻 เพิ่มอุปกรณ์</a></li>
<li><a class="dropdown-item" href="admin_add_project.php">🏗 เพิ่มโครงการ</a></li>
<li><a class="dropdown-item" href="import_assets.php">📥 Import อุปกรณ์</a></li>

</ul>
</li>

<!-- 🔥 กลุ่ม 2: จัดการโครงการ -->
<li class="nav-item dropdown">
<a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
🏢 จัดการโครงการ
</a>

<ul class="dropdown-menu">
<li><a class="dropdown-item" href="admin_assign_userproject.php">🔄 กำหนดพนักงานให้กับโครงการ</a></li>
<li><a class="dropdown-item" href="admin_rental_price_manage.php">💰 จัดการราคาเช่า</a></li>
</ul>
</li>

<!-- 🔥 กลุ่ม 2: จัดการโครงการ -->
<li class="nav-item dropdown">
<a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
🏢 จัดการอุปกรณ์
</a>
<ul class="dropdown-menu">

<li><a class="dropdown-item" href="asset_assign.php">💻 จัดการอุปกรณ์ใช้หลัก</a></li>
<li><a class="dropdown-item" href="asset_shared_add.php">📡 เพิ่มอุปกรณ์ใช้ร่วม</a></li>
<li><a class="dropdown-item" href="asset_assign_no_code.php">🆕 เพิ่มอุปกรณ์หลัก (ไม่มีรหัส)</a></li>
<li><a class="dropdown-item" href="asset_assign_shared.php">📡 จัดการอุปกรณ์ใช้ร่วม (ไม่มีรหัส)</a></li>

</ul>
</li>

<!-- 🔥 กลุ่ม 3: ส่งอุปกรณ์ -->
<li class="nav-item dropdown">
<a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
🚚 ส่งอุปกรณ์
</a>
<ul class="dropdown-menu">

<li><a class="dropdown-item" href="transfer_s_project.php">📤 ส่งมอบอุปกรณ์</a></li>
<li><a class="dropdown-item" href="transfer_s_project_list.php">📦 รายการส่งอุปกรณ์</a></li>

</ul>
</li>


<!-- 🔥 กลุ่ม 3: ส่งอุปกรณ์ -->
<li class="nav-item dropdown">
<a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
🆕 ค้นหา
</a>
<ul class="dropdown-menu">
<li><a class="dropdown-item" href="asset_check.php">📦 ค้นหารหัสผู้ใช้</a></li>

</ul>
</li>

<?php endif; ?>

</ul>

<!-- user -->
<span class="navbar-text text-white">
<?= htmlspecialchars($_SESSION['fullname']) ?> |
<a href="../" class="text-white text-decoration-underline">ออกจากระบบ</a>
</span>

</div>
</div>
</nav>

<div class="container-fluid mt-4">