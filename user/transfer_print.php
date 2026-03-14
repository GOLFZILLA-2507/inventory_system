<?php
require_once '../config/connect.php';

$id = $_GET['id'] ?? 0;

/* ===============================
   โหลดข้อมูล Header
================================ */

$stmt = $conn->prepare("
SELECT *
FROM IT_AssetTransfer_Headers
WHERE transfer_id = ?
");
$stmt->execute([$id]);
$h = $stmt->fetch(PDO::FETCH_ASSOC);

/* ===============================
   โหลดรายการอุปกรณ์
================================ */

$stmt = $conn->prepare("
SELECT no_pc,spec,ram,ssd,gpu
FROM IT_AssetTransfer_Headers
WHERE transfer_id=?
");

$stmt->execute([$id]);

$items=[];
$row=$stmt->fetch(PDO::FETCH_ASSOC);

if($row){
$items[]=$row;
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>ใบส่งอุปกรณ์</title>

<style>

body{
font-family:Tahoma;
font-size:14px;
margin:40px;
}

.header{
text-align:center;
margin-bottom:25px;
}

.header h2{
margin:0;
}

.info{
margin-bottom:20px;
}

table{
border-collapse:collapse;
width:100%;
}

table th, table td{
border:1px solid #000;
padding:8px;
}

table th{
background:#f2f2f2;
text-align:center;
}

.sign{
margin-top:60px;
width:100%;
}

.sign td{
text-align:center;
padding-top:50px;
}

@media print{
body{
margin:10px;
}
}

</style>

</head>

<body>

<div class="header">
<h2>ใบส่งอุปกรณ์ / Asset Transfer</h2>
</div>

<div class="info">

<strong>จากโครงการ :</strong> <?= $h['from_site'] ?><br>
<strong>ไปยังโครงการ :</strong> <?= $h['to_site'] ?><br>
<strong>ประเภท :</strong> <?= $h['transfer_type'] ?><br>
<strong>วันที่ :</strong> <?= $h['transfer_date'] ?><br>

</div>

<hr>

<table>

<tr>
<th width="60">ลำดับ</th>
<th width="200">รหัสอุปกรณ์</th>
<th>Spec</th>
</tr>

<?php
$i=1;

foreach($items as $it):

$spec = trim(($it['spec'] ?? '').($it['ram'] ?? '').($it['ssd'] ?? '').($it['gpu'] ?? ''));

if($spec==''){
$spec='ยังไม่ได้บันทึกข้อมูล';
}
else{
$spec=$it['spec']." | ".$it['ram']." | ".$it['ssd']." | ".$it['gpu'];
}
?>

<tr>
<td style="text-align:center"><?= $i++ ?></td>
<td><?= $it['no_pc'] ?></td>
<td><?= $spec ?></td>
</tr>

<?php endforeach; ?>

</table>

<table class="sign">

<tr>

<td>
ผู้ส่ง<br><br>
........................................
</td>

<td>
ผู้รับ<br><br>
........................................
</td>

<td>
ผู้อนุมัติ<br><br>
........................................
</td>

</tr>

</table>

<script>
window.onload=function(){
window.print();
}
</script>

</body>
</html>