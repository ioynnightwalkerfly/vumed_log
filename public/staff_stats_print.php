<?php
// public/staff_stats_print.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// 1. ตรวจสอบสิทธิ์ (Staff Only)
if ($user['role'] !== 'staff') {
    die("Access Denied");
}

// 2. รับค่าปีการศึกษา
$year = $_GET['year'] ?? '';

// ถ้าไม่เลือกปี ให้หาปีล่าสุดของตัวเอง
if (empty($year)) {
    $qYear = $conn->query("SELECT MAX(academic_year) as y FROM workload_items WHERE user_id = {$user['id']}");
    $year = $qYear->fetch_assoc()['y'] ?? (date('Y') + 543);
}

// 3. Config เกณฑ์
$GOAL_YEAR = 1645; // เกณฑ์สายสนับสนุน
$hours = [1=>0, 2=>0, 3=>0, 4=>0, 5=>0, 6=>0];
$mainAreaNames = [
    1 => "งานประจำ (Routine)", 
    2 => "งานพัฒนางาน", 
    3 => "บริการวิชาการ",
    4 => "ทำนุบำรุงศิลปฯ", 
    5 => "งานกิจกรรม ม.", 
    6 => "งานบริหาร"
];

// 4. สรุปชั่วโมงตามด้าน
$sql = "
    SELECT wc.main_area, SUM(wi.computed_hours) AS total
    FROM workload_items wi
    LEFT JOIN workload_categories wc ON wc.id = wi.category_id
    WHERE wi.user_id = ? AND wi.academic_year = ?
    GROUP BY wc.main_area
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $user['id'], $year);
$stmt->execute();
$res = $stmt->get_result();

while ($r = $res->fetch_assoc()) {
    $hours[$r['main_area']] = floatval($r['total']);
}

$totalHours = array_sum($hours);
$percent = ($totalHours / $GOAL_YEAR) * 100;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานผลการปฏิบัติงาน | <?= htmlspecialchars($user['name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        
        body { 
            font-family: "Sarabun", sans-serif; 
            font-size: 14pt; 
            color: #333;
            line-height: 1.4;
            padding: 10mm;
            margin: 0;
            -webkit-print-color-adjust: exact; 
            print-color-adjust: exact;
        }

        .no-print { background: #f8f9fa; padding: 15px; text-align: center; border-bottom:1px solid #ddd; margin: -10mm -10mm 20px -10mm; }
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
            margin-bottom: 30px;
        }

        /* Table */
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 10px 5px; vertical-align: middle; border-bottom: 1px solid #eee; }
        th { text-align: left; font-weight: bold; border-bottom: 2px solid #333; }
        
        /* Visual Bar */
        .visual-bar-track { background: #f3f4f6; height: 10px; border-radius: 5px; width: 100%; overflow: hidden; }
        .visual-bar-fill { height: 100%; background: #6b7280; } 

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
    <h2>คณะแพทยศาสตร์ คณะแพทยศาสตร์ มหาวิทยาลัยวงษ์ชวลิตกุล</h2>
</div>

<div class="user-card">
    <div>
        <div style="font-size:16pt; font-weight:bold;"><?= htmlspecialchars($user['name']) ?></div>
        <div style="color:#666;">ตำแหน่ง: เจ้าหน้าที่ / สายสนับสนุน</div>
    </div>
    <div style="text-align:right;">
        <div style="font-size:16pt; font-weight:bold;">ปีการศึกษา <?= htmlspecialchars($year) ?></div>
        <div style="color:#666;">วันที่พิมพ์: <?= date("d/m/Y") ?></div>
    </div>
</div>

<h3 style="margin-bottom:15px; padding-left:10px; border-left: 5px solid #333;">รายละเอียดแยกตามพันธกิจ</h3>
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
            $barWidth = $totalHours > 0 ? ($val / $totalHours) * 100 : 0;
        ?>
        <tr>
            <td>
                <strong><?= $id ?>. <?= $name ?></strong>
            </td>
            <td style="padding-right: 30px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <div class="visual-bar-track">
                        <div class="visual-bar-fill" style="width:<?= $barWidth ?>%; background-color: #666;"></div>
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
            <td class="text-right" style="font-size:16pt; font-weight:bold; color:#000;">
                <?= number_format($totalHours, 2) ?>
            </td>
        </tr>
    </tbody>
</table>

<div style="text-align:right; margin-top:10px; color:#666;">
    (เกณฑ์เป้าหมาย: <?= number_format($GOAL_YEAR) ?> ชั่วโมง)
</div>

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
        หัวหน้าสาขาวิชา / ผู้บังคับบัญชา
    </div>
</div>

</body>
</html>