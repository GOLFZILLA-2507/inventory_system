<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];
$user = $_SESSION['fullname'];

/* ===============================
โหลดรายชื่อโครงการ
=============================== */
$stmt = $conn->prepare("
SELECT DISTINCT site AS project_name
FROM Employee
WHERE site IS NOT NULL
AND site <> ?
ORDER BY site
");
$stmt->execute([$site]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* ===============================
โหลดข้อมูล user asset
=============================== */
$stmt = $conn->prepare("
SELECT *
FROM IT_user_information
WHERE user_project = ?
");
$stmt->execute([$site]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* ===============================
ฟังก์ชันเช็คว่า “ส่งออก + รับแล้ว”
=============================== */
function isSentAndReceived($conn,$no_pc,$site){

$stmt = $conn->prepare("
SELECT COUNT(*) 
FROM IT_AssetTransfer_Headers
WHERE no_pc = ?
AND from_site = ?
AND receive_status = 'รับแล้ว'
");

$stmt->execute([$no_pc,$site]);

return $stmt->fetchColumn() > 0;
}


/* ===============================
🔥 ฟังก์ชันเช็คซ้ำ (เพิ่มใหม่)
=============================== */
function checkDuplicate($conn,$no_pc,$site){

    $stmt = $conn->prepare("
    SELECT TOP 1 to_site, sent_transfer
    FROM IT_AssetTransfer_Headers
    WHERE no_pc = ?
    AND from_site = ?
    AND (transfer_status IS NULL OR transfer_status != 'ยกเลิก')
    ORDER BY transfer_id DESC
    ");

    $stmt->execute([$no_pc,$site]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


/* ===============================
รวม asset ทั้งหมด (เหมือนเดิม)
=============================== */
$assets = [];

foreach($rows as $row){

/* ================= PC ================= */
if(!empty($row['user_no_pc'])){

$code = trim($row['user_no_pc']);

if(!isSentAndReceived($conn,$code,$site)){

$assets[$code] = [
'asset_id'=>$code,
'no_pc'=>$code,
'type'=>$row['user_type_equipment'],
'details'=>$row['user_equipment_details'],
'new_no'=>$row['user_new_no'],
'spec'=>$row['user_spec'],
'ram'=>$row['user_ram'],
'ssd'=>$row['user_ssd'],
'gpu'=>$row['user_gpu']
];

}
}

/* ================= Monitor ================= */
$monitors = [
$row['user_monitor1'],
$row['user_monitor2']
];

foreach($monitors as $m){

$m = trim($m);

if(!empty($m) && !isSentAndReceived($conn,$m,$site)){

$assets[$m] = [
'asset_id'=>$m,
'no_pc'=>$m,
'type'=>'Monitor'
];

}
}

/* ================= UPS ================= */
if(!empty($row['user_ups'])){

$ups = trim($row['user_ups']);

if(!isSentAndReceived($conn,$ups,$site)){

$assets[$ups] = [
'asset_id'=>$ups,
'no_pc'=>$ups,
'type'=>'UPS'
];

}
}

/* ================= MULTI ================= */
$multiFields = [
'user_cctv' => 'CCTV',
'user_nvr' => 'NVR',
'user_printer' => 'Printer'
];

foreach($multiFields as $field => $type){

if(empty($row[$field])) continue;

$list = explode(',', $row[$field]);

foreach($list as $item){

$item = trim($item);

if(empty($item)) continue;

if(isSentAndReceived($conn,$item,$site)) continue;

$assets[$item] = [
'asset_id'=>$item,
'no_pc'=>$item,
'type'=>$type
];

}
}

/* ================= SINGLE ================= */
$singleFields = [
'user_projector' => 'Projector',
'user_audio_set' => 'Audio Set',
'user_plotter' => 'Plotter',
'user_Accessories_IT' => 'Accessories IT',
'user_Drone' => 'Drone',
'user_Optical_Fiber' => 'Optical Fiber',
'user_Server' => 'Server'
];

foreach($singleFields as $field => $type){

if(!empty($row[$field])){

$val = trim($row[$field]);

if(!isSentAndReceived($conn,$val,$site)){

$assets[$val] = [
'asset_id'=>$val,
'no_pc'=>$val,
'type'=>$type
];

}
}
}
}


/* ===============================
SUBMIT
=============================== */
if(isset($_POST['submit'])){

$type = $_POST['transfer_type'] ?? '';
$to   = $_POST['to_site'] ?? '';
$items = $_POST['asset_ids'] ?? [];

if(empty($items)){
echo "<script>alert('กรุณาเลือกอุปกรณ์');</script>";
}
else{

/* ===============================
🔥 ตรวจรายการซ้ำก่อน
=============================== */
$duplicates = [];

foreach($items as $aid){

    if(isset($assets[$aid])){

        $a = $assets[$aid];

        $dup = checkDuplicate($conn,$a['no_pc'],$site);

        if($dup){
            $duplicates[] = [
                'code' => $a['no_pc'],
                'to' => $dup['to_site'],
                'round' => $dup['sent_transfer']
            ];
        }
    }
}

/* ===============================
🔥 ถ้ามีซ้ำ → แจ้งเตือน + หยุด
=============================== */
if(!empty($duplicates)){

    $msg = "❌ พบรายการซ้ำ\n\n";

    foreach($duplicates as $d){
        $msg .= "รหัส: ".$d['code']."\n";
        $msg .= "เคยส่งไป: ".$d['to']."\n";
        $msg .= "รอบที่: ".$d['round']."\n\n";
    }

    echo "<script>alert(".json_encode($msg).");</script>";
    return;
}


/* ===============================
นับรอบส่งโอนย้าย
=============================== */
$stmtRound = $conn->prepare("
SELECT ISNULL(MAX(sent_transfer),0)+1 AS round_transfer
FROM IT_AssetTransfer_Headers
");
$stmtRound->execute();
$r = $stmtRound->fetch(PDO::FETCH_ASSOC);

$sent_transfer = $r['round_transfer'];


/* ===============================
INSERT (เหมือนเดิม)
=============================== */
$stmt = $conn->prepare("
INSERT INTO IT_AssetTransfer_Headers
(sent_transfer,transfer_type,from_site,to_site,created_by,admin_status,new_no,no_pc,details,spec,ssd,ram,gpu,type)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
");

foreach($items as $aid){

if(isset($assets[$aid])){

$a = $assets[$aid];

/* 🔥 กันซ้ำอีกชั้น */
$dup = checkDuplicate($conn,$a['no_pc'],$site);
if($dup) continue;

$stmt->execute([
$sent_transfer,
$type,
$site,
$to,
$user,
'รออนุมัติ',
$a['new_no'] ?? '',
$a['no_pc'] ?? '',
$a['details'] ?? '',
$a['spec'] ?? '',
$a['ssd'] ?? '',
$a['ram'] ?? '',
$a['gpu'] ?? '',
$a['type'] ?? ''
]);

}
}

header("Location: transfer_create.php?success=1");
exit;

}

}

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="container mt-4">
<div class="card shadow">

<div class="card-header bg-success text-white">
🚚 สร้างรายการโอนย้าย / ส่งมอบ / ส่งคืน
</div>

<div class="card-body">

<form method="post">

<div class="row">

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
<option><?= $p['project_name'] ?></option>
<?php endforeach; ?>
</select>
</div>

</div>

<hr>

<table class="table table-bordered">

<tr>
<th>ลำดับ</th>
<th>เลือก</th>
<th>ประเภท</th>
<th>รหัส</th>
<th>Spec</th>
</tr>

<?php $i=1; foreach($assets as $a): 

$spec = trim(($a['spec'] ?? '').($a['ram'] ?? '').($a['ssd'] ?? '').($a['gpu'] ?? ''));

if($spec==''){
$spec='<span class="badge bg-success">ยังไม่ได้บันทึกข้อมูล</span>';
}else{
$spec=$a['spec']." | ".$a['ram']." | ".$a['ssd']." | ".$a['gpu'];
}
?>

<tr>

<td><?= $i++ ?></td>

<td>
<input type="checkbox" name="asset_ids[]" value="<?= $a['asset_id'] ?>">
</td>

<td><?= $a['type'] ?></td>

<td><?= $a['no_pc'] ?></td>

<td><?= $spec ?></td>

</tr>

<?php endforeach; ?>

</table>

<div class="text-end mt-2">
<strong>
จำนวนอุปกรณ์ที่เลือก :
<span id="countSelect">0</span>
</strong>
</div>

<button class="btn btn-success mt-3" name="submit">
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
</script>