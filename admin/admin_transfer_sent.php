<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* ================= PARAM ================= */
$filter_site = $_GET['site'] ?? 'ทุกโครงการ';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$page = $_GET['page'] ?? 1;
$limit = 10;
$offset = ($page - 1) * $limit;

/* ================= COUNT TOTAL PAGE ================= */
$countSql = "
SELECT COUNT(DISTINCT sent_transfer)
FROM IT_AssetTransfer_Headers
WHERE sent_transfer IS NOT NULL
AND ( ? = 'ทุกโครงการ' OR from_site = ? )
";

$countParams = [$filter_site,$filter_site];

/* 🔥 search */
if($search){
    $countSql .= " AND (
        sent_transfer LIKE ?
        OR from_site LIKE ?
        OR to_site LIKE ?
    ) ";
    $countParams[] = "%$search%";
    $countParams[] = "%$search%";
    $countParams[] = "%$search%";
}

/* 🔥 filter */
if($status == 'cancel'){
    $countSql .= " AND receive_status = 'ยกเลิก' ";
}
elseif($status == 'waiting'){
    $countSql .= " AND admin_status = 'รออนุมัติ' ";
}
elseif($status == 'received'){
    $countSql .= " AND receive_status = 'รับแล้ว' ";
}

$stmtCount = $conn->prepare($countSql);
$stmtCount->execute($countParams);
$totalRows = $stmtCount->fetchColumn();

/* 🔥 คำนวณหน้า */
$totalPages = ceil($totalRows / $limit);

/* 🔥 กัน page เกิน */
if($page > $totalPages) $page = $totalPages;
if($page < 1) $page = 1;

/* ================= SQL ================= */
$sql = "
SELECT 
    sent_transfer,
    from_site,
    to_site,
    transfer_type,

    MIN(transfer_date) AS transfer_date,
    COUNT(*) AS total_items,

    SUM(CASE WHEN receive_status = 'รับแล้ว' THEN 1 ELSE 0 END) AS received_count,
    SUM(CASE WHEN receive_status = 'ยกเลิก' THEN 1 ELSE 0 END) AS cancel_count,
    SUM(CASE WHEN receive_status IN ('รอตรวจรับ','ไม่พบอุปกรณ์นี้') THEN 1 ELSE 0 END) AS pending_count,
    SUM(CASE WHEN admin_status = 'รออนุมัติ' THEN 1 ELSE 0 END) AS waiting_count

FROM IT_AssetTransfer_Headers

WHERE sent_transfer IS NOT NULL

/* 🔥 filter โครงการ */
AND ( ? = 'ทุกโครงการ' OR from_site = ? )

/* 🔥 search */
";

/* 🔥 SEARCH */
if($search){
    $sql .= " AND (
        sent_transfer LIKE ?
        OR from_site LIKE ?
        OR to_site LIKE ?
    ) ";
}

/* 🔥 FILTER STATUS */
if($status == 'cancel'){
    $sql .= " AND receive_status = 'ยกเลิก' ";
}
elseif($status == 'waiting'){
    $sql .= " AND admin_status = 'รออนุมัติ' ";
}
elseif($status == 'received'){
    $sql .= " AND receive_status = 'รับแล้ว' ";
}

/* ================= GROUP ================= */
$sql .= "
GROUP BY 
    sent_transfer,
    from_site,
    to_site,
    transfer_type
";

/* 🔥 กัน received เพี้ยน */
if($status == 'received'){
    $sql .= " HAVING SUM(CASE WHEN receive_status = 'รับแล้ว' THEN 1 ELSE 0 END) = COUNT(*) ";
}

/* ================= PAGE ================= */
$sql .= "
ORDER BY sent_transfer DESC
OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY
";

/* ================= EXECUTE ================= */
$params = [$filter_site,$filter_site];

if($search){
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= SITE ================= */
$siteList = $conn->query("
SELECT DISTINCT from_site FROM IT_AssetTransfer_Headers
")->fetchAll(PDO::FETCH_COLUMN);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<style>
/* 🔥 hover animation เบาๆ */
.table-hover tbody tr{
    transition: all 0.2s ease;
}
.table-hover tbody tr:hover{
    background:#eef5ff;
    transform: scale(1.01);
}
</style>

<div class="container mt-4">
<div class="card shadow">

<div class="card-header bg-primary text-white">
📦 ประวัตการโอนย้าย
</div>

<div class="card-body">

<!-- 🔥 FILTER + SEARCH -->
<form method="get" class="row mb-3">

<div class="col-md-3">
<input type="text" name="search" value="<?= $search ?>" 
class="form-control" placeholder="🔍 ค้นหา">
</div>

<div class="col-md-3">
<select name="site" class="form-control">
<option>ทุกโครงการ</option>
<?php foreach($siteList as $s): ?>
<option value="<?= $s ?>" <?= $filter_site==$s?'selected':'' ?>>
<?= $s ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-3">
<select name="status" class="form-control">
<option value="">-- ทุกสถานะ --</option>
<option value="waiting" <?= $status=='waiting'?'selected':'' ?>>รออนุมัติ</option>
<option value="received" <?= $status=='received'?'selected':'' ?>>รับครบ</option>
<option value="cancel" <?= $status=='cancel'?'selected':'' ?>>ยกเลิก</option>
</select>
</div>

<div class="col-md-3 d-flex gap-2">
<button class="btn btn-primary w-100">ค้นหา</button>
<a href="admin_transfer_sent.php" class="btn btn-secondary w-100">ล้าง</a>
</div>

</form>

<table class="table table-bordered table-hover text-center">

<thead class="table-primary">
<tr>
<th>#</th>
<th>รอบ</th>
<th>จาก</th>
<th>ไป</th>
<th>จำนวน</th>
<th>รับแล้ว</th>
<th>สถานะ</th>
<th>จัดการ</th>
</tr>
</thead>

<tbody>

<?php $i=1; foreach($data as $d): ?>

<tr>
<td><?= $i++ ?></td>

<td>
<span class="badge bg-primary">
ครั้งที่ <?= $d['sent_transfer'] ?>
</span>
</td>

<td><?= $d['from_site'] ?></td>
<td><?= $d['to_site'] ?></td>

<td><?= $d['total_items'] ?></td>

<td>
<span class="badge bg-success">
<?= $d['received_count'] ?> / <?= $d['total_items'] ?>
</span>
</td>

<td>
<?php
if($d['cancel_count'] > 0){
    echo "<span class='badge bg-danger'>❌ ยกเลิก</span>";
}
elseif($d['waiting_count'] > 0){
    echo "<span class='badge bg-warning text-dark'>⏳ รออนุมัติ</span>";
}
elseif($d['pending_count'] > 0){
    echo "<span class='badge bg-info'>📦 รอตรวจรับ</span>";
}
elseif($d['received_count'] == $d['total_items']){
    echo "<span class='badge bg-success'>✅ รับครบ</span>";
}
else{
    echo "<span class='badge bg-secondary'>ไม่ทราบ</span>";
}
?>
</td>

<td>
<a href="approve_transfer_detail.php?round=<?= $d['sent_transfer'] ?>" 
class="btn btn-primary btn-sm">
🔍 อนุมัติ
</a>
</td>

</tr>

<?php endforeach; ?>

</tbody>
</table>

<div class="d-flex justify-content-center align-items-center mt-3 gap-2">

<!-- 🔙 ย้อนกลับ -->
<a href="?page=<?= max(1,$page-1) ?>&site=<?= $filter_site ?>&status=<?= $status ?>&search=<?= $search ?>" 
class="btn btn-primary <?= $page<=1?'disabled':'' ?>">
⬅ ย้อนกลับ
</a>

<!-- 🔢 แสดงหน้า -->
<span class="fw-bold">
หน้า <?= $page ?> / <?= $totalPages ?>
</span>

<!-- 🔜 ถัดไป -->
<a href="?page=<?= min($totalPages,$page+1) ?>&site=<?= $filter_site ?>&status=<?= $status ?>&search=<?= $search ?>" 
class="btn btn-primary <?= $page>=$totalPages?'disabled':'' ?>">
ถัดไป ➡
</a>

</div>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>