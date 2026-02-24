<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

$site = $_SESSION['site'];
$user = $_SESSION['fullname'];

if(isset($_POST['submit'])){

    $type = $_POST['transfer_type'];
    $to = $_POST['to_site'];
    $items = $_POST['asset_ids'] ?? [];

    if(empty($items)){
        echo "<script>alert('กรุณาเลือกอุปกรณ์');</script>";
    }else{

        $stmt = $conn->prepare("
        INSERT INTO IT_AssetTransfer_Headers
        (transfer_type,from_site,to_site,created_by,transfer_status)
        VALUES (?,?,?,?, 'pending')
        ");
        $stmt->execute([$type,$site,$to,$user]);

        $transfer_id = $conn->lastInsertId();

        $stmtItem = $conn->prepare("
        INSERT INTO IT_AssetTransfer_Items (transfer_id,asset_id)
        VALUES (?,?)
        ");

        foreach($items as $aid){
            $stmtItem->execute([$transfer_id,$aid]);
        }

        header("Location: transfer_list.php?success=1");
        exit;
    }
}

$assets = $conn->prepare("
SELECT asset_id, no_pc, spec, ram, ssd, gpu
FROM IT_assets
WHERE project = ?
ORDER BY no_pc
");
$assets->execute([$site]);
$assets = $assets->fetchAll(PDO::FETCH_ASSOC);

$projects = $conn->query("SELECT project_name FROM IT_Projects WHERE status='active'")->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<div class="container mt-4">
<div class="card shadow">
<div class="card-header bg-success text-white">🚚 สร้างรายการโอนย้าย / ส่งมอบ / ส่งคืน</div>
<div class="card-body">

<form method="post">

<div class="row">
<div class="col-md-4">
<label>ประเภท</label>
<select name="transfer_type" class="form-control">
<option value="ส่งมอบ">ส่งมอบ</option>
<option value="โอนย้าย">โอนย้าย</option>
<option value="ส่งคืน">ส่งคืน</option>
</select>
</div>

<div class="col-md-4">
<label>จาก</label>
<input class="form-control" value="<?= $site ?>" readonly>
</div>

<div class="col-md-4">
<label>ไปยัง</label>
<select name="to_site" class="form-control">
<?php foreach($projects as $p): ?>
<option><?= $p['project_name'] ?></option>
<?php endforeach; ?>
</select>
</div>
</div>

<hr>

<table class="table table-bordered">
<tr><th></th><th>รหัสเครื่อง</th><th>Spec</th></tr>

<?php foreach($assets as $a): ?>
<tr>
<td><input type="checkbox" name="asset_ids[]" value="<?= $a['asset_id'] ?>"></td>
<td><?= $a['no_pc'] ?></td>
<td><?= $a['spec']." | ".$a['ram']." | ".$a['ssd']." | ".$a['gpu'] ?></td>
</tr>
<?php endforeach; ?>
</table>

<button class="btn btn-success" name="submit">📨 ส่งรายการ</button>
</form>

</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>