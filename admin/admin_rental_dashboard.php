<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* ================= FILTER ================= */
$search  = $_GET['search'] ?? '';
$project = $_GET['project'] ?? '';
$status  = $_GET['status'] ?? '';
$grade   = $_GET['grade'] ?? '';

/* ================= SQL ================= */
$sql = "
SELECT 
    h.user_project,
    h.user_employee,
    h.user_no_pc,
    a.type_equipment,
    DATEDIFF(DAY, h.start_date, ISNULL(h.end_date, GETDATE())) AS total_days,
    a.rental_price,
    a.How_long2,
    a.device_grade,
    DATEDIFF(DAY, h.start_date, ISNULL(h.end_date, GETDATE())) * a.rental_price AS total_price
FROM IT_user_history h
LEFT JOIN IT_assets a ON a.no_pc = h.user_no_pc
WHERE 1=1
";

$params = [];

if($search){
    $sql .= " AND (h.user_no_pc LIKE ? OR a.type_equipment LIKE ?)";
    $params[]="%$search%";
    $params[]="%$search%";
}

if($project){
    $sql .= " AND h.user_project = ?";
    $params[]=$project;
}

if($status == 'open'){
    $sql .= " AND h.end_date IS NULL";
}
if($status == 'close'){
    $sql .= " AND h.end_date IS NOT NULL";
}

if($grade){
    $sql .= " AND a.device_grade = ?";
    $params[]=$grade;
}

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= KPI ================= */
$totalRevenue = 0;
$typeSummary = [];
$typeCount = [];

foreach($data as $d){

    $totalRevenue += $d['total_price'];

    $type = $d['type_equipment'] ?? 'อื่นๆ';

    // 🔥 รวมค่าเช่าตามประเภท
    if(!isset($typeSummary[$type])){
        $typeSummary[$type] = 0;
    }
    $typeSummary[$type] += $d['total_price'];

    // 🔥 นับจำนวนประเภท
    $typeCount[$type] = true;
}

$totalDevice = count($data);
$totalType   = count($typeCount);

/* ================= DONUT ================= */
$gradeCount = ['A'=>0,'B'=>0,'C'=>0];

foreach($data as $d){
    if(isset($gradeCount[$d['device_grade']])){
        $gradeCount[$d['device_grade']]++;
    }
}

$projects = $conn->query("SELECT DISTINCT user_project FROM IT_user_history")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body{
    background:#eef4ff;
    font-family:'Sarabun';
}

/* HEADER */
.topbar{
    background:linear-gradient(135deg,#2196f3,#00bcd4);
    color:white;
    padding:15px;
    display:flex;
    justify-content:space-between;
}

/* KPI */
.kpi{
    border-radius:12px;
    padding:18px;
    color:white;
    font-weight:600;
}
.kpi-blue{ background:linear-gradient(135deg,#3f8efc,#00c6ff); }
.kpi-green{ background:linear-gradient(135deg,#00c853,#69f0ae); }
.kpi-purple{ background:linear-gradient(135deg,#7b1fa2,#ba68c8); }

/* TABLE */
.table-modern thead{
    background:linear-gradient(135deg,#2196f3,#90caf9);
    color:white;
}

/* 🔥 hover ใหม่ (นุ่ม ไม่เวียนหัว) */
.table-modern tbody tr:hover{
    background:#f1f7ff;
}

/* FILTER */
.table-header{
    background:#fff;
    padding:12px;
    border-radius:10px;
    margin-bottom:10px;
}

/* BADGE */
.badge-A{background:#4caf50;}
.badge-B{background:#ff9800;}
.badge-C{background:#f44336;}
</style>
</head>

<body>

<div class="topbar">
<div>📊 Rental Dashboard</div>
<a href="index.php" class="btn btn-light btn-sm">⬅ กลับ</a>
</div>

<div class="container-fluid mt-4">

<!-- KPI -->
<div class="row mb-3">

<div class="col-md-4">
<div class="kpi kpi-blue">
💰 รายได้รวม
<h4><?= number_format($totalRevenue,2) ?></h4>
</div>
</div>

<div class="col-md-4">
<div class="kpi kpi-green">
🖥 จำนวนอุปกรณ์
<h4><?= $totalDevice ?></h4>
</div>
</div>

<div class="col-md-4">
<div class="kpi kpi-purple">
📦 จำนวนประเภทอุปกรณ์
<h4><?= $totalType ?></h4>
</div>
</div>

</div>

<div class="row">

<!-- TABLE -->
<div class="col-md-9">
<div class="card shadow">
<div class="card-body">

<!-- FILTER -->
<div class="table-header">
<form class="row g-2">

<div class="col-md-3">
<input type="text" name="search" class="form-control"
placeholder="ค้นหา..." value="<?= $search ?>">
</div>

<div class="col-md-2">
<select name="project" class="form-control">
<option value="">โครงการ</option>
<?php foreach($projects as $p): ?>
<option value="<?= $p ?>"><?= $p ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-2">
<select name="status" class="form-control">
<option value="">สถานะ</option>
<option value="open">ใช้งาน</option>
<option value="close">ปิด</option>
</select>
</div>

<div class="col-md-2">
<select name="grade" class="form-control">
<option value="">เกรด</option>
<option value="A">A</option>
<option value="B">B</option>
<option value="C">C</option>
</select>
</div>

<div class="col-md-3">
<button class="btn btn-primary w-100">🔍 ค้นหา</button>
</div>

</form>
</div>

<!-- TABLE -->
<table class="table table-bordered table-modern text-center align-middle">

<thead>
<tr>
<th>#</th>
<th class="text-start">โครงการ</th>
<th>ชื่อผู้ใช้</th>
<th>อุปกรณ์</th>
<th>ประเภท</th>
<th>ค่าเช่า</th>
<th>วัน</th>
<th>รวม</th>
<th>อายุ</th>
<th>เกรด</th>
</tr>
</thead>

<tbody>

<?php $i=1; foreach($data as $d):

/* grade */
$gradeText = '';
$badge = '';

if($d['device_grade']=='A'){
    $gradeText="ยังใช้งานได้ดี"; $badge='badge-A';
}elseif($d['device_grade']=='B'){
    $gradeText="พอใช้งานได้"; $badge='badge-B';
}elseif($d['device_grade']=='C'){
    $gradeText="ควรเปลี่ยน"; $badge='badge-C';
}
?>

<tr>
<td><?= $i++ ?></td>
<td class="text-start"><?= $d['user_project'] ?></td>
<td><?= $d['user_employee'] ?></td>
<td><b><?= $d['user_no_pc'] ?></b></td>
<td><?= $d['type_equipment'] ?></td>
<td><?= number_format($d['rental_price'],2) ?></td>
<td><?= $d['total_days'] ?></td>
<td><span class="badge bg-success"><?= number_format($d['total_price'],2) ?></span></td>
<td><?= $d['How_long2'] ? $d['How_long2'].' ปี' : '' ?></td>
<td>
<?php if($gradeText): ?>
<span class="badge <?= $badge ?>"><?= $gradeText ?></span>
<?php endif; ?>
</td>
</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>
</div>
</div>

<!-- GRAPH -->
<div class="col-md-3">

<!-- DONUT -->
<div class="card shadow mb-3">
<div class="card-body text-center">
<h6>🎯 เกรดอุปกรณ์</h6>
<canvas id="donutChart"></canvas>
</div>
</div>

<!-- BAR -->
<div class="card shadow">
<div class="card-body text-center">
<h6>💰 ค่าเช่าตามประเภท</h6>
<canvas id="barChart"></canvas>
</div>
</div>

</div>

</div>

</div>

<script>

/* DONUT */
new Chart(document.getElementById('donutChart'), {
    type: 'doughnut',
    data: {
        labels: ['A','B','C'],
        datasets: [{
            data: [
                <?= $gradeCount['A'] ?>,
                <?= $gradeCount['B'] ?>,
                <?= $gradeCount['C'] ?>
            ]
        }]
    }
});

/* BAR */
new Chart(document.getElementById('barChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($typeSummary)) ?>,
        datasets: [{
            label: 'ค่าเช่า',
            data: <?= json_encode(array_values($typeSummary)) ?>
        }]
    }
});
</script>

</body>
</html>