<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* ================= PROJECT ================= */
$projects = $conn->query("SELECT DISTINCT site FROM Employee ORDER BY site")->fetchAll(PDO::FETCH_ASSOC);
$site = $_GET['site'] ?? $_SESSION['site'];

/* ================= DATA ================= */
$stmt = $conn->prepare("
SELECT 
d.user_employee,
d.device_type,
d.device_role,
d.device_code,
a.spec,a.ram,a.ssd,a.gpu,
a.device_grade,
a.How_long2,
e.department,
d.user_project
FROM IT_user_devices d
LEFT JOIN IT_assets a ON a.no_pc = d.device_code
LEFT JOIN Employee e ON e.fullname = d.user_employee
WHERE d.user_project = ?
AND d.device_role != 'shared'
AND d.user_employee IS NOT NULL
");
$stmt->execute([$site]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= SHARED ================= */
$stmtS = $conn->prepare("
SELECT device_type, device_code
FROM IT_user_devices
WHERE user_project = ?
AND device_role = 'shared'
");
$stmtS->execute([$site]);
$shared = $stmtS->fetchAll(PDO::FETCH_ASSOC);

/* ================= GROUP ================= */
$data=[];
$gradeCount=['A'=>0,'B'=>0,'C'=>0];
$typeCount=['PC'=>0,'Monitor'=>0,'UPS'=>0];

foreach($rows as $r){

    $emp = trim($r['user_employee']);
    if(!$emp) continue;

    if(!isset($data[$emp])){
        $data[$emp]=[
            'department'=>$r['department'],
            'PC'=>'',
            'Monitor1'=>'',
            'Monitor2'=>'',
            'UPS'=>'',
            'spec'=>'',
            'grade'=>'-',
            'age'=>'-'
        ];
    }

    /* PC */
    if(in_array($r['device_type'],['PC','Notebook','All_In_One'])){
        if(!$data[$emp]['PC']){
            $data[$emp]['PC']=$r['device_code'];
            $data[$emp]['spec']=$r['spec']." | ".$r['ram']." | ".$r['ssd']." | ".$r['gpu'];
            $data[$emp]['grade']=$r['device_grade'] ?: '-';
            $data[$emp]['age']=$r['How_long2'] ? $r['How_long2'].' ปี' : '-';
            $typeCount['PC']++;
        }
    }

    /* Monitor */
    if($r['device_type']=='Monitor'){
        if(!$data[$emp]['Monitor1']) $data[$emp]['Monitor1']=$r['device_code'];
        else $data[$emp]['Monitor2']=$r['device_code'];
        $typeCount['Monitor']++;
    }

    /* UPS */
    if($r['device_type']=='UPS'){
        $data[$emp]['UPS']=$r['device_code'];
        $typeCount['UPS']++;
    }

    /* Grade */
    if(isset($gradeCount[$r['device_grade']])){
        $gradeCount[$r['device_grade']]++;
    }
}

$totalUser = count($data);
$totalPC = $typeCount['PC'];
$totalMonitor = $typeCount['Monitor'];

/* ================= DEPARTMENT ================= */
$stmtP = $conn->prepare("SELECT DISTINCT department FROM Employee WHERE site=?");
$stmtP->execute([$site]);
$departments = $stmtP->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>Device Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body{
    margin:0;
    background:linear-gradient(135deg,#eef4ff,#cfe2ff);
    font-family:'Sarabun';
}

/* HEADER */
.header{
    background:linear-gradient(135deg,#2196f3,#00c6ff);
    color:#fff;
    padding:15px 25px;
    display:flex;
    justify-content:space-between;
    align-items:center;
}

/* KPI */
.kpi{
    padding:15px;
    border-radius:15px;
    color:white;
    text-align:center;
    transition:.3s;
}
.kpi:hover{transform:translateY(-5px)}

.kpi-blue{background:linear-gradient(135deg,#2196f3,#00c6ff);}
.kpi-green{background:linear-gradient(135deg,#00c853,#69f0ae);}
.kpi-dark{background:linear-gradient(135deg,#37474f,#607d8b);}

/* LAYOUT */
.main{
    display:flex;
    height:calc(100vh - 160px);
    gap:20px;
    padding:20px;
}
/*  ตาราง */
.left{
    flex:2;
    display:flex;
    flex-direction:column;
    gap:15px;
}

/* 🔥 scroll เฉพาะ table */
.table-scroll{
    max-height:350px;
    overflow:auto;
}

/* RIGHT */
.right{
    flex:1;
    display:flex;
    flex-direction:column;
    gap:20px;
}

/* CARD */
.card{
    border:none;
    border-radius:16px;
    box-shadow:0 10px 30px rgba(0,0,0,0.08);
}
</style>

</head>
<body>

<!-- HEADER -->
<div class="header">
    <div>
        <h4 class="m-0">🚀 Device Dashboard</h4>
    </div>

    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-light btn-sm">⬅ กลับหน้าหลัก</a>
    </div>
</div>

<!-- KPI -->
<div class="container-fluid mt-3">
<div class="row">

<div class="col-md-4">
<div class="kpi kpi-blue">
👨‍💻 ผู้ใช้งาน
<h4><?= $totalUser ?></h4>
</div>
</div>

<div class="col-md-4">
<div class="kpi kpi-green">
💻 PC ทั้งหมด
<h4><?= $totalPC ?></h4>
</div>
</div>

<div class="col-md-4">
<div class="kpi kpi-dark">
🖥 Monitor
<h4><?= $totalMonitor ?></h4>
</div>
</div>

</div>
</div>

<!-- FILTER -->
<div class="container-fluid mt-3">
<div class="card p-3">
<div class="row g-2">

<div class="col-md-3">
<select class="form-control" onchange="changeProject(this.value)">
<?php foreach($projects as $p): ?>
<option value="<?= $p['site'] ?>" <?= $p['site']==$site?'selected':'' ?>>
<?= $p['site'] ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-3">
<select id="filterDepartment" class="form-control">
<option value="">แผนกทั้งหมด</option>
<?php foreach($departments as $p): ?>
<option><?= $p ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-3">
<input type="text" id="search" class="form-control" placeholder="🔍 ค้นหาชื่อ...">
</div>

<div class="col-md-3 d-flex gap-2">
<button class="btn btn-primary w-100" onclick="filter()">ค้นหา</button>
<button class="btn btn-secondary w-100" onclick="resetFilter()">ล้าง</button>
</div>

</div>
</div>

<!-- MAIN -->
<div class="main">

    <!-- LEFT COLUMN -->
    <div class="left">

        <!-- TABLE USER -->
        <div class="table-scroll">
        <table class="table table-hover text-center" id="tableUser">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ชื่อ</th>
                        <th>แผนก</th>
                        <th>PC</th>
                        <th>Monitor1</th>
                        <th>Monitor2</th>
                        <th>UPS</th>
                        <th>เกรด</th>
                        <th>อายุ</th>
                    </tr>
                </thead>

                <tbody>
                <?php $i=1; foreach($data as $name=>$u): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= $name ?></td>
                    <td><?= $u['department'] ?></td>
                    <td><?= $u['PC'] ?: '-' ?></td>
                    <td><?= $u['Monitor1'] ?: '-' ?></td>
                    <td><?= $u['Monitor2'] ?: '-' ?></td>
                    <td><?= $u['UPS'] ?: '-' ?></td>

                    <td>
                        <span class="badge 
                        <?= $u['grade']=='A'?'bg-success':
                        ($u['grade']=='B'?'bg-warning text-dark':
                        ($u['grade']=='C'?'bg-danger':'bg-secondary')) ?>">
                        <?= $u['grade'] ?>
                        </span>
                    </td>

                    <td><?= $u['age'] ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- 🔥 SHARED TABLE (อยู่ใต้) -->
        <div class="card p-3">
            <h5>📡 อุปกรณ์ใช้ร่วม</h5>

            <table class="table table-hover text-center">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ประเภท</th>
                        <th>รหัส</th>
                    </tr>
                </thead>

                <tbody>
                <?php $i=1; foreach($shared as $s): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= $s['device_type'] ?></td>
                    <td><?= $s['device_code'] ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>

            </table>
        </div>

    </div>

    <!-- RIGHT -->
    <div class="right">
        <div class="right">

            <div class="card p-3">
            <canvas id="donut"></canvas>
            </div>

            <div class="card p-3">
            <canvas id="bar"></canvas>
            </div>

        </div>
    </div>


<script>

function changeProject(site){
window.location='?site='+site;
}

/* FILTER */
function filter(){
let search = document.getElementById('search').value.toLowerCase();
let dept = document.getElementById('filterDepartment').value.toLowerCase();

document.querySelectorAll('#tableUser tbody tr').forEach(r=>{
let name = r.children[1].innerText.toLowerCase();
let d = r.children[2].innerText.toLowerCase();

let show = true;
if(search && !name.includes(search)) show=false;
if(dept && d!==dept) show=false;

r.style.display = show ? '' : 'none';
});
}

function resetFilter(){
document.getElementById('search').value='';
document.getElementById('filterDepartment').value='';
filter();
}

/* DONUT */
new Chart(document.getElementById('donut'), {
type:'doughnut',
data:{
labels:['A','B','C'],
datasets:[{
data:[
<?= $gradeCount['A'] ?>,
<?= $gradeCount['B'] ?>,
<?= $gradeCount['C'] ?>,
],
backgroundColor:['#4caf50','#ff9800','#f44336']
}]
}
});

/* BAR */
new Chart(document.getElementById('bar'), {
type:'bar',
data:{
labels:['PC','Monitor','UPS'],
datasets:[{
data:[
<?= $typeCount['PC'] ?>,
<?= $typeCount['Monitor'] ?>,
<?= $typeCount['UPS'] ?>
]
}]
}
});
</script>

</body>
</html>