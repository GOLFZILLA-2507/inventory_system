<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';


/* ================= UPDATE STATUS ================= */
if(isset($_POST['update_status'])){
    $stmt = $conn->prepare("
        UPDATE IT_RepairTickets
        SET 
            status = ?,
            assigned_to = ?,
            solution = ?,
            cost = ?,
            updated_at = GETDATE(),
            closed_at = CASE 
                WHEN ? = N'เสร็จแล้ว' THEN GETDATE() 
                ELSE closed_at 
            END
        WHERE ticket_id = ?
    ");

    $stmt->execute([
        $_POST['status'],
        $_POST['assigned_to'],
        $_POST['solution'],
        $_POST['cost'],
        $_POST['status'],
        $_POST['ticket_id']
    ]);

    header("Location: repair_manage.php");
    exit;
}

/* ================= DASHBOARD TOP 5 ================= */
$topAssets = $conn->query("
    SELECT TOP 5 
        a.no_pc,
        COUNT(r.ticket_id) total_repairs
    FROM IT_RepairTickets r
    LEFT JOIN IT_assets a ON a.asset_id = r.asset_id
    GROUP BY a.no_pc
    ORDER BY total_repairs DESC
")->fetchAll(PDO::FETCH_ASSOC);

/* ================= LOAD DATA ================= */
$stmt = $conn->query("
SELECT 
    r.*,
    a.no_pc,a.new_no,a.spec,a.ram,a.ssd,a.gpu,
    (SELECT COUNT(*) FROM IT_RepairTickets WHERE asset_id=r.asset_id) AS repair_count
FROM IT_RepairTickets r
LEFT JOIN IT_assets a ON a.asset_id = r.asset_id
ORDER BY r.ticket_id DESC
");

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">

<style>
body{font-family:'Sarabun';font-size:14px;}
.card-header{background:linear-gradient(135deg,#0d6efd,#0dcaf0);color:white;}
.table thead{background:linear-gradient(135deg,#0d6efd,#0dcaf0);color:#fff;}
.badge-open{background:#6c757d;}
.badge-progress{background:#ffc107;color:#000;}
.badge-done{background:#198754;}
.img-thumb{height:80px;margin:4px;border-radius:8px;cursor:pointer;}
</style>

<div class="container-fluid p-3">

<!-- ================= DASHBOARD ================= -->
<div class="card shadow mb-3">
<div class="card-header">🔥 เครื่องเสียบ่อย (Top 5)</div>
<div class="card-body">
<div class="row">
<?php foreach($topAssets as $t): ?>
<div class="col-md-3 mb-2">
<div class="border p-2 text-center rounded">
<div class="fw-bold text-primary"><?= $t['no_pc'] ?></div>
<div class="text-danger fw-bold"><?= $t['total_repairs'] ?> ครั้ง</div>
</div>
</div>
<?php endforeach; ?>
</div>
</div>
</div>

<!-- ================= TABLE ================= -->
<div class="card shadow">
<div class="card-header">🛠 ระบบจัดการงานแจ้งซ่อม</div>

<div class="card-body">

<div class="row mb-3">
<div class="col-md-4">
<input id="searchInput" class="form-control" placeholder="🔍 ค้นหา ผู้ใช้ / รหัส / อาการ">
</div>

<div class="col-md-3">
<select id="statusFilter" class="form-control">
<option value="">-- ทุกสถานะ --</option>
<option value="รอรับเรื่อง">🟡 รอรับเรื่อง</option>
<option value="กำลังซ่อม">🟠 กำลังซ่อม</option>
<option value="เสร็จแล้ว">🟢 เสร็จแล้ว</option>
</select>
</div>
</div>

<div class="table-responsive">
<table class="table table-bordered table-hover align-middle">
<thead class="text-center">
<tr>
<th>#</th>
<th>รหัสเครื่อง</th>
<th>ผู้แจ้ง</th>
<th>อาการเสีย</th>
<th>จำนวนครั้งซ่อม</th>
<th>สถานะ</th>
<th>วันที่แจ้ง</th>
<th>จัดการ</th>
</tr>
</thead>

<tbody id="tableBody">

<?php $i=1; foreach($data as $r):

$badge='badge-open';
if($r['status']=='กำลังซ่อม') $badge='badge-progress';
if($r['status']=='เสร็จแล้ว') $badge='badge-done';
?>

<tr>
<td><?= $i++ ?></td>
<td class="text-primary fw-bold"><?= $r['user_no_pc'] ?></td>
<td><?= $r['user_name'] ?></td>
<td><?= $r['problem'] ?></td>

<td class="text-danger fw-bold text-center">
<?= $r['repair_count'] ?> ครั้ง
</td>

<td class="text-center">
<span class="badge <?= $badge ?> status-text">
<?= $r['status'] ?>
</span>
</td>

<td class="text-center">
<?= date('d/m/Y H:i',strtotime($r['created_at'])) ?>
</td>

<td class="text-center">
<button class="btn btn-primary btn-sm"
data-bs-toggle="modal"
data-bs-target="#repairModal<?= $r['ticket_id'] ?>">ดูรายละเอียด</button>

</td>
</tr>

<?php endforeach; ?>

</tbody>
</table>
</div>

</div>
</div>
</div>

<!-- ================= MODALS ================= -->
<?php foreach($data as $r): ?>

<div class="modal fade" id="repairModal<?= $r['ticket_id'] ?>">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<form method="post">

<div class="modal-header">
<h5 class="modal-title">รายละเอียดงานซ่อม #<?= $r['ticket_id'] ?></h5>
<button class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">

<input type="hidden" name="ticket_id" value="<?= $r['ticket_id'] ?>">

<div class="row">

<div class="col-md-6 mb-2">
<label>รหัสเครื่อง</label>
<input class="form-control" value="<?= $r['user_no_pc'] ?>" readonly>
</div>

<div class="col-md-6 mb-2">
<label>ผู้แจ้ง</label>
<input class="form-control" value="<?= $r['user_name'] ?>" readonly>
</div>

<div class="col-md-12 mb-2">
<label>Spec</label>
<textarea class="form-control" readonly>
<?= $r['new_no'] ?> | <?= $r['spec'] ?> | RAM <?= $r['ram'] ?> | SSD <?= $r['ssd'] ?> | GPU <?= $r['gpu'] ?>
</textarea>
</div>

<div class="col-md-12 mb-2">
<label>รูปภาพ</label><br>
<?php for($i2=1;$i2<=3;$i2++):
if(!empty($r["img$i2"])): ?>
<img src="../uploads/repair/<?= $r["img$i2"] ?>" class="img-thumb" onclick="zoomImg(this.src)">
<?php endif; endfor; ?>
</div>

<div class="col-md-12 mb-2">
<label>อาการเสีย</label>
<textarea class="form-control" readonly><?= $r['problem'] ?></textarea>
</div>

<div class="col-md-4 mb-2">
<label>สถานะ</label>
<select name="status" class="form-control">
<option <?= $r['status']=='รอรับเรื่อง'?'selected':'' ?>>รอรับเรื่อง</option>
<option <?= $r['status']=='กำลังซ่อม'?'selected':'' ?>>กำลังซ่อม</option>
<option <?= $r['status']=='เสร็จแล้ว'?'selected':'' ?>>เสร็จแล้ว</option>
</select>
</div>

<div class="col-md-4 mb-2">
<label>ผู้รับผิดชอบ</label>
<input name="assigned_to" class="form-control" value="<?= $r['assigned_to'] ?>">
</div>

<div class="col-md-4 mb-2">
<label>ค่าใช้จ่าย</label>
<input name="cost" class="form-control" value="<?= $r['cost'] ?>">
</div>

<div class="col-md-12 mb-2">
<label>รายละเอียดการซ่อม</label>
<textarea name="solution" class="form-control"><?= $r['solution'] ?></textarea>
</div>

<div class="col-md-6 mb-2">
<label>วันที่แจ้ง</label>
<input class="form-control" value="<?= $r['created_at'] ?>" readonly>
</div>

<div class="col-md-6 mb-2">
<label>วันที่ปิดงาน</label>
<input class="form-control" value="<?= $r['closed_at'] ?? '-' ?>" readonly>
</div>

<div class="col-md-12 mb-2">
<label>ระยะเวลาซ่อม</label>
<input class="form-control"
value="<?php
if($r['closed_at']){
echo (strtotime($r['closed_at'])-strtotime($r['created_at']))/86400 .' วัน';
}else{ echo '-'; }
?>" readonly>
</div>

</div>
</div>

<div class="modal-footer">
<button class="btn btn-success" name="update_status">💾 บันทึก</button>
<button class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
</div>

</form>
</div>
</div>
</div>

<?php endforeach; ?>

<!-- IMAGE ZOOM -->
<div class="modal fade" id="imgModal">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-body text-center">
<img id="imgView" style="max-width:100%;">
</div>
</div>
</div>
</div>

<script>
function zoomImg(src){
document.getElementById('imgView').src=src;
new bootstrap.Modal(document.getElementById('imgModal')).show();
}

/* 🔥 REALTIME SEARCH + FILTER */
document.getElementById("searchInput").addEventListener("keyup",filterTable);
document.getElementById("statusFilter").addEventListener("change",filterTable);

function filterTable(){
let key=document.getElementById("searchInput").value.toLowerCase();
let st=document.getElementById("statusFilter").value.toLowerCase();
let rows=document.querySelectorAll("#tableBody tr");

rows.forEach(row=>{
let text=row.innerText.toLowerCase();
let status=row.querySelector(".status-text").innerText.toLowerCase();
row.style.display=(text.includes(key) && (st=='' || status.includes(st))) ? '' : 'none';
});
}
</script>

<?php include 'partials/footer.php'; ?>