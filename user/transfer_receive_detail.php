<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];
$user = $_SESSION['fullname'];

$round = $_GET['round'] ?? ($_POST['round'] ?? 0);


/* =========================================
เมื่อกดยืนยันตรวจรับ
========================================= */

if(isset($_POST['confirm'])){

$checked = $_POST['check_item'] ?? [];

/* โหลดรายการทั้งหมดในรอบ */
$stmt = $conn->prepare("
SELECT 
    t.*,
    a.type_equipment,
    a.spec AS asset_spec,
    a.ram,
    a.ssd,
    a.gpu
FROM IT_AssetTransfer_Headers t
LEFT JOIN IT_assets a 
    ON a.no_pc = t.no_pc
WHERE t.sent_transfer = ?
");
$stmt->execute([$round]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($items as $row){

$id = $row['transfer_id'];

/* ถ้ารายการนี้รับแล้ว ให้ข้าม */
if($row['receive_status']=='รับแล้ว'){
continue;
}

/* =========================================
ถ้าติ๊กรับอุปกรณ์
========================================= */

if(in_array($id,$checked)){

    // 🔥 เช็ค admin
    if($row['admin_status'] != 'อนุมัติ'){
        echo "<script>alert('❌ ADMIN ยังไม่อนุมัติ');history.back();</script>";
        exit;
    }

    // ✅ update รับของ
    $stmt = $conn->prepare("
    UPDATE IT_AssetTransfer_Headers
    SET receive_status='รับแล้ว',
        arrived_date = GETDATE()
    WHERE transfer_id = ?
    ");
    $stmt->execute([$id]);

    // =========================================
    // 🔥 ย้าย asset (ต้องอยู่ในนี้เท่านั้น)
    // =========================================

    $type = $row['type'];
    $no_pc = $row['no_pc'];
    $from  = $row['from_site'];

    if(in_array($type, ['PC','Notebook','All_In_One'])){

        $conn->prepare("
        UPDATE IT_user_information
        SET user_no_pc = NULL
        WHERE user_project = ?
        AND user_no_pc = ?
        ")->execute([$from,$no_pc]);

    }
    elseif($type == 'Monitor'){

        $conn->prepare("
        UPDATE IT_user_information SET user_monitor1=NULL
        WHERE user_project=? AND user_monitor1=?
        ")->execute([$from,$no_pc]);

        $conn->prepare("
        UPDATE IT_user_information SET user_monitor2=NULL
        WHERE user_project=? AND user_monitor2=?
        ")->execute([$from,$no_pc]);

    }
    elseif($type == 'UPS'){

        $conn->prepare("
        UPDATE IT_user_information SET user_ups=NULL
        WHERE user_project=? AND user_ups=?
        ")->execute([$from,$no_pc]);

    }
    else{

        $fields = [
            'user_cctv','user_nvr','user_projector','user_printer',
            'user_audio_set','user_plotter','user_Accessories_IT',
            'user_Drone','user_Optical_Fiber','user_Server'
        ];

        foreach($fields as $f){

            $stmtF = $conn->prepare("
            SELECT id,$f FROM IT_user_information
            WHERE user_project=? AND $f LIKE ?
            ");
            $stmtF->execute([$from,"%".$no_pc."%"]);

            foreach($stmtF->fetchAll(PDO::FETCH_ASSOC) as $r){

                $list = explode(',', $r[$f]);
                $list = array_filter(array_map('trim',$list));
                $list = array_diff($list, [$no_pc]);

                $conn->prepare("
                UPDATE IT_user_information SET $f=? WHERE id=?
                ")->execute([implode(',',$list),$r['id']]);
            }
        }
    }

    // =========================================
    // 🔥 ลบ row ว่าง
    // =========================================

    $conn->prepare("
    DELETE FROM IT_user_information
    WHERE user_project = ?
    AND (
        user_no_pc IS NULL
        AND user_monitor1 IS NULL
        AND user_monitor2 IS NULL
        AND user_ups IS NULL
        AND ISNULL(user_cctv,'') = ''
        AND ISNULL(user_nvr,'') = ''
        AND ISNULL(user_projector,'') = ''
        AND ISNULL(user_printer,'') = ''
        AND ISNULL(user_audio_set,'') = ''
        AND ISNULL(user_plotter,'') = ''
        AND ISNULL(user_Accessories_IT,'') = ''
        AND ISNULL(user_Drone,'') = ''
        AND ISNULL(user_Optical_Fiber,'') = ''
        AND ISNULL(user_Server,'') = ''
    )
    ")->execute([$from]);

}
}

/* =========================================
กันกด F5 แล้วบันทึกซ้ำ (สำคัญมาก)
========================================= */
header("Location: transfer_receive_detail.php?round=".$round);
exit;
}

/* =========================================
โหลดข้อมูลใหม่หลัง update
========================================= */

$stmt = $conn->prepare("
SELECT 
    t.*,
    a.type_equipment,
    a.spec AS asset_spec,
    a.ram,
    a.ssd,
    a.gpu
FROM IT_AssetTransfer_Headers t
LEFT JOIN IT_assets a 
    ON a.no_pc = t.no_pc
WHERE t.sent_transfer = ?
");
$stmt->execute([$round]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================
เช็คว่าทั้งรอบรับครบหรือยัง
========================================= */

$allReceived = true;

foreach($data as $chk){

    if($chk['receive_status'] != 'รับแล้ว'){
        $allReceived = false;
        break;
    }

}


include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="container mt-4">

<div class="card shadow">

<div class="card-header bg-success text-white">

ตรวจรับอุปกรณ์

</div>

<div class="card-body">

<form method="post">

<input type="hidden" name="round" value="<?= $round ?>">

<table class="table table-bordered">

<tr>
<th width="120">ตรวจรับ</th>
<th>รหัสอุปกรณ์</th>
<th>ประเภท</th>
<th>Spec</th>
</tr>

<?php foreach($data as $d): ?>

<tr>

<td class="text-center">

<?php
/* =================================================
ถ้ารับแล้ว → ไม่แสดง checkbox
================================================= */

if($d['receive_status']=='รับแล้ว'){
echo '<span class="badge bg-success">รับแล้ว</span>';
}

/* =================================================
ถ้าไม่พบอุปกรณ์
================================================= */

elseif($d['receive_status']=='ไม่พบอุปกรณ์นี้'){
echo '<span class="badge bg-danger">ไม่พบ</span>';
}

/* =================================================
ถ้ายังไม่ตรวจรับ → แสดง checkbox
================================================= */

else{
?>

<input 
type="checkbox"
name="check_item[]"
value="<?= $d['transfer_id'] ?>"

<?php
/* ถ้ารับแล้วให้คงติ๊ก */
if($d['receive_status']=='รับแล้ว'){
echo "checked";
}
?>

>

<?php } ?>

</td>

<td><?= $d['no_pc'] ?></td>
<td><?= $d['type_equipment'] ?? $d['type'] ?></td>

<td>

<?php

$specParts = array_filter([
$d['asset_spec'],
$d['ram'],
$d['ssd'],
$d['gpu']
]);

echo empty($specParts)
? 'ยังไม่ได้บันทึกข้อมูล'
: implode(' | ',$specParts);

?>

</td>

</tr>

<?php endforeach; ?>

</table>

<?php if(!$allReceived): ?>

<button class="btn btn-success" name="confirm">
ยืนยันการตรวจรับ
</button>

<?php else: ?>

<div class="alert alert-success text-center">
✅ รอบนี้ตรวจรับครบแล้ว
</div>

<?php endif; ?>

</form>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>