<?php
// public/workload_add.php
require_once '../config/app.php';
require_once '../middleware/require_login.php'; 
require_once '../config/db.php'; 

$area = $_GET['area'] ?? 1;
$term_id = $_GET['term_id'] ?? 1;
$is_edit = false;

// ----------------------------------------------------
// (Rule 4) (แก้ไข) ตรวจสอบก่อนสร้าง Token
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // ถ้าเป็นการโหลดหน้า (GET) ให้สร้าง Token ใหม่
    $csrf_token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrf_token;
} else {
    // ถ้าเป็นการส่ง (POST) ให้ใช้ Token เดิมจาก Session
    // (เผื่อกรณี validation fail แล้วต้องแสดงฟอร์มซ้ำ)
    $csrf_token = $_SESSION['csrf_token'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เพิ่มภาระงาน | MedUI System</title>
    <link rel="stylesheet" href="../medui/medui.css">
    <link rel="stylesheet" href="../medui/medui.components.css">
    <link rel="stylesheet" href="../medui/medui.layout.css">
    <link rel="stylesheet" href="../medui/medui.theme.medical.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>

<div class="app">

    <?php include_once '../inc/nav.php'; ?>

   <style>
    /* 1. บังคับโครงสร้างหลักให้ขยายเต็มจอ 100% */
    .app, .main, .container, main {
        max-width: 100vw !important; /* กว้างเต็มความกว้างหน้าจอ */
        width: 100% !important;
        margin: 0 !important;
        padding-left: 5px !important;  /* เว้นขอบนิดเดียวพอ */
        padding-right: 5px !important;
    }

    /* 2. ขยายการ์ดฟอร์มให้เต็มพื้นที่ */
    .card {
        max-width: 100% !important;
        width: 100% !important;
        margin: 10px 0 !important;
        padding: 25px !important; /* เพิ่มพื้นที่ภายในการ์ด */
        border-radius: 8px;
    }

    /* 3. เพิ่มขนาดตัวหนังสือให้ใหญ่อ่านง่าย */
    body {
        font-size: 16px !important; /* ค่าเริ่มต้นใหญ่ขึ้น */
    }

    /* 4. ขยายช่องกรอก (Input) ให้ใหญ่และกดง่าย */
    input[type="text"], 
    input[type="number"], 
    input[type="date"], 
    input[type="url"], 
    select, 
    textarea,
    .input {
        font-size: 1.15rem !important; /* ตัวหนังสือในช่องใหญ่ */
        padding: 12px 15px !important; /* พื้นที่กดกว้างขึ้น */
        height: auto !important;
    }

    /* 5. ขยายหัวข้อ (Label) */
    label {
        font-size: 1.2rem !important;
        font-weight: bold !important;
        margin-bottom: 10px !important;
        display: block;
    }

    /* 6. ขยายตาราง */
    .table th, .table td {
        font-size: 1.1rem !important;
        padding: 15px !important;
    }

    /* 7. ปรับปุ่มกดให้ใหญ่สะใจ */
    .btn {
        font-size: 1.2rem !important;
        padding: 12px 30px !important;
    }
    
    /* 8. จัด Grid ให้ห่างกันหน่อยเพื่อความสบายตา */
    .grid {
        gap: 30px !important;
    }
</style>

<div class="card">
    
    </div>

    <div class="app-content">

        <header class="topbar">
            <div class="container">
                <div class="topbar-content">
                    <div class="topbar-left">
                        <h3 style="margin:0;">เพิ่มภาระงาน</h3>
                    </div>
                    <div class="topbar-right">
                         <a href="logout.php" class="btn btn-sm btn-outline-danger">
                             <i class="bi bi-box-arrow-right"></i> ออกจากระบบ
                         </a>
                    </div>
                </div>
            </div>
        </header>

        <main class="main">
            <div class="container" style="max-width:1100px;">
                <?php
                // (แก้ไข Logic ฟอร์มให้ถูกต้อง)
                switch ($area) {
                    case 1:
                        include '../forms/form_teaching.php';
                        break;
                    case 2:
                        include '../forms/form_research.php';
                        break;
                    case 3:
                        include '../forms/form_service.php';
                        break;
                    case 4: // Area 4 คือ ทำนุบำรุงฯ (Culture)
                        include '../forms/form_culture.php';;
                        break;
                    case 5: // Area 5   บริหาร (manager)
                        include '../forms/form_management.php';
                        break;
                    case 6:
                        include '../forms/form_other.php';
                        break;
                    default:
                        echo "<div class='card p-6'><p>ไม่พบฟอร์มสำหรับด้านนี้</p></div>";
                        break;
                }
                ?>
            </div>
        </main>
        
    </div> </div> </body>
</html>