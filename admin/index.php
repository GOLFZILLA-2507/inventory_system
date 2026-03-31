<?php $role = $_SESSION['role_ivt'] ?? ''; // กำหนดค่าเริ่มต้นเป็น 'user' หากไม่มี session ?>
<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* =====================================================
🔥 ดึงข้อมูล Dashboard
===================================================== */

// 🔥 รออนุมัติ
$pending = $conn->query("
SELECT COUNT(*) 
FROM IT_AssetTransfer_Headers
WHERE admin_status = 'รออนุมัติ'
AND (receive_status IS NULL OR receive_status != 'ยกเลิก')
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
SELECT COUNT(DISTINCT sent_transfer)
FROM IT_AssetTransfer_Headers
WHERE sent_transfer IS NOT NULL
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
.card-gray{background:linear-gradient(135deg,#0dcaf0,#364fc7);}

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

<?php if($role == 'admin'): ?>

<!-- ================= ADMIN เห็นทั้งหมด ================= -->

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
<h5>🛠 รายการแจ้งซ่อม</h5>
<h2><?= $repair ?></h2>
<a href="repair_manage.php" class="btn btn-light btn-sm">ดูรายการ</a>
</div>
</div>

<!-- 🔥 ส่งแล้ว -->
<div class="col-md-4">
<div class="dashboard-card card-info">
<h5>📦 รายการส่งมอบและโอนย้ายทั้งหมด</h5>
<h2><?= $sent ?></h2>
<a href="admin_transfer_sent.php" class="btn btn-light btn-sm">ดูรายการ</a>
</div>
</div>

<!-- 🔥 ทั้งหมด -->
<div class="col-md-4">
<div class="dashboard-card card-gray">
<h5>💻 อุปกรณ์ทั้งหมด</h5>
<h2><?= $total ?></h2>
<a href="asset_all.php" class="btn btn-light btn-sm">ดูทั้งหมด</a>
</div>
</div>

<?php else: ?>

<!-- ================= MD เห็นเฉพาะนี้ ================= -->

<!-- 🔥 ซ่อม -->
<div class="col-md-4">
<div class="dashboard-card card-soft">
<h5>🛠 รายการแจ้งซ่อม</h5>
<h2><?= $repair ?></h2>
<a href="repair_manage.php" class="btn btn-light btn-sm">ดูรายการ</a>
</div>
</div>

<!-- 🔥 โอนย้าย
<div class="col-md-4">
<div class="dashboard-card card-info">
<h5>📦 รายการส่งมอบและโอนย้ายทั้งหมด</h5>
<h2><?= $sent ?></h2>
<a href="admin_transfer_sent.php" class="btn btn-light btn-sm">ดูรายการ</a>
</div>
</div> -->

<!-- 🔥 อุปกรณ์ -->
<div class="col-md-4">
<div class="dashboard-card card-gray">
<h5>💻 อุปกรณ์ทั้งหมด</h5>
<h2><?= $total ?></h2>
<a href="asset_all.php" class="btn btn-light btn-sm">ดูทั้งหมด</a>
</div>
</div>

<?php endif; ?>

<!-- =====================================================
🔥 🔥 NEW: Dashboard ค่าเช่า (ADMIN + MD เห็น)
===================================================== -->
<div class="col-md-4">
<div class="dashboard-card card-indigo">
<h5>💰 ค่าเช่าอุปกรณ์</h5>
<h2>📊</h2>
<a href="admin_rental_dashboard.php" class="btn btn-light btn-sm">เข้าใช้งาน</a>
</div>
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
        labels: ['รออนุมัติ','ซ่อม','ไม่มีผู้ใช้','โอนย้ายทั้งหมด'],
        datasets: [{
            label: 'จำนวนรายการ',
            data: [<?= $pending ?>,<?= $repair ?>,<?= $sent ?>,<?= $waiting ?>],

            /* 🔥 สีแท่ง */
            backgroundColor: [
                'rgba(163, 183, 238, 0.8)',   // เหลือง
                'rgba(68, 211, 247, 0.8)',  // ฟ้า
                'rgba(155, 223, 146, 0.8)', // เทา
                'rgba(100, 211, 159, 0.8)',   // เขียว
                'rgba(209, 107, 170, 0.8)'    // แดง
            ],

            borderColor: [
                '#2503b9',
                '#0dcaf0',
                '#6c757d',
                '#198754',
                '#dc3545'
            ],

            borderWidth: 1,
            borderRadius: 8,   // 🔥 มุมโค้ง
            barThickness: 200   // 🔥 ความหนา
        }]
    },

    options: {
        responsive: true,
        plugins: {
            legend: {
                labels: {
                    font: {
                        size: 14
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
</script>