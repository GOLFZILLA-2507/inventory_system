<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';


/* =====================================================
🔐 TOKEN กันรีเฟซ / ยิงซ้ำ
===================================================== */
if(empty($_SESSION['cancel_token'])){
    $_SESSION['cancel_token'] = bin2hex(random_bytes(32));
}

/* =====================================================
📨 CANCEL รายการ (แบบเลือกได้)
===================================================== */
if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['cancel_ids'])){

    if(!isset($_POST['cancel_token']) || $_POST['cancel_token'] !== $_SESSION['cancel_token']){
        header("Location: transfer_s_project_list.php?error=duplicate"); exit;
    }

    unset($_SESSION['cancel_token']);

    $ids = $_POST['cancel_ids'];

    if(!empty($ids)){
        $in = str_repeat('?,', count($ids)-1) . '?';

        $stmt = $conn->prepare("
        UPDATE IT_AssetTransfer_Headers
        SET receive_status='ยกเลิก'
        WHERE transfer_id IN ($in)
        ");
        $stmt->execute($ids);
    }

    header("Location: transfer_s_project_list.php?success=cancel");
    exit;
}

/* =====================================================
📦 LOAD DATA
===================================================== */
$stmt = $conn->query("
SELECT 
sent_transfer,
MIN(to_site) as to_site,
COUNT(*) total,
SUM(CASE WHEN receive_status='รับแล้ว' THEN 1 ELSE 0 END) received,
MIN(transfer_date) as send_date,
MAX(arrived_date) as receive_date,
SUM(CASE WHEN receive_status='ยกเลิก' THEN 1 ELSE 0 END) cancel_count
FROM IT_AssetTransfer_Headers
GROUP BY sent_transfer
ORDER BY sent_transfer DESC
");

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* 🌊 ธีมฟ้าอมขาว */
body{
    background:linear-gradient(135deg,#e3f2fd,#ffffff);
    font-family:'Sarabun';
}

.card-header{
    background:linear-gradient(135deg,#2196f3,#64b5f6);
    color:white;
}

.table th{
    background:#e3f2fd;
}

.badge{
    font-size:12px;
}
</style>

<div class="container mt-4">
<div class="card shadow">

<div class="card-header">
📦 รายการส่งอุปกรณ์
</div>

<div class="card-body">

<table class="table table-bordered text-center align-middle">
<thead>
<tr>
<th>#</th>
<th>ปลายทาง</th>
<th>จำนวน</th>
<th>รับแล้ว</th>
<th>วันที่ส่ง</th>
<th>วันที่รับ</th>
<th>สถานะ</th>
<th>จัดการ</th>
<th>ปริ้น</th>
</tr>
</thead>

<tbody>

<?php $i=1; foreach($data as $d): ?>
<tr>

<td><?= $i++ ?></td>
<td><?= $d['to_site'] ?></td>
<td><?= $d['total'] ?></td>

<td>
<span class="badge bg-success">
<?= $d['received'] ?>/<?= $d['total'] ?>
</span>
</td>

<td><?= $d['send_date'] ?></td>
<td><?= $d['receive_date'] ?: '-' ?></td>

<td>
<?php
if($d['cancel_count'] > 0){
    echo "<span class='badge bg-danger'>ยกเลิก</span>";
}
elseif($d['received'] == $d['total']){
    echo "<span class='badge bg-success'>ครบแล้ว</span>";
}
else{
    echo "<span class='badge bg-warning text-dark'>รอดำเนินการ</span>";
}
?>
</td>

<td>
<button class="btn btn-danger btn-sm"
onclick="openCancelModal(<?= $d['sent_transfer'] ?>)">
ยกเลิก
</button>
</td>

<td>
<button class="btn btn-primary btn-sm"
onclick="printRound(<?= $d['sent_transfer'] ?>)">
🖨
</button>
</td>

</tr>
<?php endforeach; ?>

</tbody>
</table>

</div>
</div>
</div>

<!-- =====================================================
📌 MODAL CANCEL
===================================================== -->
<div class="modal fade" id="cancelModal">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">

<div class="modal-header bg-danger text-white">
<h5>ยกเลิกรายการ</h5>
</div>

<div class="modal-body">

<form method="post" id="cancelForm">

<input type="hidden" name="cancel_token" value="<?= $_SESSION['cancel_token'] ?>">

<table class="table table-bordered text-center">
<thead>
<tr>
<th>เลือก</th>
<th>รหัส</th>
<th>ประเภท</th>
<th>สถานะ</th>
</tr>
</thead>
<tbody id="cancelBody"></tbody>
</table>

</form>

</div>

<div class="modal-footer">
<button class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
<button class="btn btn-danger" onclick="confirmCancel()">ยืนยันยกเลิก</button>
</div>

</div>
</div>
</div>

<script>

/* =====================================================
📦 โหลดรายการเข้า modal
===================================================== */
function openCancelModal(round){

fetch('transfer_get_items.php?round='+round)
.then(res=>res.json())
.then(data=>{

let html='';

data.forEach(d=>{
html+=`
<tr>
<td><input type="checkbox" name="cancel_ids[]" value="${d.transfer_id}"></td>
<td>${d.no_pc}</td>
<td>${d.type}</td>
<td>${d.receive_status ?? '-'}</td>
</tr>`;
});

document.getElementById('cancelBody').innerHTML = html;

new bootstrap.Modal(document.getElementById('cancelModal')).show();

});
}

/* =====================================================
❌ CONFIRM CANCEL
===================================================== */
function confirmCancel(){

let checked = document.querySelectorAll('input[name="cancel_ids[]"]:checked');

if(checked.length==0){
Swal.fire('กรุณาเลือก'); return;
}

Swal.fire({
title:'ยืนยันยกเลิก?',
icon:'warning',
showCancelButton:true
}).then(res=>{
if(res.isConfirmed){
document.getElementById('cancelForm').submit();
}
});
}

/* =====================================================
🖨 PRINT ทั้งรอบ
===================================================== */
function printRound(round){
window.open('transfer_print.php?round='+round,'_blank');
}

/* =====================================================
📢 RESULT
===================================================== */
<?php if(isset($_GET['success'])): ?>
Swal.fire({icon:'success',title:'สำเร็จ'});
<?php endif; ?>

<?php if(isset($_GET['error'])): ?>
Swal.fire({icon:'error',title:'เกิดข้อผิดพลาด'});
<?php endif; ?>
</script>

<?php include 'partials/footer.php'; ?>