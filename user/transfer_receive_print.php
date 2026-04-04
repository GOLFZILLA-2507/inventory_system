<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];
$round = $_GET['round'] ?? 0;

/* ================= โหลดข้อมูล ================= */
$stmt = $conn->prepare("
SELECT 
    no_pc,
    type,
    from_site,
    to_site,
    transfer_date,
    other_detail,
    created_by,
    transfer_type
FROM IT_AssetTransfer_Headers
WHERE sent_transfer = ?
");
$stmt->execute([$round]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= หาค่าหัว ================= */
$from_site = $data[0]['from_site'] ?? '-';
$to_site   = $data[0]['to_site'] ?? '-';
$transfer_date = $data[0]['transfer_date'] ?? null;
$other_detail  = $data[0]['other_detail'] ?? '-';
$created_by    = $data[0]['created_by'] ?? '-';
$transfer_type = $data[0]['transfer_type'] ?? '-';

/* ================= ประเภท ================= */
if($to_site === 'สำนักงานใหญ่'){
    $type_text = 'ส่งคืน';
}else{
    $type_text = $transfer_type;
}

/* ================= วันที่ ================= */
$transfer_datetime = $transfer_date 
    ? date('d/m/Y H:i', strtotime($transfer_date)) 
    : '-';
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>ใบตรวจรับอุปกรณ์</title>

<style>
body{
    font-family: Tahoma;
    font-size:14px;
}

h2{
    text-align:center;
}

/* HEADER */
.header-box{
    margin-top:10px;
    line-height:1.8;
}

/* DETAIL */
.detail-box{
    margin-top:10px;
    margin-bottom:10px;
    padding:10px;
    border:1px dashed #999;
}

/* TABLE */
table{
    width:100%;
    border-collapse:collapse;
    margin-top:15px;
}

th,td{
    border:1px solid #000;
    padding:6px;
}

th{
    background:#eee;
}

.checkbox{
    width:20px;
    height:20px;
    border:1px solid #000;
    display:inline-block;
}

/* PRINT */
.print-btn{
    margin-bottom:10px;
}

@media print{
    .print-btn{ display:none; }
}

/* 🔥 ผู้ส่ง (ล่างซ้าย) */
.sender{
    position:fixed;
    bottom:40px;
    left:40px;
    text-align:center;
}

/* 🔥 ผู้รับ (ล่างขวาสุด) */
.receiver{
    position:fixed;
    bottom:40px;
    right:40px;
    text-align:center;
}
</style>

</head>
<body>

<button class="print-btn" onclick="window.print()">🖨 พิมพ์</button>

<h2>ใบตรวจรับอุปกรณ์</h2>

<!-- HEADER -->
<div class="header-box">
<b>รอบการส่ง:</b> <?= $round ?><br>
<b>ประเภท:</b> <?= $type_text ?><br>
<b>จากโครงการ:</b> <?= $from_site ?><br>
<b>ถึงโครงการ:</b> <?= $site ?><br>
<b>วันที่ส่ง:</b> <?= $transfer_datetime ?><br>
</div>

<!-- DETAIL -->
<div class="detail-box">
<b>รายละเอียด:</b><br>
<?= !empty($other_detail) ? nl2br(htmlspecialchars($other_detail)) : '-' ?>
</div>

<!-- TABLE -->
<table>
<tr>
<th width="50">ลำดับ</th>
<th width="500">รหัสอุปกรณ์</th>
<th width="200">ประเภท</th>
<th width="100">ตรวจรับ</th>
<th>หมายเหตุ</th>
</tr>

<?php $i=1; foreach($data as $d): ?>
<tr>
<td align="center"><?= $i++ ?></td>
<td><?= htmlspecialchars($d['no_pc']) ?></td>
<td><?= htmlspecialchars($d['type']) ?></td>
<td align="center"><div class="checkbox"></div></td>
<td></td>
</tr>
<?php endforeach; ?>
</table>

<!-- 🔥 ผู้ส่ง -->
<div class="sender">
ผู้ส่ง<br><br>
<?= htmlspecialchars($created_by) ?><br><br>
วันที่ ______ / ______ / ______
</div>

<!-- 🔥 ผู้รับ -->
<div class="receiver">
ผู้ตรวจรับ<br><br>
___________________________<br><br>
วันที่ ______ / ______ / ______
</div>

</body>
</html>