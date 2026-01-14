<?php
// public/admin_user_stats_print.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// 1. สงวนสิทธิ์
if (!in_array($user['role'], ['admin', 'manager'])) {
    die("Access Denied");
}

// 2. รับค่า
$uid = $_GET['uid'] ?? 0;
$year = $_GET['year'] ?? '';

if (!$uid) die("ไม่พบข้อมูลผู้ใช้");

// 3. ดึงข้อมูล
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$targetUser = $stmt->get_result()->fetch_assoc();

if (!$targetUser) die("ไม่พบผู้ใช้นี้");

// ปีการศึกษา
if (empty($year)) {
    $qYear = $conn->query("SELECT MAX(academic_year) as y FROM workload_items WHERE user_id = $uid");
    $year = $qYear->fetch_assoc()['y'] ?? (date('Y') + 543);
}

// 4. คำนวณสถิติ
$hours = [1=>0, 2=>0, 3=>0, 4=>0, 5=>0, 6=>0];
$mainAreaNames = [
    1 => "ด้านการสอน", 2 => "วิจัย/วิชาการ", 3 => "บริการวิชาการ",
    4 => "ทำนุบำรุงศิลปวัฒนธรรม", 5 => "บริหาร", 6 => "ภาระงานอื่น ๆ"
];

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
    $hours[$r['main_area']] = floatval($r['total']);
}

$totalHours = array_sum($hours);
$goal = 1330;
$percent = ($totalHours / $goal) * 100;

// กำหนดสีและข้อความสถานะ
if ($totalHours >= $goal) {
    $statusText = "ผ่านเกณฑ์มาตรฐาน (ดีมาก)";
    $themeColor = "#10b981"; // เขียว
    $bgColor = "#d1fae5";
} elseif ($totalHours >= $goal * 0.8) {
    $statusText = "ใกล้ถึงเกณฑ์ (ควรเพิ่มผลงาน)";
    $themeColor = "#f59e0b"; // ส้ม
    $bgColor = "#fef3c7";
} else {
    $statusText = "ต่ำกว่าเกณฑ์ (เสี่ยงไม่ผ่าน)";
    $themeColor = "#ef4444"; // แดง
    $bgColor = "#fee2e2";
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานผลการปฏิบัติงาน | <?= htmlspecialchars($targetUser['name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        
        body { 
            font-family: "Sarabun", sans-serif; 
            font-size: 14pt; 
            line-height: 1.4;
            color: #333;
            -webkit-print-color-adjust: exact; /* บังคับพิมพ์สี */
            print-color-adjust: exact;
            padding: 10mm;
        }

        .no-print { background: #f8f9fa; padding: 15px; text-align: center; margin-bottom: 20px; border-bottom:1px solid #ddd; margin: -10mm -10mm 20px -10mm; }
        .btn { background: #007bff; color: #fff; border: none; padding: 8px 20px; cursor: pointer; font-weight: bold; border-radius: 4px; font-size: 14px; }
        
        /* Header */
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #ddd; padding-bottom: 20px; }
        .header h1 { margin: 0; font-size: 20pt; }
        .header h2 { margin: 5px 0 0; font-size: 16pt; font-weight: normal; }

        /* User Card */
        .user-card { 
            display: flex; justify-content: space-between; 
            padding: 15px 20px; background: #f8f9fa; 
            border-radius: 8px; border: 1px solid #ddd;
            margin-bottom: 25px;
        }

        /* 📊 Stats Boxes (3 กล่องสี) */
        .stats-grid { display: flex; gap: 15px; margin-bottom: 25px; }
        .stat-box { 
            flex: 1; 
            padding: 20px; 
            border-radius: 12px; 
            border: 1px solid #e5e7eb; 
            text-align: center;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .stat-value { font-size: 24pt; font-weight: bold; margin-bottom: 5px; line-height: 1; }
        .stat-label { font-size: 12pt; color: #666; }
        
        /* กล่องสถานะ (สีตามเกณฑ์) */
        .stat-box.status-box {
            background-color: <?= $bgColor ?>;
            border-color: <?= $themeColor ?>;
            color: <?= $themeColor ?>;
        }
        .stat-box.status-box .stat-label { color: <?= $themeColor ?>; opacity: 0.9; font-weight: bold; }

        /* 💈 Progress Bar */
        .progress-container { margin-bottom: 30px; }
        .progress-track { background: #e5e7eb; height: 20px; border-radius: 10px; overflow: hidden; border: 1px solid #d1d5db; }
        .progress-fill { height: 100%; background-color: <?= $themeColor ?>; }
        
        /* Table & Visual Bar */
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 10px 5px; vertical-align: middle; border-bottom: 1px solid #eee; }
        th { text-align: left; font-weight: bold; border-bottom: 2px solid #333; }
        
        .visual-bar-track { background: #f3f4f6; height: 10px; border-radius: 5px; width: 100%; overflow: hidden; }
        .visual-bar-fill { height: 100%; background: #6b7280; } /* สีเทาเข้ม */

        /* Signature */
        .signature-section { margin-top: 60px; display: flex; justify-content: space-between; page-break-inside: avoid; }
        .sign-box { width: 45%; text-align: center; }
        .sign-line { border-bottom: 1px dotted #999; width: 80%; margin: 40px auto 10px auto; }

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
    <h1>รายงานสรุปผลการปฏิบัติงานรายบุคคล</h1>
    <h2>คณะแพทยศาสตร์ มหาวิทยาลัยวงษ์ชวลิตกุล</h2>
</div>

<div class="user-card">
    <div>
        <div style="font-size:16pt; font-weight:bold;"><?= htmlspecialchars($targetUser['name']) ?></div>
        <div style="color:#666;">ตำแหน่ง: อาจารย์ / บุคลากร</div>
    </div>
    <div style="text-align:right;">
        <div style="font-size:16pt; font-weight:bold;">ปีการศึกษา <?= htmlspecialchars($year) ?></div>
        <div style="color:#666;">วันที่พิมพ์: <?= date("d/m/Y") ?></div>
    </div>
</div>



<div class="progress-container">
    <div style="display:flex; justify-content:space-between; margin-bottom:5px; font-size:12pt;">
        <strong>ความสำเร็จตามเกณฑ์</strong>
        <strong style="color:<?= $themeColor ?>"><?= number_format($percent, 1) ?>%</strong>
    </div>
    <div class="progress-track">
        <div class="progress-fill" style="width: <?= min(100, $percent) ?>%;"></div>
    </div>
</div>

<h3 style="margin-bottom:15px; border-left:5px solid <?= $themeColor ?>; padding-left:10px;">รายละเอียดแยกตามพันธกิจ</h3>
<table>
    <thead>
        <tr>
            <th style="width: 50%;">ด้านภาระงาน</th>
            <th style="width: 30%;">สัดส่วน (เทียบกับงานทั้งหมด)</th>
            <th style="width: 20%;" class="text-right">ชั่วโมง</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($mainAreaNames as $id => $name): 
            $val = $hours[$id];
            // คำนวณความยาวหลอดเล็กๆ (เทียบกับยอดรวมของตัวเอง)
            $barWidth = $totalHours > 0 ? ($val / $totalHours) * 100 : 0;
        ?>
        <tr>
            <td>
                <strong><?= $id ?>. <?= $name ?></strong>
            </td>
            <td style="padding-right: 30px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <div class="visual-bar-track">
                        <div class="visual-bar-fill" style="width:<?= $barWidth ?>%; background-color: <?= $themeColor ?>;"></div>
                    </div>
                    <span style="font-size:0.8em; color:#666; min-width:30px;"><?= number_format($barWidth, 0) ?>%</span>
                </div>
            </td>
            <td class="text-right" style="font-size:14pt; font-weight:bold;">
                <?= number_format($val, 2) ?>
            </td>
        </tr>
        <?php endforeach; ?>
        
        <tr style="background-color: #f8f9fa; border-top:2px solid #333;">
            <td colspan="2" style="text-align:right; font-size:16pt; font-weight:bold; padding-right:20px;">
                รวมภาระงานสุทธิ
            </td>
            <td class="text-right" style="font-size:16pt; font-weight:bold; color:<?= $themeColor ?>;">
                <?= number_format($totalHours, 2) ?>
            </td>
        </tr>
    </tbody>
</table>

<div class="signature-section">
    <div class="sign-box">
        ขอรับรองว่าข้อมูลถูกต้อง
        <div class="sign-line"></div>
        (<?= htmlspecialchars($targetUser['name']) ?>)<br>
        ผู้รับการประเมิน
    </div>
    <div class="sign-box">
        ทราบและตรวจสอบแล้ว
        <div class="sign-line"></div>
        (..........................................................)<br>
        หัวหน้าสาขาวิชา / ผู้บังคับบัญชา
    </div>
</div>

</body>
</html>