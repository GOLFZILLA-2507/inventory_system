<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* =====================================================
🔥 รับค่า filter
===================================================== */
$project = $_GET['project'] ?? '';
$keyword = $_GET['keyword'] ?? '';

/* =====================================================
🔥 โหลด "โครงการ" จาก IT_user_information
===================================================== */
$projects = $conn->query("
SELECT DISTINCT user_project 
FROM IT_user_information
WHERE user_project IS NOT NULL
ORDER BY user_project
")->fetchAll(PDO::FETCH_COLUMN);


/* =====================================================
🔥 Query อุปกรณ์ (จาก IT_assets)
===================================================== */
$sql = "
SELECT *
FROM IT_assets
WHERE 1=1
";

$params = [];

/* ===============================
🔥 filter โครงการ
(ใช้ project จาก IT_assets)
=============================== */
if($project != ''){
    $sql .= " AND project = ?";
    $params[] = $project;
}

/* ===============================
🔥 search
=============================== */
if($keyword != ''){
    $sql .= " 
    AND (
        no_pc LIKE ?
        OR Equipment_details LIKE ?
    )";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}

$sql .= " ORDER BY [update] DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="container mt-4">

<div class="card shadow">

<div class="card-header bg-dark text-white">
💻 อุปกรณ์ทั้งหมด (Admin)
</div>

<div class="card-body">

<!-- =====================================================
🔥 FILTER
===================================================== -->
<form method="get" class="row mb-3">

<!-- 🔹 โครงการ -->
<div class="col-md-3">
<select name="project" class="form-control">
<option value="">-- ทุกโครงการ --</option>

<?php foreach($projects as $p){ ?>
<option value="<?= $p ?>" <?= $p==$project?'selected':'' ?>>
<?= $p ?>
</option>
<?php } ?>

</select>
</div>

<!-- 🔹 ค้นหา -->
<div class="col-md-4">
<input type="text" 
name="keyword" 
class="form-control"
placeholder="ค้นหา รหัส / รายละเอียด"
value="<?= htmlspecialchars($keyword) ?>">
</div>

<!-- 🔹 ปุ่ม -->
<div class="col-md-2">
<button class="btn btn-primary w-100">ค้นหา</button>
</div>

</form>

<!-- =====================================================
🔥 TABLE
===================================================== -->
<table class="table table-bordered table-hover">

<thead class="table-secondary text-center">
<tr>
<th>#</th>
<th>รหัส</th>
<th>ประเภท</th>
<th>รายละเอียด</th>
<th>โครงการ</th>
<th>Spec</th>
</tr>
</thead>

<tbody>

<?php if(empty($data)){ ?>
<tr>
<td colspan="6" class="text-center text-muted">
ไม่พบข้อมูล
</td>
</tr>
<?php } ?>

<?php $i=1; foreach($data as $d){ ?>

<tr>

<td class="text-center"><?= $i++ ?></td>

<td class="fw-bold text-primary">
<?= htmlspecialchars($d['no_pc']) ?>
</td>

<td>
<?= htmlspecialchars($d['type_equipment']) ?>
</td>

<td>
<?= htmlspecialchars($d['Equipment_details']) ?>
</td>

<td>
<?= htmlspecialchars($d['project']) ?>
</td>

<td>
<?= htmlspecialchars($d['ram']) ?> |
<?= htmlspecialchars($d['ssd']) ?> |
<?= htmlspecialchars($d['gpu']) ?>
</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>