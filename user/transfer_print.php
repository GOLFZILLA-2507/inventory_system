<?php
require_once '../config/connect.php';

$id=$_GET['id'];

$h=$conn->prepare("SELECT * FROM IT_AssetTransfer_Headers WHERE transfer_id=?");
$h->execute([$id]);
$h=$h->fetch(PDO::FETCH_ASSOC);

$items=$conn->prepare("
SELECT a.no_pc,a.spec,a.ram,a.ssd,a.gpu
FROM IT_AssetTransfer_Items i
JOIN IT_assets a ON i.asset_id=a.asset_id
WHERE i.transfer_id=?
");
$items->execute([$id]);
$items=$items->fetchAll(PDO::FETCH_ASSOC);
?>

<h3>ใบส่งอุปกรณ์</h3>
จาก: <?= $h['from_site'] ?><br>
ถึง: <?= $h['to_site'] ?><br>
วันที่: <?= $h['transfer_date'] ?><br>

<hr>

<table border="1" width="100%">
<tr><th>#</th><th>รหัส</th><th>Spec</th></tr>

<?php $i=1; foreach($items as $it): ?>
<tr>
<td><?= $i++ ?></td>
<td><?= $it['no_pc'] ?></td>
<td><?= $it['spec']." | ".$it['ram']." | ".$it['ssd']." | ".$it['gpu'] ?></td>
</tr>
<?php endforeach; ?>

</table>

<script>window.print();</script>