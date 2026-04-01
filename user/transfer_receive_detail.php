<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];
$user = $_SESSION['fullname'];

$round = $_GET['round'] ?? ($_POST['round'] ?? 0);

/* =====================================================
🔥 โหลดข้อมูลรายการโอน
===================================================== */
$stmt = $conn->prepare("
SELECT 
    t.transfer_id,
    t.no_pc,
    t.type,
    t.from_site,
    t.receive_status,
    a.spec,
    a.ram,
    a.ssd,
    a.gpu
FROM IT_AssetTransfer_Headers t
LEFT JOIN IT_assets a ON a.no_pc = t.no_pc
WHERE t.sent_transfer = ?
");
$stmt->execute([$round]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
🔥 เช็คว่ารับครบหรือยัง
===================================================== */
$total = count($data);
$received = 0;

foreach($data as $d){
    if($d['receive_status'] == 'รับแล้ว'){
        $received++;
    }
}

$isAllReceived = ($total > 0 && $received == $total);

/* =====================================================
🔥 SUBMIT (กดยืนยันตรวจรับ)
===================================================== */
$msg = "";
$status = "";

if(isset($_POST['confirm'])){

    $statusList = $_POST['status'] ?? [];

    try{

        $conn->beginTransaction();

        foreach($data as $row){

            $id = $row['transfer_id'];

            // ❌ ถ้ารับแล้ว → ข้าม
            if($row['receive_status'] == 'รับแล้ว') continue;

            $statusVal = $statusList[$id] ?? '';
            if($statusVal == '') continue;

            /* ===============================
            ❌ ไม่พบอุปกรณ์
            =============================== */
            if($statusVal == 'ไม่พบอุปกรณ์นี้'){

                $conn->prepare("
                UPDATE IT_AssetTransfer_Headers
                SET receive_status='ไม่พบอุปกรณ์นี้'
                WHERE transfer_id=?
                ")->execute([$id]);

                continue;
            }

            /* ===============================
            ✅ รับแล้ว (หัวใจสำคัญ)
            =============================== */
            if($statusVal == 'รับแล้ว'){

                // 🔥 update สถานะ
                $conn->prepare("
                UPDATE IT_AssetTransfer_Headers
                SET receive_status='รับแล้ว',
                    arrived_date = GETDATE()
                WHERE transfer_id=?
                ")->execute([$id]);

                // 🔥 key จริง (สำคัญมาก)
                $device_code = $row['no_pc'];
                $from        = $row['from_site'];

                // 🔥 ลบจากต้นทาง (ตาม requirement คุณ)
                $conn->prepare("
                DELETE FROM IT_user_devices
                WHERE device_code=? AND user_project=?
                ")->execute([$device_code,$from]);
            }
        }

        $conn->commit();

        $msg = "บันทึกการตรวจรับเรียบร้อย";
        $status = "success";

        $isAllReceived = true;

    }catch(Exception $e){

        $conn->rollBack();
        $msg = $e->getMessage();
        $status = "error";
    }
}
?>

<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container mt-4">
<div class="card shadow">

<div class="card-header bg-success text-white">
ตรวจรับอุปกรณ์ (รอบ <?= $round ?>)
</div>

<div class="card-body">

<!-- =====================================================
🔥 ปุ่มด้านบน
===================================================== -->
<div class="d-flex justify-content-between mb-3">

<a href="transfer_receive.php" class="btn btn-secondary">
⬅ ย้อนกลับ
</a>

<?php if(!$isAllReceived): ?>
<button type="button" id="openConfirm" class="btn btn-success">
✔ ยืนยันการตรวจรับ
</button>
<?php endif; ?>

</div>

<form method="post" id="mainForm">
<input type="hidden" name="round" value="<?= $round ?>">

<table class="table table-bordered text-center">

<tr>
<th>#</th>
<th>ตรวจรับ</th>
<th>จาก</th>
<th>รหัส</th>
<th>ประเภท</th>
<th>Spec</th>
</tr>

<?php $i=1; foreach($data as $d): ?>
<tr>

<td><?= $i++ ?></td>

<td>

<?php
if($d['receive_status']=='รับแล้ว'){
    echo '<span class="badge bg-success">รับแล้ว</span>';
}
elseif($d['receive_status']=='ไม่พบอุปกรณ์นี้'){
    echo '<span class="badge bg-danger">ไม่พบ</span>';
}
else{
?>
<select name="status[<?= $d['transfer_id'] ?>]"
class="form-select form-select-sm status-select"
data-no="<?= $d['no_pc'] ?>">

<option value="">-- เลือก --</option>
<option value="รับแล้ว">✅ รับ</option>
<option value="ไม่พบอุปกรณ์นี้">❌ ไม่พบ</option>

</select>
<?php } ?>

</td>

<td><?= $d['from_site'] ?></td>
<td><?= $d['no_pc'] ?></td>
<td><?= $d['type'] ?></td>

<td>
<?php
$specParts = array_filter([
$d['spec'],$d['ram'],$d['ssd'],$d['gpu']
]);
echo empty($specParts) ? '-' : implode(' | ',$specParts);
?>
</td>

</tr>
<?php endforeach; ?>

</table>

</form>

</div>
</div>
</div>

<!-- =====================================================
🔥 MODAL CONFIRM
===================================================== -->
<div class="modal fade" id="confirmModal">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">

<div class="modal-header bg-success text-white">
<h5>ยืนยันรายการ</h5>
</div>

<div class="modal-body">

<div class="text-success fw-bold">รับ</div>
<div id="listOk"></div>

<div class="text-danger fw-bold mt-2">ไม่พบ</div>
<div id="listFail"></div>

</div>

<div class="modal-footer">
<button class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
<button type="submit" name="confirm" form="mainForm" class="btn btn-success">
ยืนยัน
</button>
</div>

</div>
</div>
</div>

<script>
/* =====================================================
🔥 เปิด modal + สรุปรายการ
===================================================== */
let btn = document.getElementById("openConfirm");

if(btn){
btn.onclick = function(){

let ok="",fail="";

document.querySelectorAll(".status-select").forEach(sel=>{
    if(sel.value=="รับแล้ว") ok+="✔ "+sel.dataset.no+"<br>";
    if(sel.value=="ไม่พบอุปกรณ์นี้") fail+="✖ "+sel.dataset.no+"<br>";
});

document.getElementById("listOk").innerHTML = ok || "ไม่มี";
document.getElementById("listFail").innerHTML = fail || "ไม่มี";

new bootstrap.Modal(document.getElementById('confirmModal')).show();
};
}
</script>

<!-- =====================================================
🔥 SUCCESS
===================================================== -->
<?php if($msg): ?>
<script>
Swal.fire({
icon:'<?= $status ?>',
title:'<?= $msg ?>'
}).then(()=>{
location.reload();
});
</script>
<?php endif; ?>

<?php include 'partials/footer.php'; ?>