<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];       // 🔥 โครงการ
$user = $_SESSION['fullname'];   // 🔥 คนบันทึก

/* =========================================
🔥 mapping field ตามประเภท
========================================= */
$fieldMap = [
    'CCTV'            => 'user_cctv',
    'NVR'             => 'user_nvr',
    'Printer'         => 'user_printer',
    'Projector'       => 'user_projector',
    'Server'          => 'user_Server',
    'Audio'           => 'user_audio_set',
    'Plotter'         => 'user_plotter',
    'Accessories_IT'  => 'user_Accessories_IT',
    'Drone'           => 'user_Drone',
    'Optical_Fiber'   => 'user_Optical_Fiber'
];

/* =========================================
🔥 SUBMIT
========================================= */
if(isset($_POST['submit'])){

    $type = $_POST['type'] ?? null;
    $name = "อุปกรณ์ไม่มีรหัส";

    if(empty($type) || !isset($fieldMap[$type])){
        echo "<script>alert('เลือกประเภทไม่ถูกต้อง');history.back();</script>";
        exit;
    }

    $field = $fieldMap[$type];

    try{

        $conn->beginTransaction();

        /* =========================================
        🔥 หา row ของโครงการ
        ========================================= */
        $stmt = $conn->prepare("
            SELECT TOP 1 *
            FROM IT_user_information
            WHERE user_project=? AND user_type_equipment='SHARED'
            ORDER BY id ASC
            ");
        $stmt->execute([$site]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        /* =========================================
        🔥 ถ้าไม่มี → สร้างใหม่
        ========================================= */
        if(!$row){

            $conn->prepare("
            INSERT INTO IT_user_information(
                user_employee,
                user_project,
                user_type_equipment,
                user_update,
                user_record
            )
            VALUES(?,?, 'SHARED', GETDATE(),?)
            ")->execute([
                $site,
                $site,
                $user
            ]);

            $row = [
                'id'=>$conn->lastInsertId()
            ];
        }

        /* =========================================
        🔥 ดึงค่าเดิม
        ========================================= */
        $check = $conn->prepare("
        SELECT $field FROM IT_user_information WHERE id=?
        ");
        $check->execute([$row['id']]);
        $oldValue = $check->fetchColumn();

        /* 🔥 แปลง NULL → '' */
        $oldValue = $oldValue ?? '';

        if(trim($oldValue) !== ''){
            $newValue = $oldValue . ", " . $name;
        }else{
            $newValue = $name;
        }

        /* =========================================
        🔥 UPDATE ลง field
        ========================================= */
        $conn->prepare("
        UPDATE IT_user_information
        SET $field=?, user_update=GETDATE()
        WHERE id=?
        ")->execute([$newValue,$row['id']]);

        /* =========================================
        🔥 INSERT HISTORY (1 device = 1 row)
        ========================================= */
        $stmt = $conn->prepare("
        INSERT INTO IT_user_history (
            asset_id,
            user_employee,
            user_project,
            user_no_pc,
            action_type,
            reference_id,
            created_at,
            created_by,
            history_type,
            start_date
        )
        VALUES (
            NULL,
            ?,?,?, 'shared_assign',
            NULL,
            GETDATE(),
            ?,
            ?,
            GETDATE()
        )
        ");

        $stmt->execute([
            $site,     // 🔥 เอาโครงการลง
            $site,
            $name,
            $user,
            $type
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
📡 เพิ่มอุปกรณ์ใช้ร่วม (ไม่มีรหัส)
</div>

<div class="card-body">

<form method="post">

<!-- 🔥 แสดงโครงการ -->
<div class="mb-3">
<label>โครงการ</label>
<input class="form-control" value="<?= $site ?>" readonly>
</div>

<!-- 🔥 เลือกประเภท -->
<div class="mb-3">
<label>ประเภทอุปกรณ์</label>
<select name="type" class="form-control" required>
<option value="">-- เลือกประเภท --</option>
<?php foreach($fieldMap as $k=>$v): ?>
<option value="<?= $k ?>"><?= $k ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="alert alert-info">
ระบบจะบันทึกเป็น: <b>อุปกรณ์ไม่มีรหัส</b>
</div>

<div class="text-end">
<button class="btn btn-success" name="submit">💾 บันทึก</button>
</div>

</form>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>