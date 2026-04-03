<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];
$loginUser = $_SESSION['fullname'];

/* =====================================================
🔥 FUNCTION
===================================================== */

function countRole($conn,$emp,$role,$site){
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM IT_user_devices
        WHERE user_employee=?
        AND device_role=?
        AND user_project=?
    ");
    $stmt->execute([$emp,$role,$site]);
    return $stmt->fetchColumn();
}

function hasMain($conn,$emp,$site){
    return countRole($conn,$emp,'main',$site);
}

function countMonitor($conn,$emp,$site){
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM IT_user_devices
        WHERE user_employee=?
        AND device_role IN ('monitor1','monitor2')
        AND user_project=?
    ");
    $stmt->execute([$emp,$site]);
    return $stmt->fetchColumn();
}

function countUPS($conn,$emp,$site){
    return countRole($conn,$emp,'ups',$site);
}

function hasAny($conn,$emp,$site){
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM IT_user_devices
        WHERE user_employee=?
        AND user_project=?
    ");
    $stmt->execute([$emp,$site]);
    return $stmt->fetchColumn();
}

function checkDuplicateDevice($conn,$code){

    if($code == 'ไม่มีรหัสอุปกรณ์') return false;

    $stmt = $conn->prepare("
        SELECT TOP 1 user_project
        FROM IT_user_devices
        WHERE device_code = ?
        AND user_employee IS NOT NULL
    ");
    $stmt->execute([$code]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/* =====================================================
🔥 รับค่า (สำคัญ)
===================================================== */
$no_pc = $_GET['no_pc'] ?? ($_POST['no_pc'] ?? '');
$transfer_id = $_GET['transfer_id'] ?? ($_POST['transfer_id'] ?? 0);

/* =====================================================
🔥 โหลด asset (แก้ใหม่ ใช้ no_pc)
===================================================== */
$stmt = $conn->prepare("
SELECT a.no_pc, a.type_equipment
FROM IT_assets a
WHERE a.no_pc = ?
");
$stmt->execute([$no_pc]);
$asset = $stmt->fetch(PDO::FETCH_ASSOC);

/* 🔥 กันพัง */
if(!$asset){
    die("<script>alert('❌ ไม่พบอุปกรณ์นี้ในระบบ');history.back();</script>");
}

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

    /* 🔥 เช็คซ้ำ */
    $dup = checkDuplicateDevice($conn,$no_pc);
    if($dup){
        die("<script>alert('❌ รหัสนี้ซ้ำอยู่ที่โครงการ {$dup['user_project']}');history.back();</script>");
    }

    try{

        $conn->beginTransaction();

        /* =====================================================
        🔥 TYPE LOGIC (ของจริง)
        ===================================================== */
        $hasAny  = hasAny($conn,$user_employee,$site);
        $hasMain = hasMain($conn,$user_employee,$site);

        if($hasAny == 0){
            $role = 'main';
        }
        elseif($hasMain == 0){
            $role = 'main';
        }
        else{

            if($type == 'Monitor'){

                if(countMonitor($conn,$user_employee,$site) >= 2){
                    throw new Exception('❌ จอครบแล้ว (2 จอ)');
                }

                $role = (countMonitor($conn,$user_employee,$site) == 0)
                        ? 'monitor1' : 'monitor2';
            }

            elseif($type == 'UPS'){

                if(countUPS($conn,$user_employee,$site) >= 1){
                    throw new Exception('❌ มี UPS แล้ว');
                }

                $role = 'ups';
            }

            elseif(in_array($type,['PC','Notebook'])){

                if($hasMain >= 1){
                    throw new Exception('❌ ผู้ใช้มีเครื่องหลักแล้ว');
                }

                $role = 'main';
            }

            else{
                $role = 'device';
            }
        }

        /* =====================================================
        🔥 UPDATE DEVICE
        ===================================================== */
        $stmt = $conn->prepare("
            UPDATE IT_user_devices
            SET 
                user_employee = ?,
                device_role   = ?,
                user_project  = ?,
                created_by    = ?
            WHERE device_code = ?
            AND user_employee IS NULL
        ");

        $stmt->execute([
            $user_employee,
            $role,
            $site,
            $loginUser,
            $no_pc
        ]);

        if($stmt->rowCount() == 0){
            throw new Exception("❌ อุปกรณ์นี้ถูกใช้งานแล้ว");
        }

        /* =====================================================
        🔥 HISTORY
        ===================================================== */
        $conn->prepare("
            UPDATE IT_user_history
            SET user_employee=?, user_project=?, created_by=?
            WHERE user_no_pc=? AND end_date IS NULL
        ")->execute([$user_employee,$site,$loginUser,$no_pc]);

        /* =====================================================
        🔥 ASSET
        ===================================================== */
        if($no_pc !== 'ไม่มีรหัสอุปกรณ์'){
            $conn->prepare("
                UPDATE IT_assets SET use_it=? WHERE no_pc=?
            ")->execute([$site,$no_pc]);
        }

        $conn->commit();

        header("Location: asset_available.php?success=1");
        exit;

    }catch(Exception $e){

        $conn->rollBack();
        echo "<script>alert('".$e->getMessage()."');history.back();</script>";
        exit;
    }
}

include 'partials/header.php';
include 'partials/sidebar.php';
?>


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