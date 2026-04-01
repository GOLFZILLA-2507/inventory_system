<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];

/* =====================================================
🔥 โหลดรายการที่โอนแล้ว (ใช้ filter)
===================================================== */
$stmtT = $conn->prepare("
SELECT no_pc
FROM IT_AssetTransfer_Headers
WHERE from_site = ?
AND receive_status = 'รับแล้ว'
");
$stmtT->execute([$site]);
$transfered = array_map('trim',$stmtT->fetchAll(PDO::FETCH_COLUMN));

/* =====================================================
🔥 Dashboard
===================================================== */

// ไม่มีผู้ใช้
$stmt1 = $conn->prepare("
SELECT COUNT(*) 
FROM IT_AssetTransfer_Headers
WHERE to_site = ?
AND receive_status = 'รับแล้ว'
AND (user_status IS NULL OR user_status = '')
");
$stmt1->execute([$site]);
$count_no_user = $stmt1->fetchColumn();

// ส่งออก
$stmt2 = $conn->prepare("
SELECT COUNT(*) FROM IT_AssetTransfer_Headers
WHERE from_site = ?
");
$stmt2->execute([$site]);
$count_sent = $stmt2->fetchColumn();

// รอตรวจรับ
$stmt3 = $conn->prepare("
SELECT COUNT(*) FROM IT_AssetTransfer_Headers
WHERE to_site = ?
AND receive_status = 'รอตรวจรับ'
");
$stmt3->execute([$site]);
$count_receive = $stmt3->fetchColumn();

// ซ่อม
$stmt4 = $conn->prepare("
SELECT COUNT(*) FROM IT_RepairTickets
WHERE project = ?
AND status != 'เสร็จแล้ว'
");
$stmt4->execute([$site]);
$count_repair = $stmt4->fetchColumn();

/* =====================================================
🔥 โหลดข้อมูลจาก IT_user_devices (ตัวใหม่)
===================================================== */
$stmt = $conn->prepare("
SELECT 
    d.user_employee,
    d.device_type,
    d.device_role,
    d.device_code,

    a.spec,
    a.ram,
    a.ssd,
    a.gpu,

    e.position

FROM IT_user_devices d

LEFT JOIN IT_assets a 
ON a.no_pc = d.device_code

LEFT JOIN Employee e
ON e.fullname = d.user_employee

WHERE d.user_project = ?
AND d.device_role != 'shared' /* ไม่เอาอุปกรณ์ใช้ร่วม */
ORDER BY d.user_employee
");
$stmt->execute([$site]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
🔥 GROUP ข้อมูล (หัวใจหลัก)
===================================================== */
$data = [];

foreach($rows as $r){

    $emp = $r['user_employee'];

    if(!isset($data[$emp])){
        $data[$emp] = [
            'position' => $r['position'],
            'PC' => '',
            'Monitor1' => '',
            'Monitor2' => '',
            'UPS' => '',
            'spec' => ''
        ];
    }

    // 🔵 PC
    if($r['device_type'] == 'PC'){
        $data[$emp]['PC'] = $r['device_code'];

        if(!empty($r['spec'])){
            $data[$emp]['spec'] =
                "{$r['spec']} | {$r['ram']} | {$r['ssd']} | {$r['gpu']}";
        }
    }

    // 🟡 Monitor
    if($r['device_type'] == 'Monitor'){
        if($r['device_role'] == 'monitor1'){
            $data[$emp]['Monitor1'] = $r['device_code'];
        }else{
            $data[$emp]['Monitor2'] = $r['device_code'];
        }
    }

    // 🟣 UPS
    if($r['device_type'] == 'UPS'){
        $data[$emp]['UPS'] = $r['device_code'];
    }
}

/* =====================================================
🔥 โหลด shared (ใช้ role = shared)
===================================================== */
$stmtS = $conn->prepare("
SELECT device_type, device_code
FROM IT_user_devices
WHERE user_project=?
AND device_role='shared'
");
$stmtS->execute([$site]);
$sharedRows = $stmtS->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<style>
.card-header{
background:linear-gradient(135deg,#198754,#20c997);
color:white;
}

.empty-data{
padding:4px 10px;
font-size:12px;
color:#856404;
background:#fff3cd;
border-radius:6px;
border:1px solid #000;
}
</style>

<div class="container mt-4">

<div class="row mb-4">

<div class="col-md-3">
<a href="asset_available.php"></a>
<div class="card bg-danger text-white text-center shadow">
<div class="card-body">
<h6>🖥 ไม่มีผู้ใช้</h6>
<h2><?= $count_no_user ?></h2>
</div>
</div>
</div>

<div class="col-md-3">
<a href="transfer_list.php"></a>
<div class="card bg-primary text-white text-center shadow">
<div class="card-body">
<h6>📦 ส่งออก</h6>
<h2><?= $count_sent ?></h2>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card bg-warning text-dark text-center shadow">
<div class="card-body">
<h6>📥 รอตรวจรับ</h6>
<h2><?= $count_receive ?></h2>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card bg-success text-white text-center shadow">
<div class="card-body">
<h6>🛠 ซ่อม</h6>
<h2><?= $count_repair ?></h2>
</div>
</div>
</div>

</div>

<div class="card shadow">
<div class="card-header">
📡 อุปกรณ์ในโครงการ <?= $site ?>
</div>

<div class="card-body">

<h6>👨‍💼 อุปกรณ์พนักงาน</h6>

<table class="table table-bordered text-center">
<tr>
<th>#</th>
<th>ชื่อ</th>
<th>ตำแหน่ง</th>
<th>PC</th>
<th>Spec</th>
<th>Monitor1</th>
<th>Monitor2</th>
<th>UPS</th>
</tr>

<?php
$i=1;

foreach($data as $name => $u){

    if(in_array(trim($u['PC']),$transfered)) continue;

    $spec = $u['spec'] ?: '<span class="empty-data">ไม่มีข้อมูล</span>';
?>

<tr>
<td><?= $i++ ?></td>
<td class="text-start"><?= $name ?></td>
<td><?= $u['position'] ?: '-' ?></td>
<td><?= $u['PC'] ?: '-' ?></td>
<td><?= $spec ?></td>
<td><?= $u['Monitor1'] ?: '-' ?></td>
<td><?= $u['Monitor2'] ?: '-' ?></td>
<td><?= $u['UPS'] ?: '-' ?></td>
</tr>

<?php } ?>

</table>

<hr>

<h6>📡 อุปกรณ์ใช้ร่วม</h6>

<table class="table table-bordered text-center">
<tr>
<th>#</th>
<th>ประเภท</th>
<th>รหัส</th>
</tr>

<?php
$j=1;

foreach($sharedRows as $s){

    if(in_array($s['device_code'],$transfered)) continue;
?>

<tr>
<td><?= $j++ ?></td>
<td><?= $s['device_type'] ?></td>
<td><?= $s['device_code'] ?></td>
</tr>

<?php } ?>

</table>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>