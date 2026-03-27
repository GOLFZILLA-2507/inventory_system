<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* ================= SAVE HISTORY ================= */
function saveHistory($conn,$emp,$site,$action,$admin){

    // 🔥 1. ดึง history ล่าสุดของ user
    $stmt = $conn->prepare("
    SELECT TOP 1 *
    FROM IT_user_history
    WHERE user_employee=? AND user_project=?
    ORDER BY history_id DESC
    ");
    $stmt->execute([$emp,$site]);
    $last = $stmt->fetch(PDO::FETCH_ASSOC);

    // 🔥 2. ดึงข้อมูลปัจจุบันจาก table หลัก
    $stmt2 = $conn->prepare("
    SELECT TOP 1 *
    FROM IT_user_information
    WHERE user_employee=? AND user_project=?
    ORDER BY id DESC
    ");
    $stmt2->execute([$emp,$site]);
    $current = $stmt2->fetch(PDO::FETCH_ASSOC);

    // ❌ ไม่มีข้อมูลหลัก → ไม่ต้องทำอะไร
    if(!$current) return;

    // =========================================
    // 🔥 3. เช็ค FULL (ครบชุดหรือยัง)
    // =========================================
    $isFull = false;

    if($last){
        $isFull = (
            !empty($last['user_no_pc']) &&
            !empty($last['user_monitor1']) &&
            !empty($last['user_monitor2']) &&
            !empty($last['user_ups'])
        );
    }

    // =========================================
    // 🔴 CASE 1: ไม่มี history → INSERT ใหม่
    // =========================================
    if(!$last){

        insertHistory($conn,$current,$action,$admin);
        return;
    }

    // =========================================
    // 🔴 CASE 2: FULL แล้ว → INSERT ใหม่
    // =========================================
    if($isFull){

        insertHistory($conn,$current,$action,$admin);
        return;
    }

    // =========================================
    // 🔴 CASE 3: ยังไม่ FULL → UPDATE แถวเดิม
    // =========================================
    $stmt = $conn->prepare("
    UPDATE IT_user_history SET
        asset_id = ?,
        user_no_pc = ?,
        user_monitor1 = ?,
        user_monitor2 = ?,
        user_ups = ?,
        user_update = GETDATE(),
        action_type = ?,
        created_by = ?
    WHERE history_id = ?
    ");

    $stmt->execute([
        $current['asset_id'] ?? null,
        $current['user_no_pc'],
        $current['user_monitor1'],
        $current['user_monitor2'],
        $current['user_ups'],
        $action,
        $admin,
        $last['history_id']
    ]);
}
function insertHistory($conn,$data,$action,$admin){

    $stmt = $conn->prepare("
    INSERT INTO IT_user_history (
        asset_id,
        user_employee,
        user_project,
        user_no_pc,
        user_monitor1,
        user_monitor2,
        user_ups,
        user_record,
        original_id,
        action_type,
        created_at,
        created_by
    )
    VALUES (
        ?,?,?,?,?,?,?,?,?,
        ?,GETDATE(),?
    )
    ");

    $stmt->execute([
        $data['asset_id'] ?? null,
        $data['user_employee'],
        $data['user_project'],
        $data['user_no_pc'],
        $data['user_monitor1'],
        $data['user_monitor2'],
        $data['user_ups'],
        $data['user_record'] ?? null,
        $data['id'], // จาก IT_user_information
        $action,
        $admin
    ]);
}
$site = $_SESSION['site'];
$user = $_SESSION['fullname'];

/* ================= โหลดพนักงาน ================= */
$employees = $conn->prepare("
SELECT fullname, position, department
FROM Employee
WHERE site = ?
ORDER BY fullname
");
$employees->execute([$site]);
$employees = $employees->fetchAll(PDO::FETCH_ASSOC);

/* ================= โหลดอุปกรณ์ หลักจาก IT_assets================= */
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
$pos  = $_POST['position'] ?? null;
$asset_id = $_POST['asset_id'] ?? null;
$m1  = $_POST['monitor1'] ?? null;
$m2  = $_POST['monitor2'] ?? null;
$ups = $_POST['ups'] ?? null;

/* 🔴 กัน assign ผิด */
if(empty($emp)){
    echo "<script>alert('กรุณาเลือกพนักงาน');history.back();</script>";
    exit;
}

/* 🔴 กัน SHARED */
if($emp == $site){
    echo "<script>alert('ห้าม assign ให้โครงการ');history.back();</script>";
    exit;
}

/* ================= โหลด PC ================= */
$pc = null;
$new_no = null;
$equipment_details = null;

if(!empty($asset_id)){
    $assetInfo = $conn->prepare("
    SELECT no_pc,new_no,Equipment_details
    FROM IT_assets
    WHERE asset_id=?
    ");
    $assetInfo->execute([$asset_id]);
    $row = $assetInfo->fetch(PDO::FETCH_ASSOC);

    $pc = $row['no_pc'] ?? null;
    $new_no = $row['new_no'] ?? null;
    $equipment_details = $row['Equipment_details'] ?? null;
}

/* ================= หา user ================= */
$checkUser = $conn->prepare("
SELECT * FROM IT_user_information
WHERE user_employee=? 
AND user_project=?
AND ISNULL(user_type_equipment,'') <> 'SHARED'
AND user_employee <> user_project
");
$checkUser->execute([$emp,$site]);
$userRow = $checkUser->fetch(PDO::FETCH_ASSOC);

/* ================= INSERT ================= */
if(!$userRow){

    $stmt = $conn->prepare("
    INSERT INTO IT_user_information(
        user_employee,user_project,
        user_no_pc,
        user_monitor1,user_monitor2, user_ups,
        user_update,user_record
    )
    VALUES(?,?,?,?,?, ?,GETDATE(),?)
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
// 🔥 save history หลัง insert
saveHistory($conn,$emp,$site,'assign',$user);
}else{
/* ================= UPDATE ================= */

// 🔴 PC (ห้ามทับ)
if(!empty($pc)){

    if(!empty($userRow['user_no_pc'])){
        echo "<script>alert('มี PC แล้ว');history.back();</script>";
        exit;
    }

    $conn->prepare("
    UPDATE IT_user_information
    SET user_no_pc=?
    WHERE id=?
    ")->execute([$pc,$userRow['id']]);
}

/* ================= MONITOR ================= */

// 🔴 รวม monitor ทั้ง 2 ตัว
$monitorsInput = [];

if(!empty($m1)) $monitorsInput[] = $m1;
if(!empty($m2)) $monitorsInput[] = $m2;

// 🔴 loop ใส่ทีละตัว
foreach($monitorsInput as $monitor){

    // เช็คว่ามีครบยัง
    if(empty($userRow['user_monitor1'])){

        $conn->prepare("
        UPDATE IT_user_information 
        SET user_monitor1=? 
        WHERE id=?
        ")->execute([$monitor,$userRow['id']]);

        $userRow['user_monitor1'] = $monitor; // อัพค่าในตัวแปรด้วย

    }elseif(empty($userRow['user_monitor2'])){

        $conn->prepare("
        UPDATE IT_user_information 
        SET user_monitor2=? 
        WHERE id=?
        ")->execute([$monitor,$userRow['id']]);

        $userRow['user_monitor2'] = $monitor;


    }else{
        echo "<script>alert('มีจอครบ 2 แล้ว');history.back();</script>";
        exit;
    }

    // 🔴 อัพ asset หลัง assign สำเร็จ
    $conn->prepare("
    UPDATE IT_assets 
    SET use_it=?, project=?, [update]=GETDATE()
    WHERE no_pc=?
    ")->execute([$emp,$site,$monitor]);
}

}

/* ================= UPS ================= */

// 🔴 ถ้ามี UPS ส่งมา
if(!empty($ups)){

    // 🔴 ถ้ามี UPS อยู่แล้ว → กันซ้ำ
    if(!empty($userRow['user_ups'])){
        echo "<script>alert('มี UPS แล้ว');history.back();</script>";
        exit;
    }

    // 🔥 UPDATE ลง field user_ups
    $conn->prepare("
    UPDATE IT_user_information
    SET user_ups=?
    WHERE id=?
    ")->execute([$ups,$userRow['id']]);

    // 🔥 update asset
    $conn->prepare("
    UPDATE IT_assets 
    SET use_it=?, project=?, [update]=GETDATE()
    WHERE no_pc=?
    ")->execute([$emp,$site,$ups]);
}

/* ================= UPDATE asset ================= */
if(!empty($asset_id)){
$conn->prepare("
UPDATE IT_assets SET use_it=?, project=?, [update]=GETDATE()
WHERE asset_id=?
")->execute([$emp,$site,$asset_id]);
}

if(!empty($m1)){
$conn->prepare("
UPDATE IT_assets SET use_it=?, project=?, [update]=GETDATE()
WHERE no_pc=?
")->execute([$emp,$site,$m1]);

}
if(!empty($m2)){
$conn->prepare("
UPDATE IT_assets SET use_it=?, project=?, [update]=GETDATE()
WHERE no_pc=?
")->execute([$emp,$site,$m2]);
}

header("Location: asset_shared_view.php?success=1");
exit;
}

/* ================= โหลดข้อมูลเดิม (กัน error) ================= */
$oldData = [
    'user_monitor1' => '',
    'user_monitor2' => '',
    'user_ups' => ''
];

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