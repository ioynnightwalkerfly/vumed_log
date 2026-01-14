<?php
// public/staff_print_report.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// 1. ตรวจสิทธิ์
if (!in_array($user['role'], ['staff', 'admin', 'manager'])) {
    die("Access Denied");
}

// 2. ตัวกรอง
$filter_period = $_GET['period'] ?? ''; 

$where_sql = "WHERE wi.user_id = ?";
$params = [$user['id']];
$types = "i";
$show_term_text = "ทั้งหมด";

if (!empty($filter_period) && strpos($filter_period, '/') !== false) {
    list($t, $y) = explode('/', $filter_period);
    $where_sql .= " AND wi.term_id = ? AND wi.academic_year = ?";
    $params[] = $t;
    $params[] = $y; 
    $types .= "is";
    
    $y_th = (int)$y + 543;
    $show_term_text = "ภาคเรียนที่ $t ปีการศึกษา $y_th";
} else {
    $latest = $conn->query("SELECT MAX(academic_year) as y FROM workload_items WHERE user_id = {$user['id']}")->fetch_assoc();
    $y_latest = $latest['y'] ?? date('Y');
    $y_th_latest = $y_latest + 543;
    $show_term_text = "ประจำปีการศึกษา $y_th_latest";
}

// 3. Query ข้อมูล
$sql = "
    SELECT wi.*, wc.main_area, wc.name_th AS category_name, wc.code
    FROM workload_items wi
    LEFT JOIN workload_categories wc ON wi.category_id = wc.id
    $where_sql
    ORDER BY wc.main_area ASC, wi.start_date ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// จัดกลุ่มข้อมูล
$dataByArea = [1=>[], 2=>[], 3=>[], 4=>[], 5=>[], 6=>[]];
$totalHours = 0;

while($row = $result->fetch_assoc()) {
    $area = intval($row['main_area']);
    if (!isset($dataByArea[$area])) $dataByArea[$area] = [];
    $dataByArea[$area][] = $row;
    
    if ($row['status'] !== 'rejected') {
        $totalHours += $row['computed_hours'];
    }
}


$mainAreaNames = [
    1 => "ภาระงานหลัก/งานประจำหน้าที่",
    2 => "งานพัฒนางาน (พัฒนาตนเอง/งาน/องค์กร)",
    3 => "งานยุทธศาสตร์",
    4 => "งานอื่น ๆ ที่ได้รับมอบหมาย",
    5 => "งานมีส่วนร่วมกับกิจกรรมของมหาวิทยาลัย",
    6 => "ภาระงานบริหาร"
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานภาระงานสายสนับสนุน</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        @page { size: A4 portrait; margin: 10mm 15mm; }
        
        body { 
            font-family: "Sarabun", sans-serif; 
            font-size: 14pt; 
            line-height: 1.4; 
            color: #000;
        }

        /* Header */
        .header { text-align: center; margin-bottom: 25px; }
        .header h1 { font-size: 18pt; font-weight: bold; margin: 0; }
        .header h2 { font-size: 16pt; font-weight: bold; margin: 5px 0 0; }
        
        .user-info { 
            margin-bottom: 20px; 
            font-size: 14pt; 
            font-weight: bold; 
            border-bottom: 2px solid #000; 
            padding-bottom: 10px; 
        }

        /* Table Styling */
        .area-section { margin-bottom: 20px; }
        .area-title { 
            font-size: 14pt; font-weight: bold; 
            margin-bottom: 5px; 
            background-color: #eee; 
            padding: 4px 8px;
            display: inline-block;
            border-radius: 4px;
        }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; table-layout: fixed; }
        th, td { 
            border: 1px solid #000; padding: 5px 8px; 
            vertical-align: top; font-size: 12pt;
            word-wrap: break-word; overflow-wrap: break-word;
        }
        th { background-color: #f0f0f0; text-align: center; font-weight: bold; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }

        a.pdf-link { color: blue; text-decoration: underline; font-size: 0.85em; word-break: break-all; display: block; margin-top: 4px; }

        /* Signature */
        .signature-section { margin-top: 40px; display: flex; justify-content: space-between; page-break-inside: avoid; }
        .sign-box { width: 45%; text-align: center; font-size: 12pt; }
        .sign-line { border-bottom: 1px dotted #000; width: 80%; margin: 25px auto 5px auto; height: 10px; }

        @media print {
            .no-print { display: none; }
            thead { display: table-header-group; } 
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

<div class="no-print" style="text-align:center; padding:15px; background:#f8f9fa; border-bottom:1px solid #ddd; margin-bottom:20px;">
    <button onclick="window.print()" style="padding:8px 20px; background:#0d6efd; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">🖨 พิมพ์รายงาน / PDF</button>
</div>

<div class="header">
    <h1>แบบรายงานภาระงานบุคลากรสายสนับสนุน</h1>
    <h2>คณะแพทยศาสตร์ มหาวิทยาลัยวงษ์ชวลิตกุล</h2>
</div>

<div class="user-info">
    <div style="display:flex; justify-content:space-between;">
        <span>ชื่อ-สกุล: <?= htmlspecialchars($user['name']) ?></span>
        <span>ตำแหน่ง: เจ้าหน้าที่ / สายสนับสนุน</span>
    </div>
    <div style="margin-top:5px; font-weight:normal;">
        รอบการประเมิน: <strong><?= htmlspecialchars($show_term_text) ?></strong>
    </div>
</div>

<?php 
$hasData = false;

// วนลูปแสดงทีละด้าน (ตอนนี้ตัวแปร $mainAreaNames มีค่าแล้ว ไม่ Error แน่นอน)
foreach ($mainAreaNames as $areaId => $areaName): 
    if (empty($dataByArea[$areaId])) continue;
    $hasData = true;
    $subTotal = 0;
    
    // กำหนดหัวตารางตามบริบท Staff
    $col1 = "รายการ / กิจกรรม";
    $col2 = "รายละเอียด / ผลลัพธ์";
    
    switch ($areaId) {
        case 1: // งานประจำ
            $col1 = "ชื่องาน / กิจกรรม"; $col2 = "ผลสำเร็จของงาน"; break;
        case 2: // พัฒนางาน
            $col1 = "หัวข้ออบรม / ชื่องาน"; $col2 = "หน่วยงานที่จัด / รูปแบบ"; break;
        case 3: // ยุทธศาสตร์
            $col1 = "ชื่อโครงการ (KPI)"; $col2 = "บทบาท / ผลสำเร็จ"; break;
        case 4: // งานมอบหมาย
            $col1 = "ชื่องานที่ได้รับมอบหมาย"; $col2 = "รายละเอียด / คำสั่ง"; break;
        case 5: // กิจกรรม ม.
            $col1 = "ชื่อกิจกรรม"; $col2 = "สถานที่ / รายละเอียด"; break;
        case 6: // บริหาร
            $col1 = "ตำแหน่งบริหาร"; $col2 = "หน่วยงาน / เลขที่คำสั่ง"; break;
    }
?>

<div class="area-section">
    <div class="area-title"><?= $areaId ?>. <?= $areaName ?></div>
    
    <table>
        <colgroup>
            <col style="width: 35%;"> <col style="width: 35%;"> <col style="width: 20%;"> <col style="width: 10%;"> </colgroup>
        <thead>
            <tr>
                <th><?= $col1 ?></th>
                <th><?= $col2 ?></th>
                <th>วัน/เวลา</th>
                <th>ภาระงาน (ชม.)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($dataByArea[$areaId] as $item): 
                if($item['status'] !== 'rejected') $subTotal += $item['computed_hours'];
            ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($item['title']) ?></strong>
                    <div style="font-size:0.85em; color:#666; margin-top:2px;">
                        (<?= htmlspecialchars($item['category_name']) ?>)
                    </div>
                </td>
                <td>
                    <?= nl2br(htmlspecialchars($item['description'] ?? '-')) ?>
                    
                    <?php 
                    if (!empty($item['attachment_link'])): 
                        $url = $item['attachment_link'];
                        if (!preg_match("~^(?:f|ht)tps?://~i", $url)) $url = "http://" . $url;
                    ?>
                        <a href="<?= htmlspecialchars($url) ?>" target="_blank" class="pdf-link">
                            <?= htmlspecialchars($url) ?>
                        </a>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php 
                        // แสดงวันที่
                        echo date("d/m/y", strtotime($item['start_date']));
                        if($item['start_date'] != $item['end_date']) {
                            echo "<br>-<br>" . date("d/m/y", strtotime($item['end_date']));
                        }
                    ?>
                </td>
                <td class="text-right">
                    <?php if ($item['status'] == 'rejected'): ?>
                        <span style="text-decoration:line-through; color:#999;"><?= number_format($item['computed_hours'], 2) ?></span>
                    <?php else: ?>
                        <?= number_format($item['computed_hours'], 2) ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <tr style="background-color: #f9f9f9; font-weight:bold;">
                <td colspan="3" class="text-right">รวมด้านที่ <?= $areaId ?></td>
                <td class="text-right"><?= number_format($subTotal, 2) ?></td>
            </tr>
        </tbody>
    </table>
</div>

<?php endforeach; ?>

<?php if (!$hasData): ?>
    <div style="text-align:center; padding:50px; border:1px dashed #ccc;">
        ไม่พบข้อมูลการปฏิบัติงานในช่วงเวลานี้
    </div>
<?php endif; ?>

<?php if ($hasData): ?>
<div style="margin-top:10px; border-top:2px solid #000; border-bottom:2px solid #000; padding:5px 0;">
    <table style="margin:0; border:none;">
        <tr style="border:none;">
            <td style="border:none; text-align:right; font-size:14pt; font-weight:bold; width:85%;">
                รวมภาระงานสุทธิทั้งสิ้น
            </td>
            <td style="border:none; text-align:right; font-size:14pt; font-weight:bold; width:15%;">
                <?= number_format($totalHours, 2) ?> ชม.
            </td>
        </tr>
    </table>
</div>

<div style="text-align:right; margin-top:5px; font-size:12pt; color:#666;">
    (เกณฑ์ขั้นต่ำ: 1,645 ชั่วโมง/ปีการศึกษา)
</div>
<?php endif; ?>

<div class="signature-section">
    <div class="sign-box">
        ขอรับรองว่าข้อมูลถูกต้อง
        <div class="sign-line"></div>
        (<?= htmlspecialchars($user['name']) ?>)<br>
        ผู้รับการประเมิน
    </div>
    <div class="sign-box">
        ตรวจสอบแล้วถูกต้อง
        <div class="sign-line"></div>
        (..........................................................)<br>
        หัวหน้างาน / ผู้อำนวยการสำนัก
    </div>
</div>

</body>
</html>