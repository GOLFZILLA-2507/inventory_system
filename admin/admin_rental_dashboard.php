<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* ================= FILTER ================= */
$search  = $_GET['search'] ?? ''; /* ค้นหาได้ทั้งชื่อเครื่องและประเภท */
$project = $_GET['project'] ?? ''; /* 🔥 เพิ่ม filter โครงการ */
$status  = $_GET['status'] ?? ''; /* open = กำลังเช่า (end_date ยังไม่ระบุ) / close = หมดสัญญา (end_date มีค่า) */
$grade   = $_GET['grade'] ?? ''; /* 🔥 เพิ่ม filter เกรด */
$repair_status = $_GET['repair_status'] ?? ''; /* 🔥 เพิ่ม filter สถานะซ่อม */
$no_user = $_GET['no_user'] ?? ''; /* 🔥 เพิ่ม filter ชื่อผู้ใช้ (กัน error กรณีมีชื่อผู้ใช้ที่ตรงกับคำค้น) */

/* ================= SQL ================= */
$sql = "
SELECT 
    h.user_project,
    h.user_employee,
    h.user_no_pc,

    /* 🔥 TYPE fallback */
    COALESCE(
        NULLIF(LTRIM(RTRIM(a.type_equipment)),''),
        NULLIF(LTRIM(RTRIM(d.device_type)),''),
        'อื่นๆ'
    ) AS type_equipment,

    LTRIM(RTRIM(r.status)) AS repair_status,

    /* 🔥 DAYS */
    DATEDIFF(DAY, h.start_date, ISNULL(h.end_date, GETDATE())) AS total_days,

    /* 🔥 PRICE fallback ตามประเภท */
    COALESCE(
        a.rental_price,
        CASE 
            WHEN d.device_type = 'Monitor' THEN 10
            WHEN d.device_type = 'Printer' THEN 20
            WHEN d.device_type = 'CCTV' THEN 15
            ELSE 5
        END
    ) AS rental_price,

    a.How_long2,
    a.device_grade,

    /* 🔥 TOTAL */
    CASE 
        WHEN r.status LIKE '%รอรับเรื่อง%' 
            THEN DATEDIFF(DAY, h.start_date, r.created_at)

        WHEN r.status LIKE '%เสร็จแล้ว%' 
            THEN 
                DATEDIFF(DAY, h.start_date, r.created_at)
                +
                DATEDIFF(DAY, r.closed_at, GETDATE())

        ELSE 
            DATEDIFF(DAY, h.start_date, ISNULL(h.end_date, GETDATE()))
    END 
    *
    COALESCE(
        a.rental_price,
        CASE 
            WHEN d.device_type = 'Monitor' THEN 10
            WHEN d.device_type = 'Printer' THEN 20
            WHEN d.device_type = 'CCTV' THEN 15
            ELSE 5
        END
    ) AS total_price

FROM IT_user_history h

/* 🔥 history ล่าสุด */
INNER JOIN (
    SELECT user_no_pc, user_project, MAX(history_id) AS max_id
    FROM IT_user_history
    GROUP BY user_no_pc, user_project
) latest 
ON latest.user_no_pc = h.user_no_pc
AND latest.user_project = h.user_project
AND latest.max_id = h.history_id

/* 🔥 assets */
LEFT JOIN IT_assets a 
    ON a.no_pc = h.user_no_pc

/* 🔥 device fallback */
LEFT JOIN IT_user_devices d 
    ON d.device_code = h.user_no_pc 
    AND d.user_project = h.user_project

/* 🔥 repair ล่าสุด */
LEFT JOIN (
    SELECT *
    FROM IT_RepairTickets r1
    WHERE r1.ticket_id = (
        SELECT MAX(r2.ticket_id)
        FROM IT_RepairTickets r2
        WHERE r2.user_no_pc = r1.user_no_pc
        AND r2.project = r1.project
    )
) r 
ON r.user_no_pc = h.user_no_pc 
AND r.project = h.user_project

WHERE 1=1

";

$params = [];

if($search){
    $sql .= " AND (
        h.user_no_pc LIKE ?
        OR h.user_employee LIKE ?
        OR h.user_project LIKE ?
        OR a.type_equipment LIKE ?
    )";

    $params[]="%$search%";
    $params[]="%$search%";
    $params[]="%$search%";
    $params[]="%$search%";
}

if($project){ /* 🔥 เพิ่ม filter โครงการ (กัน error กรณีมีโครงการอื่นๆ) */
    $sql .= " AND h.user_project = ?"; /* 🔥 เพิ่ม filter โครงการ (กัน error กรณีมีโครงการอื่นๆ) */
    $params[]=$project; /* 🔥 เพิ่ม filter โครงการ (กัน error กรณีมีโครงการอื่นๆ) */
}

if($status == 'open'){ /* 🔥 แก้ไข: ปรับเงื่อนไข open ให้ถูกต้อง (กัน error กรณี end_date มีค่า) */
    $sql .= " AND h.end_date IS NULL"; /* 🔥 แก้ไข: ปรับเงื่อนไข open ให้ถูกต้อง (กัน error กรณี end_date มีค่า) */
}
if($status == 'close'){ /* 🔥 แก้ไข: ปรับเงื่อนไข close ให้ถูกต้อง (กัน error กรณี end_date มีค่า) */
    $sql .= " AND h.end_date IS NOT NULL"; /* 🔥 แก้ไข: ปรับเงื่อนไข close ให้ถูกต้อง (กัน error กรณี end_date มีค่า) */
}

if($grade){ /* 🔥 เพิ่ม filter เกรด (กัน error กรณีมีเกรดอื่นๆ) */
    $sql .= " AND a.device_grade = ?"; /* 🔥 เพิ่ม filter เกรด (กัน error กรณีมีเกรดอื่นๆ) */
    $params[]=$grade; /* 🔥 เพิ่ม filter เกรด (กัน error กรณีมีเกรดอื่นๆ) */
}

if($repair_status){ /* 🔥 เพิ่ม filter สถานะซ่อม (กัน error กรณีมี space) */
    $sql .= " AND LTRIM(RTRIM(r.status)) = ?"; /* 🔥 เพิ่ม filter สถานะซ่อม (กัน error กรณีมี space) */
    $params[]=$repair_status; /* 🔥 เพิ่ม filter สถานะซ่อม (กัน error กรณีมี space) */
}
if($no_user == 'yes'){
    $sql .= " AND h.user_employee IS NULL";
}

/* ================= END SQL ================= */
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtNoUser = $conn->prepare("
SELECT COUNT(*) 
FROM IT_user_history h
INNER JOIN (
    SELECT user_no_pc, user_project, MAX(history_id) AS max_id
    FROM IT_user_history
    GROUP BY user_no_pc, user_project
) latest 
ON latest.user_no_pc = h.user_no_pc
AND latest.user_project = h.user_project
AND latest.max_id = h.history_id
WHERE h.user_employee IS NULL
".($project ? " AND h.user_project = ?" : "")."
");

if($project){
    $stmtNoUser->execute([$project]);
}else{
    $stmtNoUser->execute();
}

$count_no_user = $stmtNoUser->fetchColumn();

/* ================= DISCOUNT ================= */
function getDiscountRate($grade){
    switch($grade){
        case 'B': return 0.10;
        case 'C': return 0.40;
        case 'D': return 0.70;
        default: return 0; // A
    }
}

/* ================= คำนวนส่วนลด DISCOUNT ================= */
$totalDiscount = 0;
$totalNet = 0;
$discountSummary = ['A'=>0,'B'=>0,'C'=>0,'D'=>0];

foreach($data as &$d){

    $grade = $d['device_grade'] ?: 'A';
    $rate  = getDiscountRate($grade);

    $d['discount'] = $d['total_price'] * $rate;
    $d['net_price'] = $d['total_price'] - $d['discount'];

    $totalDiscount += $d['discount'];
    $totalNet += $d['net_price'];

    if(isset($discountSummary[$grade])){
        $discountSummary[$grade] += $d['discount'];
    }
}
unset($d);

/* ================= SUMMARY ตาม TYPE ================= */
$typeDiscountSummary = [];
$typeNetSummary = [];

foreach($data as $d){

    $type = $d['type_equipment'];

    $typeDiscountSummary[$type] = ($typeDiscountSummary[$type] ?? 0) + $d['discount'];
    $typeNetSummary[$type] = ($typeNetSummary[$type] ?? 0) + $d['net_price'];
}


/* ================= KPI ================= */
$totalRevenue = 0; /* 🔥 เพิ่ม: เก็บรายได้รวมทั้งหมด (กัน error กรณีมีอุปกรณ์ที่ไม่ตรงกับ IT_assets) */
$typeSummary = []; /* 🔥 เพิ่ม: เก็บรายได้รวมต่อประเภท (กัน error กรณีมีอุปกรณ์ที่ไม่ตรงกับ IT_assets) */
$typeCount = []; /* 🔥 เพิ่ม: นับจำนวนเครื่องต่อประเภท (กัน error กรณีมีอุปกรณ์ที่ไม่ตรงกับ IT_assets) */
$typeDetail = []; /* 🔥 เพิ่ม: เก็บข้อมูลสำหรับ modal รายประเภท (กัน error กรณีมีอุปกรณ์ที่ไม่ตรงกับ IT_assets) */
$revenueDetail = []; /* 🔥 เพิ่ม: เก็บข้อมูลสำหรับ modal รายได้ (กัน error กรณีมีอุปกรณ์ที่ไม่ตรงกับ IT_assets) */

/* 🔥 เพิ่ม: เก็บวันเช่า + รวมต่อประเภท */
$typeDaySummary = [];   // รวมวันต่อประเภท /* 🔥 แยกไว้ใช้ modal วันเช่า */
$typePriceSummary = []; // รวมเงินต่อประเภท /* 🔥 แยกไว้ใช้ modal รายได้ */

foreach($data as $d){ /* 🔥 loop แรกเพื่อรวมข้อมูลสำหรับ modal (กัน error กรณีมีอุปกรณ์ที่ไม่ตรงกับ IT_assets) */

    $type = $d['type_equipment']; /* 🔥 ใช้ type_equipment เป็น key (กัน error กรณีมีประเภทอื่นๆ ที่ไม่ใช่ใน IT_assets) */

    // 🔥 รวมวันเช่า
    $typeDaySummary[$type] = ($typeDaySummary[$type] ?? 0) + $d['total_days']; /* 🔥 แยกไว้ใช้ modal วันเช่า */

    // 🔥 รวมเงิน (จริงๆมีอยู่แล้ว แต่แยกไว้ใช้ modal)
    $typePriceSummary[$type] = ($typePriceSummary[$type] ?? 0) + $d['total_price']; /* 🔥 แยกไว้ใช้ modal รายได้ */
}
foreach($data as $d){ /* 🔥 loop อีกครั้งเพื่อแยกข้อมูลสำหรับ modal (กัน error กรณีมีอุปกรณ์ที่ไม่ตรงกับ IT_assets) */

    $totalRevenue += $d['total_price']; /* 🔥 รวมรายได้ทั้งหมด */

    $type = $d['type_equipment']; /* 🔥 ใช้ type_equipment เป็น key (กัน error กรณีมีประเภทอื่นๆ ที่ไม่ใช่ใน IT_assets) */

    $typeSummary[$type] = ($typeSummary[$type] ?? 0) + $d['total_price']; /* 🔥 รวมรายได้ต่อประเภท (สำหรับ bar chart) */
    $typeCount[$type] = ($typeCount[$type] ?? 0) + 1; /* 🔥 นับจำนวนเครื่องต่อประเภท */

    $typeDetail[$type][] = $d['user_no_pc']; /* 🔥 เพิ่ม: เก็บชื่อเครื่องสำหรับ modal รายประเภท */

    $revenueDetail[$type][] = [ /* 🔥 เพิ่ม: เก็บข้อมูลสำหรับ modal รายได้ */
        'pc'=>$d['user_no_pc'], /* 🔥 เพิ่ม: เก็บชื่อเครื่อง (สำหรับ modal) */
        'price'=>$d['total_price'] /* 🔥 เพิ่ม: เก็บรายได้ต่อเครื่อง (สำหรับ modal) */
    ];
}

$totalDevice = count($data); /* 🔥 นับจำนวนอุปกรณ์จาก data (กัน error กรณีมีอุปกรณ์ที่ไม่ตรงกับ IT_assets) */
$totalType   = count($typeCount); /* 🔥 นับประเภทจาก typeCount (กัน error กรณีมีประเภทอื่นๆ ที่ไม่ใช่ใน IT_assets) */

/* DONUT */
$gradeCount = ['A'=>0,'B'=>0,'C'=>0]; /* 🔥 กำหนดเกรดที่สนใจ (กัน error กรณีมีเกรดอื่นๆ) */
foreach($data as $d){ /* 🔥 นับเกรดสำหรับ donut chart */
    if(isset($gradeCount[$d['device_grade']])){  /* 🔥 เช็คเกรดก่อนนับ (กัน error กรณีเกรดอื่นๆ) */
        $gradeCount[$d['device_grade']]++; /* 🔥 นับเกรด */
    }
}

$projects = $conn->query("SELECT DISTINCT user_project FROM IT_user_history")->fetchAll(PDO::FETCH_COLUMN); /* 🔥 ดึงโครงการทั้งหมด (สำหรับ filter) */
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body{background:#eef4ff;font-family:'Sarabun';}

/* KPI เท่ากัน */
.kpi{
height:140px;
display:flex;
flex-direction:column;
justify-content:space-between;
padding:18px;
border-radius:12px;
color:white;
}

.kpi:hover{transform:translateY(-3px);transition:.2s}

/* สี */
.kpi-blue{background:linear-gradient(135deg,#2196f3,#00c6ff);}
.kpi-green{background:linear-gradient(135deg,#00c853,#69f0ae);}
.kpi-purple{background:linear-gradient(135deg,#7b1fa2,#ba68c8);}

/* 🔥 animation แถว */
.table-modern tbody tr{
    transition: all 0.2s ease;
}

/* hover */
.table-modern tbody tr:hover{
    background:#e3f2fd;
    /* ❌ ห้ามใช้ scale */
    box-shadow: inset 0 0 0 1px #90caf9;
}

/* click */
.table-modern tbody tr:active{
    transform: scale(0.98);
}

/* fade ตอนโหลด */
.table-modern tbody tr{
    animation: fadeIn 0.4s ease;
}

@keyframes fadeIn{
    from{opacity:0; transform:translateY(5px);}
    to{opacity:1; transform:translateY(0);}
}
</style>
</head>

<body>

<div class="topbar p-3 bg-primary text-white d-flex justify-content-between">
<a class="navbar-brand" href="index.php">📊 Rental Dashboard</a>
<a href="index.php" class="btn btn-light btn-sm">⬅ กลับหน้าหลัก</a>
</div>

<div class="container-fluid mt-4">

<!-- KPI -->
<div class="row mb-3">

<div class="col-md-4">
<div class="kpi kpi-blue">
<div>💰 อัตราค่าเช่าทั้งหมด</div>
<h4><?= number_format($totalRevenue,2) ?></h4>
<button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#summaryModal">
📊 สรุปรวมทั้งหมด
</button>
</div>
</div>

<div class="col-md-4">
<div class="kpi kpi-green">
<div>🖥 จำนวนอุปกรณ์</div>
<h4><?= $totalDevice ?></h4>
</div>
</div>

<div class="col-md-4">
<div class="kpi kpi-purple">
<div>📦 จำนวนประเภท</div>
<h4><?= $totalType ?></h4>
<button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#typeModal">ดูรายละเอียด</button>
</div>
</div>

</div>

<div class="row">

<!-- TABLE -->
<div class="col-md-9">
<div class="card shadow">
<div class="card-body">

<form class="row g-2 mb-2">
<div class="col-md-2"><input name="search" class="form-control" placeholder="ค้นหา"></div>

<div class="col-md-2">
<select name="project" class="form-control">
<option value="">โครงการทั้งหมด</option>
<?php foreach($projects as $p): ?>
<option><?= $p ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-2">
<select name="repair_status" class="form-control">
<option value="">สถานะซ่อม</option>
<option>รอรับเรื่อง</option>
<option>เสร็จแล้ว</option>
</select>
</div>

<div class="col-md-2">
<select name="grade" class="form-control">
<option value="">เกรด</option>
<option>A</option>
<option>B</option>
<option>C</option>
</select>
</div>

<div class="col-md-2">
<select name="no_user" class="form-control">
<option value="">ทั้งหมด</option>
<option value="yes">ไม่มีผู้ใช้</option>
</select>
</div>

<div class="col-md-4 d-flex gap-1">

<!-- 🔍 ปุ่มค้นหา -->
<button class="btn btn-primary w-100">ค้นหา......🔍</button>

<!-- 🔥 ปุ่มล้างค่า -->
<a href="admin_rental_dashboard.php" class="btn btn-secondary w-100">
ล้างค่า
</a>

</div>
</form>

<div class="table-responsive">
<table class="table table-bordered table-modern text-center">
<thead>
<tr>
<th>#</th>
<th>ชื่อ</th>
<th>โครงการ</th>
<th>อุปกรณ์</th>
<th>ประเภท</th>
<th>ค่าเช่า</th>
<th>วัน</th>
<th>รวม</th>
<th>สถานะซ่อม</th>
<th>อายุเครื่อง</th>
<th>เกรด</th>
<th>ส่วนลด</th>
<th>สุทธิ</th>
</tr>
</thead>

<?php
$group = [];

foreach($data as $d){

    $emp = $d['user_employee'] ?: 'no_user_'.$d['user_no_pc'];

    if(!isset($group[$emp])){
        $group[$emp] = [
            'name' => $d['user_employee'],
            'project' => $d['user_project'],
            'PC' => '-',
            'Monitor1' => '-',
            'Monitor2' => '-',
            'UPS' => '-',
            'price' => 0,
            'days' => 0,
            'total' => 0,
            'repair' => $d['repair_status']
        ];
    }

    /* 🔥 map type */
    if(in_array($d['type_equipment'], ['PC','Notebook','All_In_One'])){
        $group[$emp]['PC'] = $d['user_no_pc'];
    }

    if($d['type_equipment'] == 'Monitor'){
        if($group[$emp]['Monitor1'] == '-'){
            $group[$emp]['Monitor1'] = $d['user_no_pc'];
        }else{
            $group[$emp]['Monitor2'] = $d['user_no_pc'];
        }
    }

    if($d['type_equipment'] == 'UPS'){
        $group[$emp]['UPS'] = $d['user_no_pc'];
    }

    /* 🔥 รวมเงิน */
    $group[$emp]['price'] += $d['rental_price'];
    $group[$emp]['days']  = max($group[$emp]['days'], $d['total_days']);
    $group[$emp]['total'] += $d['total_price'];
}
?>

<tbody>
<?php $i=1; foreach($data as $d): ?>
<tr>
<td><?= $i++ ?></td>
<td class="text-start">
<?= $d['user_employee'] ? $d['user_employee'] : '<span class="text-danger fw-bold">อุปกรณ์ไม่มีผู้ใช้</span>' ?>
</td>
<td><?= $d['user_project'] ?></td>
<td><?= $d['user_no_pc'] ?></td>
<td><?= $d['type_equipment'] ?></td>
<td><?= number_format($d['rental_price'],2) ?></td>
<td><?= $d['total_days'] ?></td>
<td><?= number_format($d['total_price'],2) ?></td>
<td><?= $d['repair_status'] ?></td>
<td><?= $d['How_long2'] ?: '' ?> ปี</td>
<td>
<span class="badge 
<?= $d['device_grade']=='A'?'bg-success':($d['device_grade']=='B'?'bg-warning':'bg-secondary') ?>">
<?= $d['device_grade'] ?: '-' ?>
</span>
</td>
<td class="text-danger"><?= number_format($d['discount'],2) ?></td>
<td class="text-success fw-bold"><?= number_format($d['net_price'],2) ?></td>

</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

</div>
</div>
</div>

<!-- GRAPH -->
<div class="col-md-3">
<canvas id="donutChart"></canvas>
<canvas id="barChart"></canvas>
<canvas id="discountDonut"></canvas>
</div>

</div>
</div>

<!-- MODAL รายได้ -->
<div class="modal fade" id="revenueModal">
<div class="modal-dialog modal-xl">
<div class="modal-content p-4">

<h5 class="mb-3 fw-bold">
💰 รายได้แยกตามประเภท (พร้อมวันเช่า)
</h5>
<hr>

<?php foreach($revenueDetail as $type=>$items): 
    $map = [];
foreach($data as $d){
    $map[$d['user_no_pc']] = $d;
}?>
    
<div class="mb-4">

<!-- 🔥 หัวประเภท -->
<h6 class="fw-bold text-primary mt-3">
<?= $type ?> (<?= count($items) ?> เครื่อง)
</h6>

<table class="table table-bordered table-hover text-center align-middle">
<thead>
<tr>
<th>เครื่อง</th>
<th>เกรด</th>
<th>วันเช่า</th>
<th>ก่อนลด</th>
<th>ส่วนลด</th>
<th>สุทธิ</th>
</tr>
</thead>

<tbody>

<?php 
$totalDay = 0;
$totalPrice = 0;
$totalDiscountType = 0;
$totalNetType = 0;

/* 🔥 ใช้ array เก็บช่วงเวลา (กันนับซ้ำ) */
$uniquePeriod = [];

foreach($items as $it):

    $row = $map[$it['pc']] ?? null;

    $day = $row['total_days'] ?? 0;
    $discount = $row['discount'] ?? 0;
    $net = $row['net_price'] ?? 0;
    $grade = $row['device_grade'] ?? '-';
?>
<?php
$grade = '-';
foreach($data as $d){
    if($d['user_no_pc'] == $it['pc']){
        $grade = $d['device_grade'] ?: '-';
        break;
    }
}
?>

<tr>
<td><?= $it['pc'] ?></td>
<td>
    <span class="badge rounded-pill 
    <?= $grade=='A'?'bg-success':
    ($grade=='B'?'bg-warning text-dark':
    ($grade=='C'?'bg-danger':'bg-dark')) ?>">
    <?= $grade ?>
    </span>
</td>
<td><?= $day ?></td>
<td><?= number_format($it['price'],2) ?></td>
<td class="text-danger"><?= number_format($discount,2) ?></td>
<td class="text-success"><?= number_format($net,2) ?></td>
</tr>
<?php endforeach; ?>

</tbody>

<!-- 🔥 footer รวม -->
<tfoot>
<tr class="table-primary">
<th>รวม</th>
<th>-</th>
<th><?= $totalDay ?> วัน</th>
<th><?= number_format($totalPrice,2) ?></th>
<th class="text-danger"><?= number_format($totalDiscountType,2) ?></th>
<th class="text-success"><?= number_format($totalNetType,2) ?></th>
</tr>
</tfoot>

</table>

</div>
<?php endforeach; ?>

</div>
</div>
</div>

<!-- MODAL TYPE -->
<div class="modal fade" id="typeModal">
<div class="modal-dialog modal-lg">
<div class="modal-content p-4">

<h5>📦 รายการประเภทอุปกรณ์</h5>

<?php foreach($typeDetail as $type=>$list): ?>
<b><?= $type ?> (<?= count($list) ?> เครื่อง)</b><br>
<?php foreach($list as $i){ echo "- $i <br>"; } ?>
<hr>
<?php endforeach; ?>

</div>
</div>
</div>

<!-- 🔥 MODAL สรุปรวมทั้งหมด -->
<div class="modal fade" id="summaryModal">
<div class="modal-dialog modal-xl">
<div class="modal-content p-4">

<h4 class="mb-3 text-center">📊 สรุปค่าเช่าทั้งระบบ</h4>

<div class="row text-center mb-4">

<div class="col-md-4">
<div class="card bg-primary text-white p-3">
ก่อนลดทั้งหมด
<h4><?= number_format($totalRevenue,2) ?></h4>
</div>
</div>

<div class="col-md-4">
<div class="card bg-danger text-white p-3">
ส่วนลดรวม
<h4><?= number_format($totalDiscount,2) ?></h4>
</div>
</div>

<div class="col-md-4">
<div class="card bg-success text-white p-3">
สุทธิ
<h4><?= number_format($totalNet,2) ?></h4>
</div>
</div>

</div>

<hr>

<h5>📦 แยกตามประเภทอุปกรณ์</h5>

<table class="table table-bordered text-center">
<tr class="table-dark">
<th>ประเภท</th>
<th>จำนวน</th>
<th>ก่อนลด</th>
<th>ส่วนลด</th>
<th>สุทธิ</th>
</tr>

<?php foreach($typeSummary as $type=>$price): ?>
<tr>
<td><?= $type ?></td>
<td><?= $typeCount[$type] ?></td>
<td><?= number_format($price,2) ?></td>
<td class="text-danger"><?= number_format($typeDiscountSummary[$type] ?? 0,2) ?></td>
<td class="text-success"><?= number_format($typeNetSummary[$type] ?? 0,2) ?></td>
</tr>
<?php endforeach; ?>

</table>
<hr>

<h5 class="mt-4">💰 รายได้แยกตามประเภท (พร้อมวันเช่า)</h5>

<?php
$map = [];
foreach($data as $d){
    $map[$d['user_no_pc']] = $d;
}
?>

<?php foreach($revenueDetail as $type=>$items): ?>

<h6 class="text-primary mt-3 fw-bold">
<?= $type ?> (<?= count($items) ?> เครื่อง)
</h6>

<table class="table table-bordered table-hover text-center">
<thead class="table-light">
<tr>
<th>เครื่อง</th>
<th>เกรด</th>
<th>วันเช่า</th>
<th>ก่อนลด</th>
<th>ส่วนลด</th>
<th>สุทธิ</th>
</tr>
</thead>

<tbody>

<?php
$totalDay = 0;
$totalPrice = 0;
$totalDiscountType = 0;
$totalNetType = 0;

foreach($items as $it):

$row = $map[$it['pc']] ?? null;

$day = $row['total_days'] ?? 0;
$discount = $row['discount'] ?? 0;
$net = $row['net_price'] ?? 0;
$grade = $row['device_grade'] ?? '-';

$totalDay += $day;
$totalPrice += $it['price'];
$totalDiscountType += $discount;
$totalNetType += $net;
?>

<tr>
<td><?= $it['pc'] ?></td>
<td>
<span class="badge rounded-pill 
<?= $grade=='A'?'bg-success':
($grade=='B'?'bg-warning text-dark':
($grade=='C'?'bg-danger':'bg-dark')) ?>">
<?= $grade ?>
</span>
</td>
<td><?= $day ?></td>
<td><?= number_format($it['price'],2) ?></td>
<td class="text-danger"><?= number_format($discount,2) ?></td>
<td class="text-success"><?= number_format($net,2) ?></td>
</tr>

<?php endforeach; ?>

</tbody>

<tfoot>
<tr class="table-primary">
<th>รวม</th>
<th>-</th>
<th><?= $totalDay ?> วัน</th>
<th><?= number_format($totalPrice,2) ?></th>
<th class="text-danger"><?= number_format($totalDiscountType,2) ?></th>
<th class="text-success"><?= number_format($totalNetType,2) ?></th>
</tr>
</tfoot>

</table>

<?php endforeach; ?>

</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
new Chart(document.getElementById('donutChart'), {
type:'doughnut',
data:{labels:['A','B','C'],
datasets:[{data:[<?= $gradeCount['A'] ?>,<?= $gradeCount['B'] ?>,<?= $gradeCount['C'] ?>]}]}
});

new Chart(document.getElementById('barChart'), {
type:'bar',
data:{
labels: <?= json_encode(array_keys($typeSummary)) ?>,
datasets:[{data: <?= json_encode(array_values($typeSummary)) ?>}]
}
});

new Chart(document.getElementById('discountDonut'), {
type:'doughnut',
data:{
labels:['A','B','C','D'],
datasets:[{
data:[
<?= $discountSummary['A'] ?>,
<?= $discountSummary['B'] ?>,
<?= $discountSummary['C'] ?>,
<?= $discountSummary['D'] ?>
],
backgroundColor:[
'#4caf50',   // A
'#ff9800',   // B
'#f44336',   // C
'#212121'    // D
]
}]
}
});
</script>

</body>
</html>