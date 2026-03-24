<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$round = $_GET['round'] ?? 0;

/* =====================================================
🔥 อนุมัติรายการ
===================================================== */
if(isset($_POST['approve_selected'])){

    $ids = $_POST['ids'] ?? [];

    if(!empty($ids)){
        $in = str_repeat('?,', count($ids)-1) . '?';

        $stmt = $conn->prepare("
        UPDATE IT_AssetTransfer_Headers
        SET admin_status = 'อนุมัติ'
        WHERE transfer_id IN ($in)
        ");
        $stmt->execute($ids);
    }

    header("Location: approve_transfer_detail.php?round=".$round);
    exit;
}




/* =====================================================
🔥 โหลดรายการทั้งหมดในรอบ
===================================================== */
$stmt = $conn->prepare("
SELECT *
FROM IT_AssetTransfer_Headers
WHERE sent_transfer = ?
ORDER BY transfer_id DESC
");
$stmt->execute([$round]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
🔥 เช็คว่ามีรายการยกเลิกในรอบนี้ไหม
===================================================== */
$stmtCancel = $conn->prepare("
SELECT COUNT(*) 
FROM IT_AssetTransfer_Headers
WHERE sent_transfer = ?
AND receive_status = 'ยกเลิก'
");
$stmtCancel->execute([$round]);

$hasCancel = $stmtCancel->fetchColumn();

/* =====================================================
🔥 เช็คว่าอนุมัติครบหรือยัง
===================================================== */
$stmtCheck = $conn->prepare("
SELECT COUNT(*) 
FROM IT_AssetTransfer_Headers
WHERE sent_transfer = ?
AND admin_status != 'อนุมัติ'
AND (receive_status IS NULL OR receive_status != 'ยกเลิก')
");
$stmtCheck->execute([$round]);

$remain = $stmtCheck->fetchColumn();

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<style>

/* =====================================================
🔥 THEME น้ำเงินฟ้า
===================================================== */
.card-header{
    background:linear-gradient(135deg,#0d6efd,#4dabf7);
    color:white;
}

.table-hover tbody tr:hover{
    background:#e7f1ff;
}

/* 🔥 badge */
.badge-success{
    background:#198754;
}
.badge-warning{
    background:#ffc107;
    color:black;
}

/* 🔥 ปุ่ม */
.btn-main{
    background:#0d6efd;
    color:white;
}
.btn-main:hover{
    background:#0b5ed7;
}

.btn-back{
    background:#6c757d;
    color:white;
}
.btn-back:hover{
    background:#5c636a;
}

/* 🔥 checkbox */
input[type="checkbox"]{
    transform:scale(1.2);
    cursor:pointer;
}

/* 🔥 card */
.card{
    border-radius:12px;
    box-shadow:0 4px 15px rgba(0,0,0,0.1);
}

</style>

<div class="container mt-4">

<div class="card">

<div class="card-header">
<h5 class="mb-0">📦 รอบที่ <?= $round ?></h5>
</div>

<div class="card-body">

<!-- 🔥 แจ้งเตือน -->
<?php if($hasCancel > 0){ ?>

<div class="alert alert-danger">
❌ รอบรายการนี้มีรายการถูกยกเลิก
</div>

<?php } elseif($remain == 0){ ?>

<div class="alert alert-success">
✅ อนุมัติครบทุกรายการแล้ว
</div>

<?php } ?>


<form method="post">

<table class="table table-bordered table-hover text-center align-middle">

<thead class="table-primary">
<tr>
<th>
<input type="checkbox" id="checkAll">
</th>
<th>ID</th>
<th>รหัส</th>
<th>ประเภท</th>
<th>จาก</th>
<th>ไป</th>
<th>สถานะ</th>
</tr>
</thead>

<tbody>

<?php foreach($data as $d){ ?>



<tr>

<td>

<?php 
// ❗ ห้ามเลือกถ้า "ยกเลิก"
if($d['admin_status'] != 'อนุมัติ' && $d['receive_status'] != 'ยกเลิก'){ 
?>
<input type="checkbox" 
name="ids[]" 
value="<?= $d['transfer_id'] ?>" 
class="item">
<?php } ?>

</td>

<td><?= $d['transfer_id'] ?></td>

<td class="fw-bold text-primary">
<?= htmlspecialchars($d['no_pc']) ?>
</td>

<td><?= htmlspecialchars($d['type']) ?></td>

<td><?= htmlspecialchars($d['from_site']) ?></td>

<td><?= htmlspecialchars($d['to_site']) ?></td>

<td>

<?php if($d['receive_status'] == 'ยกเลิก'){ ?>

<span class="badge bg-danger">
❌ ถูกยกเลิก
</span>

<?php } elseif($d['admin_status'] == 'อนุมัติ'){ ?>

<span class="badge badge-success">
✅ อนุมัติแล้ว
</span>

<?php } else { ?>

<span class="badge badge-warning">
⏳ รออนุมัติ
</span>

<?php } ?>
</td>

</tr>

<?php } ?>

</tbody>

</table>

<div class="d-flex justify-content-between mt-3">

<!-- 🔥 ปุ่มย้อนกลับ -->
<a href="admin_transfer_sent.php" class="btn btn-back">
⬅️ ย้อนกลับ
</a>

<!-- 🔥 เงื่อนไขปุ่ม -->
<?php if($remain > 0){ ?>
<button class="btn btn-main" name="approve_selected">
✅ อนุมัติที่เลือก
</button>
<?php } ?>

</div>

</form>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>

<script>
document.getElementById('checkAll').addEventListener('change', function(){
    document.querySelectorAll('.item').forEach(cb=>{
        cb.checked = this.checked;
    });
});
</script>
