<?php
require_once '../config/connect.php';

$round = $_GET['round'];

/* =====================================================
📦 ดึงข้อมูล
===================================================== */
$stmt = $conn->prepare("
SELECT *
FROM IT_AssetTransfer_Headers
WHERE sent_transfer=?
");
$stmt->execute([$round]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
📌 HEADER INFO
===================================================== */
$first = $data[0] ?? null;

$from_site = $first['from_site'] ?? '-';
$to_site   = $first['to_site'] ?? '-';

/* 🔥 format วันที่ */
$send_date_raw = $first['transfer_date'] ?? null;
$send_date = $send_date_raw
    ? date('d/m/Y H:i:s', strtotime($send_date_raw))
    : '-';
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>ใบส่งอุปกรณ์</title>

<style>
body{
    font-family:'Sarabun';
    padding:20px;
}

/* header */
.header{
    text-align:center;
    margin-bottom:20px;
}

.info{
    margin-bottom:15px;
    font-size:16px;
}

/* table */
table{
    width:100%;
    border-collapse:collapse;
}

th,td{
    border:1px solid #000;
    padding:8px;
    text-align:center;
}

/* footer */
.footer{
    margin-top:40px;
}

.sign{
    width:32%;
    display:inline-block;
    text-align:center;
}

/* ================= PRINT FIX ================= */
@media print {

    @page{
        size: A4;
        margin: 15mm;
    }

    body{
        margin:0;
        padding:0;
    }

    .wrapper{
        min-height: 100%;
        position: relative;
        padding-bottom: 100px; /* 🔥 กัน table ทับ footer */
    }

    .footer{
        position: fixed;
        bottom: 15mm;
        left: 0;
        right: 0;
    }

    table{
        page-break-inside: auto;
    }

    tr{
        page-break-inside: avoid;
    }
}
</style>
</head>

<body>

<div class="wrapper">

<div class="header">
<h2>ใบส่งอุปกรณ์</h2>
<h4>รอบที่ <?= $round ?></h4>
</div>

<!-- 🔥 ข้อมูล -->
<div class="info">
<b>ส่งจาก:</b> <?= $from_site ?> <br>
<b>ไปที่:</b> <?= $to_site ?> <br>
<b>วันที่ส่ง:</b> <?= $send_date ?>
</div>

<!-- 🔥 ตาราง -->
<table>
<tr>
<th>ลำดับ</th>
<th>รหัสอุปกรณ์</th>
<th>ประเภท</th>
<th>หมายเหตุ</th>
</tr>

<?php $i=1; foreach($data as $d): ?>
<tr>
<td><?= $i++ ?></td>
<td><?= $d['no_pc'] ?></td>
<td><?= $d['type'] ?></td>
<td><?= $d['remark'] ?? '' ?></td>
</tr>
<?php endforeach; ?>

</table>

</div> <!-- wrapper -->

<!-- 🔥 footer -->
<div class="footer">

<div class="sign">
<p>ผู้ส่ง</p>
<br><br>
( <?= $from_site ?> )
</div>

<div class="sign">
<p>ผู้จัดส่ง</p>
<br><br>
( __________________ )
</div>

<div class="sign">
<p>ผู้รับ</p>
<br><br>
( __________________ )
</div>

</div>

<script>
window.print();
</script>

</body>
</html>