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
if($_SERVER['REQUEST_METHOD']=='POST'){

    $transfer_id = $_POST['transfer_id'] ?? '';
    $no_pc       = $_POST['no_pc'] ?? '';
    $type        = $_POST['type'] ?? '';

    if(!$no_pc){
        die("ไม่พบรหัสอุปกรณ์");
    }

    // 🔥 เช็คซ้ำก่อนทุกครั้ง
    $dup = checkDuplicate($conn,$no_pc);

    if($dup){
        echo "<script>
        alert('❌ อุปกรณ์ซ้ำ\\nใช้โดย {$dup['user_employee']} ({$dup['user_project']})');
        history.back();
        </script>";
        exit;
    }

    /* =====================================================
    🔥 แยกประเภท (สำคัญมาก)
    ===================================================== */
    $mainTypes = ['PC','Notebook','All_In_One','Monitor','UPS'];

    /* =====================================================
    🔴 CASE 1: อุปกรณ์หลัก → ไปหน้า assign user
    ===================================================== */
    if(in_array($type,$mainTypes)){

        header("Location: asset_assign_user.php?no_pc=".$no_pc."&type=".$type."&transfer_id=".$transfer_id);
        exit;
    }

    /* =====================================================
    🟢 CASE 2: อุปกรณ์ใช้ร่วม → บันทึกทันที
    ===================================================== */
    else{

        // 🔥 insert ลง IT_user_devices
        insertShared($conn,$site,$type,$no_pc,$user);

        // 🔥 บันทึก history
        saveHistory($conn,$site,$no_pc,$type,$user);

        // 🔥 update transfer
        $conn->prepare("
            UPDATE IT_AssetTransfer_Headers
            SET user_status=?
            WHERE transfer_id=?
        ")->execute([$site,$transfer_id]);

        header("Location: asset_available.php?success=1");
        exit;
    }
}

/* =====================================================
🔥 โหลดรายการอุปกรณ์
===================================================== */
$stmt = $conn->prepare("
SELECT
t.transfer_id,
t.no_pc,
a.type_equipment AS type,
a.spec,a.ram,a.ssd,a.gpu,
t.from_site,
t.transfer_type,
t.arrived_date

FROM IT_AssetTransfer_Headers t
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
<a href="asset_assign_user.php?no_pc=<?= $d['no_pc'] ?>&type=<?= $d['type'] ?>&transfer_id=<?= $d['transfer_id'] ?>"
class="btn btn-primary btn-sm">
👤 เพิ่มผู้ใช้
</a>

<?php else: ?>

<!-- 🟢 อุปกรณ์ใช้ร่วม -->
<form method="post" class="d-inline">

<input type="hidden" name="transfer_id" value="<?= $d['transfer_id'] ?>">
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

<script>
document.querySelectorAll(".openConfirm").forEach(btn=>{

    btn.addEventListener("click", function(){

        let form = this.closest("form");
        let pc   = this.dataset.pc;
        let type = this.dataset.type;

        document.getElementById("confirmText").innerHTML =
            `<b>${pc}</b><br>${type}`;

        document.getElementById("confirmBtn").onclick = function(){
            form.submit();
        };

        new bootstrap.Modal(document.getElementById('confirmModal')).show();
    });

});
</script>

<?php include 'partials/footer.php'; ?>