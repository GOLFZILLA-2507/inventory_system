<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* =========================================
ดึงข้อมูลอุปกรณ์ + ราคาเช่า
========================================= */

$stmt = $conn->prepare("
SELECT 
    type_equipment,
    COUNT(*) AS total,
    MIN(rental_price) AS rental_price
FROM IT_assets
GROUP BY type_equipment
ORDER BY type_equipment
");
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="container mt-4">

<div class="card shadow">

<!-- 🔵 Header -->
<div class="card-header text-white" 
     style="background: linear-gradient(135deg,#2196f3,#00bcd4);">
    💰 จัดการราคาค่าเช่าอุปกรณ์
</div>

<div class="card-body">

<!-- 🔍 ช่องค้นหา -->
<input type="text" id="search" class="form-control mb-3"
placeholder="🔍 ค้นหาประเภทอุปกรณ์...">

<table class="table table-bordered table-hover text-center">

<tr class="table-primary">
    <th>#</th>
    <th>ประเภทอุปกรณ์</th>
    <th>จำนวน</th>
    <th>ค่าเช่าต่อวัน (บาท)</th>
    <th>จัดการ</th>
</tr>

<?php $i=1; foreach($data as $d): ?>

<tr>

<td><?= $i++ ?></td>

<td class="fw-bold"><?= $d['type_equipment'] ?></td>

<td>
<span class="badge bg-info">
    <?= $d['total'] ?>
</span>
</td>

<td>
<span class="badge bg-success fs-6">
    <?= number_format($d['rental_price'],2) ?>
</span>
</td>

<td>

<!-- 🔥 ปุ่มแก้ราคา -->
<button 
class="btn btn-warning btn-sm editBtn"
data-type="<?= $d['type_equipment'] ?>"
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
🔥 MODAL แก้ราคา
========================================= -->
<div class="modal fade" id="editModal">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <form method="post">

      <div class="modal-header bg-primary text-white">
        <h5>แก้ไขราคาค่าเช่า</h5>
      </div>

      <div class="modal-body">

        <input type="hidden" name="type_equipment" id="editType">

        <div class="mb-2">
            ประเภท:
            <b id="showType"></b>
        </div>

        <label>ราคาใหม่ (บาท/วัน)</label>
        <input type="number" step="0.01" 
               name="price" id="editPrice"
               class="form-control" required>

      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">
            ยกเลิก
        </button>

        <button class="btn btn-success" name="save_price">
            💾 บันทึก
        </button>
      </div>

      </form>

    </div>
  </div>
</div>

<?php include 'partials/footer.php'; ?>

<script>

/* =========================================
เปิด modal + set ค่า
========================================= */
document.querySelectorAll(".editBtn").forEach(btn=>{

    btn.addEventListener("click", function(){

        document.getElementById("editType").value = this.dataset.type;
        document.getElementById("editPrice").value = this.dataset.price;
        document.getElementById("showType").innerText = this.dataset.type;

        new bootstrap.Modal(document.getElementById('editModal')).show();
    });

});

/* =========================================
ค้นหา realtime
========================================= */
document.getElementById("search").addEventListener("keyup", function(){

    let val = this.value.toLowerCase();

    document.querySelectorAll("table tr").forEach((row,i)=>{

        if(i==0) return;

        row.style.display = row.innerText.toLowerCase().includes(val) 
        ? "" : "none";

    });

});
</script>

<?php
/* =========================================
🔥 บันทึกราคา
========================================= */

if(isset($_POST['save_price'])){

$type = $_POST['type_equipment'];
$price = $_POST['price'];

/* update ทั้งประเภท */
$conn->prepare("
UPDATE IT_assets
SET rental_price = ?
WHERE type_equipment = ?
")->execute([$price,$type]);

echo "<script>
alert('✅ บันทึกสำเร็จ');
location.href='admin_rental_price_manage.php';
</script>";
}
?>