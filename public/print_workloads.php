<?php
// public/print_workloads.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// 1. รับค่าตัวกรอง
$filter_year   = $_GET['year'] ?? '';
$filter_term   = $_GET['term'] ?? '';
$filter_status = $_GET['status'] ?? 'all'; // ✅ เพิ่มตัวแปรรับค่าสถานะ (Default = all)

// ถ้าปีว่าง ให้หาปีล่าสุด
if (empty($filter_year)) {
    $latest = $conn->query("SELECT MAX(academic_year) as y FROM workload_items WHERE user_id = {$user['id']}")->fetch_assoc();
    $filter_year = $latest['y'] ?? date('Y')+543;
}

// 2. Query
$sql = "
    SELECT wi.*, wc.main_area, wc.name_th AS category_name, wc.code
    FROM workload_items wi
    LEFT JOIN workload_categories wc ON wi.category_id = wc.id
    WHERE wi.user_id = ?
";
$params = [$user['id']];
$types = "i";

// กรองปี/เทอม
if ($filter_year) { $sql .= " AND wi.academic_year = ?"; $params[] = $filter_year; $types .= "s"; }
if ($filter_term) { $sql .= " AND wi.term_id = ?"; $params[] = $filter_term; $types .= "i"; }

// ✅ เพิ่ม Logic กรองสถานะ
if ($filter_status === 'approved') {
    $sql .= " AND wi.status IN ('approved_admin', 'approved_final')";
}

$sql .= " ORDER BY wc.main_area ASC, wc.code ASC, wi.start_date ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$dataByArea = [1=>[], 2=>[], 3=>[], 4=>[], 5=>[], 6=>[]];
$totalHours = 0;

while($row = $result->fetch_assoc()) {
    $area = intval($row['main_area']);
    if (!isset($dataByArea[$area])) $dataByArea[$area] = [];
    $dataByArea[$area][] = $row;
    
    // นับคะแนนเฉพาะที่ไม่ถูกปฏิเสธ
    if ($row['status'] !== 'rejected') {
        $totalHours += $row['computed_hours'];
    }
}

$mainAreaNames = [
    1 => "ด้านการสอน", 2 => "ด้านวิจัยและงานวิชาการ", 3 => "ด้านบริการวิชาการ",
    4 => "ด้านทำนุบำรุงศิลปวัฒนธรรม", 5 => "ด้านบริหาร", 6 => "ภาระงานอื่น ๆ"
];

$yearsQuery = $conn->query("SELECT DISTINCT academic_year FROM workload_items WHERE user_id = {$user['id']} ORDER BY academic_year DESC");
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานภาระงาน | <?= htmlspecialchars($user['name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { 
            font-family: "Sarabun", sans-serif; 
            font-size: 14pt; 
            line-height: 1.3; 
            color: #000;
            background: #fff;
            padding: 20mm 15mm; 
            margin: 0;
        }

        .no-print {
            background: #f8f9fa; padding: 15px; border-bottom: 1px solid #ddd; margin-bottom: 20px;
            display: flex; gap: 10px; align-items: center; justify-content: center; flex-wrap: wrap;
            font-family: sans-serif; font-size: 14px;
            margin-left: -15mm; margin-right: -15mm; margin-top: -20mm; 
        }
        
        .btn { padding: 8px 16px; border-radius: 4px; text-decoration: none; border: none; cursor: pointer; font-size: 14px; }
        .btn-primary { background: #0d6efd; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        select { padding: 6px; border-radius: 4px; border: 1px solid #ccc; }

        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { font-size: 18pt; font-weight: bold; margin: 0; }
        .header h2 { font-size: 16pt; font-weight: bold; margin: 0; }
        .user-info { margin-bottom: 15px; font-size: 14pt; font-weight: bold; border-bottom: 1px solid #000; padding-bottom: 10px; }
        
        .area-section { margin-bottom: 20px; }
        .area-title { font-size: 14pt; font-weight: bold; margin-bottom: 5px; color: #000; background:#eee; padding: 2px 5px;}
        
        table { width: 100%; table-layout: fixed; border-collapse: collapse; margin-bottom: 10px; }
        
        th, td { 
            border: 1px solid #000; 
            padding: 4px 6px; 
            vertical-align: top; 
            font-size: 11pt; 
            word-wrap: break-word; overflow-wrap: break-word; white-space: normal;
        }
        
        th { background-color: #f0f0f0; text-align: center; font-weight: bold; -webkit-print-color-adjust: exact; print-color-adjust: exact; font-size: 12pt; }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        
        a.pdf-link { color: blue; text-decoration: underline; font-size: 0.9em; word-break: break-all; display: inline-block; }

        .signature-section { margin-top: 30px; display: flex; justify-content: space-between; page-break-inside: avoid; }
        .sign-box { width: 45%; text-align: center; font-size: 12pt; }
        .sign-line { border-bottom: 1px dotted #000; width: 90%; margin: 20px auto 5px auto; height: 10px; }

        @media print {
            .no-print { display: none !important; }
            thead { display: table-header-group; } 
            tr { page-break-inside: avoid; }
            a.pdf-link { color: blue !important; text-decoration: underline !important; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <form method="GET" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <label><strong>ตัวเลือกพิมพ์:</strong></label>
        
        <select name="year" onchange="this.form.submit()">
            <option value="">-- ปีการศึกษา --</option>
            <?php if($yearsQuery) { $yearsQuery->data_seek(0); while($y = $yearsQuery->fetch_assoc()): ?>
                <option value="<?= $y['academic_year'] ?>" <?= $filter_year==$y['academic_year']?'selected':'' ?>>
                    ปี <?= $y['academic_year'] ?>
                </option>
            <?php endwhile; } ?>
        </select>

        <select name="term" onchange="this.form.submit()">
            <option value="">-- ทุกเทอม --</option>
            <option value="1" <?= $filter_term=='1'?'selected':'' ?>>เทอม 1</option>
            <option value="2" <?= $filter_term=='2'?'selected':'' ?>>เทอม 2</option>
            <option value="3" <?= $filter_term=='3'?'selected':'' ?>>ฤดูร้อน</option>
        </select>

        <select name="status" onchange="this.form.submit()" style="background-color: #e3f2fd; border-color: #2196f3; color: #0d47a1; font-weight: bold;">
            <option value="all" <?= $filter_status=='all'?'selected':'' ?>>📋 รายการทั้งหมด</option>
            <option value="approved" <?= $filter_status=='approved'?'selected':'' ?>>✅ เฉพาะอนุมัติแล้ว</option>
        </select>

        <div style="border-left: 1px solid #ccc; height: 20px; margin: 0 5px;"></div>

        <a href="workloads.php" class="btn btn-secondary">กลับ</a>
        <button type="button" class="btn btn-primary" onclick="window.print()">🖨 พิมพ์</button>
    </form>
</div>

<div class="report-container">
    
    <div class="header">
        <h1>แบบรายงานภาระงานคณาจารย์</h1>
        <h2>คณะแพทยศาสตร์ มหาลัยวงษ์ชวลิตกุล</h2>
    </div>

    <div class="user-info">
        <div style="display:flex; justify-content:space-between;">
            <span>ชื่อ-สกุล: <?= htmlspecialchars($user['name']) ?></span>
            <span>ตำแหน่ง: อาจารย์</span>
        </div>
        <div style="margin-top:5px; font-weight:normal;">
            ประจำปีการศึกษา: <strong><?= $filter_year ? $filter_year : 'ทั้งหมด' ?></strong> 
            <?= $filter_term ? " / ภาคการศึกษาที่ $filter_term" : '' ?>
            <span style="font-size:0.9em; color:#666;">
                (<?= $filter_status=='approved' ? 'เฉพาะรายการที่อนุมัติแล้ว' : 'รายการทั้งหมด' ?>)
            </span>
        </div>
    </div>

    <?php 
    $hasData = false;
    foreach ($mainAreaNames as $areaId => $areaName): 
        if (empty($dataByArea[$areaId])) continue;
        $hasData = true;
        $subTotal = 0;
        
        $col1 = "รายการ";
        $col2 = "รายละเอียด / ลิงก์หลักฐาน";
        
        switch ($areaId) {
            case 1: $col1 = "รหัสวิชา / ชื่อวิชา"; $col2 = "รายละเอียดการสอน"; break;
            case 2: $col1 = "ชื่องานวิจัย / บทความ"; $col2 = "บทบาท / การเผยแพร่"; break;
            case 3: $col1 = "ชื่อโครงการบริการ"; $col2 = "วันเวลา / สถานที่"; break;
            case 4: $col1 = "ชื่อโครงการ / กิจกรรม"; $col2 = "บทบาทในโครงการ"; break;
            case 5: $col1 = "ตำแหน่งบริหาร"; $col2 = "หน่วยงาน / คำสั่งแต่งตั้ง"; break;
            case 6: $col1 = "ภาระงาน"; $col2 = "รายละเอียด"; break;
        }
    ?>

    <div class="area-section">
        <div class="area-title"><?= $areaId ?>. <?= $areaName ?></div>
        
        <table>
            <colgroup>
                <col style="width: 35%;"> 
                <col style="width: 40%;"> 
                <col style="width: 15%;"> 
                <col style="width: 10%;"> 
            </colgroup>
            <thead>
                <tr>
                    <th><?= $col1 ?></th>
                    <th><?= $col2 ?></th>
                    <th>สถานะ</th>
                    <th>ชั่วโมง</th>
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
                            if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
                                $url = "http://" . $url;
                            }
                        ?>
                            <div style="margin-top: 4px; padding-top:4px; border-top:1px dashed #ccc;">
                                <span style="font-size:0.8em; font-weight:bold;">ลิงก์แนบ:</span><br>
                                <a href="<?= htmlspecialchars($url) ?>" target="_blank" class="pdf-link">
                                    <?= htmlspecialchars($url) ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </td>
                    
                    <td class="text-center" style="font-size:0.9em;">
                        <?php 
                            if ($item['status']=='approved_final') echo "✅ อนุมัติ";
                            elseif ($item['status']=='approved_admin') echo "☑️ ผ่านขั้นต้น";
                            elseif ($item['status']=='rejected') echo "❌ แก้ไข";
                            else echo "⏳ รอตรวจ";
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
                <tr style="background-color: #fafafa; font-weight:bold;">
                    <td colspan="3" class="text-right">รวมด้านที่ <?= $areaId ?></td>
                    <td class="text-right"><?= number_format($subTotal, 2) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <?php endforeach; ?>

    <?php if (!$hasData): ?>
        <div style="text-align:center; padding:50px; color:#666; border:1px dashed #ccc; margin-top:20px;">
            ไม่พบข้อมูลภาระงานในช่วงเวลานี้
        </div>
    <?php endif; ?>

    <?php if ($hasData): ?>
    <div style="margin-top:20px; border-top:2px solid #000; border-bottom:2px solid #000; padding:5px 0;">
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
    <?php endif; ?>

    <div class="signature-section">
        <div class="sign-box">
            ขอรับรองว่าข้อมูลข้างต้นเป็นความจริง
            <div class="sign-line"></div>
            (<?= htmlspecialchars($user['name']) ?>)<br>
            ผู้ขอรับการประเมิน<br>
            วันที่ ........./........./.........
        </div>
        <div class="sign-box">
            ได้ตรวจสอบความถูกต้องแล้ว
            <div class="sign-line"></div>
            (..........................................................)<br>
            หัวหน้าสาขาวิชา / หัวหน้างาน<br>
            วันที่ ........./........./.........
        </div>
    </div>
    
    <div class="signature-section" style="margin-top: 30px; justify-content: center;">
        <div class="sign-box">
            อนุมัติภาระงาน
            <div class="sign-line"></div>
            (..........................................................)<br>
            คณบดีคณะแพทยศาสตร์<br>
            วันที่ ........./........./.........
        </div>
    </div>

</div>

</body>
</html>