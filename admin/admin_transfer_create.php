<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];
$user = $_SESSION['fullname'];

/* ===============================
โหลดโครงการ
=============================== */
$stmt = $conn->prepare("
SELECT DISTINCT site 
FROM Employee
WHERE site IS NOT NULL
AND site <> ?
ORDER BY site
");
$stmt->execute([$site]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ===============================
โหลด assets
=============================== */
$stmt = $conn->query("
SELECT no_pc, type_equipment, spec, ram, ssd, gpu
FROM IT_assets
ORDER BY no_pc
");
$assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ===============================
SUBMIT
=============================== */
if(isset($_POST['submit'])){

$type = $_POST['transfer_type'];
$to   = $_POST['to_site'];
$items = $_POST['asset_ids'] ?? [];

if(empty($items)){
    echo "<script>alert('กรุณาเลือกอุปกรณ์');</script>";
}else{

$stmtRound = $conn->query("
SELECT ISNULL(MAX(sent_transfer),0)+1 AS round_transfer
FROM IT_AssetTransfer_Headers
");
$r = $stmtRound->fetch(PDO::FETCH_ASSOC);
$round = $r['round_transfer'];

$stmt = $conn->prepare("
INSERT INTO IT_AssetTransfer_Headers
(sent_transfer,transfer_type,from_site,to_site,created_by,admin_status,no_pc,type)
VALUES (?,?,?,?,?,?,?,?)
");

foreach($items as $code){

    foreach($assets as $a){
        if($a['no_pc'] == $code){

            $stmt->execute([
                $round,
                $type,
                $site,
                $to,
                $user,
                'อนุมัติ',
                $a['no_pc'],
                $a['type_equipment']
            ]);
        }
    }
}

header("Location: admin_transfer_sent.php");
exit;
}
}

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<style>
.card-header{
    background:linear-gradient(135deg,#0d6efd,#4dabf7);
    color:white;
}

.search-box{
    border-radius:10px;
}

.filter-box{
    border-radius:10px;
}

.table-hover tbody tr:hover{
    background:#e7f1ff;
}

.counter{
    font-weight:bold;
    color:#0d6efd;
}
</style>

<div class="container mt-4">

<div class="card">

<div class="card-header">
🚚 ส่งอุปกรณ์ (Admin)
</div>

<div class="card-body">

<form method="post">

<!-- 🔥 FILTER ZONE -->
<div class="row mb-3">

<div class="col-md-4">
<input type="text" id="search" class="form-control search-box" placeholder="🔍 ค้นหารหัส / ประเภท">
</div>

<div class="col-md-3">
<select id="typeFilter" class="form-control filter-box">
<option value="">-- ทุกประเภท --</option>
<?php
$types = array_unique(array_column($assets,'type_equipment'));
foreach($types as $t){
    echo "<option value='$t'>$t</option>";
}
?>
</select>
</div>

<div class="col-md-5 text-end">
เลือกแล้ว: <span id="countSelect" class="counter">0</span> รายการ
</div>

</div>

<hr>

<!-- 🔥 FORM -->
<div class="row mb-3">

<div class="col-md-4">
<select name="transfer_type" class="form-control">
<option value="ส่งมอบ">ส่งมอบ</option>
</select>
</div>

<div class="col-md-4">
<input class="form-control" value="<?= $site ?>" readonly>
</div>

<div class="col-md-4">
<select name="to_site" class="form-control">
<?php foreach($projects as $p): ?>
<option><?= $p['site'] ?></option>
<?php endforeach; ?>
</select>
</div>

</div>

<table class="table table-bordered table-hover text-center" id="assetTable">

<thead>
<tr>
<th><input type="checkbox" id="checkAll"></th>
<th>รหัส</th>
<th>ประเภท</th>
<th>สเปค</th>
</tr>
</thead>

<tbody>

<?php foreach($assets as $a): ?>
<tr data-type="<?= $a['type_equipment'] ?>">

<td>
<input type="checkbox" name="asset_ids[]" value="<?= $a['no_pc'] ?>" class="item">
</td>

<td class="code"><?= $a['no_pc'] ?></td>
<td class="type"><?= $a['type_equipment'] ?></td>
<td class="spec">
<?php
$spec = trim(
    ($a['spec'] ?? '') . ' ' .
    ($a['ram'] ?? '') . ' ' .
    ($a['ssd'] ?? '') . ' ' .
    ($a['gpu'] ?? '')
);

if($spec == ''){
    echo "<span class='badge bg-secondary'>ไม่มีข้อมูล</span>";
}else{
    echo "<span class='badge bg-light text-dark'>$spec</span>";
}
?>
</td>

</tr>
<?php endforeach; ?>

</tbody>

</table>

<div class="text-end">
<button class="btn btn-primary" name="submit">
📨 ส่งอุปกรณ์
</button>
</div>

</form>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>

<script>

/* =====================================================
🔍 SEARCH + FILTER REALTIME
===================================================== */

const search = document.getElementById("search");
const typeFilter = document.getElementById("typeFilter");
const rows = document.querySelectorAll("#assetTable tbody tr");

function filterTable(){

let keyword = search.value.toLowerCase();
let typeVal = typeFilter.value;

rows.forEach(row=>{

let code = row.querySelector(".code").innerText.toLowerCase();
let type = row.querySelector(".type").innerText;

let matchSearch = code.includes(keyword) || type.toLowerCase().includes(keyword);
let matchType = !typeVal || type === typeVal;

row.style.display = (matchSearch && matchType) ? "" : "none";

});

}

search.addEventListener("keyup", filterTable);
typeFilter.addEventListener("change", filterTable);


/* =====================================================
☑️ SELECT ALL (เฉพาะที่มองเห็น)
===================================================== */

document.getElementById("checkAll").addEventListener("change", function(){

rows.forEach(row=>{
if(row.style.display !== "none"){
row.querySelector(".item").checked = this.checked;
}
});

updateCount();
});


/* =====================================================
📊 COUNT SELECT
===================================================== */

const checkboxes = document.querySelectorAll(".item");
const counter = document.getElementById("countSelect");

function updateCount(){
let count = 0;
checkboxes.forEach(cb=>{
if(cb.checked) count++;
});
counter.innerText = count;
}

checkboxes.forEach(cb=>{
cb.addEventListener("change", updateCount);
});

</script>