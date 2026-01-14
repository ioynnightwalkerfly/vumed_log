<?php
// public/staff_workload_select.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';

// ตรวจสอบสิทธิ์ (Staff Only)
if ($user['role'] !== 'staff') {
    header("Location: index.php");
    exit;
}

// --- สร้างตัวเลือกปีงบประมาณ (ไทย) ---
$currentYear = date("Y") + 543;
// เลือกช่วงปีที่จะแสดง (เช่น ล่วงหน้า 1 ปี, ปีปัจจุบัน, ย้อนหลัง 1 ปี)
$years = [
    $currentYear + 1,
    $currentYear,
    $currentYear - 1
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เลือกประเภทงาน (สายสนับสนุน) | MedUI</title>
    <link rel="stylesheet" href="../medui/medui.css">
    <link rel="stylesheet" href="../medui/medui.components.css">
    <link rel="stylesheet" href="../medui/medui.layout.css">
    <link rel="stylesheet" href="../medui/medui.theme.medical.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        .select-card {
            appearance: none; border: 1px solid #e5e7eb; background: #fff;
            border-radius: 12px; padding: 30px 20px; text-align: center; cursor: pointer;
            transition: all 0.2s; width: 100%; font-family: inherit; display: block;
            height: 100%; /* ให้การ์ดสูงเท่ากันใน grid */
        }
        .select-card:hover {
            border-color: #6366f1; transform: translateY(-4px);
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.15);
        }
        .select-card:active { transform: scale(0.98); }
        
        .icon-box {
            width: 70px; height: 70px; background: #e0e7ff; color: #4f46e5;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 2rem; margin: 0 auto 16px auto;
        }
        .select-card h4 { margin: 0 0 8px 0; font-size: 1.1rem; font-weight: bold; }
        .select-card p { margin: 0; color: #666; font-size: 0.9rem; }
        
        .doc-banner {
            background: #f5f3ff; border: 1px dashed #8b5cf6; border-radius: 12px;
            padding: 20px; margin-bottom: 24px;
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;
        }
        .btn-purple { background-color: #6366f1; color: white; border:none; }
        .btn-purple:hover { background-color: #4f46e5; color: white; }

        .required-select {
            border: 2px solid #6366f1; 
            background-color: #fdfdff;
            font-size: 1.1rem;
            padding: 10px;
            border-radius: 8px;
            width: 100%;
        }
    </style>
</head>
<body>

<div class="app">
    <?php include '../inc/nav.php'; ?>

    <div class="app-content">
        <header class="topbar">
            <div class="container">
                <div class="topbar-content">
                    <div class="topbar-left">
                        <h3 style="margin:0;">เลือกประเภทงาน (สายสนับสนุน)</h3>
                    </div>
                    <div class="topbar-right">
                        <a href="staff_index.php" class="btn btn-muted">ยกเลิก</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="main">
            <div class="container">
                
                <div class="doc-banner">
                    <div style="display:flex; align-items:center; gap:15px;">
                        <div style="font-size: 2.5rem; color: #7c3aed;">
                            <i class="bi bi-file-earmark-richtext-fill"></i>
                        </div>
                        <div>
                            <h4 style="margin:0 0 4px 0; color: #5b21b6;">เกณฑ์ภาระงานสายสนับสนุน</h4>
                            <p class="muted m-0">ศึกษาเกณฑ์การนับชั่วโมงปฏิบัติงานก่อนบันทึกข้อมูล</p>
                        </div>
                    </div>
                    <a href="docs/ภาระงานสายสนับสนุน.pdf" target="_blank" class="btn btn-purple">
                        <i class="bi bi-download"></i> ดาวน์โหลดเกณฑ์ (PDF)
                    </a>
                </div>
                
                <form action="staff_workload_add.php" method="GET">
                    
                    <div class="card p-4 mb-4" style="border-left: 5px solid #6366f1;">
                        <label class="form-label" style="font-weight:bold; font-size:1.1rem; color:#333;">
                            <i class="bi bi-calendar-event text-purple"></i> เลือกปีงบประมาณ <span class="text-danger">*</span>
                        </label>
                        
                        <div style="max-width:350px; margin-top:10px;">
                            <select name="academic_year" class="required-select" required>
                                <?php foreach ($years as $y): ?>
                                    <option value="<?= $y ?>" <?= $y == $currentYear ? 'selected' : '' ?>>
                                        ปีงบประมาณ <?= $y ?> (ตลอดปี)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <input type="hidden" name="term_id" value="3">
                        
                        <p class="text-muted small mt-2">
                            * ระบบจะบันทึกเป็น "ตลอดปีการศึกษา" โดยอัตโนมัติ
                        </p>
                    </div>

                    <h4 class="mb-3">เลือกด้านภาระงานเพื่อเริ่มบันทึก</h4>
                    
                    <div class="grid grid-3" style="gap: 24px;">
                        <button type="submit" name="category_id" value="1" class="select-card">
                            <div class="icon-box"><i class="bi bi-briefcase"></i></div>
                            <h4>1. ภาระงานหลัก/งานประจำ</h4>
                            <p>งานตามตำแหน่ง, งานรูทีน</p>
                        </button>

                        <button type="submit" name="category_id" value="2" class="select-card">
                            <div class="icon-box"><i class="bi bi-graph-up-arrow"></i></div>
                            <h4>2. งานพัฒนางาน</h4>
                            <p>อบรม, พัฒนาตนเอง/องค์กร</p>
                        </button>

                        <button type="submit" name="category_id" value="3" class="select-card">
                            <div class="icon-box"><i class="bi bi-people"></i></div>
                            <h4>3. บริการวิชาการ</h4>
                            <p>แก่สังคม / ชุมชน / หน่วยงาน</p>
                        </button>

                        <button type="submit" name="category_id" value="4" class="select-card">
                            <div class="icon-box"><i class="bi bi-palette"></i></div>
                            <h4>4. ทำนุบำรุงศิลปวัฒนธรรม</h4>
                            <p>ร่วมกิจกรรมประเพณี</p>
                        </button>

                        <button type="submit" name="category_id" value="5" class="select-card">
                            <div class="icon-box"><i class="bi bi-activity"></i></div>
                            <h4>5. ร่วมกิจกรรมมหาวิทยาลัย</h4>
                            <p>งานส่วนรวม / กีฬา / จิตอาสา</p>
                        </button>

                        <button type="submit" name="category_id" value="6" class="select-card">
                            <div class="icon-box"><i class="bi bi-building-gear"></i></div>
                            <h4>6. ภาระงานบริหาร</h4>
                            <p>ผอ.กอง, หัวหน้างาน</p>
                        </button>

                    </div>
                </form>

            </div>
        </main>
    </div>
</div>

</body>
</html>