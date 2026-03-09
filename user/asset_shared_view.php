<?php
require_once '../config/connect.php';
require_once '../config/checklogin.php';

/* =====================================================
   ดึงชื่อโครงการของ user ที่ login
===================================================== */
$site = $_SESSION['site'];


/* =====================================================
   โหลดข้อมูลอุปกรณ์พนักงาน
   ตาราง : IT_user_information
   เงื่อนไข : แสดงเฉพาะ project ของ user
===================================================== */

$userAssets = $conn->prepare("
SELECT 
    u.user_employee,
    u.user_no_pc,
    u.user_type_equipment,
    u.user_spec,
    u.user_ram,
    u.user_ssd,
    u.user_gpu,
    u.user_monitor1,
    u.user_monitor2,
    u.user_ups
FROM IT_user_information u
WHERE LTRIM(RTRIM(u.user_project)) = LTRIM(RTRIM(?))
ORDER BY u.user_employee
");

$userAssets->execute([$site]);

/* ดึงข้อมูลทั้งหมด */
$userData = $userAssets->fetchAll(PDO::FETCH_ASSOC);



/* =====================================================
   โหลดอุปกรณ์ใช้ร่วม
   ตาราง : IT_user_information
   ดึงหลาย column แล้วรวมเป็น list
===================================================== */

$sqlShared = "
SELECT
    user_cctv,
    user_nvr,
    user_projector,
    user_printer,
    user_audio_set,
    user_plotter,
    user_Accessories_IT,
    user_Drone,
    user_Optical_Fiber,
    user_Server
FROM IT_user_information
WHERE user_project = ?
";

$stmt = $conn->prepare($sqlShared);

/* ส่งค่า project เข้า query */
$stmt->execute([$site]);

/* ดึงข้อมูลทั้งหมด */
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* array สำหรับเก็บอุปกรณ์ใช้ร่วม */
$sharedData = [];

/* loop ทุก record */
foreach($rows as $r){

    /* ตรวจว่ามีค่าแล้วค่อย push เข้า array */

    if(!empty($r['user_cctv']))
        $sharedData[] = ['type'=>'CCTV','code'=>$r['user_cctv']];

    if(!empty($r['user_nvr']))
        $sharedData[] = ['type'=>'NVR','code'=>$r['user_nvr']];

    if(!empty($r['user_projector']))
        $sharedData[] = ['type'=>'Projector','code'=>$r['user_projector']];

    if(!empty($r['user_printer']))
        $sharedData[] = ['type'=>'Printer','code'=>$r['user_printer']];

    if(!empty($r['user_audio_set']))
        $sharedData[] = ['type'=>'Audio Set','code'=>$r['user_audio_set']];

    if(!empty($r['user_plotter']))
        $sharedData[] = ['type'=>'Plotter','code'=>$r['user_plotter']];

    if(!empty($r['user_Accessories_IT']))
        $sharedData[] = ['type'=>'Accessories IT','code'=>$r['user_Accessories_IT']];

    if(!empty($r['user_Drone']))
        $sharedData[] = ['type'=>'Drone','code'=>$r['user_Drone']];

    if(!empty($r['user_Optical_Fiber']))
        $sharedData[] = ['type'=>'Optical Fiber','code'=>$r['user_Optical_Fiber']];

    if(!empty($r['user_Server']))
        $sharedData[] = ['type'=>'Server','code'=>$r['user_Server']];
}


/* โหลด header และ sidebar */
include 'partials/header.php';
include 'partials/sidebar.php';
?>

<style>
.card-header{
background:linear-gradient(135deg,#198754,#20c997);
color:white;
}
</style>

<div class="container mt-4">

<div class="card shadow">

<div class="card-header">
<h5 class="mb-0">📡 อุปกรณ์ภายในโครงการ <?= $site ?></h5>
</div>

<div class="card-body">


<!-- =====================================================
     ตารางอุปกรณ์พนักงาน
===================================================== -->

<h6 class="text-success">👨‍💼 อุปกรณ์พนักงาน</h6>

<table class="table table-bordered table-hover">

<thead class="table-success text-center">
<tr>
<th style="width:60px">ลำดับ</th>
<th>ชื่อผู้ใช้</th>
<th>รหัสเครื่อง</th>
<th>ประเภท</th>
<th>Spec</th>
<th>จอที่ 1</th>
<th>จอที่ 2</th>
<th>UPS</th>
</tr>
</thead>

<tbody>

<?php 
$i=1;

foreach($userData as $u):

/* รวม spec */
$spec = $u['user_spec']." | ".$u['user_ram']." | ".$u['user_ssd']." | ".$u['user_gpu'];

?>

<tr>

<td class="text-center"><?= $i++ ?></td>

<td><?= $u['user_employee'] ?></td>

<td class="fw-bold text-primary">
<?= $u['user_no_pc'] ?>
</td>

<td>
<?= $u['user_type_equipment'] ?: '-' ?>
</td>

<td>
<?= $spec ?>
</td>

<td>
<?= $u['user_monitor1'] ?: '-' ?>
</td>

<td>
<?= $u['user_monitor2'] ?: '-' ?>
</td>

<td>
<?= $u['user_ups'] ?: '-' ?>
</td>

</tr>

<?php endforeach; ?>

</tbody>
</table>



<hr>



<!-- =====================================================
     ตารางอุปกรณ์ใช้ร่วม
===================================================== -->

<h6 class="text-success">📡 อุปกรณ์ใช้ร่วม</h6>

<table class="table table-bordered table-hover">

<thead class="table-success text-center">

<tr>
<th style="width:60px">ลำดับ</th>
<th>ประเภท</th>
<th>รหัส</th>
</tr>

</thead>

<tbody>

<?php 

$j = 1;

/* =====================================================
   ตัวแปรเก็บอุปกรณ์ใช้ร่วมทั้งหมด
===================================================== */

$sharedData = [];

/* =====================================================
   ตัวแปรกันข้อมูลซ้ำ
   ใช้ key เป็น type + code
===================================================== */

$unique = [];


/* =====================================================
   loop ข้อมูลจากฐานทั้งหมด
===================================================== */

foreach($rows as $r){

    /* ================= CCTV ================= */

    if(!empty($r['user_cctv'])){

        /* แยก CCTV ที่คั่นด้วย comma */
        $cctvList = explode(',', $r['user_cctv']);

        foreach($cctvList as $cctv){

            $cctv = trim($cctv);

            $key = 'CCTV-'.$cctv;

            /* ตรวจสอบว่าซ้ำหรือไม่ */

            if(!isset($unique[$key])){

                $sharedData[] = [
                    'type' => 'CCTV',
                    'code' => $cctv
                ];

                $unique[$key] = true;
            }
        }
    }



    /* ================= NVR ================= */

    if(!empty($r['user_nvr'])){

        $code = trim($r['user_nvr']);

        $key = 'NVR-'.$code;

        if(!isset($unique[$key])){

            $sharedData[] = [
                'type'=>'NVR',
                'code'=>$code
            ];

            $unique[$key] = true;
        }
    }



    /* ================= Projector ================= */

    if(!empty($r['user_projector'])){

        $code = trim($r['user_projector']);

        $key = 'Projector-'.$code;

        if(!isset($unique[$key])){

            $sharedData[] = [
                'type'=>'Projector',
                'code'=>$code
            ];

            $unique[$key] = true;
        }
    }



    /* ================= Printer ================= */

    if(!empty($r['user_printer'])){

        $code = trim($r['user_printer']);

        $key = 'Printer-'.$code;

        if(!isset($unique[$key])){

            $sharedData[] = [
                'type'=>'Printer',
                'code'=>$code
            ];

            $unique[$key] = true;
        }
    }



    /* ================= Audio ================= */

    if(!empty($r['user_audio_set'])){

        $code = trim($r['user_audio_set']);

        $key = 'Audio-'.$code;

        if(!isset($unique[$key])){

            $sharedData[] = [
                'type'=>'Audio Set',
                'code'=>$code
            ];

            $unique[$key] = true;
        }
    }



    /* ================= Plotter ================= */

    if(!empty($r['user_plotter'])){

        $code = trim($r['user_plotter']);

        $key = 'Plotter-'.$code;

        if(!isset($unique[$key])){

            $sharedData[] = [
                'type'=>'Plotter',
                'code'=>$code
            ];

            $unique[$key] = true;
        }
    }



    /* ================= Accessories ================= */

    if(!empty($r['user_Accessories_IT'])){

        $code = trim($r['user_Accessories_IT']);

        $key = 'ACC-'.$code;

        if(!isset($unique[$key])){

            $sharedData[] = [
                'type'=>'Accessories IT',
                'code'=>$code
            ];

            $unique[$key] = true;
        }
    }



    /* ================= Drone ================= */

    if(!empty($r['user_Drone'])){

        $code = trim($r['user_Drone']);

        $key = 'Drone-'.$code;

        if(!isset($unique[$key])){

            $sharedData[] = [
                'type'=>'Drone',
                'code'=>$code
            ];

            $unique[$key] = true;
        }
    }



    /* ================= Fiber ================= */

    if(!empty($r['user_Optical_Fiber'])){

        $code = trim($r['user_Optical_Fiber']);

        $key = 'Fiber-'.$code;

        if(!isset($unique[$key])){

            $sharedData[] = [
                'type'=>'Optical Fiber',
                'code'=>$code
            ];

            $unique[$key] = true;
        }
    }



    /* ================= Server ================= */

    if(!empty($r['user_Server'])){

        $code = trim($r['user_Server']);

        $key = 'Server-'.$code;

        if(!isset($unique[$key])){

            $sharedData[] = [
                'type'=>'Server',
                'code'=>$code
            ];

            $unique[$key] = true;
        }
    }

}

if(!empty($r['user_cctv'])){

    /* แยก CCTV ที่คั่นด้วย comma */
    $cctvList = explode(',', $r['user_cctv']);

    foreach($cctvList as $cctv){

        $cctv = trim($cctv);

        $key = 'CCTV-'.$cctv;

        if(!isset($unique[$key])){

            $sharedData[] = [
                'type' => 'CCTV',
                'code' => $cctv
            ];

            $unique[$key] = true;
        }

    }
}

    if(!empty($r['user_nvr'])){
        $key = 'NVR-'.$r['user_nvr'];
        if(!isset($unique[$key])){
            $sharedData[] = ['type'=>'NVR','code'=>$r['user_nvr']];
            $unique[$key] = true;
        }
    }

    if(!empty($r['user_projector'])){
        $key = 'Projector-'.$r['user_projector'];
        if(!isset($unique[$key])){
            $sharedData[] = ['type'=>'Projector','code'=>$r['user_projector']];
            $unique[$key] = true;
        }
    }

    if(!empty($r['user_printer'])){
        $key = 'Printer-'.$r['user_printer'];
        if(!isset($unique[$key])){
            $sharedData[] = ['type'=>'Printer','code'=>$r['user_printer']];
            $unique[$key] = true;
        }
    }

    if(!empty($r['user_audio_set'])){
        $key = 'Audio-'.$r['user_audio_set'];
        if(!isset($unique[$key])){
            $sharedData[] = ['type'=>'Audio Set','code'=>$r['user_audio_set']];
            $unique[$key] = true;
        }
    }

    if(!empty($r['user_plotter'])){
        $key = 'Plotter-'.$r['user_plotter'];
        if(!isset($unique[$key])){
            $sharedData[] = ['type'=>'Plotter','code'=>$r['user_plotter']];
            $unique[$key] = true;
        }
    }

    if(!empty($r['user_Accessories_IT'])){
        $key = 'Accessories-'.$r['user_Accessories_IT'];
        if(!isset($unique[$key])){
            $sharedData[] = ['type'=>'Accessories IT','code'=>$r['user_Accessories_IT']];
            $unique[$key] = true;
        }
    }

    if(!empty($r['user_Drone'])){
        $key = 'Drone-'.$r['user_Drone'];
        if(!isset($unique[$key])){
            $sharedData[] = ['type'=>'Drone','code'=>$r['user_Drone']];
            $unique[$key] = true;
        }
    }

    if(!empty($r['user_Optical_Fiber'])){
        $key = 'Fiber-'.$r['user_Optical_Fiber'];
        if(!isset($unique[$key])){
            $sharedData[] = ['type'=>'Optical Fiber','code'=>$r['user_Optical_Fiber']];
            $unique[$key] = true;
        }
    }

    if(!empty($r['user_Server'])){
        $key = 'Server-'.$r['user_Server'];
        if(!isset($unique[$key])){
            $sharedData[] = ['type'=>'Server','code'=>$r['user_Server']];
            $unique[$key] = true;
        }
    }




/* ================= แสดงผลในตาราง ================= */

foreach($sharedData as $s):

?>

<tr>

<td class="text-center"><?= $j++ ?></td>

<td>
<?= $s['type'] ?>
</td>

<td class="fw-bold text-primary">
<?= $s['code'] ?>
</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>


</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>