<?php
require_once '../config/connect.php';

$asset = $_POST['asset'] ?? null;
$site  = $_POST['site'] ?? null;

$sql = "
SELECT user_employee,
       user_no_pc,
       user_monitor1,
       user_monitor2,
       user_ups
FROM IT_user_information
WHERE user_project = ?
";

$stmt = $conn->prepare($sql);
$stmt->execute([$site]);

while($row = $stmt->fetch(PDO::FETCH_ASSOC)){

if(
$row['user_no_pc'] == $asset ||
$row['user_monitor1'] == $asset ||
$row['user_monitor2'] == $asset ||
$row['user_ups'] == $asset
){

echo json_encode([
"status"=>"duplicate",
"user"=>$row['user_employee'],
"asset"=>$asset
]);

exit;

}

}

echo json_encode([
"status"=>"ok"
]);