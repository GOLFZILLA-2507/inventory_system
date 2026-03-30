<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];
$user = $_SESSION['fullname'];

/* =========================================
🔥 ตรวจอุปกรณ์ซ้ำ + บอกว่า ซ้ำที่ไหน
========================================= */
function checkDuplicateDetail($conn,$no_pc){

    $stmt = $conn->prepare("
    SELECT TOP 1 
        user_employee,
        user_project,

        CASE 
            WHEN user_no_pc = ? THEN 'PC'
            WHEN user_monitor1 = ? THEN 'Monitor1'
            WHEN user_monitor2 = ? THEN 'Monitor2'
            WHEN user_ups = ? THEN 'UPS'
        END AS duplicate_field

    FROM IT_user_information
    WHERE 
        user_no_pc = ?
        OR user_monitor1 = ?
        OR user_monitor2 = ?
        OR user_ups = ?
    ");

    $stmt->execute([
        $no_pc,$no_pc,$no_pc,$no_pc,
        $no_pc,$no_pc,$no_pc,$no_pc
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/* =========================================
🔥 NEW: บันทึก history (1 อุปกรณ์ = 1 row)
+ เก็บประเภทลง history_type
========================================= */
function saveHistoryNew($conn,$emp,$site,$no_pc,$admin){

    // 🔥 ดึงประเภทอุปกรณ์
    $stmtType = $conn->prepare("
    SELECT type_equipment 
    FROM IT_assets 
    WHERE no_pc=?
    ");
    $stmtType->execute([$no_pc]);
    $type = $stmtType->fetchColumn();

    // 🔥 insert history
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
        $no_pc,
        $type,
        $admin,
        'assign'
    ]);
}

/* ================= โหลดพนักงาน ================= */
$employees = $conn->prepare("
SELECT fullname, position, department
FROM Employee
WHERE site = ?
ORDER BY fullname
");
$employees->execute([$site]);
$employees = $employees->fetchAll(PDO::FETCH_ASSOC);

/* ================= โหลดอุปกรณ์ ================= */
function getAssets($conn,$types){

    $in  = str_repeat('?,', count($types) - 1) . '?';

    $sql = "
    SELECT asset_id,no_pc,new_no,Equipment_details,type_equipment,spec,ram,ssd,gpu
    FROM IT_assets
    WHERE type_equipment IN ($in)
    AND (use_it IS NULL OR use_it = '')
    ORDER BY no_pc
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($types);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$computers = getAssets($conn,['PC','Notebook','All_In_One']);
$monitors  = getAssets($conn,['Monitor']);
$upsList   = getAssets($conn,['UPS']);

/* ================= SUBMIT ================= */
if(isset($_POST['submit'])){

    $emp  = $_POST['employee'] ?? null;
    $asset_id = $_POST['asset_id'] ?? null;
    $m1  = $_POST['monitor1'] ?? null;
    $m2  = $_POST['monitor2'] ?? null;
    $ups = $_POST['ups'] ?? null;

    /* 🔴 ตรวจ input */
    if(empty($emp)){
        echo "<script>alert('กรุณาเลือกพนักงาน');history.back();</script>";
        exit;
    }

    if($emp == $site){
        echo "<script>alert('ห้าม assign ให้โครงการ');history.back();</script>";
        exit;
    }

    /* ================= โหลด PC ================= */
    $pc = null;

    if(!empty($asset_id)){
        $stmt = $conn->prepare("
        SELECT no_pc FROM IT_assets WHERE asset_id=?
        ");
        $stmt->execute([$asset_id]);
        $pc = $stmt->fetchColumn();
    }

    /* ================= หา user ================= */
    $stmt = $conn->prepare("
    SELECT * FROM IT_user_information
    WHERE user_employee=? AND user_project=?
    ");
    $stmt->execute([$emp,$site]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

    /* =========================================
    🔴 กรณีไม่มี user → INSERT ใหม่
    ========================================= */
    if(!$userRow){

        $stmt = $conn->prepare("
        INSERT INTO IT_user_information(
            user_employee,user_project,
            user_no_pc,
            user_monitor1,user_monitor2,user_ups,
            user_update,user_record
        )
        VALUES(?,?,?,?,?,?,GETDATE(),?)
        ");

        $stmt->execute([
            $emp,
            $site,
            $pc,
            $m1,
            $m2,
            $ups,
            $user
        ]);

        $userRow = [
            'user_no_pc'=>$pc,
            'user_monitor1'=>$m1,
            'user_monitor2'=>$m2,
            'user_ups'=>$ups,
            'id'=>$conn->lastInsertId()
        ];
    }

    /* =========================================
    🔵 PC
    ========================================= */
    if(!empty($pc)){

        $dup = checkDuplicateDetail($conn,$pc);

        if($dup){
            echo "<script>alert('❌ อุปกรณ์ซ้ำ\nรหัส: $pc\nใช้โดย: {$dup['user_employee']}\nโครงการ: {$dup['user_project']}\nตำแหน่ง: {$dup['duplicate_field']}');history.back();</script>";
            exit;
        }

        if(!empty($userRow['user_no_pc'])){
            echo "<script>alert('❌ ผู้ใช้นี้มี PC อยู่แล้ว');history.back();</script>";
            exit;
        }

        $conn->prepare("
        UPDATE IT_user_information
        SET user_no_pc=?
        WHERE id=?
        ")->execute([$pc,$userRow['id']]);

        $conn->prepare("
        UPDATE IT_assets 
        SET use_it=?, project=?, [update]=GETDATE()
        WHERE no_pc=?
        ")->execute([$emp,$site,$pc]);

        saveHistoryNew($conn,$emp,$site,$pc,$user);
    }

    /* =========================================
    🟡 MONITOR
    ========================================= */
    $monitorList = [];
    if(!empty($m1)) $monitorList[] = $m1;
    if(!empty($m2)) $monitorList[] = $m2;

    foreach($monitorList as $monitor){

        $dup = checkDuplicateDetail($conn,$monitor);

        if($dup){
            echo "<script>alert('❌ Monitor ซ้ำ\nรหัส: $monitor\nใช้โดย: {$dup['user_employee']}\nโครงการ: {$dup['user_project']}');history.back();</script>";
            exit;
        }

        if(empty($userRow['user_monitor1'])){

            $conn->prepare("
            UPDATE IT_user_information 
            SET user_monitor1=? 
            WHERE id=?
            ")->execute([$monitor,$userRow['id']]);

            $userRow['user_monitor1'] = $monitor;

        }elseif(empty($userRow['user_monitor2'])){

            $conn->prepare("
            UPDATE IT_user_information 
            SET user_monitor2=? 
            WHERE id=?
            ")->execute([$monitor,$userRow['id']]);

            $userRow['user_monitor2'] = $monitor;

        }else{
            echo "<script>alert('จอครบ 2 แล้ว');history.back();</script>";
            exit;
        }

        $conn->prepare("
        UPDATE IT_assets 
        SET use_it=?, project=?, [update]=GETDATE()
        WHERE no_pc=?
        ")->execute([$emp,$site,$monitor]);

        saveHistoryNew($conn,$emp,$site,$monitor,$user);
    }

    /* =========================================
    🟣 UPS
    ========================================= */
    if(!empty($ups)){

        $dup = checkDuplicateDetail($conn,$ups);

        if($dup){
            echo "<script>alert('❌ UPS ซ้ำ\nรหัส: $ups\nใช้โดย: {$dup['user_employee']}\nโครงการ: {$dup['user_project']}');history.back();</script>";
            exit;
        }

        if(!empty($userRow['user_ups'])){
            echo "<script>alert('❌ มี UPS แล้ว');history.back();</script>";
            exit;
        }

        $conn->prepare("
        UPDATE IT_user_information
        SET user_ups=?
        WHERE id=?
        ")->execute([$ups,$userRow['id']]);

        $conn->prepare("
        UPDATE IT_assets 
        SET use_it=?, project=?, [update]=GETDATE()
        WHERE no_pc=?
        ")->execute([$emp,$site,$ups]);

        saveHistoryNew($conn,$emp,$site,$ups,$user);
    }

    header("Location: asset_shared_view.php?success=1");
    exit;
}

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<style>
body{font-family:'Sarabun';font-size:14px;}
.card-header{background:linear-gradient(135deg,#198754,#20c997);color:white;}
</style>

<div class="container mt-4">
<div class="card shadow">
<div class="card-header">
<h5 class="mb-0">🖥️ จัดการอุปกรณ์ให้พนักงาน</h5>
</div>

<div class="card-body">

<?php if(isset($_GET['success'])): ?>
<div class="alert alert-success">บันทึกข้อมูลเรียบร้อย</div>
<?php endif; ?>

<form method="post" onsubmit="return confirm('ยืนยันการมอบอุปกรณ์ให้พนักงาน ?')">

<div class="row">

<div class="col-md-6">
<label>เลือกพนักงาน</label>
<select id="empSelect" name="employee" class="form-control mb-2" required>
<option value="">-- เลือกพนักงาน --</option>
<?php foreach($employees as $e): ?>
<option value="<?= $e['fullname'] ?>"
data-pos="<?= $e['position'] ?>"
data-dep="<?= $e['department'] ?>">
<?= $e['fullname'] ?>
</option>
<?php endforeach; ?>
</select>

<input type="text" id="position" name="position" class="form-control mb-2" readonly>
<input type="text" id="department" class="form-control mb-3" readonly>
</div>

<div class="col-md-6">
<label>เลือกเครื่อง</label>
<select id="pcSelect" name="asset_id" class="form-control mb-2" >
<option value="">-- เลือกเครื่อง --</option>

<?php foreach($computers as $c): ?>

<option value="<?= $c['asset_id'] ?>"

data-pc="<?= $c['no_pc'] ?>"
data-new="<?= $c['new_no'] ?>"
data-detail="<?= $c['Equipment_details'] ?>"

data-spec="<?= $c['spec'] ?>"
data-ram="<?= $c['ram'] ?>"
data-ssd="<?= $c['ssd'] ?>"
data-gpu="<?= $c['gpu'] ?>"

data-type="<?= $c['type_equipment'] ?>"
>

<?= $c['no_pc'] ?>

</option>

<?php endforeach; ?>
</select>
<input type="text" id="no_pc" name="no_pc" class="form-control mb-2" readonly>

<label>รหัสใหม่</label>
<input type="text" id="new_no" class="form-control mb-2" readonly>

<label>รายละเอียดอุปกรณ์</label>
<input type="text" id="equipment_details" class="form-control mb-2" readonly>

<input type="text" id="spec_full" class="form-control mb-2" readonly>
<input type="text" id="spec_full" class="form-control mb-2" readonly>
<input type="hidden" name="spec" id="spec">
<input type="hidden" name="ram" id="ram">
<input type="hidden" name="ssd" id="ssd">
<input type="hidden" name="gpu" id="gpu">
<input type="hidden" name="type_equipment" id="type_equipment">
</div>

<hr>

<div class="row">
<div class="col-md-4">
<label>Monitor 1</label>
<select name="monitor1" class="form-control" >
<option value="">-- เลือกจอ --</option>
<?php foreach($monitors as $m): ?>
<option value="<?= $m['no_pc'] ?>"
<?= (isset($oldData['user_monitor1']) && $oldData['user_monitor1']==$m['no_pc'])?'selected':'' ?>>
<?= $m['no_pc'] ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-4">
<label>Monitor 2</label>
<select name="monitor2" class="form-control">
<option value="">-- เลือกจอ --</option>
<?php foreach($monitors as $m): ?>
<option value="<?= $m['no_pc'] ?>"
<?= (isset($oldData['user_monitor2']) && $oldData['user_monitor2']==$m['no_pc'])?'selected':'' ?>>
<?= $m['no_pc'] ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-4">
<label>UPS</label>
<select name="ups" class="form-control" >
<option value="">-- เลือก UPS --</option>
<?php foreach($upsList as $u): ?>
<option value="<?= $u['no_pc'] ?>"
<?= (isset($oldData['user_ups']) && $oldData['user_ups']==$u['no_pc'])?'selected':'' ?>>
<?= $u['no_pc'] ?>
</option>
<?php endforeach; ?>
</select>
</div>
</div>

<div class="text-end mt-3">
<button class="btn btn-success px-4" name="submit">💾 บันทึก</button>
</div>

</form>

</div>
</div>
</div>


<script>
document.getElementById('empSelect').addEventListener('change',function(){
let opt=this.options[this.selectedIndex];
document.getElementById('position').value=opt.getAttribute('data-pos')||'';
document.getElementById('department').value=opt.getAttribute('data-dep')||'';
});

document.getElementById('pcSelect').addEventListener('change',function(){
let opt=this.options[this.selectedIndex];

let pc = opt.getAttribute('data-pc')||'';
let new_no = opt.getAttribute('data-new')||'';
let details = opt.getAttribute('data-detail')||'';
let spec = opt.getAttribute('data-spec')||'';
let ram = opt.getAttribute('data-ram')||'';
let ssd = opt.getAttribute('data-ssd')||'';
let gpu = opt.getAttribute('data-gpu')||'';
let type = opt.getAttribute('data-type')||'';

document.getElementById('no_pc').value = pc;
document.getElementById('new_no').value = new_no;
document.getElementById('equipment_details').value = details;
document.getElementById('spec_full').value = spec+" | RAM "+ram+" | SSD "+ssd+" | GPU "+gpu;
document.getElementById('spec').value = spec;
document.getElementById('ram').value = ram;
document.getElementById('ssd').value = ssd;
document.getElementById('gpu').value = gpu;
document.getElementById('type_equipment').value = type;
});
</script>

<?php include 'partials/footer.php'; ?>