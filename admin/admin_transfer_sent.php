<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* =====================================================
🔥 filter site (default = สำนักงานใหญ่)
===================================================== */
$filter_site = $_GET['site'] ?? 'สำนักงานใหญ่';

/* =====================================================
🔥 โหลดรายการที่ admin อนุมัติ
===================================================== */
$stmt = $conn->prepare("
SELECT 
    sent_transfer,
    from_site,
    to_site,
    transfer_type,

    MIN(transfer_date) AS transfer_date,
    COUNT(*) AS total_items,

    SUM(CASE WHEN receive_status='รับแล้ว' THEN 1 ELSE 0 END) AS received_items

FROM IT_AssetTransfer_Headers

WHERE admin_status = 'อนุมัติ'

-- 🔥 filter
AND (
    ? = 'ทุกโครงการ'
    OR from_site = ?
    OR to_site = ?
)

GROUP BY 
    sent_transfer,
    from_site,
    to_site,
    transfer_type

ORDER BY sent_transfer DESC
");

$stmt->execute([$filter_site,$filter_site,$filter_site]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
📦 รายการส่งจาก Admin (ส่งมอบให้หน้างาน)
</div>

<div class="card-body">

<!-- 🔍 SEARCH + FILTER -->
<div class="row mb-3">

<div class="col-md-4">
<input type="text" id="search" class="form-control" placeholder="🔍 ค้นหา (รอบ / โครงการ)">
</div>

<div class="col-md-4">
<form method="get">
<select name="site" class="form-control" onchange="this.form.submit()">

<option value="ทุกโครงการ" <?= $filter_site=='ทุกโครงการ'?'selected':'' ?>>
ทุกโครงการ
</option>

<option value="สำนักงานใหญ่" <?= $filter_site=='สำนักงานใหญ่'?'selected':'' ?>>
สำนักงานใหญ่
</option>

</select>
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
<th>สถานะ</th>
<th>จัดการ</th>
</tr>
</thead>

<tbody id="tableBody">

<?php $i=1; foreach($data as $d): ?>

<tr>

<td><?= $i++ ?></td>

<td class="round">
<span class="badge badge-round">
ครั้งที่ <?= $d['sent_transfer'] ?>
</span>
</td>

<td class="from">
<?= htmlspecialchars($d['from_site']) ?>
</td>

<td class="to">
<span class="badge bg-info">
<?= htmlspecialchars($d['to_site']) ?>
</span>
</td>

<td><?= htmlspecialchars($d['transfer_type']) ?></td>

<td>
<span class="badge bg-dark">
<?= $d['total_items'] ?> รายการ
</span>
</td>

<td><?= $d['transfer_date'] ?></td>

<td>
<?php
if($d['received_items'] == $d['total_items']){
    echo "<span class='badge bg-success'>✅ รับครบแล้ว</span>";
}
elseif($d['received_items'] > 0){
    echo "<span class='badge bg-warning text-dark'>📦 รับบางส่วน</span>";
}
else{
    echo "<span class='badge bg-secondary'>⏳ ยังไม่รับ</span>";
}
?>
</td>

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

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>

<!-- 🔍 SEARCH SCRIPT -->
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