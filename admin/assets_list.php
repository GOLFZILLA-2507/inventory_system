<?php
// เรียก header
include('../partials/header.php');

// เชื่อมต่อฐานข้อมูล
include('../config/db.php');
?>

<h2>รายการอุปกรณ์</h2>

<table border="1" width="100%">
<tr>
    <th>ประเภท</th>
    <th>Serial</th>
    <th>ผู้ใช้งาน</th>
    <th>โครงการ</th>
    <th>สถานะ</th>
</tr>

<?php
// ดึงข้อมูลอุปกรณ์ + พนักงาน + โครงการ
$sql = "
SELECT a.*, e.fullname, p.project_name
FROM IT_Assets a
LEFT JOIN Employee e ON a.employee_id = e.id
LEFT JOIN IT_Projects p ON a.project_id = p.project_id
";

// รัน query
$q = sqlsrv_query($conn, $sql);

// วนลูปแสดงข้อมูล
while ($r = sqlsrv_fetch_array($q)) {
    echo "<tr>
        <td>{$r['asset_type']}</td>
        <td>{$r['serial_no']}</td>
        <td>{$r['fullname']}</td>
        <td>{$r['project_name']}</td>
        <td>{$r['status']}</td>
    </tr>";
}
?>
</table>
