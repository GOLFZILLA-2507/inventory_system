<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];
$user = $_SESSION['fullname'];

/* =========================================
🔥 โหลดพนักงาน
========================================= */
$employees = $conn->prepare("
SELECT fullname FROM Employee
WHERE site = ?
ORDER BY fullname
");
$employees->execute([$site]);
$employees = $employees->fetchAll(PDO::FETCH_ASSOC);

/* =========================================
🔥 โหลดประเภทจาก IT_assets
========================================= */
$types = $conn->query("
SELECT DISTINCT type_equipment 
FROM IT_assets
WHERE type_equipment IN ('PC','Notebook','All_In_One','Monitor','UPS')
ORDER BY type_equipment
")->fetchAll(PDO::FETCH_COLUMN);

/* =========================================
🔥 SUBMIT
========================================= */
if(isset($_POST['submit'])){


    $emp   = $_POST['employee'];
    $type  = $_POST['type'];

    // 🔥 fix ชื่อ
    $name  = "ไม่มีรหัสอุปกรณ์";

    if(empty($emp) || empty($type)){
        echo "<script>alert('กรอกข้อมูลให้ครบ');history.back();</script>";
        exit;
    }

    try{

        $conn->beginTransaction();

        /* ================= หา user ================= */
        $stmt = $conn->prepare("
        SELECT * FROM IT_user_information
        WHERE user_employee=? AND user_project=?
        ");
        $stmt->execute([$emp,$site]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);

        if(!$u){
            $conn->prepare("
            INSERT INTO IT_user_information(user_employee,user_project,user_update)
            VALUES(?,?,GETDATE())
            ")->execute([$emp,$site]);

            $u = [
                'id'=>$conn->lastInsertId(),
                'user_no_pc'=>null,
                'user_monitor1'=>null,
                'user_monitor2'=>null,
                'user_ups'=>null
            ];
        }

        /* =========================================
        🔥 แยก logic ตามประเภท
        ========================================= */

        // 🔵 กลุ่มเครื่องหลัก
        if(in_array($type,['PC','Notebook','All_In_One'])){

            if(!empty($u['user_no_pc'])){
                throw new Exception("ผู้ใช้นี้มีเครื่องหลักแล้ว");
            }

            $conn->prepare("
            UPDATE IT_user_information SET user_no_pc=? WHERE id=?
            ")->execute([$name,$u['id']]);
        }

        // 🟡 Monitor
        elseif($type == 'Monitor'){

            if(empty($u['user_monitor1'])){
                $conn->prepare("
                UPDATE IT_user_information SET user_monitor1=? WHERE id=?
                ")->execute([$name,$u['id']]);

            }elseif(empty($u['user_monitor2'])){
                $conn->prepare("
                UPDATE IT_user_information SET user_monitor2=? WHERE id=?
                ")->execute([$name,$u['id']]);

            }else{
                throw new Exception("จอเต็มแล้ว (2 จอ)");
            }
        }

        // 🟣 UPS
        elseif($type == 'UPS'){

            if(!empty($u['user_ups'])){
                throw new Exception("มี UPS แล้ว");
            }

            $conn->prepare("
            UPDATE IT_user_information SET user_ups=? WHERE id=?
            ")->execute([$name,$u['id']]);
        }

        // 🔴 อื่นๆ ไม่อนุญาต
        else{
            throw new Exception("ประเภทนี้ใช้หน้านี้ไม่ได้");
        }

        /* =========================================
        🔥 INSERT HISTORY
        ========================================= */

        $stmt = $conn->prepare("
        INSERT INTO IT_user_history (
            user_employee,
            user_project,
            user_no_pc,
            history_type,
            start_date,
            created_at,
            created_by,
            action_type
        )
        VALUES (?,?,?, ?,GETDATE(),GETDATE(),?,?)
        ");

        $stmt->execute([
            $emp,
            $site,
            $name,
            $type,
            $user,
            'assign'
        ]);

        $conn->commit();

        echo "<script>alert('บันทึกสำเร็จ');location.href='asset_shared_view.php';</script>";
        exit;

    }catch(Exception $e){

        $conn->rollBack();
        echo "<script>alert('❌ ".$e->getMessage()."');history.back();</script>";
        exit;
    }
}
?>

<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>

<div class="container mt-4">
<div class="card shadow">
<div class="card-header bg-success text-white">
เพิ่มอุปกรณ์ (ไม่มีรหัส)
</div>

<div class="card-body">

<form method="post">

<label>พนักงาน</label>
<select name="employee" class="form-control mb-3" required>
<option value="">-- เลือกพนักงาน --</option>
<?php foreach($employees as $e): ?>
<option value="<?= $e['fullname'] ?>">
<?= $e['fullname'] ?>
</option>
<?php endforeach; ?>
</select>

<label>ประเภทอุปกรณ์</label>
<select name="type" class="form-control mb-3" required>
<option value="">-- เลือกประเภท --</option>
<?php foreach($types as $t): ?>
<option><?= $t ?></option>
<?php endforeach; ?>
</select>

<div class="alert alert-info">
ระบบจะบันทึกเป็น: <b>ไม่มีรหัสอุปกรณ์</b>
</div>

<div class="text-end">
<button class="btn btn-success" name="submit">💾 บันทึก</button>
</div>

</form>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>