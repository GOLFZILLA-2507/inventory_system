<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$keyword = $_GET['search'] ?? '';

$result = null;

if($keyword){

    $stmt = $conn->prepare("
    SELECT 
        a.no_pc,
        a.type_equipment,
        a.spec,
        a.ram,
        a.ssd,
        a.gpu,
        a.project,
        a.rental_price,
        a.use_it,

        u.user_employee,
        u.user_project,
        u.device_role

    FROM IT_assets a

    LEFT JOIN IT_user_devices u
        ON u.device_code = a.no_pc

    WHERE a.no_pc LIKE ?
    ");

    $stmt->execute(["%$keyword%"]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>

<div class="container mt-4">
<div class="card shadow">

<div class="card-header bg-primary text-white">
🔍 ตรวจสอบรหัสอุปกรณ์
</div>

<div class="card-body">

<form class="row mb-3">
<div class="col-md-10">
<input name="search" class="form-control"
placeholder="พิมพ์รหัส เช่น PC001..."
value="<?= htmlspecialchars($keyword) ?>">
</div>
<div class="col-md-2">
<button class="btn btn-primary w-100">ค้นหา</button>
</div>
</form>

<?php if($keyword): ?>

<?php if(empty($result)): ?>
<div class="alert alert-danger text-center">
❌ ไม่พบอุปกรณ์
</div>
<?php else: ?>

<table class="table table-bordered text-center">
<tr>
<th>รหัส</th>
<th>ประเภท</th>
<th>Spec</th>
<th>โครงการ (Asset)</th>
<th>ผู้ใช้</th>
<th>โครงการ (User)</th>
<th>สถานะ</th>
<th>ค่าเช่า</th>
</tr>

<?php foreach($result as $r): ?>

<tr>

<td><?= $r['no_pc'] ?></td>

<td><?= $r['type_equipment'] ?></td>

<td class="text-start">
<?= $r['spec'] ?> <br>
RAM: <?= $r['ram'] ?> | SSD: <?= $r['ssd'] ?> | GPU: <?= $r['gpu'] ?>
</td>

<td><?= $r['project'] ?></td>

<td>
<?= $r['user_employee'] 
? $r['user_employee'] 
: '<span class="text-danger">ไม่มีผู้ใช้</span>' ?>
</td>

<td><?= $r['user_project'] ?? '-' ?></td>

<td>
<?php if($r['user_employee']): ?>
<span class="badge bg-success">ใช้งานอยู่</span>
<?php else: ?>
<span class="badge bg-secondary">ว่าง</span>
<?php endif; ?>
</td>

<td><?= number_format($r['rental_price'],2) ?></td>

</tr>

<?php endforeach; ?>

</table>

<?php endif; ?>
<?php endif; ?>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>