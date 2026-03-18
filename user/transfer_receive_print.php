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
    transfer_date
FROM IT_AssetTransfer_Headers
WHERE sent_transfer = ?
");

$stmt->execute([$round]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= หาค่าหัว ================= */

$from_site = $data[0]['from_site'] ?? '-';
$transfer_date = $data[0]['transfer_date'] ?? null;

/* แปลงวันที่ */
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

table{
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
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

.print-btn{
    margin-bottom:10px;
}

@media print{
    .print-btn{
        display:none;
    }
}
.signature{
    position: fixed;
    bottom: 40px;
    left: 0;
    width: 100%;
    display: flex;
    justify-content: space-between;
    padding: 0 40px;
    font-size:14px;
}

.left{
    text-align:left;
}

.right{
    text-align:right;
}
</style>

</head>
<body>

<button class="print-btn" onclick="window.print()">🖨 พิมพ์</button>

<h2>ใบตรวจรับอุปกรณ์</h2>

<p>
<b>รอบการส่ง:</b> <?= $round ?><br>
<b>จากโครงการ:</b> <?= $from_site ?><br>
<b>ถึงโครงการ:</b> <?= $site ?><br>
<b>วันที่ส่ง:</b> <?= $transfer_datetime ?><br>
</p>

<table>
<tr>
<th width="50">ลำดับ</th>
<th width="800">รหัสอุปกรณ์</th>
<th width="300">ประเภท</th>
<th width="100">ตรวจรับ</th>
<th>หมายเหตุ</th>
</tr>

<?php $i=1; foreach($data as $d): ?>

<tr>
<td align="center"><?= $i++ ?></td>

<td><?= htmlspecialchars($d['no_pc']) ?></td>

<td><?= htmlspecialchars($d['type']) ?></td>

<td align="center">
<div class="checkbox"></div>
</td>

<td></td>

</tr>

<?php endforeach; ?>

</table>

<br><br>

<div class="signature">

    <div class="left">
        ผู้ตรวจรับ ___________________________
    </div>

    <div class="right">
        วันที่ ______ / ______ / ______
    </div>

</div>
</table>

</body>
</html>