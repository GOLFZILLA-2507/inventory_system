<?php
// เปิด session ถ้ายังไม่เปิด
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>IT Inventory System</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Icon (emoji ได้ ไม่บังคับใช้ bi) -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>