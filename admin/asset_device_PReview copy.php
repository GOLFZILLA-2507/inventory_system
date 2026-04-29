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

/* Grade Count */
if(isset($gradeCount[$r['device_grade']])){
$gradeCount[$r['device_grade']]++;
}

}

$totalUser = count($data);
$totalPC = $typeCount['PC'];

/* ================= DEPARTMENT ================= */
$stmtP = $conn->prepare("SELECT DISTINCT department FROM Employee WHERE site=?");
$stmtP->execute([$site]);
$departments = $stmtP->fetchAll(PDO::FETCH_COLUMN);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<style>
    
.header{
background:linear-gradient(135deg,#2196f3,#00c6ff);
color:white;
padding:15px 25px;
box-shadow:0 5px 15px rgba(0,0,0,0.1);
}


body{
background:linear-gradient(135deg,#eef4ff,#cfe2ff);
font-family:'Sarabun';
}

/* glass card */
.glass{
background:rgba(255,255,255,0.7);
backdrop-filter: blur(10px);
border-radius:16px;
box-shadow:0 10px 30px rgba(0,0,0,0.08);
}

/* KPI */
.kpi{
padding:20px;
border-radius:16px;
color:#fff;
text-align:center;
transition:.3s;
}
.kpi:hover{transform:translateY(-5px)}

.kpi-blue{background:linear-gradient(135deg,#2196f3,#00c6ff);}
.kpi-green{background:linear-gradient(135deg,#00c853,#69f0ae);}
.kpi-dark{background:linear-gradient(135deg,#37474f,#607d8b);}

/* table */
.table thead{background:#e3f2fd;}
.table tbody tr:hover{
background:#f1f7ff;
transform:scale(1.01);
}

/* badge */
.badge{font-size:13px;padding:6px 10px;border-radius:10px}
</style>

<div class="container mt-4">

<h4 class="mb-3">🚀 Device Dashboard PRO</h4>

<!-- KPI -->
<div class="row mb-3">
<div class="col-md-4">
<div class="kpi kpi-blue">
👨‍💻 ผู้ใช้งาน<br><h4><?= $totalUser ?></h4>
</div>
</div>

<div class="col-md-4">
<div class="kpi kpi-green">
💻 PC ทั้งหมด<br><h4><?= $totalPC ?></h4>
</div>
</div>

<div class="col-md-4">
<div class="kpi kpi-dark">
🏢 โครงการ<br><h4><?= $site ?></h4>
</div>
</div>
</div>

<!-- FILTER -->
<div class="glass p-3 mb-3">
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

<!-- TABLE -->
<div class="glass p-3 mb-4">

<button class="btn btn-success mb-2" onclick="exportCSV()">📥 Export CSV</button>

<table class="table table-hover text-center" id="tableUser">
<thead>
<tr>
<th>#</th>
<th>ชื่อ</th>
<th>แผนก</th>
<th>PC</th>
<th>Spec</th>
<th>เกรดคอมพิวเตอร์</th>
<th>อายุ</th>
<th>Monitor</th>
<th>UPS</th>
</tr>
</thead>

<tbody>
<?php $i=1; foreach($data as $name=>$u): ?>
<tr onclick="showDetail('<?= $name ?>','<?= $u['spec'] ?>','<?= $u['grade'] ?>','<?= $u['age'] ?>')">
<td><?= $i++ ?></td>
<td class="text-start"><?= $name ?></td>
<td><?= $u['department'] ?></td>
<td><?= $u['PC'] ?></td>
<td><?= $u['spec'] ?></td>

<td>
<span class="badge 
<?= $u['grade']=='A'?'bg-success':
($u['grade']=='B'?'bg-warning text-dark':
($u['grade']=='C'?'bg-danger':'bg-secondary')) ?>">
<?= $u['grade'] ?>
</span>
</td>

<td><?= $u['age'] ?></td>
<td><?= $u['Monitor1'].' '.$u['Monitor2'] ?></td>
<td><?= $u['UPS'] ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

</div>

<!-- CHART -->
<div class="row">
<div class="col-md-6"><canvas id="gradeChart"></canvas></div>
<div class="col-md-6"><canvas id="deviceChart"></canvas></div>
</div>

</div>

<!-- MODAL -->
<div class="modal fade" id="detailModal">
<div class="modal-dialog">
<div class="modal-content p-4">
<h5>รายละเอียด</h5>
<div id="modalContent"></div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

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

/* EXPORT CSV */
function exportCSV(){
let rows = document.querySelectorAll("table tr");
let csv = [];
rows.forEach(r=>{
let cols = r.querySelectorAll("td,th");
let row=[];
cols.forEach(c=>row.push(c.innerText));
csv.push(row.join(","));
});
let blob = new Blob([csv.join("\n")], {type:"text/csv"});
let a = document.createElement("a");
a.href = URL.createObjectURL(blob);
a.download = "device.csv";
a.click();
}

/* MODAL */
function showDetail(name,spec,grade,age){
let html = `
<b>ชื่อ:</b> ${name}<br>
<b>Spec:</b> ${spec}<br>
<b>เกรด:</b> ${grade}<br>
<b>อายุ:</b> ${age}
`;
document.getElementById('modalContent').innerHTML = html;
new bootstrap.Modal(document.getElementById('detailModal')).show();
}

/* DONUT */
new Chart(document.getElementById('gradeChart'), {
type:'doughnut',
data:{
labels:['A','B','C'],
datasets:[{
data:[
<?= $gradeCount['A'] ?>,
<?= $gradeCount['B'] ?>,
<?= $gradeCount['C'] ?>
],
backgroundColor:['#4caf50','#ff9800','#f44336']
}]
}
});

/* BAR */
new Chart(document.getElementById('deviceChart'), {
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

<?php include 'partials/footer.php'; ?>