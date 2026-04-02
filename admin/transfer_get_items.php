<?php
require_once '../config/connect.php';

$round = $_GET['round'];

$stmt = $conn->prepare("
SELECT transfer_id,no_pc,type,receive_status
FROM IT_AssetTransfer_Headers
WHERE sent_transfer=?
");
$stmt->execute([$round]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));