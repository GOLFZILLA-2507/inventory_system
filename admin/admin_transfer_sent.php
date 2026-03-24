<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* =====================================================
🔥 PARAM
===================================================== */
$filter_site = $_GET['site'] ?? 'ทุกโครงการ'; // filter โครงการ
$status = $_GET['status'] ?? ''; // filter สถานะ

$page = $_GET['page'] ?? 1; // pagination
$limit = 15; // จำนวนรายการต่อหน้า
$offset = ($page - 1) * $limit; // คำนวณ offset สำหรับ SQL

/* =====================================================
🔥 สร้าง SQL (สำคัญมาก)
===================================================== */
$sql = "
SELECT 
    sent_transfer,
    from_site,
    to_site,
    transfer_type,

    MIN(transfer_date) AS transfer_date,
    COUNT(*) AS total_items,

    MAX(receive_status) AS receive_status,
    MAX(admin_status) AS admin_status

    FROM IT_AssetTransfer_Headers
    WHERE sent_transfer IS NOT NULL

    AND (
        ? = 'ทุกโครงการ'
        OR from_site = ?
    )
    ";

/* =====================================================
🔥 filter สถานะ (ต้องอยู่นอก SQL เท่านั้น)
===================================================== */
    if($status == 'cancel'){
        $sql .= " AND receive_status = 'ยกเลิก' ";
    }
    elseif($status == 'waiting'){
        $sql .= " AND admin_status = 'รออนุมัติ' ";
    }
    elseif($status == 'received'){
        $sql .= " AND receive_status = 'รับแล้ว' ";
    }
/* =====================================================
🔥 GROUP + PAGINATION
===================================================== */
$sql .= "
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
$stmt->execute([$filter_site,$filter_site,$filter_site]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

<div class="col-md-6">
<select name="status" class="form-control" onchange="this.form.submit()">
<option value="">-- ทุกสถานะ --</option>
<option value="waiting" <?= $status=='waiting'?'selected':'' ?>>รออนุมัติ</option>
<option value="received" <?= $status=='received'?'selected':'' ?>>รับแล้ว</option>
<option value="cancel" <?= $status=='cancel'?'selected':'' ?>>ยกเลิก</option>

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
<th>สถานะ</th>
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

<td class="from">
    <span class="badge bg-gray text-dark">
<?= htmlspecialchars($d['from_site']) ?>
</span>
</td>

<td class="to">
<span class="badge bg-gray text-dark">
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
if($d['receive_status'] == 'ยกเลิก'){
    echo "<span class='badge bg-danger'>❌ ยกเลิก</span>";
}
elseif($d['admin_status'] == 'รออนุมัติ'){
    echo "<span class='badge bg-warning text-dark'>⏳ รออนุมัติ</span>";
}
elseif($d['receive_status'] == 'รับแล้ว'){
    echo "<span class='badge bg-success'>✅ รับแล้ว</span>";
}
else{
    echo "<span class='badge bg-secondary'>ไม่ทราบสถานะ</span>";
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

<!-- 🔥 PAGINATION -->
<div class="text-center mt-3">

<a href="?page=<?= max(1,$page-1) ?>&site=<?= $filter_site ?>&status=<?= $status ?>" 
class="btn btn-primary">
⬅ ย้อนกลับ
</a>

<span class="mx-3">หน้า <?= $page ?></span>

<a href="?page=<?= $page+1 ?>&site=<?= $filter_site ?>&status=<?= $status ?>" 
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