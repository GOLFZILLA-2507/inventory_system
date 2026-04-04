<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$round = $_GET['round'] ?? 0;
$role = $_SESSION['role_ivt'] ?? '';

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

    header("Location: approve_transfer_detail.php?round=".$round."&success=1");
    exit;
}

/* =====================================================
🔥 LOAD DATA
===================================================== */
$stmt = $conn->prepare("
SELECT *
FROM IT_AssetTransfer_Headers
WHERE sent_transfer = ?
ORDER BY transfer_id DESC
");
$stmt->execute([$round]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$headerDetail = '';
$headerImages = [];

foreach($data as $d){

    // เอา detail แค่ตัวแรกที่มี
    if(empty($headerDetail) && !empty($d['other_detail'])){
        $headerDetail = $d['other_detail'];
    }

    // รวมรูปทั้งหมด
    if(!empty($d['transfer_image'])){
        // รองรับหลายรูป (คั่นด้วย ,)
        $imgs = explode(',', $d['transfer_image']);
        foreach($imgs as $img){
            $headerImages[] = trim($img);
        }
    }
}

/* กันรูปซ้ำ */
$headerImages = array_unique($headerImages);
/* ===== 🔥 จบตรงนี้ ===== */

/* =====================================================
🔥 CHECK STATUS
===================================================== */
$stmtCancel = $conn->prepare("
SELECT COUNT(*) 
FROM IT_AssetTransfer_Headers
WHERE sent_transfer = ?
AND receive_status = 'ยกเลิก'
");
$stmtCancel->execute([$round]);
$hasCancel = $stmtCancel->fetchColumn();

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

<div class="container mt-4">
<div class="card shadow">

<div class="card-header bg-primary text-white">
📦 รายการอนุมัติรอบที่ <?= $round ?>
</div>

<div class="border border-primary rounded p-3 mt-1 mb-3">

<b>รายละเอียด:</b><br>
<?= !empty($headerDetail) ? nl2br(htmlspecialchars($headerDetail)) : '-' ?>

<br><br>

<b>รูปภาพ:</b><br>

<?php if(!empty($headerImages)): ?>
    <img src="../uploads/transfer/<?= $headerImages[0] ?>"
         style="width:120px;cursor:pointer"
         onclick="openGallery(0)">
<?php else: ?>
    -
<?php endif; ?>

</div>

<div class="card-body">

<?php if($hasCancel > 0){ ?>
<div class="alert alert-danger">❌ มีรายการถูกยกเลิก</div>
<?php } elseif($remain == 0){ ?>
<div class="alert alert-success">✅ อนุมัติครบแล้ว</div>
<?php } ?>

<form method="post" id="mainForm">

<table class="table table-bordered text-center align-middle">
<thead class="table-primary">
<tr>
<th><input type="checkbox" id="checkAll"></th>
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
<?php if($d['admin_status'] != 'อนุมัติ' && $d['receive_status'] != 'ยกเลิก'){ ?>
<input type="checkbox" name="ids[]" value="<?= $d['transfer_id'] ?>" class="item">
<?php } ?>
</td>

<td><?= $d['transfer_id'] ?></td>
<td class="fw-bold text-primary"><?= $d['no_pc'] ?></td>
<td><?= $d['type'] ?></td>
<td><?= $d['from_site'] ?></td>
<td><?= $d['to_site'] ?></td>

<td>
<?php if($d['receive_status']=='ยกเลิก'){ ?>
<span class="badge bg-danger">❌ ยกเลิก</span>
<?php } elseif($d['admin_status']=='อนุมัติ'){ ?>
<span class="badge bg-success">✅ อนุมัติ</span>
<?php } else { ?>
<span class="badge bg-warning text-dark">⏳ รออนุมัติ</span>
<?php } ?>
</td>

</tr>
<?php } ?>
</tbody>
</table>

<div class="d-flex justify-content-between">

<a href="admin_transfer_sent.php" class="btn btn-secondary">
⬅️ ย้อนกลับ
</a>

<?php if($remain > 0 && $role != 'MD'){ ?>
<button type="button" id="btnApprove" class="btn btn-primary">
✅ อนุมัติที่เลือก
</button>
<?php } ?>

</div>

</form>

</div>
</div>
</div>

<!-- ================= MODAL CONFIRM ================= -->
<div class="modal fade" id="confirmModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">

<div class="modal-header bg-primary text-white">
<h5>ยืนยันการอนุมัติ</h5>
<button class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body text-center">
ต้องการอนุมัติรายการที่เลือกใช่หรือไม่?
</div>

<div class="modal-footer">
<button class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
<button id="confirmSubmit" class="btn btn-primary">ยืนยัน</button>
</div>

</div>
</div>
</div>

<!-- ================= MODAL SUCCESS ================= -->
<div class="modal fade" id="successModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">

<div class="modal-header bg-success text-white">
<h5>สำเร็จ</h5>
<button class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body text-center">
✅ อนุมัติรายการเรียบร้อยแล้ว
</div>

<div class="modal-footer">
<button class="btn btn-success" data-bs-dismiss="modal">ตกลง</button>
</div>

</div>
</div>
</div>

<div class="modal fade" id="imgModal">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">

<div class="modal-body text-center">

<img id="imgPreview" style="width:100%;max-height:70vh;object-fit:contain">

<div class="mt-2">
<button class="btn btn-primary btn-sm" onclick="prevImg()">⬅</button>
<button class="btn btn-primary btn-sm" onclick="nextImg()">➡</button>
</div>

</div>

</div>
</div>
</div>


<script>
// ===============================
// GLOBAL CLEAN MODAL (ตัวสำคัญสุด)
// ===============================
function forceResetModal(){

    // ลบ backdrop ทุกตัว
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());

    // ลบ class ที่ล็อก scroll
    document.body.classList.remove('modal-open');

    // reset style
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
}

// 🔥 ทำตอนโหลด
document.addEventListener("DOMContentLoaded", forceResetModal);

// 🔥 ทำตอน modal ปิด
document.addEventListener('hidden.bs.modal', forceResetModal);

// ===============================
// CHECK ALL
// ===============================
document.getElementById('checkAll')?.addEventListener('change', function(){
    document.querySelectorAll('.item').forEach(cb=>{
        cb.checked = this.checked;
    });
});

// ===============================
// OPEN CONFIRM MODAL
// ===============================
document.getElementById('btnApprove')?.addEventListener('click', function(){

    let checked = document.querySelectorAll('.item:checked');

    if(checked.length === 0){
        alert('❌ กรุณาเลือกรายการ');
        return;
    }

    let modalEl = document.getElementById('confirmModal');

    // 🔥 ป้องกัน modal ซ้อน
    let modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    modal.show();
});

// ===============================
// SUBMIT
// ===============================
document.getElementById('confirmSubmit')?.addEventListener('click', function(){

    let form = document.getElementById('mainForm');

    if(!form.querySelector('[name="approve_selected"]')){
        let input = document.createElement("input");
        input.type = "hidden";
        input.name = "approve_selected";
        input.value = "1";
        form.appendChild(input);
    }

    form.submit();
});

// ===============================
// SUCCESS MODAL
// ===============================
<?php if(isset($_GET['success'])): ?>
window.addEventListener('DOMContentLoaded', function(){

    let modalEl = document.getElementById('successModal');

    let modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    modal.show();

    window.history.replaceState({}, document.title, window.location.pathname + '?round=<?= $round ?>');
});
<?php endif; ?>

// ===============================
// IMAGE SLIDE
// ===============================
let images = <?= json_encode(array_values($headerImages ?? [])) ?>;
let currentIndex = 0;

function openGallery(index){

    if(images.length === 0) return;

    currentIndex = index;

    document.getElementById('imgPreview').src =
        '../uploads/transfer/' + images[currentIndex];

    let modalEl = document.getElementById('imgModal');

    let modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    modal.show();
}

function nextImg(){
    if(images.length === 0) return;

    currentIndex = (currentIndex + 1) % images.length;
    updateImage();
}

function prevImg(){
    if(images.length === 0) return;

    currentIndex = (currentIndex - 1 + images.length) % images.length;
    updateImage();
}

function updateImage(){
    document.getElementById('imgPreview').src =
        '../uploads/transfer/' + images[currentIndex];
}
</script>

<?php include 'partials/footer.php'; ?>