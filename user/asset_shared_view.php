<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];
$search = $_GET['search'] ?? '';
$grade_filter = $_GET['grade'] ?? '';

/* KPI */
$stmt = $conn->prepare("SELECT COUNT(*) FROM IT_user_devices WHERE user_employee IS NULL AND user_project = ?");
$stmt->execute([$site]);
$count_no_user = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM IT_RepairTickets WHERE project = ? AND status != 'เสร็จแล้ว'");
$stmt->execute([$site]);
$count_repair = $stmt->fetchColumn();

/* DATA */
$sql = "
SELECT d.user_employee,d.device_type,d.device_code,a.spec,a.ram,a.ssd,a.gpu,a.device_grade,a.How_long2,e.position FROM IT_user_devices d
LEFT JOIN IT_assets a ON a.no_pc = d.device_code
LEFT JOIN Employee e ON e.fullname = d.user_employee
WHERE d.user_project = ?
AND d.device_role != 'shared'
AND d.user_employee IS NOT NULL
";

if($search){
    $sql .= " AND (d.user_employee LIKE ? OR d.device_code LIKE ? OR d.device_type LIKE ?)";
}

if($grade_filter){
    $sql .= " AND a.device_grade = ?";
}

$stmt = $conn->prepare($sql);

if($search){
    $stmt->execute([$site,"%$search%","%$search%","%$search%"]);
}else{
    $stmt->execute([$site, $grade_filter, $grade_filter]);
}

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* GROUP */
$data=[];
$gradeCount=['A'=>0,'B'=>0,'C'=>0,'D'=>0];

foreach($rows as $r){
    $emp = trim($r['user_employee']);
    if(!$emp) continue;

    if(!isset($data[$emp])){
        $data[$emp]=[
            'position'=>$r['position'],
            'PC'=>'','Monitor1'=>'','Monitor2'=>'','UPS'=>'',
            'spec'=>'','grade'=>'-','How_long2'=>'-'
        ];
    }

    if(in_array($r['device_type'],['PC','Notebook','All_In_One'])){
        if(!$data[$emp]['PC']){
            $data[$emp]['PC']=$r['device_code'];
            $data[$emp]['spec']=$r['spec']." | ".$r['ram']." | ".$r['ssd']." | ".$r['gpu'];
            $data[$emp]['grade']=$r['device_grade'] ?: '-';
            $data[$emp]['How_long2']=$r['How_long2'] ?: '-';
        }
    }

    if($r['device_type']=='Monitor'){
        if(!$data[$emp]['Monitor1']) $data[$emp]['Monitor1']=$r['device_code'];
        else $data[$emp]['Monitor2']=$r['device_code'];
    }

    if($r['device_type']=='UPS'){
        $data[$emp]['UPS']=$r['device_code'];
    }

    if(isset($gradeCount[$r['device_grade']])){
        $gradeCount[$r['device_grade']]++;
    }
}

/* ================= DEVICE TYPE COUNT ================= */
// 🔥 นับจำนวนอุปกรณ์แต่ละประเภท
$typeCount = [];

foreach($rows as $r){
    $type = $r['device_type'] ?? 'อื่นๆ';

    if(!isset($typeCount[$type])){
        $typeCount[$type] = 0;
    }

    $typeCount[$type]++;
}

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<style>
body{background:#eef4ff;font-family:'Sarabun';}

/* KPI */
.kpi{
    padding:15px;
    border-radius:12px;
    text-align:center;
    color:#fff;
    font-weight:600;
}
.kpi-green{background:#198754;}
.kpi-warning{background:#ffc107;color:#000;}
.kpi-red{background:#dc3545;}
.kpi-dark{background:#212529;}

.card{
    border:none;
    border-radius:15px;
    box-shadow:0 8px 25px rgba(0,0,0,0.08);
}

canvas{
    max-height:260px;
}
</style>

<div class="container mt-4">

<div class="row g-3 mb-4">

    <!-- LEFT -->
    <div class="col-md-8">

        <div class="card p-3">

            <h6 class="mb-3">📊 สถานะอุปกรณ์</h6>

            <!-- grade -->
            <div class="row g-3 mb-3">

                <div class="col-md-3">
                    <div class="kpi kpi-green">A<br><h4><?= $gradeCount['A'] ?></h4></div>
                </div>

                <div class="col-md-3">
                    <div class="kpi kpi-warning">B<br><h4><?= $gradeCount['B'] ?></h4></div>
                </div>

                <div class="col-md-3">
                    <div class="kpi kpi-red">C<br><h4><?= $gradeCount['C'] ?></h4></div>
                </div>

                <div class="col-md-3">
                    <div class="kpi kpi-dark">D<br><h4><?= $gradeCount['D'] ?></h4></div>
                </div>

            </div>

        <!-- 🔥 GRAPH DEVICE TYPE -->
            <div class="mt-3">
                <h6 class="mb-3">📊 ประเภทอุปกรณ์</h6>
                <canvas id="deviceChart"></canvas>
            </div>

        </div>

    </div>

    <!-- RIGHT -->
    <div class="col-md-4">

        <div class="card p-3 h-100">

            <h6 class="text-center">📊 สัดส่วนเกรดอุปกรณ์</h6>

            <canvas id="gradeChart"></canvas>

        </div>

    </div>

</div>

<!-- TABLE -->
<div class="card">

<div class="card-header bg-success text-white">
📡 อุปกรณ์ในโครงการ <?= $site ?>
</div>

<form class="p-3 row g-2">

<div class="col-md-4">
<input name="search" class="form-control" placeholder="🔍 ค้นหา..." value="<?= $search ?>">
</div>

<div class="col-md-5">
<select name="grade" class="form-control">
<option value="">ทุกเกรด</option>
<option value="A">A</option>
<option value="B">B</option>
<option value="C">C</option>
<option value="D">D</option>
</select>
</div>

<div class="col-md-3 d-flex gap-2">
<button class="btn btn-success w-100">ค้นหา</button>
<a href="asset_shared_view.php" class="btn btn-secondary w-100">ล้าง</a>
</div>

</form>

<div class="card-body">

<table class="table table-bordered text-center">
<thead>
<tr>
<th>#</th>
<th>ชื่อ</th>
<th>ตำแหน่ง</th>
<th>PC</th>
<th>อายุ</th>
<th>เกรด</th>
<th>Spec</th>
<th>Monitor</th>
<th>UPS</th>
</tr>
</thead>

<tbody>
<?php $i=1; foreach($data as $name=>$u): ?>
<tr>
<td><?= $i++ ?></td>
<td><?= $name ?></td>
<td><?= $u['position'] ?></td>
<td><?= $u['PC'] ?></td>
<td><?= $u['How_long2'] ?></td>
<td>
<span class="badge 
<?= $u['grade']=='A'?'bg-success':
($u['grade']=='B'?'bg-warning text-dark':
($u['grade']=='C'?'bg-danger':'bg-secondary')) ?>">
<?= $u['grade'] ?>
</span>
</td>

<td><?= $u['spec'] ?></td>
<td><?= $u['Monitor1'].' '.$u['Monitor2'] ?></td>
<td><?= $u['UPS'] ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

</div>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
new Chart(document.getElementById('gradeChart'), {
type:'doughnut',
data:{
labels:['A','B','C','D'],
datasets:[{
data:[
<?= $gradeCount['A'] ?>,
<?= $gradeCount['B'] ?>,
<?= $gradeCount['C'] ?>,
<?= $gradeCount['D'] ?>
],
backgroundColor:['#198754','#ffc107','#dc3545','#212529']
}]
}
});

// 🔥 กราฟประเภทอุปกรณ์
new Chart(document.getElementById('deviceChart'), {
    type:'bar',
    data:{
        labels: <?= json_encode(array_keys($typeCount)) ?>,
        datasets:[{
            label:'จำนวนอุปกรณ์',
            data: <?= json_encode(array_values($typeCount)) ?>
        }]
    }
});
</script>

<?php include 'partials/footer.php'; ?>