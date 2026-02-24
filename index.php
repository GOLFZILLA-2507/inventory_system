<?php
session_start();
require_once 'config/connect.php'; // เชื่อมต่อกับฐานข้อมูล

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $EmployeeID = $_POST['EmployeeID'];
    $password = $_POST['password'];

    try {
        // ตรวจสอบข้อมูลผู้ใช้งานในฐานข้อมูล
        $stmt = $conn->prepare("SELECT EmployeeID, password, fullname, role, site, position, project_id FROM Employee WHERE EmployeeID = :EmployeeID AND active = 1");
        $stmt->bindParam(':EmployeeID', $EmployeeID);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // เช็คว่าไม่พบรหัสพนักงาน
        if (!$user) {
            $error = "รหัสพนักงานไม่ถูกต้อง หรือ พนักงานไม่ได้รับการอนุมัติ";
        }
        // เช็คว่ารหัสพนักงานตรง แต่รหัสผ่านผิด
        elseif (!password_verify($password, $user['password'])) {
            $error = "รหัสผ่านไม่ถูกต้อง";
        }
        else {
            // เก็บข้อมูลผู้ใช้งานใน session
            $_SESSION['EmployeeID'] = $user['EmployeeID'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['site'] = $user['site'];
            $_SESSION['position'] = $user['position'];
            $_SESSION['project_id'] = $user['project_id'];

            // เปลี่ยนเส้นทางไปยังหน้า dashboard
            if($user['role']=='admin'){ 
                echo "Hi Welcome Back Admin<br />";   
                echo "<script>window.location='admin/index.php'; </script>";
            }
            else{
                echo "Hi Welcome Back user<br />";   
                echo "<script>window.location='user/index.php'; </script>";
            }
            exit();
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
    <title>บริษัท วี.สถาปัตย์ จำกัด</title>
    <link rel="icon" type="image/x-icon" href="img/favicon_v.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-container {
            width: 500px;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        .login-logo {
            display: block;
            margin: 0 auto 20px;
            width: 260px;
            height: 80px;
        }
    </style>
</head>
<body>
    <div class="login-container">

        <img src="img/login-logo.png" alt="Logo" class="login-logo">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label for="EmployeeID" class="form-label">รหัสพนักงาน</label>
                <input type="text" class="form-control" id="EmployeeID" name="EmployeeID" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">รหัสผ่าน</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">เข้าสู่ระบบ</button>
        </form>
        <div class="mt-3 text-center">
            <a href="forgot_password.php">ลืมรหัสผ่าน?</a>
        </div>
    </div>
</body>
</html>