<?php
require_once '../config/connect.php';

$id=$_GET['id'];
$action=$_GET['action'];

if($action=='start'){
$conn->prepare("UPDATE IT_AssetTransfer_Headers SET transfer_status='in_transit' WHERE transfer_id=?")->execute([$id]);
}

header("Location: transfer_list.php");