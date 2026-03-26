<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* =====================================================
   ดึงโครงการของ user ที่ login
===================================================== */
$site = $_SESSION['site'];

/* =====================================================
   โหลดอุปกรณ์ที่ยังไม่มีผู้ใช้งาน
   👉 เงื่อนไข:
   - มาถึงปลายทางแล้ว (receive_status = 'รับแล้ว')
   - เป็นของโครงการนี้ (to_site)
   - ยังไม่ถูกใช้งาน (status IS NULL)
===================================================== */
$stmt = $conn->prepare("
SELECT
t.transfer_id,
t.no_pc,

-- 🔥 ดึง type จริงจาก assets
a.type_equipment AS type,

-- 🔥 เอารายละเอียดจริง
a.Equipment_details AS details,
a.spec,
a.ram,
a.ssd,
a.gpu,

t.from_site,
t.transfer_type,
t.arrived_date

FROM IT_AssetTransfer_Headers t

-- 🔥 JOIN เพื่อเอาข้อมูล asset จริง
LEFT JOIN IT_assets a ON a.no_pc = t.no_pc

WHERE t.to_site = ?
AND t.receive_status = 'รับแล้ว'
AND t.user_status IS NULL

ORDER BY t.arrived_date DESC
");

$stmt->execute([$site]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<style>
.card-header{
background:linear-gradient(135deg,#198754,#20c997);
color:white;
}

.empty-data{
display:inline-block;
padding:4px 10px;
font-size:12px;
font-weight:600;
color:#856404;
background:#fff3cd;
border-radius:6px;
border:1px solid #000000;
}

.table-green thead{
    background: linear-gradient(135deg,#198754,#20c997);
    color:white;
}

.table-green tbody tr:hover{
    background:#e9f7ef;
}

.badge-green{
    background:#198754;
}
</style>

<div class="container mt-4">
<div class="card shadow">

<div class="card-header">
<h5 class="mb-0">
🖥 อุปกรณ์ที่ยังไม่มีผู้ใช้งาน  
(โครงการ <?= $site ?>)
</h5>
</div>

<div class="card-body">

<table class="table table-bordered table-green">

<thead class="table-success text-center">
<tr>
<th>ลำดับ</th>
<th>รหัสอุปกรณ์</th>
<th>ประเภท</th>
<th>Spec</th>
<th>หมายเหตุ</th>
<th>วันที่รับ</th>
<th>จัดการ</th>
</tr>
</thead>

<tbody>

<?php if(empty($data)): ?>

<tr>
<td colspan="7" class="text-center text-muted">
ไม่พบอุปกรณ์ที่ยังไม่มีผู้ใช้งาน
</td>
</tr>

<?php else: ?>

<?php $i=1; foreach($data as $d): ?>

<tr>

<td class="text-center"><?= $i++ ?></td>

<!-- 🔥 รหัสอุปกรณ์ -->
<td class="fw-bold text-primary">
<?= $d['no_pc'] ?: '<span class="empty-data">ไม่มีข้อมูล</span>' ?>
</td>

<!-- 🔥 ประเภท -->
<td class="text-center">
<?= $d['type'] ?: '-' ?>
</td>

<td>
<?php
$specParts = array_filter([
$d['spec'],
$d['ram'],
$d['ssd'],
$d['gpu']
]);

echo empty($specParts)
? '<span class="empty-data">ไม่มีข้อมูล</span>'
: implode(' | ', $specParts);
?>
</td>

<!-- 🔥 หมายเหตุ -->
<td>
<?php if(!empty($d['from_site'])): ?>
โอนจาก : <b><?= htmlspecialchars($d['from_site']) ?></b><br>
ประเภท : <span class="badge bg-info"><?= htmlspecialchars($d['transfer_type']) ?></span>
<?php else: ?>
<span class="empty-data">ไม่มีข้อมูล</span>
<?php endif; ?>
</td>

<!-- 🔥 วันที่ -->
<td class="text-center">
<?= $d['arrived_date'] ?: '-' ?>
</td>

<!-- 🔥 ปุ่มจัดการ -->
<td class="text-center">

<?php
// 🔥 แยกประเภท
$mainTypes = ['PC','Notebook','All_In_One','Monitor','UPS','Printer','Scanner','Projector','audio_set'];
?>

<?php if(in_array($d['type'], $mainTypes)): ?>

<!-- 🔴 อุปกรณ์หลัก -->
<a href="asset_assign_user.php?transfer_id=<?= $d['transfer_id'] ?>&no_pc=<?= $d['no_pc'] ?>"
class="btn btn-sm btn-primary">
👤 เพิ่มผู้ใช้
</a>

<?php else: ?>

<!-- 🟢 อุปกรณ์ร่วม -->
<a href="asset_assign_shared.php?transfer_id=<?= $d['transfer_id'] ?>&no_pc=<?= $d['no_pc'] ?>"
class="btn btn-sm btn-success">
📦 นำมาใช้งาน
</a>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>
<?php endif; ?>

</tbody>

</table>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>