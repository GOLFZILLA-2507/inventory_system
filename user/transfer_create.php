<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];
$user = $_SESSION['fullname'];

/* =====================================================
🔥 โหลดโครงการ
===================================================== */
$stmt = $conn->prepare("
SELECT DISTINCT site FROM Employee
WHERE site IS NOT NULL AND site <> ?
ORDER BY site
");
$stmt->execute([$site]);
$projects = $stmt->fetchAll(PDO::FETCH_COLUMN);

/* =====================================================
🔥 โหลด user
===================================================== */
$stmt = $conn->prepare("
SELECT * FROM IT_user_information WHERE user_project = ?
");
$stmt->execute([$site]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
🔥 ดึงข้อมูล asset จาก table หลัก
===================================================== */
function getAssetInfo($conn,$no_pc){
    $stmt = $conn->prepare("
    SELECT no_pc,spec,ram,ssd,gpu,type_equipment
    FROM IT_assets
    WHERE no_pc = ?
    ");
    $stmt->execute([$no_pc]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/* =====================================================
🔥 เช็คสถานะโอน (สำคัญมาก)
===================================================== */
function getTransferStatus($conn,$no_pc,$site){

    $stmt = $conn->prepare("
    SELECT TOP 1 to_site, receive_status
    FROM IT_AssetTransfer_Headers
    WHERE no_pc = ?
    AND from_site = ?   -- 🔥 ต้องเช็คจากต้นทาง
    ORDER BY transfer_id DESC
    ");

    $stmt->execute([$no_pc,$site]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/* =====================================================
🔥 รวม asset
===================================================== */
$assets = [];

foreach($rows as $row){

// ===== PC =====
if(!empty($row['user_no_pc'])){

    $pc = trim($row['user_no_pc']);
    $t = getTransferStatus($conn,$pc,$site);

    // ❌ ถ้ารับแล้ว → ไม่แสดง
    if($t && $t['receive_status']=='รับแล้ว') continue;

    $info = getAssetInfo($conn,$pc);

    $assets[$pc] = [
        'no_pc'=>$pc,
        'type'=>$info['type_equipment'] ?? 'PC',
        'spec'=>$info['spec'] ?? '',
        'ram'=>$info['ram'] ?? '',
        'ssd'=>$info['ssd'] ?? '',
        'gpu'=>$info['gpu'] ?? '',
        'transfer'=>$t
    ];
}

// ===== MONITOR =====
foreach([$row['user_monitor1'],$row['user_monitor2']] as $m){

    $m = trim($m);
    if(empty($m)) continue;

    $t = getTransferStatus($conn,$m,$site);
    if($t && $t['receive_status']=='รับแล้ว') continue;

    $info = getAssetInfo($conn,$m);

    $assets[$m] = [
        'no_pc'=>$m,
        'type'=>'Monitor',
        'spec'=>$info['spec'] ?? '',
        'ram'=>$info['ram'] ?? '',
        'ssd'=>$info['ssd'] ?? '',
        'gpu'=>$info['gpu'] ?? '',
        'transfer'=>$t
    ];
}

// ===== UPS =====
if(!empty($row['user_ups'])){

    $ups = trim($row['user_ups']);

    $t = getTransferStatus($conn,$ups,$site);
    if($t && $t['receive_status']=='รับแล้ว') continue;

    $info = getAssetInfo($conn,$ups);

    $assets[$ups] = [
        'no_pc'=>$ups,
        'type'=>'UPS',
        'spec'=>$info['spec'] ?? '',
        'ram'=>$info['ram'] ?? '',
        'ssd'=>$info['ssd'] ?? '',
        'gpu'=>$info['gpu'] ?? '',
        'transfer'=>$t
    ];
}

// ===== MULTI =====
$multiFields = [
'user_cctv','user_nvr','user_projector','user_printer',
'user_audio_set','user_plotter','user_Accessories_IT',
'user_Drone','user_Optical_Fiber','user_Server'
];

foreach($multiFields as $field){

    if(empty($row[$field])) continue;

    $list = explode(',', $row[$field]);

    foreach($list as $item){

        $item = trim($item);
        if(empty($item)) continue;

        $t = getTransferStatus($conn,$item,$site);
        if($t && $t['receive_status']=='รับแล้ว') continue;

        $info = getAssetInfo($conn,$item);

        $assets[$item] = [
            'no_pc'=>$item,
            'type'=>$info['type_equipment'] ?? 'OTHER',
            'spec'=>$info['spec'] ?? '',
            'ram'=>$info['ram'] ?? '',
            'ssd'=>$info['ssd'] ?? '',
            'gpu'=>$info['gpu'] ?? '',
            'transfer'=>$t
        ];
    }
}
}

/* =====================================================
🔥 SUBMIT
===================================================== */
if(isset($_POST['submit'])){

$items = $_POST['asset_ids'] ?? [];
$type  = $_POST['transfer_type'];
$to    = $_POST['to_site'];

if(empty($items)){
    echo "<script>alert('กรุณาเลือกอุปกรณ์');</script>";
}else{

$conn->beginTransaction();

try{

$sent_transfer = $conn->query("
SELECT ISNULL(MAX(sent_transfer),0)+1 FROM IT_AssetTransfer_Headers
")->fetchColumn();

$stmt = $conn->prepare("
INSERT INTO IT_AssetTransfer_Headers
(sent_transfer,transfer_type,from_site,to_site,created_by,admin_status,no_pc,type)
VALUES (?,?,?,?,?,?,?,?)
");

foreach($items as $aid){

if(!isset($assets[$aid])) continue;

$t = getTransferStatus($conn,$aid,$site);

// ❌ กันโอนซ้ำ
if($t && $t['receive_status']!='รับแล้ว'){

    echo "<script>alert('รายการ $aid ถูกโอนไปที่ ".$t['to_site']." แล้ว (ยังไม่รับ)');</script>";
    $conn->rollBack();
    return;
}

// INSERT
$stmt->execute([
$sent_transfer,$type,$site,$to,$user,'รออนุมัติ',$aid,$assets[$aid]['type']
]);

// 🔥 REMOVE USER
$conn->prepare("UPDATE IT_user_information SET user_no_pc=NULL WHERE user_project=? AND user_no_pc=?")->execute([$site,$aid]);
$conn->prepare("UPDATE IT_user_information SET user_monitor1=NULL WHERE user_project=? AND user_monitor1=?")->execute([$site,$aid]);
$conn->prepare("UPDATE IT_user_information SET user_monitor2=NULL WHERE user_project=? AND user_monitor2=?")->execute([$site,$aid]);
$conn->prepare("UPDATE IT_user_information SET user_ups=NULL WHERE user_project=? AND user_ups=?")->execute([$site,$aid]);

foreach($multiFields as $field){

$stmtM = $conn->prepare("
SELECT id,$field FROM IT_user_information
WHERE user_project=? AND $field LIKE ?
");
$stmtM->execute([$site,"%$aid%"]);

foreach($stmtM->fetchAll(PDO::FETCH_ASSOC) as $r){

$list = explode(',', $r[$field]);
$list = array_filter(array_map('trim',$list));
$list = array_diff($list, [$aid]);

$conn->prepare("UPDATE IT_user_information SET $field=? WHERE id=?")
->execute([implode(',',$list),$r['id']]);
}
}

}

$conn->commit();

header("Location: transfer_create.php?success=1");
exit;

}catch(Exception $e){

$conn->rollBack();
die($e->getMessage());

}

}
}
include 'partials/header.php';
include 'partials/sidebar.php';
?>

<!-- ================= UI ================= -->
<div class="container mt-4">
<div class="card shadow">

<div class="card-header bg-success text-white">
🚚 สร้างรายการโอนย้าย
</div>

<div class="card-body">

<form method="post">

<div class="row mb-3">

<div class="col-md-4">
<label>ประเภท</label>
<select name="transfer_type" class="form-control">
<option value="โอนย้าย">โอนย้าย</option>
<option value="ส่งคืน">ส่งคืน</option>
</select>
</div>

<div class="col-md-4">
<label>จาก</label>
<input class="form-control" value="<?= $site ?>" readonly>
</div>

<div class="col-md-4">
<label>ไปยัง</label>
<select name="to_site" class="form-control">
<?php foreach($projects as $p): ?>
<option><?= $p ?></option>
<?php endforeach; ?>
</select>
</div>

</div>

<table class="table table-bordered text-center">

<tr>
<th>#</th>
<th>เลือก</th>
<th>ประเภท</th>
<th>รหัส</th>
<th>Spec</th>
</tr>

<?php $i=1; foreach($assets as $a): ?>

<tr>
<td><?= $i++ ?></td>

<td>
<input type="checkbox" name="asset_ids[]" value="<?= $a['no_pc'] ?>">
</td>

<td><?= $a['type'] ?></td>
<td><?= $a['no_pc'] ?></td>

<td>
<?= !empty($a['spec']) 
? $a['spec']." | ".$a['ram']." | ".$a['ssd']." | ".$a['gpu']
: '<span class="badge bg-success">ยังไม่มีข้อมูล</span>' ?>
</td>

</tr>

<?php endforeach; ?>

</table>

<button class="btn btn-success" name="submit">
📨 ส่งรายการ
</button>

</form>
</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>

<script>
const checkboxes=document.querySelectorAll("input[name='asset_ids[]']");
const counter=document.getElementById("countSelect");

function updateCount(){
let count=0;
checkboxes.forEach(cb=>{
if(cb.checked) count++;
});
counter.innerText=count;
}

checkboxes.forEach(cb=>{
cb.addEventListener("change",updateCount);
});

<?php if(isset($_GET['success'])): ?>

let toSite = "<?= $_GET['to'] ?? '' ?>";
let items = "<?= $_GET['items'] ?? '' ?>".split(",");

// 🔥 แปลง list เป็น HTML
let html = "<b>ส่งไปที่:</b> " + toSite + "<br><br>";
html += "<b>รายการอุปกรณ์:</b><br>";

items.forEach((i,index)=>{
    if(i.trim() !== ""){
        html += (index+1)+". "+i+"<br>";
    }
});

document.getElementById("successDetail").innerHTML = html;

// 🔥 แสดง modal
let modal = new bootstrap.Modal(document.getElementById('successModal'));
modal.show();

<?php endif; ?>
</script>