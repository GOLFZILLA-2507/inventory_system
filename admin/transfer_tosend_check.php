<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];
$round = $_GET['round'] ?? 0;

/* =====================================================
📦 โหลดรายการ
===================================================== */
$stmt = $conn->prepare("
SELECT *
FROM IT_AssetTransfer_Headers
WHERE sent_transfer = ?
AND to_site = ?
ORDER BY transfer_id
");
$stmt->execute([$round,$site]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
🔥 คำนวณสถานะรอบ
===================================================== */
$total = count($data);
$received = 0;
$cancelled = 0;

foreach($data as $d){
    if($d['receive_status']=='รับแล้ว') $received++;
    if($d['receive_status']=='ยกเลิก') $cancelled++;
}

/* =====================================================
🔥 สถานะรอบ
===================================================== */
if(($received + $cancelled) == $total){

    if($cancelled > 0){
        $roundStatus = '<span class="badge bg-danger">มีรายการถูกยกเลิก</span>';
    }else{
        $roundStatus = '<span class="badge bg-success">ตรวจรับแล้ว</span>';
    }

    $isComplete = true;

}elseif($received > 0 || $cancelled > 0){

    $roundStatus = '<span class="badge bg-warning text-dark">ตรวจรับบางรายการ</span>';
    $isComplete = false;

}else{

    $roundStatus = '<span class="badge bg-secondary">ยังไม่ตรวจรับ</span>';
    $isComplete = false;
}

/* =====================================================
📨 SUBMIT
===================================================== */
if($_SERVER['REQUEST_METHOD']=='POST'){

    try{
        $conn->beginTransaction();

        foreach($_POST['status'] as $id => $status){

            // 🔴 รับแล้ว → ข้าม (กันยิงซ้ำ)
            $check = $conn->prepare("
            SELECT receive_status FROM IT_AssetTransfer_Headers
            WHERE transfer_id=?
            ");
            $check->execute([$id]);
            $current = $check->fetchColumn();

            if($current == 'รับแล้ว') continue;

            /* ===============================
            ✅ รับ
            =============================== */
            if($status == 'รับ'){

                $conn->prepare("
                UPDATE IT_AssetTransfer_Headers
                SET receive_status='รับแล้ว',
                    receive_date=GETDATE(),
                    arrived_date=GETDATE()
                WHERE transfer_id=?
                ")->execute([$id]);

                // 🔥 update asset
                $conn->prepare("
                UPDATE IT_assets
                SET use_it=?
                WHERE no_pc = (
                    SELECT no_pc FROM IT_AssetTransfer_Headers WHERE transfer_id=?
                )
                ")->execute([$site,$id]);
            }

            /* ===============================
            ❌ ไม่พบ (แก้ใหม่)
            =============================== */
            elseif($status == 'ไม่พบ'){

                $conn->prepare("
                UPDATE IT_AssetTransfer_Headers
                SET receive_status='ไม่พบอุปกรณ์นี้'
                WHERE transfer_id=?
                ")->execute([$id]);
            }
        }

        $conn->commit();

        header("Location: transfer_tosend_check.php?round=$round&success=1");
        exit;

    }catch(Exception $e){
        $conn->rollBack();
        header("Location: transfer_tosend_check.php?round=$round&error=1");
        exit;
    }
}
?>

<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* 🔥 พื้นหลังรวม */
body{
    background:linear-gradient(135deg,#e3f2fd,#ffffff);
}

/* 🔥 card */
.card{
    border-radius:12px;
}

/* 🔥 header */
.card-header{
    background:linear-gradient(135deg,#2196f3,#64b5f6);
    color:white;
}

/* 🔥 ตาราง */
.table{
    border-radius:10px;
    overflow:hidden;
}

/* 🔥 หัวตาราง */
.table thead tr th{
    background:#e3f2fd !important;
    color:#0d47a1;
    font-weight:bold;
}

/* 🔥 แถวสลับสี */
.table tbody tr:nth-child(odd){
    background:#f8fbff;
}

.table tbody tr:nth-child(even){
    background:#eef6ff;
}

/* 🔥 hover */
.table tbody tr:hover{
    background:#dbeeff !important;
    transition:0.2s;
}

/* 🔴 แถวที่ยกเลิก */
.table-secondary{
    background:#f1f1f1 !important;
    color:#999;
}

/* 🔥 select */
.form-control{
    border-radius:8px;
}

/* 🔥 ปุ่ม */
.btn-primary{
    background:linear-gradient(135deg,#2196f3,#42a5f5);
    border:none;
}
</style>


<div class="container mt-4">
<div class="card shadow">

<div class="card-header d-flex justify-content-between">
<span>
📦 ตรวจรับอุปกรณ์ (รอบ <?= $round ?>)
<?= $roundStatus ?>
</span>

<a href="transfer_receive_check.php" class="btn btn-light btn-sm">ย้อนกลับ</a>
</div>

<div class="card-body">

<form method="post" id="formReceive">

<table class="table table-bordered text-center align-middle">

<tr>
<th>#</th>
<th>สถานะ</th>
<th>จาก</th>
<th>รหัส</th>
<th>ประเภท</th>
<th>Spec</th>
</tr>

<?php $i=1; foreach($data as $d): ?>
<tr class="<?= $d['receive_status']=='ยกเลิก' ? 'table-secondary' : '' ?>">

<td><?= $i++ ?></td>

<td>

<?php
// 🔴 รับแล้ว → ห้ามเลือก
if($d['receive_status']=='รับแล้ว'){
    echo '<span class="badge bg-success">รับแล้ว</span>';
}

// 🔴 ยกเลิก → ห้ามเลือก
elseif($d['receive_status']=='ยกเลิก'){
    echo '<span class="badge bg-danger">ยกเลิก</span>';
}

// 🔴 ไม่พบ → ยังเลือกได้ (เผื่อหาเจอทีหลัง)
elseif($d['receive_status']=='ไม่พบอุปกรณ์นี้'){
?>
<select name="status[<?= $d['transfer_id'] ?>]" class="form-control">
<option value="">-- เลือก --</option>
<option value="รับ">✅ รับ</option>
<option value="ไม่พบ" selected>❌ ไม่พบ</option>
</select>
<?php
}

// 🟡 ยังไม่ตรวจรับ
else{
?>
<select name="status[<?= $d['transfer_id'] ?>]" class="form-control">
<option value="">-- เลือก --</option>
<option value="รับ">✅ รับ</option>
<option value="ไม่พบ">❌ ไม่พบ</option>
</select>
<?php } ?>

</td>

<td><?= $d['from_site'] ?></td>
<td><?= $d['no_pc'] ?></td>
<td><?= $d['type'] ?></td>
<td><?= $d['spec'] ?? '-' ?></td>

</tr>
<?php endforeach; ?>

</table>

<?php if(!$isComplete): ?>
<button type="button" id="btnConfirm" class="btn btn-primary w-100">
✔ ยืนยันการตรวจรับ
</button>
<?php endif; ?>

</form>

</div>
</div>
</div>

<script>
/* ================= CONFIRM ================= */
let btn = document.getElementById('btnConfirm');

if(btn){
btn.onclick=function(){

Swal.fire({
title:'ยืนยันการตรวจรับ',
text:'คุณแน่ใจหรือไม่?',
icon:'question',
showCancelButton:true,
confirmButtonText:'ยืนยัน',
cancelButtonText:'ยกเลิก'
}).then(res=>{
if(res.isConfirmed){
document.getElementById('formReceive').submit();
}
});
};
}

/* ================= RESULT ================= */
<?php if(isset($_GET['success'])): ?>
Swal.fire({
icon:'success',
title:'บันทึกสำเร็จ'
});
<?php endif; ?>

<?php if(isset($_GET['error'])): ?>
Swal.fire({
icon:'error',
title:'เกิดข้อผิดพลาด'
});
<?php endif; ?>
</script>

<?php include 'partials/footer.php'; ?>