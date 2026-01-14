<?php
// public/print_stats.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// 1. ใช้ข้อมูลของคนล็อกอิน (User/Staff)
$uid = $user['id'];
$role = $user['role'];

// 2. รับค่าปีการศึกษา
$year = $_GET['year'] ?? '';

// หาปีล่าสุดถ้าไม่ได้เลือก
if (empty($year)) {
    $qYear = $conn->prepare("SELECT MAX(academic_year) as y FROM workload_items WHERE user_id = ?");
    $qYear->bind_param("i", $uid);
    $qYear->execute();
    $year = $qYear->get_result()->fetch_assoc()['y'] ?? (date('Y') + 543);
}

// 3. กำหนดค่าตาม Role (Config)
if ($role === 'staff') {
    // --- สายสนับสนุน ---
    $GOAL_YEAR = 1645;
    $reportTitle = "แบบรายงานสรุปภาระงาน (สายสนับสนุน)";
    $positionLabel = "เจ้าหน้าที่ / สายสนับสนุน";
    $mainAreaNames = [
        1 => "ภาระงานหลัก/งานประจำ", 2 => "งานพัฒนางาน", 3 => "งานยุทธศาสตร์",
        4 => "งานมอบหมาย", 5 => "กิจกรรม ม.", 6 => "งานบริหาร"
    ];
} else {
    // --- อาจารย์ ---
    $GOAL_YEAR = 1330;
    $reportTitle = "แบบรายงานสรุปภาระงาน (สายวิชาการ)";
    $positionLabel = "อาจารย์ / สายวิชาการ";
    $mainAreaNames = [
        1 => "ด้านการสอน", 2 => "วิจัย/วิชาการ", 3 => "บริการวิชาการ",
        4 => "ทำนุบำรุงศิลปฯ", 5 => "ด้านบริหาร", 6 => "ภาระงานอื่น ๆ"
    ];
}

// 4. ดึงข้อมูลและคำนวณ
$hours = array_fill(1, 6, 0); // เตรียม Array ว่าง 1-6

$sql = "
    SELECT wc.main_area, SUM(wi.computed_hours) AS total
    FROM workload_items wi
    LEFT JOIN workload_categories wc ON wc.id = wi.category_id
    WHERE wi.user_id = ? AND wi.academic_year = ?
    GROUP BY wc.main_area
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $uid, $year);
$stmt->execute();
$res = $stmt->get_result();

while ($r = $res->fetch_assoc()) {
    $hours[(int)$r['main_area']] = floatval($r['total']);
}

$totalHours = array_sum($hours);
$percent = ($totalHours > 0) ? ($totalHours / $GOAL_YEAR) * 100 : 0;

// 5. ประเมินผล (กำหนดสี)
if ($totalHours >= $GOAL_YEAR) {
    $statusText = "ผ่านเกณฑ์มาตรฐาน (ดีมาก)";
    $themeColor = "#10b981"; // เขียว
    $bgColor = "#d1fae5";
} elseif ($totalHours >= $GOAL_YEAR * 0.8) {
    $statusText = "ใกล้ถึงเกณฑ์ (ควรเพิ่มผลงาน)";
    $themeColor = "#f59e0b"; // ส้ม
    $bgColor = "#fef3c7";
} else {
    $statusText = "ต่ำกว่าเกณฑ์ (ต้องปรับปรุง)";
    $themeColor = "#ef4444"; // แดง
    $bgColor = "#fee2e2";
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานสรุป | <?= htmlspecialchars($user['name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        
        body { 
            font-family: "Sarabun", sans-serif; 
            font-size: 14pt; 
            line-height: 1.4;
            color: #333;
            margin: 0; padding: 10mm;
            /* บังคับพิมพ์สีพื้นหลัง */
            -webkit-print-color-adjust: exact; 
            print-color-adjust: exact;
        }

        /* Control Panel */
        .no-print { 
            background: #f8f9fa; padding: 15px; text-align: center; 
            border-bottom: 1px solid #ddd; margin: -10mm -10mm 20px -10mm; 
        }
        .btn { 
            background: #007bff; color: #fff; border: none; 
            padding: 8px 20px; cursor: pointer; font-weight: bold; border-radius: 4px; font-size: 14px; 
        }

        /* Header */
        .header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #eee; padding-bottom: 20px; }
        .header h1 { font-size: 20pt; font-weight: bold; margin: 0; }
        .header h2 { font-size: 16pt; margin: 5px 0 0; font-weight: normal; }

        /* User Info Card */
        .user-card { 
            display: flex; justify-content: space-between; 
            padding: 15px 20px; background: #f8f9fa; 
            border-radius: 8px; border: 1px solid #ddd;
            margin-bottom: 25px;
        }
        .font-bold { font-weight: bold; }
        .text-muted { color: #666; font-size: 0.9em; }

        /* 📊 Stats Boxes (3 กล่องเหมือนหน้าจอ) */
        .stats-grid { display: flex; gap: 15px; margin-bottom: 25px; }
        .stat-box { 
            flex: 1; padding: 20px; border-radius: 12px; 
            border: 1px solid #e5e7eb; text-align: center; background: #fff;
        }
        .stat-value { font-size: 22pt; font-weight: bold; margin-bottom: 5px; line-height: 1; color: #333; }
        .stat-label { font-size: 12pt; color: #666; }
        
        /* กล่องสถานะ (มีสี) */
        .stat-box.status-box {
            background-color: <?= $bgColor ?>;
            border-color: <?= $themeColor ?>;
        }
        .stat-box.status-box .stat-value { color: <?= $themeColor ?>; }
        .stat-box.status-box .stat-label { color: <?= $themeColor ?>; font-weight:bold; }

        /* 💈 Progress Bar */
        .progress-section { margin-bottom: 30px; }
        .progress-track { background: #e5e7eb; height: 20px; border-radius: 10px; overflow: hidden; border: 1px solid #d1d5db; }
        .progress-fill { height: 100%; background-color: <?= $themeColor ?>; }

        /* Table */
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 10px 5px; vertical-align: middle; border-bottom: 1px solid #eee; }
        th { text-align: left; font-weight: bold; border-bottom: 2px solid #333; font-size: 13pt; }
        
        /* Visual Bar in Table */
        .visual-bar-track { background: #f3f4f6; height: 8px; border-radius: 4px; width: 100%; overflow: hidden; }
        .visual-bar-fill { height: 100%; background: #64748b; }

        /* Signature */
        .signature-section { margin-top: 60px; display: flex; justify-content: space-between; page-break-inside: avoid; }
        .sign-box { width: 45%; text-align: center; font-size: 12pt; }
        .sign-line { border-bottom: 1px dotted #999; width: 90%; margin: 40px auto 10px auto; }

        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()" class="btn">🖨 สั่งพิมพ์ / PDF</button>
</div>

<div class="header">
    <h1><?= htmlspecialchars($reportTitle) ?></h1>
    <h2>คณะแพทยศาสตร์ มหาลัยวงษ์ชวลิตกุล</h2>
</div>

<div class="user-card">
    <div>
        <div class="font-bold" style="font-size:16pt;"><?= htmlspecialchars($user['name']) ?></div>
        <div class="text-muted">ตำแหน่ง: <?= htmlspecialchars($positionLabel) ?></div>
    </div>
    <div style="text-align:right;">
        <div class="font-bold" style="font-size:16pt;">ปีการศึกษา <?= htmlspecialchars($year) ?></div>
        <div class="text-muted">วันที่พิมพ์: <?= date("d/m/Y") ?></div>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-box">
        <div class="stat-value"><?= number_format($totalHours, 2) ?></div>
        <div class="stat-label">ชั่วโมงสะสมจริง</div>
    </div>
    <div class="stat-box">
        <div class="stat-value"><?= number_format($GOAL_YEAR) ?></div>
        <div class="stat-label">เกณฑ์ขั้นต่ำ (ต่อปี)</div>
    </div>
    <div class="stat-box status-box">
        <div class="stat-value"><?= number_format($percent, 1) ?>%</div>
        <div class="stat-label"><?= $statusText ?></div>
    </div>
</div>

<div class="progress-section">
    <div style="display:flex; justify-content:space-between; margin-bottom:5px; font-size:12pt;">
        <strong>ความสำเร็จตามเกณฑ์</strong>
        <strong style="color:<?= $themeColor ?>"><?= number_format($percent, 1) ?>%</strong>
    </div>
    <div class="progress-track">
        <div class="progress-fill" style="width: <?= min(100, $percent) ?>%;"></div>
    </div>
</div>

<h3 style="margin-bottom:15px; border-left:5px solid <?= $themeColor ?>; padding-left:10px;">สรุปรายละเอียดแยกรายด้าน</h3>
<table>
    <thead>
        <tr>
            <th style="width: 50%;">ด้านภาระงาน</th>
            <th style="width: 30%;">สัดส่วน (Visual)</th>
            <th style="width: 20%; text-align:right;">ชั่วโมง</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($mainAreaNames as $id => $name): 
            $val = $hours[$id];
            $barWidth = $totalHours > 0 ? ($val / $totalHours) * 100 : 0;
        ?>
        <tr>
            <td>
                <strong><?= $id ?>. <?= $name ?></strong>
            </td>
            <td style="padding-right: 30px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <div class="visual-bar-track">
                        <div class="visual-bar-fill" style="width:<?= $barWidth ?>%; background-color:<?= $themeColor ?>;"></div>
                    </div>
                    <span style="font-size:0.8em; color:#666; min-width:30px;"><?= number_format($barWidth, 0) ?>%</span>
                </div>
            </td>
            <td style="text-align:right; font-weight:bold; font-size:14pt;">
                <?= number_format($val, 2) ?>
            </td>
        </tr>
        <?php endforeach; ?>
        
        <tr style="background-color: #f8f9fa; border-top:2px solid #333;">
            <td colspan="2" style="text-align:right; font-size:16pt; font-weight:bold; padding-right:20px;">
                รวมภาระงานสุทธิ
            </td>
            <td style="text-align:right; font-size:16pt; font-weight:bold; color:<?= $themeColor ?>;">
                <?= number_format($totalHours, 2) ?>
            </td>
        </tr>
    </tbody>
</table>

<div class="signature-section">
    <div class="sign-box">
        ขอรับรองว่าข้อมูลถูกต้อง
        <div class="sign-line"></div>
        (<?= htmlspecialchars($user['name']) ?>)<br>
        ผู้รับการประเมิน
    </div>
    <div class="sign-box">
        ทราบและตรวจสอบแล้ว
        <div class="sign-line"></div>
        (..........................................................)<br>
        หัวหน้าสาขาวิชา / ผู้อำนวยการ<br>
    </div>
</div>

</body>
</html>