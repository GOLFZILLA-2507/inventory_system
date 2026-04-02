<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* =====================================================
🔐 Session
===================================================== */
$site = $_SESSION['site'];       // โครงการปลายทาง (ผู้รับ)
$user = $_SESSION['fullname'];   // ผู้ใช้งาน

// รับค่า round (ทั้ง GET / POST)
$round = $_GET['round'] ?? ($_POST['round'] ?? 0);

/* =====================================================
📦 โหลดรายการโอนของรอบนี้
- เพิ่ม admin_status เพื่อใช้เช็คอนุมัติ
===================================================== */
$stmt = $conn->prepare("
SELECT 
    t.transfer_id,
    t.no_pc,
    t.type,
    t.from_site,
    t.receive_status,
    t.admin_status,           -- 🔥 สำคัญ: ใช้เช็คอนุมัติ
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
🔍 เช็คว่า “อนุมัติครบหรือยัง”
- ถ้ามีรายการไหนยังไม่ 'อนุมัติแล้ว' → ห้ามรับ
===================================================== */
$isApproved = true;
foreach($data as $d){
    if($d['admin_status'] !== 'อนุมัติ'){
        $isApproved = false;
        break;
    }
}

/* =====================================================
🔍 เช็คว่ารับครบหรือยัง (ไว้ซ่อนปุ่ม)
===================================================== */
$total = count($data);
$done = 0;

foreach($data as $d){

    // ✅ ถือว่า "จบแล้ว" มี 2 สถานะ
    if(
        $d['receive_status'] === 'รับแล้ว' ||
        $d['receive_status'] === 'ยกเลิก' ||
        $d['receive_status'] === 'ไม่พบอุปกรณ์นี้'
    ){
        $done++;
    }
}

$isAllReceived = ($total > 0 && $done === $total);

/* =====================================================
📨 SUBMIT: ยืนยันตรวจรับ
- ใช้ Transaction
- กันกรณียิง POST ตรง (ต้องเช็ค $isApproved ซ้ำ)
- ใช้ PRG (POST → REDIRECT → GET) กัน modal เด้งซ้ำ
===================================================== */
if(isset($_POST['confirm'])){

    // ❌ ยังไม่อนุมัติ → ห้ามทำงาน
    if(!$isApproved){
        header("Location: transfer_receive_detail.php?round=".$round."&not_approved=1");
        exit;
    }

    $statusList = $_POST['status'] ?? [];

    try{
        $conn->beginTransaction();

        foreach($data as $row){

            $id = $row['transfer_id'];

            // ❌ ถ้ารับแล้ว → ข้าม (กันยิงซ้ำ)
            if($row['receive_status'] === 'รับแล้ว') continue;

            // ค่าที่ user เลือก
            $statusVal = $statusList[$id] ?? '';
            if($statusVal === '') continue;

            /* ===============================
            ❌ ไม่พบอุปกรณ์
            =============================== */
            if($statusVal === 'ไม่พบอุปกรณ์นี้'){

                $conn->prepare("
                UPDATE IT_AssetTransfer_Headers
                SET receive_status='ไม่พบอุปกรณ์นี้'
                WHERE transfer_id=?
                ")->execute([$id]);

                continue;
            }

            /* ===============================
            ✅ รับแล้ว
            =============================== */
            if($statusVal === 'รับแล้ว'){

    // 🔑 ต้องประกาศก่อนใช้
    $device_code = $row['no_pc'];
    $from        = $row['from_site'];

    // 🔥 อัปเดตสถานะการรับ
    $conn->prepare("
    UPDATE IT_AssetTransfer_Headers
    SET receive_status='รับแล้ว',
        arrived_date = GETDATE()
    WHERE transfer_id=?
    ")->execute([$id]);

    /* =====================================================
    🔥 FIX: อัปเดตโครงการลง IT_assets (ตอนนี้จะทำงานแล้ว)
    ===================================================== */
    $conn->prepare("
    UPDATE IT_assets
    SET use_it = ?
    WHERE no_pc = ?
    ")->execute([
        $site,
        $device_code
    ]);

    /* =====================================================
    🔥 ลบ user เดิม
    ===================================================== */
    $stmtDel = $conn->prepare("
    SELECT TOP 1 id
    FROM IT_user_devices
    WHERE device_code=? AND user_project=?
    ORDER BY id ASC
    ");
    $stmtDel->execute([$device_code, $from]);
    $delRow = $stmtDel->fetch(PDO::FETCH_ASSOC);

    if($delRow){
        $conn->prepare("
        DELETE FROM IT_user_devices
        WHERE id=?
        ")->execute([$delRow['id']]);
    }
}
        }

        $conn->commit();

        // 🔁 PRG: redirect เพื่อกัน refresh แล้วทำซ้ำ + modal ค้าง
        header("Location: transfer_receive_detail.php?round=".$round."&success=1");
        exit;

    }catch(Exception $e){
        $conn->rollBack();

        header("Location: transfer_receive_detail.php?round=".$round."&error=1");
        exit;
    }
}
?>

<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>

<!-- SweetAlert -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container mt-4">
<div class="card shadow">

<div class="card-header bg-success text-white">
ตรวจรับอุปกรณ์ (รอบ <?= htmlspecialchars($round) ?>)
</div>

<div class="card-body">

<!-- =====================================================
🔙 ปุ่มด้านบน
- แยกกรณี:
  1) รับครบแล้ว → ไม่ต้องมีปุ่มยืนยัน
  2) ยังไม่อนุมัติ → ปุ่มเตือน
  3) อนุมัติแล้ว → ปุ่มยืนยัน
===================================================== -->
<div class="d-flex justify-content-between mb-3">

<a href="transfer_receive.php" class="btn btn-success">
⬅ ย้อนกลับ
</a>

<?php if(!$isAllReceived): ?>

    <?php if($isApproved): ?>
        <!-- ✅ อนุมัติแล้ว → กดได้ -->
        <button type="button" id="openConfirm" class="btn btn-success">
        ✔ ยืนยันการตรวจรับ
        </button>
    <?php else: ?>
        <!-- ❌ ยังไม่อนุมัติ -->
        <button type="button" id="notApprovedBtn" class="btn btn-secondary">
        ⛔ รอ Admin อนุมัติ
        </button>
    <?php endif; ?>

<?php endif; ?>

</div>

<form method="post" id="mainForm">
<input type="hidden" name="round" value="<?= htmlspecialchars($round) ?>">

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
<tr class="<?= $d['receive_status']=='ยกเลิก' ? 'table-secondary' : '' ?>">

<td><?= $i++ ?></td>

<td>
<?php

// 🔴 ยกเลิก → แสดงอย่างเดียว ห้ามเลือก
if($d['receive_status'] === 'ยกเลิก'){
    echo '<span class="badge bg-danger">ถูกยกเลิก</span>';
}

// 🟢 รับแล้ว
elseif($d['receive_status'] === 'รับแล้ว'){
    echo '<span class="badge bg-success">รับแล้ว</span>';
}

// 🔴 ไม่พบ
elseif($d['receive_status'] === 'ไม่พบอุปกรณ์นี้'){
    echo '<span class="badge bg-danger">ไม่พบ</span>';
}

// 🟡 ยังไม่ตรวจรับ → ให้เลือก
else{
?>
<select name="status[<?= $d['transfer_id'] ?>]"
class="form-select form-select-sm status-select"
data-no="<?= htmlspecialchars($d['no_pc']) ?>">

<option value="">-- เลือก --</option>
<option value="รับแล้ว">✅ รับ</option>
<option value="ไม่พบอุปกรณ์นี้">❌ ไม่พบ</option>

</select>
<?php } ?>
</td>

<td><?= htmlspecialchars($d['from_site']) ?></td>
<td><?= htmlspecialchars($d['no_pc']) ?></td>
<td><?= htmlspecialchars($d['type']) ?></td>

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
🧾 MODAL CONFIRM
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
📊 สรุปรายการก่อนกดยืนยัน
===================================================== */
let btn = document.getElementById("openConfirm");

if(btn){
btn.onclick = function(){

let ok="",fail="";

document.querySelectorAll(".status-select").forEach(sel=>{
    if(sel.value==="รับแล้ว") ok+="✔ "+sel.dataset.no+"<br>";
    if(sel.value==="ไม่พบอุปกรณ์นี้") fail+="✖ "+sel.dataset.no+"<br>";
});

document.getElementById("listOk").innerHTML = ok || "ไม่มี";
document.getElementById("listFail").innerHTML = fail || "ไม่มี";

new bootstrap.Modal(document.getElementById('confirmModal')).show();
};
}

/* =====================================================
⛔ ยังไม่อนุมัติ → แจ้งเตือน
===================================================== */
let btnBlock = document.getElementById("notApprovedBtn");

if(btnBlock){
btnBlock.onclick = function(){
    Swal.fire({
        icon:'warning',
        title:'ยังไม่สามารถรับได้',
        text:'รายการนี้ยังไม่ได้รับการอนุมัติจาก Admin'
    });
};
}
</script>

<!-- =====================================================
🔔 SUCCESS / ERROR / NOT APPROVED (ใช้ GET → ไม่ค้าง)
===================================================== -->
<?php if(isset($_GET['success'])): ?>
<script>
Swal.fire({
icon:'success',
title:'บันทึกการตรวจรับเรียบร้อย'
});
</script>
<?php endif; ?>

<?php if(isset($_GET['error'])): ?>
<script>
Swal.fire({
icon:'error',
title:'เกิดข้อผิดพลาด'
});
</script>
<?php endif; ?>

<?php if(isset($_GET['not_approved'])): ?>
<script>
Swal.fire({
icon:'warning',
title:'ยังไม่อนุมัติ',
text:'ต้องรอ Admin อนุมัติก่อน'
});
</script>
<?php endif; ?>

<?php include 'partials/footer.php'; ?>