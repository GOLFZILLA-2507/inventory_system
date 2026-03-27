<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];
$loginUser = $_SESSION['fullname'];

/* =====================================================
   รับ asset_id
   - ตอนแรกมาจาก GET
   - ตอน submit มาจาก POST
===================================================== */
$transfer_id = $_GET['transfer_id'] ?? ($_POST['transfer_id'] ?? 0);
$no_pc = $_GET['no_pc'] ?? ($_POST['no_pc'] ?? '');

/* =====================================================
   โหลดข้อมูล asset
===================================================== */
$stmt = $conn->prepare("
SELECT t.*, a.*
FROM IT_AssetTransfer_Headers t
LEFT JOIN IT_assets a ON a.no_pc = t.no_pc
WHERE t.transfer_id = ?
");
$stmt->execute([$transfer_id]);
$asset = $stmt->fetch(PDO::FETCH_ASSOC);

/* =====================================================
   โหลดรายชื่อ user ในโครงการเดียวกัน
===================================================== */
$stmt = $conn->prepare("
SELECT fullname, position
FROM Employee
WHERE site = ?
");

$stmt->execute([$site]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
   เมื่อกดบันทึก
===================================================== */
if(isset($_POST['save'])){

    $transfer_id   = $_POST['transfer_id'];
    $no_pc         = $_POST['no_pc'];
    $user_employee = $_POST['user_employee'];

    /* =====================================================
    🔥 โหลดข้อมูล asset
    ===================================================== */
    $stmtA = $conn->prepare("
    SELECT type_equipment
    FROM IT_assets
    WHERE no_pc=?
    ");
    $stmtA->execute([$no_pc]);
    $type = $stmtA->fetchColumn();

    /* =====================================================
    🔥 โหลด user
    ===================================================== */
    $stmtUser = $conn->prepare("
    SELECT position, site
    FROM Employee
    WHERE fullname = ?
    ");
    $stmtUser->execute([$user_employee]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if(!$userData){
        die("ไม่พบผู้ใช้");
    }

    $user_position = $userData['position'];
    $user_project  = $userData['site'];

    /* =====================================================
    🔥 หา user แถวเดิม
    ===================================================== */
    $stmt = $conn->prepare("
    SELECT * FROM IT_user_information
    WHERE user_employee=? AND user_project=?
    ");
    $stmt->execute([$user_employee,$user_project]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    /* =====================================================
    🔥 TYPE LOGIC
    ===================================================== */

    if($type == 'Monitor'){

        if($row){

            if(empty($row['user_monitor1'])){
                $conn->prepare("
                UPDATE IT_user_information
                SET user_monitor1=?, user_update=GETDATE()
                WHERE id=?
                ")->execute([$no_pc,$row['id']]);

            }elseif(empty($row['user_monitor2'])){
                $conn->prepare("
                UPDATE IT_user_information
                SET user_monitor2=?, user_update=GETDATE()
                WHERE id=?
                ")->execute([$no_pc,$row['id']]);

            }else{
                die("<script>alert('ผู้ใช้นี้มีจอครบแล้ว');history.back();</script>");
            }

        }else{

            $conn->prepare("
            INSERT INTO IT_user_information
            (user_employee,user_position,user_project,user_monitor1,user_record,user_update)
            VALUES (?,?,?,?,?,GETDATE())
            ")->execute([
                $user_employee,
                $user_position,
                $user_project,
                $no_pc,
                $loginUser
            ]);
        }

    }elseif($type == 'UPS'){

        if($row && !empty($row['user_ups'])){
            die("<script>alert('มี UPS อยู่แล้ว');history.back();</script>");
        }

        if($row){
            $conn->prepare("
            UPDATE IT_user_information
            SET user_ups=?, user_update=GETDATE()
            WHERE id=?
            ")->execute([$no_pc,$row['id']]);
        }else{
            $conn->prepare("
            INSERT INTO IT_user_information
            (user_employee,user_position,user_project,user_ups,user_record,user_update)
            VALUES (?,?,?,?,?,GETDATE())
            ")->execute([
                $user_employee,
                $user_position,
                $user_project,
                $no_pc,
                $loginUser
            ]);
        }

    }else{ // PC / Notebook

        if($row && !empty($row['user_no_pc'])){
            die("<script>alert('มีเครื่องใช้อยู่แล้ว');history.back();</script>");
        }

        if($row){
            $conn->prepare("
            UPDATE IT_user_information
            SET user_no_pc=?, user_update=GETDATE()
            WHERE id=?
            ")->execute([$no_pc,$row['id']]);
        }else{
            $conn->prepare("
            INSERT INTO IT_user_information
            (user_employee,user_position,user_project,user_no_pc,user_record,user_update)
            VALUES (?,?,?,?,?,GETDATE())
            ")->execute([
                $user_employee,
                $user_position,
                $user_project,
                $no_pc,
                $loginUser
            ]);
        }
    }

    /* =====================================================
    🔥 HISTORY
    ===================================================== */
    $conn->prepare("
    INSERT INTO IT_user_history
    (user_employee,user_project,user_no_pc,created_at,created_by)
    VALUES (?,?,?,GETDATE(),?)
    ")->execute([
        $user_employee,
        $user_project,
        $no_pc,
        $loginUser
    ]);

    /* =====================================================
    🔥 UPDATE TRANSFER
    ===================================================== */
    $conn->prepare("
    UPDATE IT_AssetTransfer_Headers
    SET user_status=?
    WHERE transfer_id=?
    ")->execute([$user_employee,$transfer_id]);

    header("Location: asset_assign_user.php?success=1&pc=".$no_pc);
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

<!-- =====================================================
     ส่ง asset_id ไปตอน submit (สำคัญมาก)
===================================================== -->
<input type="hidden" name="transfer_id" value="<?= $transfer_id ?>">
<input type="hidden" name="no_pc" value="<?= $no_pc ?>">

<div class="mb-3">
<label>รหัสอุปกรณ์</label>
<input class="form-control" value="<?= $asset['no_pc'] ?>" readonly>
</div>

<div class="mb-3">
<label>เลือกผู้ใช้งาน</label>
<select name="user_employee" class="form-control" required>

<option value="">-- เลือกผู้ใช้ --</option>

<?php foreach($users as $u): ?>
<option value="<?= htmlspecialchars($u['fullname']) ?>">
<?= htmlspecialchars($u['fullname']) ?>
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

<div class="modal fade" id="confirmModal">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header bg-success text-white">
        <h5>ยืนยัน</h5>
      </div>

      <div class="modal-body text-center">
        ต้องการเพิ่มอุปกรณ์นี้ใช่หรือไม่?
      </div>

      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
        <button type="submit" name="save" form="mainForm" class="btn btn-success">
            ยืนยัน
        </button>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="successModal">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-4">
        <h4>สำเร็จ</h4>
        <p id="successText"></p>
    </div>
  </div>
</div>

<?php include 'partials/footer.php'; ?>

<script>
document.getElementById("openConfirm").onclick = function(){
    new bootstrap.Modal(document.getElementById('confirmModal')).show();
};

<?php if(isset($_GET['success'])): ?>

document.getElementById("successText").innerHTML =
"เพิ่ม <?= $_GET['pc'] ?> สำเร็จ";

new bootstrap.Modal(document.getElementById('successModal')).show();

<?php endif; ?>
</script>