<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* =====================================================
🔥 PARAM
===================================================== */
$filter_site = $_GET['site'] ?? 'ทุกโครงการ';

$page = $_GET['page'] ?? 1;
$limit = 10;
$offset = ($page - 1) * $limit;

/* =====================================================
🔥 SQL (แก้ใหม่)
👉 ตัด "ยกเลิก" ออกทั้งระบบ
===================================================== */
$sql = "
SELECT 
    sent_transfer,
    from_site,
    to_site,
    transfer_type,

    MIN(transfer_date) AS transfer_date,
    COUNT(*) AS total_items

FROM IT_AssetTransfer_Headers

WHERE admin_status = 'รออนุมัติ'
AND sent_transfer IS NOT NULL

-- 🔥 filter โครงการ
AND (
    ? = 'ทุกโครงการ'
    OR from_site = ?
)

-- 🔥 ❗ ตัดรายการยกเลิกออก
AND (receive_status IS NULL OR receive_status != 'ยกเลิก')

GROUP BY 
    sent_transfer,
    from_site,
    to_site,
    transfer_type

ORDER BY sent_transfer DESC
OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY
";

/* =====================================================
🔥 EXECUTE
===================================================== */
$stmt = $conn->prepare($sql);
$stmt->execute([$filter_site,$filter_site]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
🔥 dropdown project
===================================================== */
$siteList = $conn->query("
SELECT DISTINCT from_site 
FROM IT_AssetTransfer_Headers
WHERE from_site IS NOT NULL
ORDER BY from_site
")->fetchAll(PDO::FETCH_COLUMN);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<style>
.card-header{
    background:linear-gradient(135deg,#0d6efd,#4dabf7);
    color:white;
}
.table-hover tbody tr:hover{
    background:#e7f1ff;
}
.badge-round{
    background:#0d6efd;
}
</style>

<div class="container mt-4">

<div class="card shadow">

<div class="card-header">
📦 ประวัตการโอนย้าย/ส่งมอบ อุปกรณ์
</div>

<div class="card-body">

<!-- 🔍 SEARCH + FILTER -->
<div class="row mb-3">

<div class="col-md-4">
<input type="text" id="search" class="form-control" placeholder="🔍 ค้นหา (รอบ / โครงการ)">
</div>

<div class="col-md-4">
<form method="get" class="row">

<div class="col-md-6">
<select name="site" class="form-control" onchange="this.form.submit()">

<option value="ทุกโครงการ">ทุกโครงการ</option>

<?php foreach($siteList as $s): ?>
<option value="<?= $s ?>" <?= $filter_site==$s?'selected':'' ?>>
<?= $s ?>
</option>
<?php endforeach; ?>

</select>
</div>

</form>
</div>

</div>

<table class="table table-bordered table-hover text-center">

<thead class="table-primary">
<tr>
<th>#</th>
<th>รอบ</th>
<th>จาก</th>
<th>ไป</th>
<th>ประเภท</th>
<th>จำนวน</th>
<th>วันที่</th>
<th>จัดการ</th>
</tr>
</thead>

<tbody id="tableBody">

<?php $i = $offset + 1; foreach($data as $d): ?>

<tr>

<td><?= $i++ ?></td>

<td class="round">
<span class="badge badge-round">
ครั้งที่ <?= $d['sent_transfer'] ?>
</span>
</td>

<td>
<?= htmlspecialchars($d['from_site']) ?>
</td>

<td >
<?= htmlspecialchars($d['to_site']) ?>
</td>

<td><?= htmlspecialchars($d['transfer_type']) ?></td>

<td>
<span class="badge bg-success">
<?= $d['total_items'] ?> รายการ
</span>
</td>

<td><?= $d['transfer_date'] ?></td>

<td>
<a href="approve_transfer_detail.php?round=<?= $d['sent_transfer'] ?>" 
class="btn btn-primary btn-sm">
🔍 ดูรายละเอียด
</a>
</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<!-- 🔥 PAGINATION -->
<div class="text-center mt-3">

<a href="?page=<?= max(1,$page-1) ?>&site=<?= $filter_site ?>" 
class="btn btn-primary">
⬅ ย้อนกลับ
</a>

<span class="mx-3">หน้า <?= $page ?></span>

<a href="?page=<?= $page+1 ?>&site=<?= $filter_site ?>" 
class="btn btn-primary">
ถัดไป ➡
</a>

</div>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>

<script>
const search = document.getElementById("search");
const rows = document.querySelectorAll("#tableBody tr");

search.addEventListener("keyup", function(){
let keyword = this.value.toLowerCase();

rows.forEach(row=>{
let round = row.querySelector(".round").innerText.toLowerCase();
let from = row.querySelector(".from").innerText.toLowerCase();
let to   = row.querySelector(".to").innerText.toLowerCase();

let match = round.includes(keyword) || from.includes(keyword) || to.includes(keyword);
row.style.display = match ? "" : "none";
});
});
</script>