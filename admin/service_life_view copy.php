<?php
require_once '../config/connect.php';

/* ================= PAGINATION ================= */
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;
$offset = ($page-1)*$limit;

/* ================= FILTER ================= */
$type = $_GET['type'] ?? '';

$where = "";
$params = [];

if($type != ''){
    $where = "WHERE a.Equipment_details = ?";
    $params[] = $type;
}

/* ================= LOAD DATA ================= */
$sql = "
SELECT 
    a.*,
    u.user_employee,
    u.user_project
FROM IT_assets a
LEFT JOIN IT_user_information u ON a.asset_id = u.asset_id
$where
ORDER BY a.asset_id DESC
OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= COUNT PAGE ================= */
$total = $conn->query("SELECT COUNT(*) FROM IT_assets")->fetchColumn();
$total_pages = ceil($total / $limit);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<style>
.card-header{
    background: linear-gradient(135deg,#0d6efd,#0dcaf0);
    color:white;
}
.table td,.table th{white-space:nowrap;}
</style>

<div class="container-fluid p-3">
<div class="card shadow border-0">
<div class="card-header">
<h5 class="mb-0">📊 ระบบคำนวณอายุการใช้งาน (Service Life)</h5>
</div>

<div class="card-body">

<!-- SEARCH + FILTER -->
<div class="row mb-3">
<div class="col-md-6">
<input type="text" id="searchInput" class="form-control"
placeholder="🔍 ค้นหา ผู้ใช้ / โครงการ / รหัสเครื่อง">
</div>

<div class="col-md-6">
<form method="get">
<select name="type" class="form-control" onchange="this.form.submit()">
<option value="">-- ทุกประเภท --</option>
<option value="PC" <?= $type=='PC'?'selected':'' ?>>PC</option>
<option value="NOTEBOOK" <?= $type=='NOTEBOOK'?'selected':'' ?>>NOTEBOOK</option>
<option value="PRINTER" <?= $type=='PRINTER'?'selected':'' ?>>PRINTER</option>
</select>
</form>
</div>
</div>

<div class="table-responsive">
<table class="table table-bordered table-hover">
<thead class="table-primary text-center">
<tr>
<th>#</th>
<th>ผู้ใช้</th>
<th>โครงการ</th>
<th>รหัส</th>
<th>Spec</th>
<th>ปีผลิต</th>
<th>อายุ</th>
<th>ปีใช้งาน</th>
<th>อายุใช้งาน</th>
<th>มูลค่า</th>
<th>สถานะ</th>
<th>แก้ไข</th>
<th>ลบ</th>
</tr>
</thead>

<tbody id="tableBody">

<?php $i=1; foreach($data as $row):

$spec = $row['spec']." | ".$row['ram']." | ".$row['ssd']." | ".$row['gpu'];

$age1 = $row['How_long'];
$age2 = $row['How_long2'];

/* STATUS FROM อายุใช้งาน */
$status = 'เกรด A - ใช้งานได้ดี';
$badge = 'badge bg-success';

if($age2 >= 10){
    $status = 'เกรด C - ควรเปลี่ยน';
    $badge = 'badge bg-danger';
}else if($age2 >= 5){
    $status = 'เกรด B - พอใช้งาน';
    $badge = 'badge bg-warning';
}

?>

<tr>

<td><?= $i++ ?></td>
<td><?= $row['user_employee'] ?? '-' ?></td>
<td><?= $row['user_project'] ?? '-' ?></td>
<td class="fw-bold text-primary"><?= $row['no_pc'] ?></td>

<td><?= $spec ?></td>

<!-- ปีผลิต -->
<td>
<form method="post">
<input type="hidden" name="asset_id" value="<?= $row['asset_id'] ?>">
<input type="month" name="yfm_1"
value="<?= $row['yfm_1'] ? date('Y-m',strtotime($row['yfm_1'])) : '' ?>"
class="form-control form-control-sm">
<button name="save_yfm" class="btn btn-sm btn-primary mt-1">💾</button>
</form>
</td>

<td><?= $age1 ?> ปี</td>

<!-- ปีใช้งาน -->
<td>
<form method="post">
<input type="hidden" name="asset_id" value="<?= $row['asset_id'] ?>">
<input type="month" name="yfm_2"
value="<?= $row['yfm_2'] ? date('Y-m',strtotime($row['yfm_2'])) : '' ?>"
class="form-control form-control-sm">
<button name="save_yfm2" class="btn btn-sm btn-success mt-1">💾</button>
</form>
</td>

<td><?= $age2 ?> ปี</td>

<!-- มูลค่า -->
<td>
<form method="post">
<input type="hidden" name="asset_id" value="<?= $row['asset_id'] ?>">
<input type="number" name="Machine_value"
value="<?= $row['Machine_value'] ?>"
class="form-control form-control-sm">
<button name="save_value" class="btn btn-sm btn-success mt-1">💾</button>
</form>
</td>

<td><span class="<?= $badge ?>"><?= $status ?></span></td>

<td>
<button class="btn btn-warning btn-sm">✏️</button>
</td>

<td>
<a href="?delete=<?= $row['asset_id'] ?>" class="btn btn-danger btn-sm">🗑</a>
</td>

</tr>

<?php endforeach; ?>
</tbody>
</table>
</div>

<!-- PAGINATION -->
<nav>
<ul class="pagination justify-content-center">

<?php
$start = max(1, $page-5);
$end = min($total_pages, $page+5);

for($p=$start;$p<=$end;$p++):
?>

<li class="page-item <?= $p==$page?'active':'' ?>">
<a class="page-link" href="?page=<?= $p ?>&type=<?= $type ?>"><?= $p ?></a>
</li>

<?php endfor; ?>

</ul>
</nav>

</div>
</div>
</div>

<script>
// realtime search
document.getElementById("searchInput").addEventListener("keyup", function(){
let v=this.value.toLowerCase();
document.querySelectorAll("#tableBody tr").forEach(tr=>{
tr.style.display = tr.innerText.toLowerCase().includes(v)?'':'none';
});
});
</script>

<?php include 'partials/footer.php'; ?>