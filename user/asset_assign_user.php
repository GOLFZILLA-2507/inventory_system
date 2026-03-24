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

    $transfer_id = $_POST['transfer_id'] ?? 0;
    $no_pc = $_POST['no_pc'] ?? '';
    $user_employee = $_POST['user_employee'] ?? '';

    // 🔥 เช็คใหม่ (ใช้ transfer_id แทน asset_id)
    if(!empty($transfer_id) && !empty($no_pc) && !empty($user_employee)){

        /* โหลด user */
        $stmtUser = $conn->prepare("
        SELECT position, site
        FROM Employee
        WHERE fullname = ?
        ");
        $stmtUser->execute([$user_employee]);
        $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if(!$userData){
            echo "<script>alert('ไม่พบผู้ใช้');</script>";
            exit;
        }

        $user_position = $userData['position'];
        $user_project  = $userData['site'];

        /* 🔥 INSERT */
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

        /* 🔥 UPDATE status */
        $conn->prepare("
        UPDATE IT_AssetTransfer_Headers
        SET status='ถูกนำไปใช้แล้ว'
        WHERE transfer_id=?
        ")->execute([$transfer_id]);

        header("Location: asset_available.php");
        exit;

    }else{
        echo "<script>alert('ข้อมูลไม่ครบ');</script>";
    }
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

<form method="post">

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

<button class="btn btn-success" name="save">
💾 บันทึก
</button>

<a href="asset_available.php" class="btn btn-secondary">
ย้อนกลับ
</a>

</form>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>