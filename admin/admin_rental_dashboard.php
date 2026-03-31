<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* ================= FILTER ================= */
$search  = $_GET['search'] ?? ''; /* ค้นหาได้ทั้งชื่อเครื่องและประเภท */
$project = $_GET['project'] ?? ''; /* 🔥 เพิ่ม filter โครงการ */
$status  = $_GET['status'] ?? ''; /* open = กำลังเช่า (end_date ยังไม่ระบุ) / close = หมดสัญญา (end_date มีค่า) */
$grade   = $_GET['grade'] ?? ''; /* 🔥 เพิ่ม filter เกรด */
$repair_status = $_GET['repair_status'] ?? ''; /* 🔥 เพิ่ม filter สถานะซ่อม */

/* ================= SQL ================= */
$sql = "
SELECT 
    h.user_project, /* 🔥 ปรับการดึงชื่อโครงการให้ใช้ user_project จาก IT_user_history (กัน error กรณีมีหลายเครื่องและหลายประวัติ) */
    h.user_employee,  /* 🔥 ปรับการดึงชื่อพนักงานให้ใช้ user_employee จาก IT_user_history (กัน error กรณีมีหลายเครื่องและหลายประวัติ) */
    h.user_no_pc, /* 🔥 ปรับการดึงชื่อเครื่องให้ใช้ no_pc จาก IT_user_history (กัน error กรณีมีหลายเครื่องและหลายประวัติ) */

    ISNULL(NULLIF(LTRIM(RTRIM(a.type_equipment)),''),'อื่นๆ') AS type_equipment, /* 🔥 ปรับการดึงประเภทอุปกรณ์ให้ใช้ LTRIM/RTRIM กัน error กรณีมี space และใช้ ISNULL/NULLIF กัน error กรณีไม่มีข้อมูลใน IT_assets */
    LTRIM(RTRIM(r.status)) AS repair_status, /* 🔥 ปรับการดึงสถานะซ่อมให้ใช้ LTRIM/RTRIM กัน error กรณีมี space */

    DATEDIFF(DAY, h.start_date, ISNULL(h.end_date, GETDATE())) AS total_days,  /* 🔥 ปรับการคำนวณ total_days ให้รวมวันซ่อม (กัน error กรณีมีการซ่อม) */
    a.rental_price, /* 🔥 เพิ่ม rental_price จาก IT_assets (กัน error กรณีมีอุปกรณ์ที่ไม่ตรงกับ IT_assets) */
    a.How_long2, /* 🔥 เพิ่ม How_long2 จาก IT_assets (กัน error กรณีมีอุปกรณ์ที่ไม่ตรงกับ IT_assets) */
    a.device_grade, /* 🔥 เพิ่ม device_grade จาก IT_assets (กัน error กรณีมีอุปกรณ์ที่ไม่ตรงกับ IT_assets) */

    CASE 
    WHEN r.status LIKE '%รอรับเรื่อง%' 
        THEN DATEDIFF(DAY, h.start_date, r.created_at) /* 🔥 ปรับการคำนวณ total_days ให้รวมวันซ่อม (กัน error กรณีมีการซ่อม) */

    WHEN r.status LIKE '%เสร็จแล้ว%' /* 🔥 เพิ่มเงื่อนไขกรณีซ่อมเสร็จแล้ว (กัน error กรณีมีการซ่อม) */
        THEN 
            DATEDIFF(DAY, h.start_date, r.created_at) /* 🔥 ปรับการคำนวณ total_days ให้รวมวันซ่อม (กัน error กรณีมีการซ่อม) */
            +
            DATEDIFF(DAY, r.closed_at, GETDATE()) /* 🔥 ปรับการคำนวณ total_days ให้รวมวันซ่อม (กัน error กรณีมีการซ่อม) */

    ELSE 
        DATEDIFF(DAY, h.start_date, ISNULL(h.end_date, GETDATE())) /* 🔥 ปรับการคำนวณ total_days ให้รวมวันซ่อม (กัน error กรณีมีการซ่อม) */
END * a.rental_price AS total_price /* 🔥 ปรับการคำนวณ total_price ให้รวมวันซ่อม (กัน error กรณีมีการซ่อม) */


FROM IT_user_history h /* 🔥 ปรับการ join กับ IT_user_history ให้ใช้ no_pc และ project เป็น key (กัน error กรณีมีหลายเครื่องและหลายประวัติ) */
INNER JOIN ( /* 🔥 ปรับการ join กับ IT_user_history ให้ใช้ no_pc และ project เป็น key (กัน error กรณีมีหลายเครื่องและหลายประวัติ) */
    SELECT user_no_pc, user_project, MAX(history_id) AS max_id /* 🔥 ปรับการ join กับ IT_user_history ให้ใช้ no_pc และ project เป็น key (กัน error กรณีมีหลายเครื่องและหลายประวัติ) */
    FROM IT_user_history /* 🔥 ปรับการ join กับ IT_user_history ให้ใช้ no_pc และ project เป็น key (กัน error กรณีมีหลายเครื่องและหลายประวัติ) */
    GROUP BY user_no_pc, user_project /* 🔥 ปรับการ join กับ IT_user_history ให้ใช้ no_pc และ project เป็น key (กัน error กรณีมีหลายเครื่องและหลายประวัติ) */
) latest  /* 🔥 ปรับการ join กับ IT_user_history ให้ใช้ no_pc และ project เป็น key (กัน error กรณีมีหลายเครื่องและหลายประวัติ) */
ON latest.user_no_pc = h.user_no_pc /* 🔥 ปรับการ join กับ IT_user_history ให้ใช้ no_pc เป็น key (กัน error กรณีมีหลายเครื่อง) */
AND latest.user_project = h.user_project /* 🔥 ปรับการ join กับ IT_user_history ให้ใช้ history_id ล่าสุด (กัน error กรณีมีหลายประวัติ) */
AND latest.max_id = h.history_id /* 🔥 ปรับการ join กับ IT_user_history ให้ใช้ history_id ล่าสุด (กัน error กรณีมีหลายประวัติ) */

LEFT JOIN IT_assets a ON a.no_pc = h.user_no_pc /* 🔥 ปรับการ join กับ IT_assets ให้ใช้ no_pc เป็น key (กัน error กรณีมีอุปกรณ์ที่ไม่ตรงกับ IT_assets) */

LEFT JOIN ( /* 🔥 ปรับการ join กับ IT_RepairTickets ให้ดึงแค่ ticket ล่าสุดของแต่ละเครื่อง (กัน error กรณีมีหลาย ticket) */
    SELECT *
    FROM IT_RepairTickets r1 /* 🔥 ปรับการ join กับ IT_RepairTickets ให้ดึงแค่ ticket ล่าสุดของแต่ละเครื่อง (กัน error กรณีมีหลาย ticket) */
    WHERE r1.ticket_id = ( /* 🔥 ปรับการ join กับ IT_RepairTickets ให้ดึงแค่ ticket ล่าสุดของแต่ละเครื่อง (กัน error กรณีมีหลาย ticket) */
        SELECT MAX(r2.ticket_id) /* 🔥 ปรับการ join กับ IT_RepairTickets ให้ดึงแค่ ticket ล่าสุดของแต่ละเครื่อง (กัน error กรณีมีหลาย ticket) */
        FROM IT_RepairTickets r2 /* 🔥 ปรับการ join กับ IT_RepairTickets ให้ดึงแค่ ticket ล่าสุดของแต่ละเครื่อง (กัน error กรณีมีหลาย ticket) */
        WHERE r2.user_no_pc = r1.user_no_pc /* 🔥 ปรับการ join กับ IT_RepairTickets ให้ดึงแค่ ticket ล่าสุดของแต่ละเครื่อง (กัน error กรณีมีหลาย ticket) */
        AND r2.project = r1.project /* 🔥 ปรับการ join กับ IT_RepairTickets ให้ดึงแค่ ticket ล่าสุดของแต่ละเครื่อง (กัน error กรณีมีหลาย ticket) */
    )
) r ON r.user_no_pc = h.user_no_pc /* 🔥 ปรับการ join กับ IT_RepairTickets ให้ดึงแค่ ticket ล่าสุดของแต่ละเครื่อง (กัน error กรณีมีหลาย ticket) */
 AND r.project = h.user_project /* 🔥 ปรับการ join กับ IT_RepairTickets ให้ดึงแค่ ticket ล่าสุดของแต่ละเครื่อง (กัน error กรณีมีหลาย ticket) */

WHERE 1=1 /* 🔥 ปรับเงื่อนไขค้นหาให้ครอบคลุมทั้งชื่อเครื่องและประเภท (กัน error กรณีมีคำค้นที่ตรงกับทั้งสอง) */
";

$params = [];

if($search){ /* 🔥 ปรับเงื่อนไขค้นหาให้ครอบคลุมทั้งชื่อเครื่องและประเภท (กัน error กรณีมีคำค้นที่ตรงกับทั้งสอง) */
    $sql .= " AND (h.user_no_pc LIKE ? OR a.type_equipment LIKE ?)"; /* 🔥 ปรับเงื่อนไขค้นหาให้ครอบคลุมทั้งชื่อเครื่องและประเภท (กัน error กรณีมีคำค้นที่ตรงกับทั้งสอง) */
    $params[]="%$search%"; /* 🔥 ปรับเงื่อนไขค้นหาให้ครอบคลุมทั้งชื่อเครื่องและประเภท (กัน error กรณีมีคำค้นที่ตรงกับทั้งสอง) */
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

$stmt = $conn->prepare($sql); /* 🔥 ปรับการดึงข้อมูลให้รองรับ filter และแก้ไข SQL ตาม requirement ใหม่ */
$stmt->execute($params); /* 🔥 ปรับการดึงข้อมูลให้รองรับ filter และแก้ไข SQL ตาม requirement ใหม่ */
$data = $stmt->fetchAll(PDO::FETCH_ASSOC); /* ================= END SQL ================= */

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
    transform: scale(1.01);
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
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
<button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#revenueModal">ดูรายละเอียด</button>
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
<option value="">โครงการ</option>
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

<div class="col-md-4 d-flex gap-1">

<!-- 🔍 ปุ่มค้นหา -->
<button class="btn btn-primary w-100">ค้นหา......🔍</button>

<!-- 🔥 ปุ่มล้างค่า -->
<a href="admin_rental_dashboard.php" class="btn btn-secondary w-100">
ล้างค่าทั้งหมด
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
</tr>
</thead>

<tbody>
<?php $i=1; foreach($data as $d): ?>
<tr>
<td><?= $i++ ?></td>
<td class="text-start"><?= $d['user_employee'] ?></td>
<td><?= $d['user_project'] ?></td>
<td><?= $d['user_no_pc'] ?></td>
<td><?= $d['type_equipment'] ?></td>
<td><?= number_format($d['rental_price'],2) ?></td>
<td><?= $d['total_days'] ?></td>
<td><?= number_format($d['total_price'],2) ?></td>
<td><?= $d['repair_status'] ?></td>
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
</div>

</div>
</div>

<!-- MODAL รายได้ (ปรับใหม่ตาม requirement) -->
<div class="modal fade" id="revenueModal">
<div class="modal-dialog modal-xl">
<div class="modal-content p-4">

<h5 class="mb-3">💰 รายได้แยกตามประเภท (พร้อมวันเช่า)</h5>

<?php foreach($revenueDetail as $type=>$items): ?>
<div class="mb-4">

<!-- 🔥 หัวประเภท -->
<h6 class="text-primary">
<?= $type ?> 
(<?= count($items) ?> เครื่อง)
</h6>

<table class="table table-sm table-bordered text-center align-middle">
<thead>
<tr>
<th>เครื่อง</th>
<th>วันเช่า</th>
<th>รายได้</th>
</tr>
</thead>

<tbody>

<?php 
$totalDay = 0;
$totalPrice = 0;

/* 🔥 ใช้ array เก็บช่วงเวลา (กันนับซ้ำ) */
$uniquePeriod = [];

foreach($items as $it): 

    foreach($data as $d){
        if($d['user_no_pc'] == $it['pc']){

            // 🔥 key ช่วงเวลา (กันซ้ำ)
            $key = $d['user_project'].'_'.$d['total_days'];

            if(!isset($uniquePeriod[$key])){
                $totalDay += $d['total_days'];
                $uniquePeriod[$key] = true;
            }

            $day = $d['total_days'];
            break;
        }
    }

    $totalPrice += $it['price'];
?>
<tr>
<td><?= $it['pc'] ?></td>
<td><?= $day ?></td>
<td><?= number_format($it['price'],2) ?></td>
</tr>
<?php endforeach; ?>

</tbody>

<!-- 🔥 footer รวม -->
<tfoot>
<tr class="table-primary">
<th>รวม</th>
<th><?= $totalDay ?> วัน</th>
<th><?= number_format($totalPrice,2) ?></th>
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
</script>

</body>
</html>