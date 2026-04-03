<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];
$user = $_SESSION['fullname'];



/* =====================================================
🔥 FUNCTION: เช็คซ้ำทั้งระบบ
===================================================== */
function checkDuplicate($conn,$code){
    $stmt = $conn->prepare("
        SELECT TOP 1 user_employee,user_project
        FROM IT_user_devices
        WHERE device_code=?
    ");
    $stmt->execute([$code]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/* =====================================================
🔥 FUNCTION: insert device (ใช้ร่วม)
===================================================== */
function insertShared($conn,$site,$type,$code,$user){

    $conn->prepare("
        INSERT INTO IT_user_devices
        (user_employee,user_project,device_type,device_role,device_code,created_by,user_record)
        VALUES (?,?,?,?,?,?,?)
    ")->execute([
        $site,        // 🔥 ใช้ project เป็น owner
        $site,
        $type,
        'shared',     // 🔥 role = shared
        $code,
        $user,
        $user
    ]);
}

/* =====================================================
🔥 FUNCTION: save history
===================================================== */
function saveHistory($conn,$site,$code,$type,$user){

    $conn->prepare("
        INSERT INTO IT_user_history
        (user_employee,user_project,user_no_pc,action_type,created_at,created_by,history_type,start_date)
        VALUES (?,?,?,'shared_assign',GETDATE(),?,?,GETDATE())
    ")->execute([
        $site,
        $site,
        $code,
        $user,
        $type
    ]);
}

/* =====================================================
🔥 SUBMIT (หัวใจหลัก)
===================================================== */
/* =====================================================
🔥 SUBMIT (หัวใจหลัก - UPDATE user_employee)
===================================================== */
if($_SERVER['REQUEST_METHOD']=='POST'){

    $no_pc = trim($_POST['no_pc'] ?? '');
    $type  = $_POST['type'] ?? '';

    if(!$no_pc){
        die("ไม่พบรหัสอุปกรณ์");
    }

    try{

        $conn->beginTransaction();

        /* =====================================================
        🔍 ดึงข้อมูลล่าสุด
        ===================================================== */
        $stmt = $conn->prepare("
            SELECT TOP 1 id, user_employee, user_project
            FROM IT_user_devices
            WHERE device_code = ?
        ");
        $stmt->execute([$no_pc]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        if(!$device){
            throw new Exception("ไม่พบอุปกรณ์ในระบบ");
        }

        /* =====================================================
        🔥 เช็ค "ไม่มีรหัส"
        ===================================================== */
        $isNoCode = (
            $no_pc == '-' ||
            strtolower($no_pc) == 'n/a' ||
            $no_pc == 'ไม่มีรหัส'
        );

        /* =====================================================
        🚫 ถ้ามี user แล้ว → ห้ามใช้
        ===================================================== */
        if(!$isNoCode && !empty($device['user_employee'])){
            throw new Exception("อุปกรณ์นี้ถูกใช้งานโดย {$device['user_employee']} ({$device['user_project']})");
        }

        /* =====================================================
        🔥 UPDATE user_employee (หัวใจสำคัญ)
        ===================================================== */
        $stmtUpdate = $conn->prepare("
            UPDATE IT_user_devices
            SET 
                user_employee = ?,     -- 🔥 จุดที่คุณต้องการ
                device_role = 'shared',
                created_by = ?
            WHERE device_code = ?
            AND (user_employee IS NULL OR ? = 1)
        ");

        $stmtUpdate->execute([
            $site,             // 🔥 ใส่ชื่อ user
            $user,
            $no_pc,
            $isNoCode ? 1 : 0
        ]);

        if($stmtUpdate->rowCount() == 0){
            throw new Exception("อุปกรณ์นี้ถูกใช้ไปแล้ว (refresh ใหม่)");
        }

        /* =====================================================
        🔥 UPDATE HISTORY (ตัวสำคัญ)
        ===================================================== */
        $stmtHistory = $conn->prepare("
            UPDATE IT_user_history
            SET 
                user_employee = ?,      -- 🔥 โครงการ
                user_project  = ?,      -- 🔥 โครงการ
                created_by    = ?
            WHERE user_no_pc = ?
            AND end_date IS NULL       -- 🔥 เอา record ล่าสุดเท่านั้น
        ");

        $stmtHistory->execute([
            $site,     // 🔥 project
            $site,
            $user,
            $no_pc
        ]);

        $conn->commit();

        header("Location: asset_available.php?success=1");
        exit;

    }catch(Exception $e){

        $conn->rollBack();

        echo "<script>
        alert('❌ ".$e->getMessage()."');
        history.back();
        </script>";
        exit;
    }
}


/* =====================================================
🔥 โหลดรายการอุปกรณ์
===================================================== */
$stmt = $conn->prepare("
SELECT
d.id,
d.device_code AS no_pc,
d.device_type AS type,
a.spec,a.ram,a.ssd,a.gpu,
d.created_at

FROM IT_user_devices d
LEFT JOIN IT_assets a ON a.no_pc = d.device_code

WHERE d.user_employee IS NULL   -- 🔥 ไม่มีผู้ใช้
AND d.user_project = ?          -- 🔥 ของโครงการเรา

ORDER BY d.created_at DESC
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
</style>

<div class="container mt-4">
<div class="card shadow">

<div class="card-header">
🖥 อุปกรณ์ที่ยังไม่มีผู้ใช้งาน
</div>

<div class="card-body">

<table class="table table-bordered text-center">

<tr>
<th>#</th>
<th>รหัส</th>
<th>ประเภท</th>
<th>Spec</th>
<th>จัดการ</th>
</tr>

<?php $i=1; foreach($data as $d): ?>

<tr>
<td><?= $i++ ?></td>

<td class="fw-bold text-primary"><?= $d['no_pc'] ?></td>

<td><?= $d['type'] ?></td>

<td>
<?= implode(' | ',array_filter([
$d['spec'],$d['ram'],$d['ssd'],$d['gpu']
])) ?: '-' ?>
</td>

<td>

<?php
$mainTypes = ['PC','Notebook','All_In_One','Monitor','UPS'];
?>

<?php if(in_array($d['type'],$mainTypes)): ?>


<!-- 🔴 อุปกรณ์หลัก -->
<a href="asset_assign_user.php?no_pc=<?= $d['no_pc'] ?>&type=<?= $d['type'] ?>"
class="btn btn-primary btn-sm">
👤 เพิ่มผู้ใช้
</a>

<?php else: ?>

<!-- 🟢 อุปกรณ์ใช้ร่วม -->
<form method="post" class="d-inline">

<input type="hidden" name="no_pc" value="<?= $d['no_pc'] ?>">
<input type="hidden" name="type" value="<?= $d['type'] ?>">

<button type="button"
class="btn btn-success btn-sm openConfirm"
data-pc="<?= $d['no_pc'] ?>"
data-type="<?= $d['type'] ?>">
📦 นำมาใช้
</button>

</form>

<?php endif; ?>

</td>
</tr>

<?php endforeach; ?>

</table>

</div>
</div>
</div>

<!-- 🔥 MODAL CONFIRM -->
<div class="modal fade" id="confirmModal">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content shadow">

<div class="modal-header bg-success text-white">
<h5>ยืนยันการใช้งาน</h5>
</div>

<div class="modal-body text-center" id="confirmText"></div>

<div class="modal-footer">
<button class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
<button id="confirmBtn" class="btn btn-success">ยืนยัน</button>
</div>

</div>
</div>
</div>

<div class="modal fade" id="successModal">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content shadow">

<div class="modal-header bg-success text-white">
<h5>สำเร็จ</h5>
</div>

<div class="modal-body text-center">
✅ บันทึกเรียบร้อยแล้ว
</div>

<div class="modal-footer justify-content-center">
<button class="btn btn-success" data-bs-dismiss="modal">ตกลง</button>
</div>

</div>
</div>
</div>

<script>
document.querySelectorAll(".openConfirm").forEach(btn=>{

    btn.addEventListener("click", function(){

        let form = this.closest("form");
        let pc   = this.dataset.pc;
        let type = this.dataset.type;

        document.getElementById("confirmText").innerHTML =
            `<div class="fs-5">
                📦 <b>${pc}</b><br>
                <span class="text-muted">${type}</span>
            </div>`;

        // กันซ้อน event
        let confirmBtn = document.getElementById("confirmBtn");
        confirmBtn.onclick = null;

        confirmBtn.onclick = function(){
            confirmBtn.disabled = true; // กันกดซ้ำ
            form.submit();
        };

        new bootstrap.Modal(document.getElementById('confirmModal')).show();
    });

});
</script>

<?php if(isset($_GET['success'])): ?>
<script>
window.addEventListener('DOMContentLoaded', function(){

    // 🔥 แสดง modal success
    new bootstrap.Modal(document.getElementById('successModal')).show();

    // 🔥 ล้าง ?success=1 ออกจาก URL (กันรีเฟรชแล้วขึ้นซ้ำ)
    if(window.location.search.includes('success')){
        window.history.replaceState({}, document.title, window.location.pathname);
    }

});
</script>
<?php endif; ?>

<?php include 'partials/footer.php'; ?>