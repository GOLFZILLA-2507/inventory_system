<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* =====================================================
🔥 INSERT ส่งอุปกรณ์ (Bulk)
===================================================== */
if(isset($_POST['submit_transfer'])){

    $ids = $_POST['asset_ids'] ?? [];
    $project = $_POST['project'] ?? '';
    $round = time(); // 🔥 batch id

    if(!empty($ids) && $project){

        foreach($ids as $id){

            // 🔥 ดึงข้อมูล asset
            $stmtA = $conn->prepare("SELECT * FROM IT_assets WHERE asset_id=?");
            $stmtA->execute([$id]);
            $a = $stmtA->fetch(PDO::FETCH_ASSOC);

            if($a){

                $stmt = $conn->prepare("
                INSERT INTO IT_AssetTransfer_Headers
                (transfer_type,from_site,to_site,transfer_date,created_by,created_at,
                 no_pc,type,asset_id,sent_transfer,admin_status)
                VALUES
                ('ส่งมอบ',?,?,?,?,GETDATE(),?,?,?,?,'รออนุมัติ')
                ");

                $stmt->execute([
                    $a['project'],       // จาก
                    $project,            // ไป
                    date('Y-m-d H:i:s'),
                    $_SESSION['EmployeeID'],
                    $a['no_pc'],
                    $a['type_equipment'],
                    $a['asset_id'],
                    $round
                ]);
            }
        }
    }

    header("Location: transfer_s_project.php?success=1");
    exit;
}

/* =====================================================
🔥 ยกเลิกรายการ
===================================================== */
if(isset($_POST['cancel_id'])){

    $stmt = $conn->prepare("
    UPDATE IT_AssetTransfer_Headers
    SET receive_status='ยกเลิก'
    WHERE transfer_id=?
    ");
    $stmt->execute([$_POST['cancel_id']]);

    header("Location: transfer_s_project.php");
    exit;
}

/* =====================================================
🔥 โหลดอุปกรณ์
===================================================== */
$assets = $conn->query("
SELECT asset_id,no_pc,type_equipment,project
FROM IT_assets
ORDER BY no_pc
")->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
🔥 โหลดโครงการ
===================================================== */
$projects = $conn->query("
SELECT DISTINCT project FROM IT_assets
")->fetchAll(PDO::FETCH_COLUMN);

/* =====================================================
🔥 history
===================================================== */
$history = $conn->query("
SELECT TOP 50 *
FROM IT_AssetTransfer_Headers
WHERE transfer_type='ส่งมอบ'
ORDER BY transfer_id DESC
")->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="container mt-4">

<div class="card">
<div class="card-header bg-primary text-white">
🚚 ส่งมอบอุปกรณ์
</div>

<div class="card-body">

<?php if(isset($_GET['success'])){ ?>
<div class="alert alert-success">✅ ส่งมอบสำเร็จ</div>
<?php } ?>

<form id="transferForm" method="post">

<!-- 🔥 เลือกโครงการ -->
<div class="mb-3">
<label>โครงการปลายทาง</label>
<select name="project" id="projectSelect" class="form-control" required>
<option value="">-- เลือกโครงการ --</option>
<?php foreach($projects as $p){ ?>
<option><?= $p ?></option>
<?php } ?>
</select>
</div>

<!-- 🔥 ตารางเลือก -->
<table class="table table-bordered text-center">
<thead>
<tr>
<th><input type="checkbox" id="checkAll"></th>
<th>รหัส</th>
<th>ประเภท</th>
<th>โครงการปัจจุบัน</th>
</tr>
</thead>

<tbody>
<?php foreach($assets as $a){ ?>
<tr>
<td>
<input type="checkbox" name="asset_ids[]" value="<?= $a['asset_id'] ?>" class="item">
</td>
<td><?= $a['no_pc'] ?></td>
<td><?= $a['type_equipment'] ?></td>
<td><?= $a['project'] ?></td>
</tr>
<?php } ?>
</tbody>
</table>

<!-- 🔥 ปุ่ม -->
<button type="button" class="btn btn-primary" onclick="openConfirm()">
📦 ส่งมอบ
</button>

<input type="hidden" name="submit_transfer">

</form>

</div>
</div>

<!-- ================= HISTORY ================= -->
<div class="card mt-4">
<div class="card-header bg-dark text-white">📋 รายการที่ส่งแล้ว</div>

<div class="card-body">

<button class="btn btn-secondary mb-2" onclick="window.print()">🖨 Print</button>

<table class="table table-bordered text-center">
<tr>
<th>ID</th>
<th>รหัส</th>
<th>จาก</th>
<th>ไป</th>
<th>สถานะ</th>
<th>จัดการ</th>
</tr>

<?php foreach($history as $h){ ?>
<tr>
<td><?= $h['transfer_id'] ?></td>
<td><?= $h['no_pc'] ?></td>
<td><?= $h['from_site'] ?></td>
<td><?= $h['to_site'] ?></td>

<td>
<?= $h['receive_status']=='ยกเลิก' ? '❌ ยกเลิก' : '⏳ รออนุมัติ' ?>
</td>

<td>
<?php if($h['receive_status']!='ยกเลิก'){ ?>
<form method="post">
<input type="hidden" name="cancel_id" value="<?= $h['transfer_id'] ?>">
<button class="btn btn-danger btn-sm">ยกเลิก</button>
</form>
<?php } ?>
</td>

</tr>
<?php } ?>

</table>

</div>
</div>

</div>

<!-- ================= MODAL CONFIRM ================= -->
<div class="modal fade" id="confirmModal">
<div class="modal-dialog">
<div class="modal-content p-3">

<h5>ยืนยันการส่งมอบ</h5>
<div id="confirmContent"></div>

<button class="btn btn-success mt-3" onclick="submitForm()">ยืนยัน</button>
<button class="btn btn-secondary mt-2" data-bs-dismiss="modal">ยกเลิก</button>

</div>
</div>
</div>

<!-- ================= MODAL SUCCESS ================= -->
<div class="modal fade" id="successModal">
<div class="modal-dialog">
<div class="modal-content text-center p-3">
<h5 class="text-success">✅ สำเร็จ</h5>
</div>
</div>
</div>

<script>

/* 🔥 select all */
document.getElementById('checkAll').addEventListener('change',function(){
document.querySelectorAll('.item').forEach(cb=>cb.checked=this.checked);
});

/* 🔥 confirm */
function openConfirm(){

let checked=document.querySelectorAll('.item:checked');
let project=document.getElementById('projectSelect').value;

if(checked.length==0){ alert('เลือกอย่างน้อย 1 รายการ'); return; }
if(!project){ alert('เลือกโครงการ'); return; }

let html=`<b>ส่งไป:</b> ${project}<br><b>รายการ:</b><ul>`;

checked.forEach(c=>{
let row=c.closest('tr');
html+=`<li>${row.children[1].innerText}</li>`;
});

html+='</ul>';

document.getElementById('confirmContent').innerHTML=html;

new bootstrap.Modal(document.getElementById('confirmModal')).show();
}

/* 🔥 submit */
function submitForm(){

bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
new bootstrap.Modal(document.getElementById('successModal')).show();

setTimeout(()=>{
document.getElementById('transferForm').submit();
},1000);

}

</script>

<?php include 'partials/footer.php'; ?>