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
$asset_id = $_GET['asset_id'] ?? ($_POST['asset_id'] ?? 0);

/* =====================================================
   โหลดข้อมูล asset
===================================================== */
$stmt = $conn->prepare("
SELECT *
FROM IT_user_information
WHERE asset_id = ?
");

$stmt->execute([$asset_id]);
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

    $user_employee = $_POST['user_employee'] ?? '';
    $asset_id = $_POST['asset_id'] ?? 0;

    if($asset_id > 0 && !empty($user_employee)){

        /* ===============================
           ดึงข้อมูลของ user ที่เลือก
        =============================== */
        $stmtUser = $conn->prepare("
        SELECT position, site
        FROM Employee
        WHERE fullname = ?
        ");

        $stmtUser->execute([$user_employee]);
        $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

        /* กันพัง */
        if(!$userData){
            echo "<script>alert('ไม่พบข้อมูลผู้ใช้');</script>";
            exit;
        }

        $user_position = $userData['position']; // ตำแหน่งของผู้ใช้
        $user_project  = $userData['site'];     // โครงการของผู้ใช้

        /* ===============================
           update ลง asset
        =============================== */
        $stmt = $conn->prepare("
        UPDATE IT_user_information
        SET 
        user_employee = ?,   -- ชื่อผู้ใช้
        user_position = ?,   -- ตำแหน่งของผู้ใช้
        user_project  = ?    -- โครงการของผู้ใช้
        WHERE asset_id = ?
        ");

        $stmt->execute([
            $user_employee,
            $user_position,
            $user_project,
            $asset_id
        ]);

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
<input type="hidden" name="asset_id" value="<?= $asset_id ?>">

<div class="mb-3">
<label>รหัสอุปกรณ์</label>
<input class="form-control" value="<?= $asset['user_no_pc'] ?>" readonly>
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