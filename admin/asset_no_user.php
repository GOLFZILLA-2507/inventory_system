<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* =====================================================
🔥 ดึงอุปกรณ์ที่ไม่มีผู้ใช้
===================================================== */
$data = $conn->query("
SELECT *
FROM IT_user_information
WHERE user_employee IS NULL
ORDER BY user_update DESC
")->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="container mt-4">

<div class="card shadow">

<div class="card-header bg-primary text-white">
👤 อุปกรณ์ที่ยังไม่มีผู้ใช้งาน (Admin)
</div>

<div class="card-body">

<table class="table table-bordered table-hover">

<tr>
<th>#</th>
<th>รหัส</th>
<th>ประเภท</th>
<th>โครงการ</th>
<th>วันที่</th>
</tr>

<?php $i=1; foreach($data as $d){ ?>

<tr>
<td><?= $i++ ?></td>

<td>
<?= $d['user_no_pc'] 
?? $d['user_monitor1'] 
?? $d['user_ups'] 
?? '-' ?>
</td>

<td><?= $d['user_type_equipment'] ?></td>

<td><?= $d['user_project'] ?></td>

<td><?= $d['user_update'] ?></td>

</tr>

<?php } ?>

</table>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>