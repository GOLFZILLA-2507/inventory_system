<?php
require_once '../config/connect.php';

/* =====================================================
รับค่ารอบการส่ง
===================================================== */
$round = $_GET['round'] ?? 0;

/* =====================================================
โหลด header ของรอบนั้น
===================================================== */
$stmt = $conn->prepare("
SELECT TOP 1 *
FROM IT_AssetTransfer_Headers
WHERE sent_transfer = ?
");

$stmt->execute([$round]);
$h = $stmt->fetch(PDO::FETCH_ASSOC);

/* =====================================================
โหลดรายการอุปกรณ์ทั้งหมดในรอบนั้น
===================================================== */
$stmt = $conn->prepare("
SELECT 
    h.no_pc,
    a.type_equipment,
    a.spec,
    a.ram,
    a.ssd,
    a.gpu
FROM IT_AssetTransfer_Headers h
LEFT JOIN IT_assets a ON h.no_pc = a.no_pc
WHERE h.sent_transfer = ?
");

$stmt->execute([$round]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">
<title>ใบส่งอุปกรณ์</title>

<style>

/* =====================================================
ตั้งค่าพื้นฐาน
===================================================== */

body{
font-family:tahoma;
font-size:14px;
margin:40px;
}

/* =====================================================
หัวเอกสาร
===================================================== */

.header{
text-align:center;
margin-bottom:20px;
}

/* =====================================================
ตารางรายการอุปกรณ์
===================================================== */

table{
border-collapse:collapse;
width:100%;
}

table th,table td{
border:1px solid #000;
padding:8px;
}

table th{
background:#eee;
}

/* =====================================================
ตารางลายเซ็น
ทำให้ไปอยู่ล่างสุดของหน้ากระดาษ
===================================================== */

.sign{
position:fixed;
bottom:60px;
left:40px;
right:40px;
width:calc(100% - 80px);
}

.sign td{
text-align:center;
padding-top:60px;
border:none;
}

/* =====================================================
ตอนสั่งปริ้น
===================================================== */

@media print{

body{
margin:10px;
}

}

</style>

</head>

<body>

<div class="header">
<h2>ใบส่งอุปกรณ์</h2>
</div>

<div>

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
<th width="120">ประเภท</th>
<th>Spec</th>
</tr>

<?php $i=1; foreach($items as $it): 

/* =====================================================
รวม spec และตัดค่าที่ว่างออก
===================================================== */

$specParts = array_filter([
$it['spec'],
$it['ram'],
$it['ssd'],
$it['gpu']
]);

$spec = empty($specParts)
? 'ยังไม่ได้บันทึกข้อมูล'
: implode(' | ',$specParts);

?>

<tr>

<td style="text-align:center"><?= $i++ ?></td>

<td><?= $it['no_pc'] ?></td>

<td><?= $it['type_equipment'] ?? 'ไม่ทราบประเภท' ?></td>

<td><?= $spec ?></td>

</tr>

<?php endforeach; ?>

</table>


<!-- =====================================================
ลายเซ็นผู้ส่ง / ผู้รับ / ผู้อนุมัติ
===================================================== -->

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