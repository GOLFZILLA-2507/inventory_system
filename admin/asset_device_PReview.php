<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* =====================================================
🔥 LOAD PROJECT
===================================================== */
$projects = $conn->query("
SELECT DISTINCT site FROM Employee ORDER BY site
")->fetchAll(PDO::FETCH_ASSOC);

$site = $_GET['site'] ?? $_SESSION['site'];

/* =====================================================
🔥 LOAD USER DEVICE
===================================================== */
$stmt = $conn->prepare("
SELECT 
d.user_employee,
d.device_type,
d.device_role,
d.device_code,
a.spec,a.ram,a.ssd,a.gpu,
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

/* =====================================================
🔥 GROUP USER
===================================================== */
$data=[];

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
'spec'=>''
];
}

/* PC */
if(in_array($r['device_type'],['PC','Notebook','All_In_One'])){
if(!$data[$emp]['PC']){
$data[$emp]['PC']=$r['device_code'];
$data[$emp]['spec']=$r['spec']." | ".$r['ram']." | ".$r['ssd']." | ".$r['gpu'];
}
}

/* Monitor */
if($r['device_type']=='Monitor'){
if(!$data[$emp]['Monitor1']) $data[$emp]['Monitor1']=$r['device_code'];
else $data[$emp]['Monitor2']=$r['device_code'];
}

/* UPS */
if($r['device_type']=='UPS'){
$data[$emp]['UPS']=$r['device_code'];
}

}

/* =====================================================
🔥 LOAD SHARED DEVICE
===================================================== */
$stmtS = $conn->prepare("
SELECT device_type, device_code
FROM IT_user_devices
WHERE user_project = ?
AND device_role = 'shared'
");
$stmtS->execute([$site]);
$shared = $stmtS->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
🔥 LOAD DEPARTMENT
===================================================== */
$stmtP = $conn->prepare("
SELECT DISTINCT department FROM Employee WHERE site=?
");
$stmtP->execute([$site]);
$departments = $stmtP->fetchAll(PDO::FETCH_COLUMN);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<style>
body{background:#f4f9ff;}

.card{
border-radius:16px;
border:none;
box-shadow:0 10px 30px rgba(13,110,253,0.1);
}

.card-header{
background:linear-gradient(135deg,#0d6efd,#74c0fc);
color:white;
font-weight:600;
}

.filter-box{
background:#fff;
padding:15px;
border-radius:12px;
box-shadow:0 5px 15px rgba(0,0,0,0.05);
margin-bottom:20px;
}

.table thead{
background:#e7f1ff;
}

.search{
border-radius:10px;
}
.badge-shared{
background:#0dcaf0;
}
</style>

<div class="container mt-4">

<h4 class="mb-3">📊 Device Preview</h4>

<!-- ================= FILTER ================= -->
<div class="filter-box">
<div class="row g-2">

<div class="col-md-4">
<label>โครงการ</label>
<select class="form-control" onchange="changeProject(this.value)">
<?php foreach($projects as $p): ?>
<option value="<?= $p['site'] ?>" <?= $p['site']==$site?'selected':'' ?>>
<?= $p['site'] ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-4">
<label>แผนก</label>
<select id="filterDepartment" class="form-control">
<option value="">ทั้งหมด</option>
<?php foreach($departments as $p): ?>
<option><?= $p ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-4">
<label>ค้นหา</label>
<input type="text" id="search" class="form-control search" placeholder="พิมชื่อ...">
</div>

</div>
</div>

<!-- ================= USER TABLE ================= -->
<div class="card mb-4">
<div class="card-header">
👨‍💻 อุปกรณ์พนักงาน (<?= $site ?>)
</div>

<div class="card-body">

<table class="table table-hover text-center" id="tableUser">
<thead>
<tr>
<th>#</th>
<th>ชื่อ</th>
<th>ตำแหน่ง</th>
<th>PC</th>
<th>Spec</th>
<th>Monitor</th>
<th>UPS</th>
</tr>
</thead>

<tbody>
<?php $i=1; foreach($data as $name=>$u): ?>
<tr>
<td><?= $i++ ?></td>
<td class="text-start"><?= $name ?></td>
<td><?= $u['department'] ?></td>
<td><?= $u['PC'] ?: '-' ?></td>
<td><?= $u['spec'] ?: '-' ?></td>
<td><?= $u['Monitor1'].' '.$u['Monitor2'] ?></td>
<td><?= $u['UPS'] ?: '-' ?></td>
</tr>
<?php endforeach; ?>
</tbody>

</table>

</div>
</div>

<!-- ================= SHARED TABLE ================= -->
<div class="card">
<div class="card-header">
📡 อุปกรณ์ใช้ร่วม (Shared)
</div>

<div class="card-body">

<table class="table table-hover text-center" id="tableShared">
<thead>
<tr>
<th>#</th>
<th>ประเภท</th>
<th>รหัส</th>
<th>สถานะ</th>
</tr>
</thead>

<tbody>

<?php $j=1; foreach($shared as $s): ?>
<tr>
<td><?= $j++ ?></td>
<td><?= $s['device_type'] ?></td>
<td><?= $s['device_code'] ?></td>
<td><span class="badge badge-shared">Shared</span></td>
</tr>
<?php endforeach; ?>

</tbody>
</table>

</div>
</div>

</div>

<script>

/* ================= FILTER ================= */
document.getElementById('search').addEventListener('keyup',filter);
document.getElementById('filterdepartment').addEventListener('change',filter);

function filter(){

let search = document.getElementById('search').value.toLowerCase();
let department = document.getElementById('filterdepartment').value.toLowerCase();

/* USER TABLE */
document.querySelectorAll('#tableUser tbody tr').forEach(r=>{

let name = r.children[1].innerText.toLowerCase();
let department = r.children[2].innerText.toLowerCase();

let show = true;

if(search && !name.includes(search)) show=false;
if(department && department!==department) show=false;

r.style.display = show ? '' : 'none';

});

/* SHARED TABLE (search เฉพาะ code/type) */
document.querySelectorAll('#tableShared tbody tr').forEach(r=>{

let txt = r.innerText.toLowerCase();

let show = true;
if(search && !txt.includes(search)) show=false;

r.style.display = show ? '' : 'none';

});

}

/* ================= CHANGE PROJECT ================= */
function changeProject(site){
window.location='?site='+site;
}

</script>

<?php include 'partials/footer.php'; ?>