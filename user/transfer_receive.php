<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];
$user = $_SESSION['fullname'];
$emp  = $_SESSION['EmployeeID'];

/* =====================================================
   เมื่อกดยืนยันรับอุปกรณ์ (รับทั้งรอบ)
===================================================== */
if(isset($_POST['receive'])){

    $round = $_POST['round'];

    /* =====================================================
       โหลดรายการทั้งหมดในรอบนั้น
    ===================================================== */
    $stmt = $conn->prepare("
    SELECT *
    FROM IT_AssetTransfer_Headers
    WHERE sent_transfer = ?
    ");
    $stmt->execute([$round]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($items as $t){

        /* =====================================================
           1. อัพเดทสถานะรับของ (แก้ถูกต้อง)
        ===================================================== */
        $stmt = $conn->prepare("
        UPDATE IT_AssetTransfer_Headers
        SET 
            receive_status = 'รับแล้ว',
            arrived_date = GETDATE()
        WHERE transfer_id = ?
        ");
        $stmt->execute([$t['transfer_id']]);


        /* =====================================================
           2. บันทึกเข้า IT_user_information
        ===================================================== */

        // 🔥 หา asset_id ใหม่
        $stmtMax = $conn->prepare("
        SELECT ISNULL(MAX(asset_id),0) + 1 AS new_id
        FROM IT_user_information
        ");
        $stmtMax->execute();
        $new_asset_id = $stmtMax->fetchColumn();

        // 🔥 INSERT (ให้ตรงจำนวน column)
        $stmtInsert = $conn->prepare("
        INSERT INTO IT_user_information
        (
            asset_id,
            user_employee,
            user_project,
            user_no_pc,
            user_record,
            user_update
        )
        VALUES
        (?,?,?,?,?,GETDATE())
        ");

        $stmtInsert->execute([
            $new_asset_id,
            NULL,              // ยังไม่ assign คน
            $site,             // โครงการปลายทาง
            $t['no_pc'],       // 🔥 ใช้ no_pc เท่านั้น
            $user              // คนที่รับ
        ]);

    }

    /* =====================================================
       กลับหน้าเดิม
    ===================================================== */
    header("Location: transfer_receive.php");
    exit;
}


/* =====================================================
   โหลดรายการรอรับแบบ "รอบการส่ง"
===================================================== */
$stmt = $conn->prepare("
SELECT 
    sent_transfer,
    from_site,
    MIN(transfer_date) AS transfer_date,

    COUNT(*) AS total_items,

    -- 🔥 นับจำนวนที่รับแล้ว
    SUM(CASE WHEN receive_status = 'รับแล้ว' THEN 1 ELSE 0 END) AS received_items

FROM IT_AssetTransfer_Headers
WHERE to_site = ?
AND (receive_status IS NULL OR receive_status != 'ยกเลิก')
GROUP BY sent_transfer,from_site
ORDER BY sent_transfer DESC
");

$stmt->execute([$site]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="container mt-4">
<div class="card shadow">

<div class="card-header bg-success text-white">
📥 รายการรอรับอุปกรณ์
</div>

<div class="card-body">

<table class="table table-bordered table-hover">

<tr>
<th width="60">ลำดับ</th>
<th>รอบการส่ง</th>
<th>จากโครงการ</th>
<th>จำนวนอุปกรณ์</th>
<th>จำนวนที่รับแล้ว</th>
<th>วันที่โอน</th>
<th>ตรวจเช็คอุปกรณ์</th>
<th>พิมพ์ใบตรวจเช็ค</th>
<th>สถานะ</th>
</tr>

<?php $i=1; foreach($data as $d): ?>

<tr>

<td><?= $i++ ?></td>

<td>
<span class="badge bg-primary">
ครั้งที่ <?= $d['sent_transfer'] ?>
</span>
</td>

<td><?= $d['from_site'] ?></td>

<td>
<span class="badge bg-dark">
<?= $d['total_items'] ?> รายการ
</span>
</td>
<td>

<span class="badge bg-info">
<?= $d['received_items'] ?> / <?= $d['total_items'] ?>
</span>
</td>

<td><?= $d['transfer_date'] ?></td>
<td>

<a href="transfer_receive_detail.php?round=<?= $d['sent_transfer'] ?>" 
class="btn btn-info btn-sm">

ดูอุปกรณ์

</a>



</td>
<td>

<a href="transfer_receive_print.php?round=<?= $d['sent_transfer'] ?>" 
class="btn btn-secondary btn-sm" target="_blank">

🖨 ปริ้นใบตรวจเช็ค

</a>

</td>

<td>
<?php
if($d['received_items'] == 0){
    echo "<span class='badge bg-secondary'>⏳ ยังไม่ได้ตรวจรับ</span>";
}
elseif($d['received_items'] < $d['total_items']){
    echo "<span class='badge bg-warning text-dark'>📦 รับบางรายการ</span>";
}
else{
    echo "<span class='badge bg-success'>✅ รับครบแล้ว</span>";
}
?>
</td>

</tr>

<?php endforeach; ?>

</table>
<!-- 🔥 ปุ่มย้อนกลับ (อยู่ล่าง) -->
<div class="mt-3 text-start">
    <a href="asset_shared_view.php" class="btn btn-secondary">
        ⬅️ กลับหน้าหลัก
    </a>
</div>
</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>