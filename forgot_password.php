<?php
require_once 'config/connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $EmployeeID = $_POST['EmployeeID'];

    try {
        // ตรวจสอบว่ารหัสพนักงานมีอยู่ในระบบ
        $stmt = $conn->prepare("SELECT * FROM Employee WHERE EmployeeID = :EmployeeID AND active = 1");
        $stmt->bindParam(':EmployeeID', $EmployeeID);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // รีเซ็ตรหัสผ่านเป็น 1234 และแฮชรหัสผ่านใหม่
            $newPassword = password_hash('1234', PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare("UPDATE Employee SET password = :password WHERE EmployeeID = :EmployeeID");
            $updateStmt->bindParam(':password', $newPassword);
            $updateStmt->bindParam(':EmployeeID', $EmployeeID);
            $updateStmt->execute();

            $success = "รีเซ็ตรหัสผ่านเรียบร้อยแล้ว <br>รหัสพนักงาน: " . htmlspecialchars($EmployeeID) . " <br>รหัสผ่านใหม่: 1234";
        } else {
            $error = "ไม่พบรหัสพนักงานในระบบ หรือไม่ได้รับการอนุมัติ";
        }
    } catch (Exception $e) {
        $error = "ข้อผิดพลาด: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รีเซ็ตรหัสผ่าน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        .container 
        {
            width: 500px; /* ความกว้าง */
            padding: 30px; /* เว้นระยะด้านใน */
            background: #ffffff; /* สีพื้นหลัง */
            border-radius: 10px; /* มุมกล่องโค้งมน */
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1); /* เงากล่อง */
            border: 1px solid #e0e0e0; /* เส้นขอบสีเทาอ่อน */
            transition: all 0.3s ease-in-out; /* เอฟเฟกต์เวลามีการเปลี่ยนแปลง */
        }
        .container:hover
        {
            box-shadow: 0 15px 25px rgba(0, 0, 0, 0.15); /* เงาเพิ่มเมื่อชี้เมาส์ */
            transform: translateY(-5px); /* ยกกล่องขึ้นเล็กน้อย */
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h3 class="text-center">รีเซ็ตรหัสผ่าน?</h3>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
            <?php elseif (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label for="EmployeeID" class="form-label">รหัสพนักงาน</label>
                <input type="text" class="form-control" id="EmployeeID" name="EmployeeID" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">รีเซ็ตรหัสผ่าน</button>
        </form>
        
        <div class="text-center mt-3">
            <a href="index.php" class="btn btn-secondary w-100">กลับไปหน้าเข้าสู่ระบบ</a>
        </div>
    </div>
</body>
</html>
