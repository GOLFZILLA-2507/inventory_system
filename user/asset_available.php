<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* =====================================================
   ดึงโครงการของ user ที่ login
===================================================== */
$site = $_SESSION['site'];

/* =====================================================
🔥 เมื่อกด "นำมาใช้"
===================================================== */
if(isset($_POST['transfer_id'])){

    $transfer_id = $_POST['transfer_id'];
    $no_pc       = $_POST['no_pc'];
    $type        = $_POST['type'];
    $user        = $_SESSION['fullname'];

    /* ===============================
    🔥 map type → field
    =============================== */
    $map = [
        'CCTV'=>'user_cctv',
        'NVR'=>'user_nvr',
        'Projector'=>'user_projector',
        'Printer'=>'user_printer',
        'audio_set'=>'user_audio_set',
        'Plotter'=>'user_plotter',
        'Accessories_IT'=>'user_Accessories_IT',
        'Drone'=>'user_Drone',
        'Optical_Fiber'=>'user_Optical_Fiber',
        'Server'=>'user_Server'
    ];

    if(!isset($map[$type])){
        die("ไม่ใช่อุปกรณ์ใช้ร่วม");
    }

    $field = $map[$type];

    /* ===============================
    🔥 หา SHARED row
    =============================== */
    $stmt = $conn->prepare("
    SELECT * FROM IT_user_information
    WHERE user_project=? AND user_type_equipment='SHARED'
    ");
    $stmt->execute([$site]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    /* ===============================
    🔥 ถ้าไม่มี → สร้าง
    =============================== */
    if(!$row){

        $conn->prepare("
        INSERT INTO IT_user_information
        (user_project,user_type_equipment,user_record,user_update)
        VALUES (?,?,?,GETDATE())
        ")->execute([$site,'SHARED',$user]);

        $stmt->execute([$site]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /* ===============================
    🔥 ต่อค่า IT_user_information
    =============================== */
    $old = $row[$field] ?? '';
    $arr = !empty($old) ? explode(',', $old) : [];
    $arr = array_map('trim',$arr);

    if(!in_array($no_pc,$arr)){
        $arr[] = $no_pc;
    }

    $new = implode(',', array_filter($arr));

    $conn->prepare("
    UPDATE IT_user_information
    SET $field=?, user_employee=?, user_update=GETDATE(), user_record=?
    WHERE id=?
    ")->execute([$new,$site,$user,$row['id']]);

    /* ===============================
    🔥 HISTORY → ต่อค่า (ไม่เพิ่ม row)
    =============================== */
    $stmtH = $conn->prepare("
    SELECT * FROM IT_user_history
    WHERE user_project=? AND history_type='SHARED'
    ");
    $stmtH->execute([$site]);
    $h = $stmtH->fetch(PDO::FETCH_ASSOC);

    if(!$h){

        $conn->prepare("
        INSERT INTO IT_user_history
        (user_employee,user_project,$field,history_type,created_at,created_by)
        VALUES (?,?,?,GETDATE(),?)
        ")->execute([$site,$site,$no_pc,'SHARED',$user]);

    }else{

        $oldH = $h[$field] ?? '';
        $arrH = !empty($oldH) ? explode(',', $oldH) : [];
        $arrH = array_map('trim',$arrH);

        if(!in_array($no_pc,$arrH)){
            $arrH[] = $no_pc;
        }

        $newH = implode(',', array_filter($arrH));

        $conn->prepare("
        UPDATE IT_user_history
        SET $field=?, user_employee=?, created_at=GETDATE(), created_by=?
        WHERE history_id=?
        ")->execute([$newH,$site,$user,$h['history_id']]);
    }

    /* ===============================
    🔥 update transfer
    =============================== */
    $conn->prepare("
    UPDATE IT_AssetTransfer_Headers
    SET user_status=?
    WHERE transfer_id=?
    ")->execute([$site,$transfer_id]);

    header("Location: asset_available.php?success=1&pc=".$no_pc);
    exit;
}
/* =====================================================
   โหลดอุปกรณ์ที่ยังไม่มีผู้ใช้งาน
   👉 เงื่อนไข:
   - มาถึงปลายทางแล้ว (receive_status = 'รับแล้ว')
   - เป็นของโครงการนี้ (to_site)
   - ยังไม่ถูกใช้งาน (status IS NULL)
===================================================== */
$stmt = $conn->prepare("
SELECT
t.transfer_id,
t.no_pc,

-- 🔥 ดึง type จริงจาก assets
a.type_equipment AS type,

-- 🔥 เอารายละเอียดจริง
a.Equipment_details AS details,
a.spec,
a.ram,
a.ssd,
a.gpu,

t.from_site,
t.transfer_type,
t.arrived_date

FROM IT_AssetTransfer_Headers t

-- 🔥 JOIN เพื่อเอาข้อมูล asset จริง
LEFT JOIN IT_assets a ON a.no_pc = t.no_pc

WHERE t.to_site = ?
AND t.receive_status = 'รับแล้ว'
AND t.user_status IS NULL

ORDER BY t.arrived_date DESC
");

$stmt->execute([$site]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<style>
.card-header{
background:linear-gradient(135deg,#198754,#20c997);
color:white;
}

.empty-data{
display:inline-block;
padding:4px 10px;
font-size:12px;
font-weight:600;
color:#856404;
background:#fff3cd;
border-radius:6px;
border:1px solid #000000;
}

.table-green thead{
    background: linear-gradient(135deg,#198754,#20c997);
    color:white;
}

.table-green tbody tr:hover{
    background:#e9f7ef;
}

.badge-green{
    background:#198754;
}
</style>

<div class="container mt-4">
<div class="card shadow">

<div class="card-header">
<h5 class="mb-0">
🖥 อุปกรณ์ที่ยังไม่มีผู้ใช้งาน  
(โครงการ <?= $site ?>)
</h5>
</div>

<div class="card-body">

<table class="table table-bordered table-green">

<thead class="table-success text-center">
<tr>
<th>ลำดับ</th>
<th>รหัสอุปกรณ์</th>
<th>ประเภท</th>
<th>Spec</th>
<th>หมายเหตุ</th>
<th>วันที่รับ</th>
<th>จัดการ</th>
</tr>
</thead>

<tbody>

<?php if(empty($data)): ?>

<tr>
<td colspan="7" class="text-center text-muted">
ไม่พบอุปกรณ์ที่ยังไม่มีผู้ใช้งาน
</td>
</tr>

<?php else: ?>

<?php $i=1; foreach($data as $d): ?>

<tr>

<td class="text-center"><?= $i++ ?></td>

<!-- 🔥 รหัสอุปกรณ์ -->
<td class="fw-bold text-primary">
<?= $d['no_pc'] ?: '<span class="empty-data">ไม่มีข้อมูล</span>' ?>
</td>

<!-- 🔥 ประเภท -->
<td class="text-center">
<?= $d['type'] ?: '-' ?>
</td>

<td>
<?php
$specParts = array_filter([
$d['spec'],
$d['ram'],
$d['ssd'],
$d['gpu']
]);

echo empty($specParts)
? '<span class="empty-data">ไม่มีข้อมูล</span>'
: implode(' | ', $specParts);
?>
</td>

<!-- 🔥 หมายเหตุ -->
<td>
<?php if(!empty($d['from_site'])): ?>
โอนจาก : <b><?= htmlspecialchars($d['from_site']) ?></b><br>
ประเภท : <span class="badge bg-info"><?= htmlspecialchars($d['transfer_type']) ?></span>
<?php else: ?>
<span class="empty-data">ไม่มีข้อมูล</span>
<?php endif; ?>
</td>

<!-- 🔥 วันที่ -->
<td class="text-center">
<?= $d['arrived_date'] ?: '-' ?>
</td>

<!-- 🔥 ปุ่มจัดการ -->
<td class="text-center">

<?php
// 🔥 แยกประเภท
$mainTypes = ['PC','Notebook','All_In_One','Monitor','UPS','Printer','Scanner','Projector','audio_set'];
?>

<?php if(in_array($d['type'], $mainTypes)): ?>

<!-- 🔴 อุปกรณ์หลัก -->
<a href="asset_assign_user.php?transfer_id=<?= $d['transfer_id'] ?>&no_pc=<?= $d['no_pc'] ?>"
class="btn btn-sm btn-primary">
👤 เพิ่มผู้ใช้
</a>

<?php else: ?>
<!-- 🟢 อุปกรณ์ร่วม -->
<form method="post" class="d-inline">

<input type="hidden" name="transfer_id" value="<?= $d['transfer_id'] ?>">
<input type="hidden" name="no_pc" value="<?= $d['no_pc'] ?>">
<input type="hidden" name="type" value="<?= $d['type'] ?>">

<button type="button"
class="btn btn-sm btn-success openConfirm"
data-pc="<?= $d['no_pc'] ?>"
data-type="<?= $d['type'] ?>">
📦 นำมาใช้
</button>

</form>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>
<?php endif; ?>

</tbody>

</table>

</div>
</div>
</div>

<!-- ✅ CONFIRM MODAL -->
<div class="modal fade" id="confirmModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow border-0">

      <div class="modal-header text-white"
           style="background:linear-gradient(135deg,#198754,#20c997)">
        <h5 class="modal-title">ยืนยันการใช้งาน</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body text-center">

        <div style="font-size:48px;">📦</div>

        <div id="confirmText" class="mt-3"></div>

      </div>

      <div class="modal-footer justify-content-center">

        <button class="btn btn-light" data-bs-dismiss="modal">
          ยกเลิก
        </button>

        <button id="confirmBtn" class="btn btn-success">
          ✔ ยืนยัน
        </button>

      </div>

    </div>
  </div>
</div>

<!-- ✅ SUCCESS MODAL -->
<div class="modal fade" id="successModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">

      <div class="modal-header text-white"
           style="background:linear-gradient(135deg,#198754,#20c997)">
        <h5 class="modal-title">สำเร็จ</h5>
      </div>

      <div class="modal-body text-center">

        <div style="font-size:48px;">✅</div>

        <div id="successText" class="mt-3"></div>

      </div>

      <div class="modal-footer justify-content-center">
        <button class="btn btn-success" data-bs-dismiss="modal">
          ตกลง
        </button>
      </div>

    </div>
  </div>
</div>

<?php include 'partials/footer.php'; ?>
<script>
document.querySelectorAll(".openConfirm").forEach(btn=>{

    btn.addEventListener("click", function(){

        let form = this.closest("form");
        let pc   = this.dataset.pc;
        let type = this.dataset.type;

        document.getElementById("confirmText").innerHTML = `
            <b>ยืนยันนำอุปกรณ์ไปใช้</b><br><br>
            <span style="color:#198754">${pc}</span><br>
            <small>${type}</small>
        `;

        // 🔥 set submit
        document.getElementById("confirmBtn").onclick = function(){
            form.submit();
        };

        new bootstrap.Modal(document.getElementById('confirmModal')).show();
    });

});

<?php if(isset($_GET['success'])): ?>

let pc = "<?= $_GET['pc'] ?? '' ?>";

document.getElementById("successText").innerHTML = `
    <b>นำอุปกรณ์สำเร็จ</b><br><br>
    <span style="color:#198754">${pc}</span><br>
    <small>ถูกเพิ่มเข้าโครงการแล้ว</small>
`;

new bootstrap.Modal(document.getElementById('successModal')).show();

<?php endif; ?>
</script>
