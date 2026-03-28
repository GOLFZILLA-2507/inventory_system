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

    // 🔥 รับค่าจาก dropdown
    $statusList = $_POST['status'] ?? [];

    // โหลดข้อมูล
    $stmt = $conn->prepare("
    SELECT t.*, a.type_equipment, a.spec AS asset_spec, a.ram, a.ssd, a.gpu
    FROM IT_AssetTransfer_Headers t
    LEFT JOIN IT_assets a ON a.no_pc = t.no_pc
    WHERE t.sent_transfer = ?
    ");
    $stmt->execute([$round]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($items as $row){

        $id = $row['transfer_id'];

        // ❌ ถ้ารับแล้ว → ข้าม
        if($row['receive_status']=='รับแล้ว') continue;

        // 🔥 ค่าที่เลือก
        $status = $statusList[$id] ?? '';
        if($status == '') continue;

        // ❌ ไม่พบ
        if($status == 'ไม่พบอุปกรณ์นี้'){

            $conn->prepare("
            UPDATE IT_AssetTransfer_Headers
            SET receive_status='ไม่พบอุปกรณ์นี้'
            WHERE transfer_id=?
            ")->execute([$id]);

            continue;
        }

        // ✅ รับแล้ว
        if($status == 'รับแล้ว'){

            // 🔥 update รับของ
            $conn->prepare("
            UPDATE IT_AssetTransfer_Headers
            SET receive_status='รับแล้ว',
                arrived_date = GETDATE()
            WHERE transfer_id = ?
            ")->execute([$id]);

            // =============================
            // 🔥 ย้าย asset (logic เดิมคุณ)
            // =============================

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

            // 🔥 ลบ row ว่าง
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

    header("Location: transfer_receive_detail.php?round=".$round);
    exit;
}

/* =========================================
โหลดข้อมูล
========================================= */
$stmt = $conn->prepare("
SELECT t.*, a.type_equipment, a.spec AS asset_spec, a.ram, a.ssd, a.gpu
FROM IT_AssetTransfer_Headers t
LEFT JOIN IT_assets a ON a.no_pc = t.no_pc
WHERE t.sent_transfer = ?
");
$stmt->execute([$round]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="container mt-4">
<div class="card shadow">
<div class="card-header bg-success text-white">
ตรวจรับอุปกรณ์
</div>

<div class="card-body">

<form method="post" id="mainForm">

<input type="hidden" name="round" value="<?= $round ?>">

<table class="table table-bordered">

<tr>
<th>ลำดับ</th></th>
<th>ตรวจรับ</th>
<th>ส่งมาจาก</th>
<th>รหัส</th>
<th>ประเภท</th>
<th>Spec</th>
</tr>
<?php $i=1?>
<?php foreach($data as $d): ?>
<tr>

<td><?= $i++ ?></td> <!-- 🔥 เลขลำดับ -->

<td class="text-center">

<?php
// 🔥 ไม่ให้เลือกถ้ายกเลิก
if($d['receive_status']=='ยกเลิก'){
    echo '<span class="badge bg-secondary">ยกเลิก</span>';
}
elseif($d['receive_status']=='รับแล้ว'){
    echo '<span class="badge bg-success">รับแล้ว</span>';
}
elseif($d['receive_status']=='ไม่พบอุปกรณ์นี้'){
    echo '<span class="badge bg-danger">ไม่พบ</span>';
}
else{
?>

<select name="status[<?= $d['transfer_id'] ?>]"
class="form-select form-select-sm status-select"
data-no="<?= $d['no_pc'] ?>">
<option value="">-- เลือก --</option>
<option value="รับแล้ว">✅ รับอุปกรณ์</option>
<option value="ไม่พบอุปกรณ์นี้">❌ ไม่พบ</option>
</select>
<?php } ?>
</td>
<td><?= $d['from_site'] ?></td>
<td><?= $d['no_pc'] ?></td>
<td><?= $d['type_equipment'] ?? $d['type'] ?></td>
<td>
<?php
$specParts = array_filter([
$d['asset_spec'],$d['ram'],$d['ssd'],$d['gpu']
]);
echo empty($specParts) ? '-' : implode(' | ',$specParts);
?>
</td>

</tr>
<?php endforeach; ?>

</table>

<!-- 🔥 ปุ่มเปิด modal -->
<button type="button" id="openConfirm" class="btn btn-success">
ยืนยันการตรวจรับ
</button>

</form>

</div>
</div>
</div>

<!-- 🔥 MODAL -->
<div class="modal fade" id="confirmModal">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">

<div class="modal-header bg-success text-white">
<h5>ยืนยันรายการ</h5>
</div>

<div class="modal-body">
<div class="text-success fw-bold">รับ</div>
<div id="listOk"></div>

<div class="text-danger fw-bold mt-2">ไม่พบ</div>
<div id="listFail"></div>
</div>

<div class="modal-footer">
<button class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
<button type="submit" name="confirm" form="mainForm" class="btn btn-success">
ยืนยัน
</button>
</div>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>

<script>
// 🔥 เปิด modal + สรุปรายการ
document.getElementById("openConfirm").onclick = function(){

let ok="",fail="";

document.querySelectorAll(".status-select").forEach(sel=>{
    if(sel.value=="รับแล้ว") ok+="✔ "+sel.dataset.no+"<br>";
    if(sel.value=="ไม่พบอุปกรณ์นี้") fail+="✖ "+sel.dataset.no+"<br>";
});

document.getElementById("listOk").innerHTML = ok || "ไม่มี";
document.getElementById("listFail").innerHTML = fail || "ไม่มี";

new bootstrap.Modal(document.getElementById('confirmModal')).show();
};

// 🔥 เปลี่ยนสี dropdown
document.querySelectorAll(".status-select").forEach(sel=>{
sel.addEventListener("change",function(){
this.classList.remove("border-success","border-danger");
if(this.value=="รับแล้ว") this.classList.add("border-success");
if(this.value=="ไม่พบอุปกรณ์นี้") this.classList.add("border-danger");
});
});
</script>