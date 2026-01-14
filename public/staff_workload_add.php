<?php
// public/staff_workload_add.php

// 1. Start Buffer
ob_start();

require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// 2. ตรวจสอบสิทธิ์ (Staff Only)
if ($user['role'] !== 'staff') {
    header("Location: index.php");
    exit;
}

// 3. รับค่าจาก URL (ปรับให้รองรับชื่อตัวแปรใหม่จากหน้า Select)
$area = $_GET['category_id'] ?? $_GET['area'] ?? 0; // รับทั้ง category_id และ area เพื่อความชัวร์
$term_id = $_GET['term_id'] ?? 3; // Default เป็น 3 (ตลอดปี)
$academic_year = $_GET['academic_year'] ?? (date('Y')+543); // รับปีงบประมาณ

// ถ้ายังไม่เลือกด้าน ให้เด้งกลับไปหน้าเลือก
if (!$area) {
    header("Location: staff_workload_select.php");
    exit;
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ชื่อด้าน (Mapping)
$areaNames = [
    1 => "ภาระงานหลัก / งานประจำ (Routine)",
    2 => "งานพัฒนางาน (Development)",
    3 => "งานเชิงกลยุทธ์ (Strategy)",
    4 => "งานที่ได้รับมอบหมาย (Assigned)",
    5 => "กิจกรรมมหาวิทยาลัย (Activity)",
    6 => "ภาระงานบริหาร (Admin)"
];
$currentAreaName = $areaNames[$area] ?? 'ไม่ทราบด้าน';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>บันทึกงาน (สายสนับสนุน)</title>
    <link rel="stylesheet" href="../medui/medui.css">
    <link rel="stylesheet" href="../medui/medui.components.css">
    <link rel="stylesheet" href="../medui/medui.layout.css">
    <link rel="stylesheet" href="../medui/medui.theme.medical.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    
    <style>
        /* 1. บังคับเต็มจอ 100% */
        .app, .main, .container, main, .app-content {
            max-width: 100vw !important; width: 100% !important;
            margin: 0 !important; padding-left: 10px !important; padding-right: 10px !important;
        }
        
        /* 2. ฟอนต์พื้นฐาน */
        body { font-size: 16px !important; }

        /* 3. Topbar ปรับแต่ง */
        .topbar { border-bottom: 3px solid #6366f1; } /* สีม่วงธีม Staff */
        
        /* 4. การ์ด (บังคับ Style ให้ฟอร์มลูกที่ Include เข้ามา) */
        .card {
            max-width: 100% !important;
            margin: 15px 0 !important;
            padding: 25px !important;
        }

        /* 5. Input และ ปุ่ม ในฟอร์มลูก */
        input, select, textarea { font-size: 1.2rem !important; padding: 12px !important; }
        .btn { font-size: 1.1rem !important; padding: 10px 20px !important; }
    </style>
</head>
<body>

<div class="app">
    
    <?php include '../inc/nav.php'; ?>

    <div class="app-content">
        
        <header class="topbar">
            <div class="container">
                <div class="topbar-content stack-between">
                    <div class="topbar-left">
                        <h2 style="margin:0; font-size:2rem; font-weight:bold;">บันทึกการปฏิบัติงาน</h2>
                        <p class="muted" style="margin:5px 0 0; font-size:1.1rem;">
                            <span class="badge bg-light text-dark" style="font-size:1rem;">สายสนับสนุน</span>
                            <span class="text-primary font-bold"><?= htmlspecialchars($currentAreaName) ?></span>
                            <span class="badge bg-purple text-white ml-2" style="background-color:#6366f1;">ปีงบประมาณ <?= $academic_year ?></span>
                        </p>
                    </div>
                    <div class="topbar-right">
                         <a href="staff_workload_select.php" class="btn btn-muted btn-lg">
                             <i class="bi bi-grid"></i> เลือกหมวดอื่น
                         </a>
                    </div>
                </div>
            </div>
        </header>

        <main class="main">
            <div class="container">
                
                <?php include '../inc/alert.php'; ?>

                <?php
                //  ใช้ Switch Case เดิม
                switch ($area) {
                    case 1: include '../forms/staff/form_staff_routine.php'; break;
                    case 2: include '../forms/staff/form_staff_development.php'; break;
                    case 3: include '../forms/staff/form_staff_strategy.php'; break;
                    case 4: include '../forms/staff/form_staff_assigned.php'; break;
                    case 5: include '../forms/staff/form_staff_activity.php'; break;
                    case 6: include '../forms/staff/form_staff_admin.php'; break;
                    default:
                        echo "<div class='card p-6 text-center text-danger'>
                                <i class='bi bi-exclamation-circle' style='font-size:3rem;'></i><br>
                                <h3>ไม่พบแบบฟอร์มสำหรับด้านนี้</h3>
                                <p>Area ID: $area</p>
                              </div>";
                }
                ?>
                
            </div>
        </main>
    </div>
</div>

</body>
</html>
<?php
ob_end_flush();
?>