<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* =====================================================
🔥 MAP แสดงผล (ชื่อสเปค)
===================================================== */
$RANK_LABEL = [
    'ees' => 'วิศวะสเปคสูง',
    'ee'  => 'วิศวะทั่วไป',
    'hr'  => 'บุคคลและคลัง',
    '-'   => 'ไม่ระบุ'
];

/* =====================================================
🔥 ดึงข้อมูล (แยก type + rank_spec)
===================================================== */
$stmt = $conn->prepare("
SELECT 
    type_equipment,
    ISNULL(rank_spec,'-') AS rank_spec,
    COUNT(*) AS total,
    MIN(rental_price) AS rental_price
FROM IT_assets
GROUP BY type_equipment, rank_spec
ORDER BY type_equipment, rank_spec
");
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* =========================================
🔥 สีสเปค 
========================================= */
.rank-ees{
    background:#d32f2f;
    color:white;
}
.rank-ee{
    background:#1976d2;
    color:white;
}
.rank-hr{
    background:#388e3c;
    color:white;
}
.rank-default{
    background:#6c757d;
    color:white;
}
</style>

<div class="container mt-4">
<div class="card shadow">

<div class="card-header text-white" 
     style="background: linear-gradient(135deg,#2196f3,#00bcd4);">
    💰 จัดการราคาค่าเช่า (แยกตามสเปค)
</div>

<div class="card-body">

<!-- 🔍 ค้นหา -->
<input type="text" id="search" class="form-control mb-3"
placeholder="🔍 ค้นหา ประเภท / สเปค...">

<table class="table table-bordered table-hover text-center">

<tr class="table-primary">
    <th>#</th>
    <th>ประเภท</th>
    <th>สเปค</th>
    <th>จำนวน</th>
    <th>ค่าเช่า/วัน</th>
    <th>จัดการ</th>
</tr>

<?php $i=1; foreach($data as $d): 

$rank = strtolower($d['rank_spec']);

/* 🔥 เลือกสี */
$colorClass = match($rank){
    'ees' => 'rank-ees',
    'ee'  => 'rank-ee',
    'hr'  => 'rank-hr',
    default => 'rank-default'
};

?>

<tr>
<td><?= $i++ ?></td>

<td class="fw-bold"><?= $d['type_equipment'] ?></td>

<td>
<span class="badge <?= $colorClass ?>">
<?= $RANK_LABEL[$rank] ?? $rank ?>
</span>
</td>

<td>
<span class="badge bg-info"><?= $d['total'] ?></span>
</td>

<td>
<span class="badge bg-success fs-6">
<?= number_format($d['rental_price'],2) ?>
</span>
</td>

<td>
<button 
class="btn btn-warning btn-sm editBtn"
data-type="<?= $d['type_equipment'] ?>"
data-rank="<?= $d['rank_spec'] ?>"
data-price="<?= $d['rental_price'] ?>"
>
✏️ แก้ไข
</button>
</td>
</tr>

<?php endforeach; ?>

</table>

</div>
</div>
</div>

<!-- =========================================
🔥 MODAL
========================================= -->
<div class="modal fade" id="editModal">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">

<form method="post" id="formSave">

<input type="hidden" name="action" value="save_price">

<div class="modal-header bg-primary text-white">
<h5>แก้ไขราคาค่าเช่า</h5>
</div>

<div class="modal-body">

<input type="hidden" name="type_equipment" id="editType">
<input type="hidden" name="rank_spec" id="editRank">

<div class="mb-2">
ประเภท: <b id="showType"></b>
</div>

<div class="mb-2">
สเปค: <span id="showRank"></span>
</div>

<label>ราคาใหม่ (บาท/วัน)</label>
<input type="number" step="0.01" 
       name="price" id="editPrice"
       class="form-control" required>

</div>

<div class="modal-footer">

<!-- 🔥 สำคัญ: ต้อง type=button -->
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
ยกเลิก
</button>

<button type="button" id="btnConfirm" class="btn btn-success">
💾 บันทึก
</button>

</div>

</form>

</div>
</div>
</div>

<script>

/* =========================================
🔥 map label + color
========================================= */
let rankMap = {
    'ees':'วิศวะสเปคสูง',
    'ee':'วิศวะทั่วไป',
    'hr':'บุคคลและคลัง',
    '-':'ไม่ระบุ'
};

let colorMap = {
    'ees':'badge rank-ees',
    'ee':'badge rank-ee',
    'hr':'badge rank-hr',
    '-':'badge rank-default'
};

/* =========================================
🔥 เปิด modal
========================================= */
document.querySelectorAll(".editBtn").forEach(btn=>{

    btn.addEventListener("click", function(){

        editType.value  = this.dataset.type;
        editRank.value  = this.dataset.rank;
        editPrice.value = this.dataset.price;

        showType.innerText = this.dataset.type;

        showRank.innerHTML =
        `<span class="${colorMap[this.dataset.rank] || 'badge bg-dark'}">
        ${rankMap[this.dataset.rank] || this.dataset.rank}
        </span>`;

        new bootstrap.Modal(editModal).show();
    });

});

/* =========================================
🔥 confirm
========================================= */
let isSubmitting = false;

btnConfirm.onclick = function(){

    if(isSubmitting) return;

    let price = editPrice.value;

    if(!price){
        Swal.fire('❌ กรุณากรอกราคา');
        return;
    }

    Swal.fire({
        title:'ยืนยันการบันทึก?',
        icon:'question',
        showCancelButton:true,
        confirmButtonText:'ยืนยัน',
        cancelButtonText:'ยกเลิก'
    }).then(res=>{
        if(res.isConfirmed){
            isSubmitting = true;
            formSave.submit();
        }
    });
};

/* =========================================
🔥 search realtime
========================================= */
search.onkeyup = function(){

    let val = this.value.toLowerCase();

    document.querySelectorAll("table tr").forEach((row,i)=>{

        if(i==0) return;

        row.style.display = row.innerText.toLowerCase().includes(val) 
        ? "" : "none";

    });
};
</script>

<?php
/* =========================================
🔥 SAVE (ปลอดภัย 100%)
========================================= */
if(isset($_POST['action']) && $_POST['action']=='save_price'){

$type  = $_POST['type_equipment'];
$rank  = $_POST['rank_spec'];
$price = $_POST['price'];

$conn->prepare("
UPDATE IT_assets
SET rental_price = ?
WHERE type_equipment = ?
AND ISNULL(rank_spec,'-') = ?
")->execute([$price,$type,$rank]);

echo "<script>
Swal.fire({
icon:'success',
title:'บันทึกสำเร็จ 🎉'
}).then(()=>{
location.href='admin_rental_price_manage.php';
});
</script>";
}
?>

<?php include 'partials/footer.php'; ?>