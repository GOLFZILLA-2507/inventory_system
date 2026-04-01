<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];
$loginUser = $_SESSION['fullname'];

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
🔥 FUNCTION: นับจำนวนอุปกรณ์ user
===================================================== */
function countDevice($conn,$emp,$type){
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM IT_user_devices
        WHERE user_employee=? AND device_type=?
    ");
    $stmt->execute([$emp,$type]);
    return $stmt->fetchColumn();
}

/* =====================================================
🔥 FUNCTION: insert device (1 row)
===================================================== */
function insertDevice($conn,$emp,$site,$type,$role,$code,$user){

    $conn->prepare("
        INSERT INTO IT_user_devices
        (user_employee,user_project,device_type,device_role,device_code,created_by,user_record)
        VALUES (?,?,?,?,?,?,?)
    ")->execute([
        $emp,
        $site,
        $type,
        $role,
        $code,
        $user,
        $user
    ]);
}

/* =====================================================
🔥 FUNCTION: save history
===================================================== */
function saveHistory($conn,$emp,$site,$code,$type,$user){

    $conn->prepare("
        INSERT INTO IT_user_history
        (user_employee,user_project,user_no_pc,action_type,created_at,created_by,history_type,start_date)
        VALUES (?,?,?,'assign_user',GETDATE(),?,?,GETDATE())
    ")->execute([
        $emp,
        $site,
        $code,
        $user,
        $type
    ]);
}

/* =====================================================
🔥 รับค่า
===================================================== */
$transfer_id = $_GET['transfer_id'] ?? ($_POST['transfer_id'] ?? 0);
$no_pc       = $_GET['no_pc'] ?? ($_POST['no_pc'] ?? '');

/* =====================================================
🔥 โหลด asset
===================================================== */
$stmt = $conn->prepare("
SELECT t.*, a.type_equipment
FROM IT_AssetTransfer_Headers t
LEFT JOIN IT_assets a ON a.no_pc = t.no_pc
WHERE t.transfer_id = ?
");
$stmt->execute([$transfer_id]);
$asset = $stmt->fetch(PDO::FETCH_ASSOC);

/* =====================================================
🔥 โหลด user
===================================================== */
$stmt = $conn->prepare("
SELECT fullname, position
FROM Employee
WHERE site = ?
");
$stmt->execute([$site]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
🔥 SUBMIT
===================================================== */
if(isset($_POST['save'])){

    $user_employee = $_POST['user_employee'];
    $type = $asset['type_equipment'];

    if(!$user_employee){
        die("<script>alert('เลือกผู้ใช้');history.back();</script>");
    }

    // 🔥 ไม่เช็คซ้ำ ถ้าเป็น "ไม่มีรหัสอุปกรณ์"
    if($no_pc !== 'ไม่มีรหัสอุปกรณ์'){

    $dup = checkDuplicate($conn,$no_pc);

    if($dup){
        die("<script>alert('❌ ซ้ำกับ {$dup['user_employee']} ({$dup['user_project']})');history.back();</script>");
    }
    }

    /* =====================================================
    🔥 TYPE LOGIC (หัวใจ)
    ===================================================== */

    // 🟡 MONITOR
    if($type == 'Monitor'){

        $count = countDevice($conn,$user_employee,'Monitor');

        if($count >= 2){
            die("<script>alert('❌ จอครบแล้ว (2 จอ)');history.back();</script>");
        }

        $role = ($count == 0) ? 'monitor1' : 'monitor2';

        insertDevice($conn,$user_employee,$site,'Monitor',$role,$no_pc,$loginUser);
        saveHistory($conn,$user_employee,$site,$no_pc,'Monitor',$loginUser);
    }

    // 🟣 UPS
    elseif($type == 'UPS'){

        if(countDevice($conn,$user_employee,'UPS') > 0){
            die("<script>alert('❌ มี UPS แล้ว');history.back();</script>");
        }

        insertDevice($conn,$user_employee,$site,'UPS','ups',$no_pc,$loginUser);
        saveHistory($conn,$user_employee,$site,$no_pc,'UPS',$loginUser);
    }

    // 🔵 PC / Notebook
    else{

        if(countDevice($conn,$user_employee,'PC') > 0){
            die("<script>alert('❌ มีเครื่องแล้ว');history.back();</script>");
        }

        insertDevice($conn,$user_employee,$site,'PC','main',$no_pc,$loginUser);
        saveHistory($conn,$user_employee,$site,$no_pc,'PC',$loginUser);
    }

    /* =====================================================
    🔥 UPDATE IT_assets (เฉพาะมีรหัสจริง)
    ===================================================== */

    // ❌ ไม่ update ถ้าเป็น "ไม่มีรหัสอุปกรณ์"
    if($no_pc !== 'ไม่มีรหัสอุปกรณ์'){

        $conn->prepare("
            UPDATE IT_assets
            SET use_it = ?
            WHERE no_pc = ?
        ")->execute([
            $site,     // 🔥 โครงการปัจจุบัน
            $no_pc
        ]);
    }

    /* =====================================================
    🔥 UPDATE TRANSFER
    ===================================================== */
    $conn->prepare("
        UPDATE IT_AssetTransfer_Headers
        SET user_status=?
        WHERE transfer_id=?
    ")->execute([$user_employee,$transfer_id]);

    header("Location: asset_available.php?success=1");
    exit;
}
?>

<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>

<div class="container mt-4">
<div class="card shadow">

<div class="card-header bg-success text-white">
👤 เพิ่มผู้ใช้อุปกรณ์
</div>

<div class="card-body">

<form method="post" id="mainForm">

<input type="hidden" name="transfer_id" value="<?= $transfer_id ?>">
<input type="hidden" name="no_pc" value="<?= $no_pc ?>">

<div class="mb-3">
<label>รหัสอุปกรณ์</label>
<input class="form-control" value="<?= $no_pc ?>" readonly>
</div>

<div class="mb-3">
<label>เลือกผู้ใช้งาน</label>
<select name="user_employee" class="form-control" required>
<option value="">-- เลือกผู้ใช้ --</option>
<?php foreach($users as $u): ?>
<option value="<?= $u['fullname'] ?>">
<?= $u['fullname'] ?>
</option>
<?php endforeach; ?>
</select>
</div>

<button type="button" id="openConfirm" class="btn btn-success">
💾 บันทึก
</button>

<a href="asset_available.php" class="btn btn-secondary">
ย้อนกลับ
</a>

</form>

</div>
</div>
</div>

<!-- 🔥 MODAL CONFIRM -->
<div class="modal fade" id="confirmModal">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">

<div class="modal-header bg-success text-white">
<h5>ยืนยัน</h5>
</div>

<div class="modal-body text-center">
ต้องการบันทึกใช่หรือไม่
</div>

<div class="modal-footer">
<button class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
<button type="submit" name="save" form="mainForm" class="btn btn-success">
ยืนยัน
</button>
</div>

</div>
</div>
</div>

<script>
document.getElementById("openConfirm").onclick = function(){
    new bootstrap.Modal(document.getElementById('confirmModal')).show();
};
</script>

<?php include 'partials/footer.php'; ?>