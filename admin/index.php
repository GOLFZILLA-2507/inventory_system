<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* =====================================================
🔥 ดึงข้อมูล Dashboard
===================================================== */

// 🔥 รออนุมัติ
$pending = $conn->query("
SELECT COUNT(*) FROM IT_AssetTransfer_Headers
WHERE admin_status = 'รออนุมัติ'
")->fetchColumn();

// 🔥 แจ้งซ่อม (สมมุติ table repair)
$repair = $conn->query("
SELECT COUNT(*) FROM IT_RepairTickets WHERE status = 'รอรับเรื่อง'
")->fetchColumn();

// 🔥 ไม่มีผู้ใช้
$noUser = $conn->query("
SELECT COUNT(*) FROM IT_user_information
WHERE user_employee IS NULL
")->fetchColumn();

// 🔥 ส่งแล้ว
$sent = $conn->query("
SELECT COUNT(*) FROM IT_AssetTransfer_Headers
WHERE admin_status = 'อนุมัติ'
")->fetchColumn();

// 🔥 รอตรวจรับ
$waiting = $conn->query("
SELECT COUNT(*) FROM IT_AssetTransfer_Headers
WHERE receive_status = 'รอตรวจรับ'
")->fetchColumn();

// 🔥 ทั้งหมด
$total = $conn->query("
SELECT COUNT(*) FROM IT_assets
")->fetchColumn();

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<style>

/* 🔥 THEME ฟ้าอมน้ำเงิน */
.dashboard-card{
    color:white;
    border-radius:12px;
    padding:20px;
    transition:0.3s;
}
.dashboard-card:hover{
    transform:scale(1.05);
}

/* 🔥 สี */
.card-main{background:linear-gradient(135deg,#0d6efd,#3da5ff);}
.card-soft{background:linear-gradient(135deg,#4dabf7,#74c0fc);}
.card-deep{background:linear-gradient(135deg,#0b5ed7,#1c7ed6);}
.card-info{background:linear-gradient(135deg,#3bc9db,#66d9e8);}
.card-indigo{background:linear-gradient(135deg,#364fc7,#5c7cfa);}
.card-gray{background:linear-gradient(135deg,#495057,#868e96);}

/* 🔴 แจ้งเตือน
.blink{
    animation: blink 1s infinite;
} */
@keyframes blink{
    0%{opacity:1;}
    50%{opacity:0.3;}
    100%{opacity:1;}
}

</style>

<div class="container mt-4">

<h4 class="mb-4">📊 Admin Dashboard</h4>

<div class="row g-3">

<!-- 🔥 อนุมัติ -->
<div class="col-md-4">
<div class="dashboard-card card-main <?= $pending>0?'blink':'' ?>">
<h5>📋 โอนย้ายจากโครงการ รออนุมัติ</h5>
<h2><?= $pending ?></h2>
<a href="approve_transfer.php" class="btn btn-light btn-sm">ดูรายการ</a>
</div>
</div>

<!-- 🔥 ซ่อม -->
<div class="col-md-4">
<div class="dashboard-card card-soft">
<h5>🛠 แจ้งซ่อม</h5>
<h2><?= $repair ?></h2>
<a href="repair_manage.php" class="btn btn-light btn-sm">ดูรายการ</a>
</div>
</div>

<!-- 🔥 ไม่มีผู้ใช้ 
<div class="col-md-4">
<div class="dashboard-card card-deep">
<h5>👤 ไม่มีผู้ใช้</h5>
<h2><?= $noUser ?></h2>
<a href="asset_no_user.php" class="btn btn-light btn-sm">จัดการ</a>
</div>
</div>-->

<!-- 🔥 ส่งแล้ว -->
<div class="col-md-4">
<div class="dashboard-card card-info">
<h5>📦 รายการส่งของฉัน</h5>
<h2><?= $sent ?></h2>
<a href="admin_transfer_sent.php" class="btn btn-light btn-sm">ดู</a>
</div>
</div>

<!-- 🔥 รอตรวจรับ 
<div class="col-md-4">
<div class="dashboard-card card-indigo">
<h5>⏳ รอตรวจรับ</h5>
<h2><?= $waiting ?></h2>
<a href="receive_list.php" class="btn btn-light btn-sm">ตรวจรับ</a>
</div>
</div>-->

<!-- 🔥 ทั้งหมด -->
<div class="col-md-4">
<div class="dashboard-card card-gray">
<h5>💻 อุปกรณ์ทั้งหมด</h5>
<h2><?= $total ?></h2>
<a href="asset_all.php" class="btn btn-light btn-sm">ดูทั้งหมด</a>
</div>
</div>

</div>

<hr>

<!-- =====================================================
📊 กราฟ
===================================================== -->
<canvas id="chart" height="100"></canvas>

</div>

<?php include 'partials/footer.php'; ?>

<!-- 🔥 Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const ctx = document.getElementById('chart');

new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['รออนุมัติ','ซ่อม','ไม่มีผู้ใช้','ส่งแล้ว','รอตรวจรับ'],
        datasets: [{
            label: 'จำนวน',
            data: [<?= $pending ?>,<?= $repair ?>,<?= $noUser ?>,<?= $sent ?>,<?= $waiting ?>]
        }]
    }
});
</script>