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

/* helper: แปลงค่าที่คั่นด้วย comma เป็นบรรทัดใหม่ */
function formatCommaLines($value) {
    $parts = array_filter(array_map('trim', explode(',', $value ?? '')));
    return $parts ? implode('<br>', $parts) : '-';
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
<th>เครื่องสำรองไฟ</th>
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

        /* แยก NVR ที่คั่นด้วย comma */
        $nvrList = explode(',', $r['user_nvr']);

        foreach($nvrList as $nvr){

            $nvr = trim($nvr);

            $key = 'NVR-'.$nvr;

            /* ตรวจสอบว่าซ้ำหรือไม่ */

            if(!isset($unique[$key])){

                $sharedData[] = [
                    'type' => 'NVR',
                    'code' => $nvr
                ];

                $unique[$key] = true;
            }
        }
     }




    /* ================= Projector ================= */

    if(!empty($r['user_projector'])){

        $code = trim($r['user_projector']);

        $key = 'Projector-'.$code;

            if(!isset($unique[$key])){

                $sharedData[] = [
                    'type' => 'CCTV',
                    'code' => $cctv
                ];

                $unique[$key] = true;
            }
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

        $key = 'Accessories IT-'.$code;

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