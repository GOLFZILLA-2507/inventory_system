<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$role = $_SESSION['role_ivt'] ?? '';
$site = $_SESSION['site'];

/* =====================================================
🔥 Dashboard
===================================================== */

// 🔥 รออนุมัติ
$pending = $conn->query("
SELECT COUNT(*) 
FROM IT_AssetTransfer_Headers
WHERE admin_status = 'รออนุมัติ'
AND (receive_status IS NULL OR receive_status != 'ยกเลิก')
")->fetchColumn();

// 🔥 แจ้งซ่อม
$repair = $conn->query("
SELECT COUNT(*) FROM IT_RepairTickets WHERE status = 'รอรับเรื่อง'
")->fetchColumn();

// 🔥 ❗ ไม่มีผู้ใช้ (เปลี่ยนฐานเป็น IT_user_devices)
$noUser = $conn->query("
SELECT COUNT(*) 
FROM IT_assets a
LEFT JOIN IT_user_devices d 
ON a.no_pc = d.device_code
WHERE d.device_code IS NULL
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

$receiveMe = $conn->prepare("
SELECT COUNT(DISTINCT sent_transfer)
FROM IT_AssetTransfer_Headers
WHERE to_site = ?
AND receive_status = 'รอตรวจรับ'
");
$receiveMe->execute([$site]);
$receiveMe = $receiveMe->fetchColumn();

// 🔥 ทั้งหมด
$total = $conn->query("
SELECT COUNT(*) FROM IT_assets
")->fetchColumn();

/* ================= 🔥 ค่าเช่าแยกโครงการ ================= */

// 🔥 กำหนดค่าเช่าต่อวัน (แก้ได้)
$price_per_day = 50;

$stmt = $conn->query("
SELECT 
    user_project,
    COUNT(*) as total_items,
    SUM(
        DATEDIFF(DAY, start_date, ISNULL(end_date, GETDATE()))
    ) as total_days
FROM IT_user_history
WHERE user_project IS NOT NULL
GROUP BY user_project
");

$projects = [];
$amounts = [];
$counts = [];

while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
    $projects[] = $row['user_project'];
    $counts[]   = $row['total_items'];

    // 🔥 คำนวณเงิน
    $amounts[]  = $row['total_days'] * $price_per_day;
}

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

.box{
    background:#fff;
    border-radius:16px;
    padding:20px;
    box-shadow:0 10px 30px rgba(13,110,253,0.1);
    transition:0.3s;
}
.box:hover{
    transform:translateY(-3px);
}

.title{
    font-weight:600;
    font-size:16px;
    margin-bottom:10px;
    color:#0d6efd;
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

<!-- 🔥 รับเข้า (ส่งมาหาฉัน) -->
<div class="col-md-4">
<div class="dashboard-card card-deep <?= $receiveMe>0?'blink':'' ?>">
<h5>📥 ตรวจรับอุปกรณ์</h5>
<h2><?= $receiveMe ?></h2>
<a href="transfer_receive_check.php" class="btn btn-light btn-sm">ดูรายการ</a>
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

<!-- 🔥 อุปกรณ์ทั้งหมด -->
<div class="col-md-4">
<div class="dashboard-card card-gray">
<h5>💻 อุปกรณ์ทั้งหมด</h5>
<h2><?= $total ?></h2>
<a href="asset_all.php" class="btn btn-light btn-sm">ดูทั้งหมด</a>
</div>
</div>

<div class="col-md-4">
<div class="dashboard-card card-indigo">
<h5>🚀 Device  Dashboard</h5>
<h2>📥</h2>
<a href="asset_device_PReview.php" class="btn btn-light btn-sm">ดูรายละเอียด</a>
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
<a href="admin_rental_dashboard.php" class="btn btn-light btn-sm">ดูรายละเอียด</a>
</div>
</div>

</div>

</div>

<hr>

<!-- ================= CHART ================= -->
<div class="row mt-4">

<!-- 🔥 BAR -->
<div class="col-md-8">
<div class="box">
<div class="title">📊 ภาพรวมระบบ</div>
<canvas id="barChart" height="90"></canvas>
</div>
</div>

<!-- 🔥 DONUT -->
<div class="col-md-4">
<div class="box text-center">

<div class="title">💰 ค่าเช่าแยกโครงการ</div>

<canvas id="donutChart" style="max-height:260px"></canvas>

<!-- 🔥 จำนวนโครงการ -->
<h5 class="mt-3 text-primary">
<?= count($projects) ?> โครงการ
</h5>

<small class="text-muted">คิดเป็นค่าเช่าทั้งสิ้น <?= array_sum($amounts) ?> บาท</small>

</div>
</div>

</div>
</div>

</div>

<?php include 'partials/footer.php'; ?>

<!-- 🔥 Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
/* ================= 🔥 BAR GRADIENT ================= */
const ctx = document.getElementById('barChart').getContext('2d');

const gradient = ctx.createLinearGradient(0,0,0,400);
gradient.addColorStop(0,'#0d6efd');
gradient.addColorStop(1,'#74c0fc');

new Chart(ctx,{
type:'bar',
data:{
labels:['รออนุมัติ','ซ่อม','โอนย้าย','รอตรวจรับ'],
datasets:[{
label:'จำนวน',
data:[<?= $pending ?>,<?= $repair ?>,<?= $sent ?>,<?= $waiting ?>],
backgroundColor: gradient,
borderRadius:12,
barThickness:40
}]
},
options:{
responsive:true,
plugins:{
legend:{display:false}
},
scales:{
x:{
grid:{display:false}
},
y:{
beginAtZero:true,
grid:{
color:'rgba(0,0,0,0.05)'
}
}
}
}
});


/* ================= 🔥 DONUT ================= */
const projectLabels = <?= json_encode($projects) ?>;
const projectAmounts = <?= json_encode($amounts) ?>;
const projectCounts = <?= json_encode($counts) ?>;

/* 🔥 สร้างสี auto */
function generateColors(num){
    let colors = [];
    for(let i=0;i<num;i++){
        let hue = i * (360 / num);
        colors.push(`hsl(${hue},70%,60%)`);
    }
    return colors;
}

const colors = generateColors(projectLabels.length);

new Chart(document.getElementById('donutChart'),{
type:'doughnut',
data:{
labels: projectLabels,
datasets:[{
data: projectAmounts,
backgroundColor: colors,
borderWidth:0
}]
},
options:{
cutout:'65%',
plugins:{
legend:{
position:'bottom',
labels:{
usePointStyle:true
}
},
tooltip:{
callbacks:{
label: function(context){

let index = context.dataIndex;

let project = projectLabels[index];
let amount = projectAmounts[index];
let count  = projectCounts[index];

return [
'โครงการ: ' + project,
'จำนวน: ' + count + ' รายการ',
'ค่าเช่า: ' + amount.toLocaleString() + ' บาท'
];

}
}
}
}
}
});
</script>