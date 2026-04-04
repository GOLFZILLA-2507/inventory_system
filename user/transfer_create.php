<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* =====================================================
🔐 SESSION
===================================================== */
$site = $_SESSION['site'];
$user = $_SESSION['fullname'];

/* =====================================================
📍 โหลดโครงการปลายทาง
===================================================== */
$stmt = $conn->prepare("
SELECT DISTINCT site 
FROM Employee
WHERE site IS NOT NULL AND site <> ?
ORDER BY site
");
$stmt->execute([$site]);
$projects = $stmt->fetchAll(PDO::FETCH_COLUMN);


/* =====================================================
📷 UPLOAD FILE
===================================================== */
$uploadName = null;

if(!empty($_FILES['transfer_image']['name'])){

    $dir = "../uploads/transfer/";

    if(!is_dir($dir)){
        mkdir($dir,0777,true);
    }

    $ext = pathinfo($_FILES['transfer_image']['name'], PATHINFO_EXTENSION);
    $uploadName = 'TR_'.time().'_'.rand(1000,9999).'.'.$ext;

    move_uploaded_file($_FILES['transfer_image']['tmp_name'],$dir.$uploadName);
}
/* =====================================================
🔍 FUNCTION: ดึงสถานะล่าสุดของอุปกรณ์ (หัวใจ)
===================================================== */
function getTransferStatus($conn,$code){

    $stmt = $conn->prepare("
        SELECT TOP 1 to_site, receive_status
        FROM IT_AssetTransfer_Headers
        WHERE no_pc=?
        ORDER BY transfer_id DESC
    ");
    $stmt->execute([$code]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/* =====================================================
📦 โหลด record ล่าสุดของแต่ละเครื่อง (สำคัญมาก)
===================================================== */
$stmt = $conn->prepare("
SELECT 
    d.device_code AS no_pc,
    d.device_type AS type,
    d.user_project,
    d.user_employee,

    a.spec,
    a.ram,
    a.ssd,
    a.gpu

FROM IT_user_devices d
LEFT JOIN IT_assets a ON a.no_pc = d.device_code

WHERE d.user_project = ?
");
$stmt->execute([$site]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$assets = [];

foreach($rows as $r){

    // 🔥 ดึงสถานะจาก transfer
    $t = getTransferStatus($conn,$r['no_pc']);

    // 🔥 ใส่สถานะเข้าไปเลย
    $r['transfer'] = $t;

    // ❗ requirement ใหม่:
    // ต้อง "เห็นเครื่องที่ถูกส่งด้วย"
    // 👉 ดังนั้น "ไม่ต้อง filter ทิ้งแล้ว"

    $assets[] = $r;
}

/* =====================================================
🔥 ใส่สถานะล่าสุด (สำหรับ UI + block)
===================================================== */
foreach($assets as $i => $a){
    $assets[$i]['transfer'] = getTransferStatus($conn,$a['no_pc']);
}

/* =====================================================
📨 SUBMIT (กัน F5)
===================================================== */
if($_SERVER['REQUEST_METHOD']=='POST'){

    $items = $_POST['asset_ids'] ?? [];
    $type  = $_POST['transfer_type'];
    $to    = $_POST['to_site'];

    $other_item   = $_POST['other_item'] ?? null;
    $other_detail = $_POST['other_detail'] ?? null;

    $adminStatus = 'รออนุมัติ';

    // 🔥 ส่งสำนักงานใหญ่ auto
    if($to === 'สำนักงานใหญ่'){
        $type = 'ส่งคืน';
        $adminStatus = 'อนุมัติ';
    }

    if(empty($items)){
        header("Location: transfer_create.php?error=empty");
        exit;
    }

    try{
        $conn->beginTransaction();

        $round = $conn->query("
        SELECT ISNULL(MAX(sent_transfer),0)+1 
        FROM IT_AssetTransfer_Headers
        ")->fetchColumn();

        $stmt = $conn->prepare("
            INSERT INTO IT_AssetTransfer_Headers
            (
                sent_transfer,
                transfer_type,
                from_site,
                to_site,
                created_by,
                admin_status,
                no_pc,
                type,
                transfer_image,
                other_item,
                other_detail
            )
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
            ");

            $stmt->execute([
    $round,
    $type,
    $site,
    $to,
    $user,
    $adminStatus,
    $code,
    '',
    $uploadName,
    $other_item,
    $other_detail
]);


        foreach($items as $code){

            $t = getTransferStatus($conn,$code);

            /* ================= BLOCK ================= */

            // ❌ ไม่พบ → ห้ามส่ง
            if($t && $t['receive_status']=='ไม่พบอุปกรณ์นี้'){
                $conn->rollBack();
                header("Location: transfer_create.php?notfound=1&pc=$code&to=".$t['to_site']);
                exit;
            }

            // ❌ รอตรวจรับ → ห้ามส่ง
            if($t && $t['receive_status']!='รับแล้ว' && $t['receive_status']!='ยกเลิก'){
                $conn->rollBack();
                header("Location: transfer_create.php?waiting=1&pc=$code&to=".$t['to_site']);
                exit;
            }

            /* ================= INSERT ================= */
            $stmt->execute([
                $round,
                $type,
                $site,
                $to,
                $user,
                $adminStatus,
                $code,
                ''
            ]);
        }

        $conn->commit();
        header("Location: transfer_create.php?success=1");
        exit;

    }catch(Exception $e){
        $conn->rollBack();
        header("Location: transfer_create.php?error=fail");
        exit;
    }
}
?>

<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container mt-4">
<div class="card shadow">

<div class="card-header bg-success text-white">
🚚 สร้างรายการโอนย้าย
</div>

<div class="card-body">

<form method="post" id="formTransfer">

<div class="row mb-3">

<div class="col-md-4">
<label>ประเภท</label>
<select name="transfer_type" class="form-control">
<option value="โอนย้าย">โอนย้าย</option>
</select>
</div>

<div class="col-md-4">
<label>จาก</label>
<input class="form-control" value="<?= $site ?>" readonly>
</div>

<div class="col-md-4">
<label>ไปยัง</label>
<select name="to_site" id="to_site" class="form-control">
<?php foreach($projects as $p): ?>
<option><?= $p ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="row mb-3 mt-3">

<div class="col-md-4">
<label>กรณีมีรายการอื่นๆ</label>
<select name="other_item" class="form-control">
<option value="other_item">อื่นๆ</option>
</select>
</div>

<div class="col-md-8">
<label>📝 รายละเอียดเพิ่มเติม</label>
<textarea name="other_detail" class="form-control" rows="2" placeholder="รายละเอียด..."></textarea>
</div>

</div>

<div class="mb-3">
<label>📷 แนบรูป</label>
<input type="file" name="transfer_image" class="form-control" accept="image/*">
</div>

</div>

<table class="table table-bordered text-center">

<tr>
<th>#</th>
<th>เลือก</th>
<th>สถานะ</th>
<th>รหัส</th>
<th>ประเภท</th>
<th>Spec</th>
</tr>

<?php $i=1; foreach($assets as $a): 

$t = $a['transfer'] ?? null;

$isNotFound = ($t && $t['receive_status']=='ไม่พบอุปกรณ์นี้');
$isWaiting  = ($t && $t['receive_status']!='รับแล้ว' && $t['receive_status']!='ยกเลิก');

?>

<tr class="<?= $isWaiting ? 'table-warning' : '' ?>">

<td><?= $i++ ?></td>

<td>
<input type="checkbox"
name="asset_ids[]"
value="<?= $a['no_pc'] ?>"
<?= ($isNotFound || $isWaiting) ? 'disabled class="lock-item" data-site="'.$t['to_site'].'" data-status="'.$t['receive_status'].'"' : '' ?>
>
</td>

<td>
<?php if($isNotFound): ?>
<span class="badge bg-warning"><?= $a['to_site'] ?> : ไม่พบอุปกรณ์ </span>

<?php elseif($isWaiting): ?>
<span class="badge bg-warning">รอตรวจรับ</span>

<?php elseif($t && $t['receive_status']=='ยกเลิก'): ?>
<span class="badge bg-secondary">ยกเลิก</span>

<?php else: ?>
<span class="badge bg-primary">ปกติ</span>
<?php endif; ?>
</td>

<td><?= $a['no_pc'] ?></td>
<td><?= $a['type'] ?></td>
<td><?= $a['spec'] ?? '-' ?></td>

</tr>

<?php endforeach; ?>

</table>

<button type="button" id="btnConfirm" class="btn btn-success">
📨 ส่งรายการ
</button>

</form>

</div>
</div>
</div>

<script>
// confirm
document.getElementById('btnConfirm').onclick=function(){

let checked=document.querySelectorAll('input[name="asset_ids[]"]:checked');

if(checked.length===0){
Swal.fire('เลือกอย่างน้อย 1 รายการ');
return;
}

let list='';
checked.forEach(el=> list+=el.value+'<br>');

let to=document.getElementById('to_site').value;

Swal.fire({
title:'ยืนยันส่ง',
html:`ไปที่ <b>${to}</b><hr>${list}`,
icon:'question',
showCancelButton:true
}).then(res=>{
if(res.isConfirmed){
document.getElementById('formTransfer').submit();
}
});
};

// 🔥 lock item modal
document.querySelectorAll('.lock-item').forEach(el=>{
el.closest('tr').onclick=function(e){
if(e.target.tagName==='INPUT') return;

Swal.fire({
icon:'warning',
title:'ไม่สามารถเลือกได้',
html:`ส่งไปที่ <b>${el.dataset.site}</b><br>สถานะ: ${el.dataset.status}`
});
};
});
</script>

<?php if(isset($_GET['success'])): ?>
<script>Swal.fire('สำเร็จ','','success');</script>
<?php endif; ?>

<?php if(isset($_GET['notfound'])): ?>
<script>
Swal.fire({
icon:'error',
title:'ไม่สามารถส่งได้',
html:'อุปกรณ์ <?= $_GET['pc'] ?><br>ปลายทาง <?= $_GET['to'] ?><br>สถานะ: ไม่พบอุปกรณ์'
});
</script>
<?php endif; ?>

<?php if(isset($_GET['waiting'])): ?>
<script>
Swal.fire({
icon:'warning',
title:'ยังส่งไม่ได้',
html:'อุปกรณ์ <?= $_GET['pc'] ?><br>กำลังรอตรวจรับที่ <?= $_GET['to'] ?>'
});
</script>
<?php endif; ?>

<?php include 'partials/footer.php'; ?>