<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$user = $_SESSION['fullname'];

/* =====================================================
🔥 โหลดข้อมูลแจ้งซ่อม (รวมรูป img1)
===================================================== */
$stmt = $conn->prepare("
SELECT r.*, a.no_pc
FROM IT_RepairTickets r
LEFT JOIN IT_assets a ON a.asset_id = r.asset_id
WHERE r.user_name = ?
ORDER BY r.ticket_id DESC
");
$stmt->execute([$user]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="container mt-4">
<div class="card shadow">

<div class="card-header bg-success text-white text-center">
<h5>📦 ติดตามสถานะการแจ้งซ่อมของคุณ</h5>
</div>

<div class="card-body">

<table class="table table-bordered table-hover text-center align-middle">

<tr class="table-success">
<th>ลำดับ</th>
<th>รหัสอุปกรณ์</th>
<th>รายละเอียด</th>
<th>สถานะ</th>
<th>วันที่แจ้ง</th>
<th>วันที่ซ่อมเสร็จ</th>
<th>ดู</th>
</tr>

<?php 
$i=1;
foreach($data as $r): 

/* =====================================================
🔥 สีสถานะ
===================================================== */
$color="secondary";
$row="";

if($r['status']=="กำลังซ่อม"){ $color="primary"; $row="table-primary"; }
if($r['status']=="เสร็จแล้ว"){ $color="success"; $row="table-success"; }
if($r['status']=="ส่ง Vendor"){ $color="warning"; $row="table-warning"; }
if($r['status']=="ยกเลิก"){ $color="danger"; $row="table-danger"; }
?>

<tr class="<?= $row ?>">

<td><?= $i++ ?></td>

<td><?= htmlspecialchars($r['user_no_pc']) ?></td>

<!-- ตัดข้อความ -->
<td><?= htmlspecialchars(mb_strimwidth($r['problem'],0,30,'...')) ?></td>

<td>
<span class="badge bg-<?= $color ?>">
<?= $r['status'] ?>
</span>
</td>

<td><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>

<td>
<?= !empty($r['finished_at']) 
? date('d/m/Y', strtotime($r['finished_at'])) 
: '-' ?>
</td>

<!-- ปุ่มดู -->
<td>
<button class="btn btn-info btn-sm"
onclick="viewDetail(
'<?= $r['ticket_id'] ?>',
'<?= htmlspecialchars($r['user_no_pc']) ?>',
'<?= htmlspecialchars($r['problem']) ?>',
'<?= $r['status'] ?>',
'<?= date('d/m/Y H:i', strtotime($r['created_at'])) ?>',
'<?= !empty($r['finished_at']) ? date('d/m/Y H:i', strtotime($r['finished_at'])) : '-' ?>',
'<?= $r['img1'] ?? '' ?>'
)">
🔍
</button>
</td>

</tr>

<?php endforeach; ?>

</table>

</div>
</div>
</div>

<!-- =====================================================
🔥 MODAL รายละเอียด
===================================================== -->
<div class="modal fade" id="detailModal">
<div class="modal-dialog modal-lg">
<div class="modal-content">

<div class="modal-header bg-success text-white">
<h5 class="modal-title w-100 text-center">📋 รายละเอียดการแจ้งซ่อม</h5>
</div>

<div class="modal-body">

<!-- ================= ข้อมูล ================= -->
<div class="row mb-3">

<div class="col-md-6">
<p><b>Ticket:</b><br><span id="m_id"></span></p>
</div>

<div class="col-md-6">
<p><b>รหัสอุปกรณ์:</b><br><span id="m_pc"></span></p>
</div>

<div class="col-md-6">
<p><b>สถานะ:</b><br><span id="m_status"></span></p>
</div>

<div class="col-md-6">
<p><b>วันที่แจ้ง:</b><br><span id="m_date"></span></p>
</div>

<div class="col-md-6">
<p><b>วันที่ซ่อมเสร็จ:</b><br><span id="m_finish"></span></p>
</div>

</div>

<!-- ================= อาการ ================= -->
<div class="mb-3">
<p><b>อาการ:</b></p>
<div class="border rounded p-2 bg-light text-danger fw-bold" id="m_problem"></div>
</div>

<hr>

<!-- ================= รูป ================= -->
<div class="text-center">
<p><b>รูปภาพ (กดเพื่อขยาย)</b></p>

<img id="m_img"
class="img-fluid rounded shadow"
style="max-height:250px; cursor:pointer; display:none;">

<p id="no_img" class="text-muted">ไม่มีรูปภาพ</p>
</div>

</div>

<div class="modal-footer justify-content-center">
<button class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
</div>

</div>
</div>
</div>

<div class="modal fade" id="imgModal">
<div class="modal-dialog modal-xl modal-dialog-centered">
<div class="modal-content bg-dark">

<div class="modal-body text-center">
<img id="zoomImg" class="img-fluid">
</div>

</div>
</div>
</div>

<!-- =====================================================
🔥 SCRIPT
===================================================== -->
<script>
function viewDetail(id,pc,problem,status,date,finish,img){

    // ใส่ข้อมูล
    document.getElementById('m_id').innerText = id;
    document.getElementById('m_pc').innerText = pc;
    document.getElementById('m_problem').innerText = problem;
    document.getElementById('m_status').innerText = status;
    document.getElementById('m_date').innerText = date;
    document.getElementById('m_finish').innerText = finish;

    let imgTag = document.getElementById('m_img');
    let noImg = document.getElementById('no_img');

    /* =====================================================
    🔥 path รูป (ตรงระบบคุณ)
    ===================================================== */
    let path = '../uploads/repair/';

    if(img && img.trim() !== ''){

        imgTag.src = path + img;
        imgTag.style.display = 'block';
        noImg.style.display = 'none';

        /* กันรูปเสีย */
        imgTag.onerror = function(){
            this.style.display = 'none';
            noImg.style.display = 'block';
        };

    }else{
        imgTag.style.display = 'none';
        noImg.style.display = 'block';
    }

    new bootstrap.Modal(document.getElementById('detailModal')).show();
}
</script>

<script>
function viewDetail(id,pc,problem,status,date,finish,img){

    document.getElementById('m_id').innerText = id;
    document.getElementById('m_pc').innerText = pc;
    document.getElementById('m_problem').innerText = problem;
    document.getElementById('m_status').innerText = status;
    document.getElementById('m_date').innerText = date;
    document.getElementById('m_finish').innerText = finish;

    let imgTag = document.getElementById('m_img');
    let noImg = document.getElementById('no_img');

    let path = '../uploads/repair/';

    if(img && img.trim() !== ''){

        imgTag.src = path + img;
        imgTag.style.display = 'block';
        noImg.style.display = 'none';

        /* 🔥 click zoom */
        imgTag.onclick = function(){
            document.getElementById('zoomImg').src = this.src;
            new bootstrap.Modal(document.getElementById('imgModal')).show();
        };

        /* กันรูปเสีย */
        imgTag.onerror = function(){
            this.style.display = 'none';
            noImg.style.display = 'block';
        };

    }else{
        imgTag.style.display = 'none';
        noImg.style.display = 'block';
    }

    new bootstrap.Modal(document.getElementById('detailModal')).show();
}
</script>

<?php include 'partials/footer.php'; ?>