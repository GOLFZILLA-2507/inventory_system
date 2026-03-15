<?php

require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* =====================================================
   update ข้อมูลอุปกรณ์
===================================================== */

if($_SERVER['REQUEST_METHOD']=='POST'){

$stmt=$conn->prepare("

UPDATE IT_user_information
SET

user_type_equipment=?,
user_spec=?,
user_ram=?,
user_ssd=?,
user_gpu=?,
user_monitor1=?,
user_monitor2=?,
user_ups=?

WHERE asset_id=?

");

$stmt->execute([

$_POST['user_type_equipment'],
$_POST['user_spec'],
$_POST['user_ram'],
$_POST['user_ssd'],
$_POST['user_gpu'],
$_POST['user_monitor1'],
$_POST['user_monitor2'],
$_POST['user_ups'],
$_POST['asset_id']

]);

header("Location: asset_shared_view.php");

exit;

}