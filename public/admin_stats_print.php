<?php
// public/admin_stats_print.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// 1. จำกัดสิทธิ์
if (!in_array($user['role'], ['admin', 'manager'])) {
    header("Location: index.php?error=สิทธิ์ไม่เพียงพอ");
    exit;
}

// ==================================================
// 2. จัดการตัวเลือกการแสดงผล (Checkbox Logic)
// ==================================================
// ถ้าไม่มีการส่งค่ามา (เข้าครั้งแรก) ให้ติ๊กถูกเฉพาะ 3 ส่วนแรก (ส่วนรายละเอียดมันเยอะ ปิดไว้ก่อน)
$is_first_load = empty($_GET);

$show_area   = $is_first_load ? true : isset($_GET['show_area']);
$show_status = $is_first_load ? true : isset($_GET['show_status']);
$show_users  = $is_first_load ? true : isset($_GET['show_users']);
$show_list   = $is_first_load ? false : isset($_GET['show_list']); // Default ปิด

// ==================================================
// 3. ดึงข้อมูล (Query ตามที่เลือกเท่านั้น เพื่อความเร็ว)
// ==================================================

$hours = [1=>0, 2=>0, 3=>0, 4=>0, 5=>0, 6=>0];
$mainAreaNames = [1=>"การสอน", 2=>"วิจัย/วิชาการ", 3=>"บริการวิชาการ", 4=>"ทำนุบำรุงฯ", 5=>"บริหาร", 6=>"อื่นๆ"];
$grandTotal = 0;

// 3.1 รายด้าน
if ($show_area) {
    $areaQuery = $conn->query("
        SELECT wc.main_area, SUM(wi.computed_hours) AS total_hours
        FROM workload_items wi
        LEFT JOIN workload_categories wc ON wi.category_id = wc.id
        WHERE wi.status IN ('approved_admin', 'approved_final')
        GROUP BY wc.main_area
    ");
    while ($a = $areaQuery->fetch_assoc()) {
        $val = floatval($a['total_hours']);
        $hours[intval($a['main_area'])] = $val;
        $grandTotal += $val;
    }
}

// 3.2 สถานะ
$total = ['pending'=>0, 'approved'=>0, 'rejected'=>0];
$hoursStatus = ['pending'=>0, 'approved'=>0, 'rejected'=>0];

if ($show_status) {
    $statusQuery = $conn->query("
        SELECT status, COUNT(*) AS total, SUM(computed_hours) AS hours
        FROM workload_items GROUP BY status
    ");
    while ($s = $statusQuery->fetch_assoc()) {
        $st = $s['status'];
        $g = ($st=='approved_admin'||$st=='approved_final') ? 'approved' : (($st=='rejected')?'rejected':'pending');
        $total[$g] += $s['total'];
        $hoursStatus[$g] += $s['hours'];
    }
}

// 3.3 รายบุคคล
$userStats = [];
if ($show_users) {
    $userQuery = $conn->query("
        SELECT u.name, 
           SUM(CASE WHEN wi.status IN ('approved_admin', 'approved_final') THEN wi.computed_hours ELSE 0 END) AS total_hours
        FROM users u
        LEFT JOIN workload_items wi ON wi.user_id = u.id
        WHERE u.role = 'user'
        GROUP BY u.id ORDER BY total_hours DESC
    ");
    while($row = $userQuery->fetch_assoc()) $userStats[] = $row;
}

// 3.4 รายการละเอียด
$workList = [];
if ($show_list) {
    $listQuery = $conn->query("
        SELECT wi.*, u.name AS user_name, wc.main_area, wc.name_th AS category_name
        FROM workload_items wi
        LEFT JOIN users u ON wi.user_id = u.id
        LEFT JOIN workload_categories wc ON wi.category_id = wc.id
        WHERE wi.status IN ('approved_admin', 'approved_final')
        ORDER BY u.name ASC, wi.created_at DESC
    ");
    while($row = $listQuery->fetch_assoc()) $workList[] = $row;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>รายงานสถิติภาระงาน</title>
<style>
    @page { size: A4 portrait; margin: 15mm; }
    body { font-family: "Sarabun", sans-serif; font-size: 14pt; line-height: 1.4; color: #000; }
    
    /* --- Control Panel (ไม่พิมพ์ออกกระดาษ) --- */
    .control-panel {
        background: #f8f9fa;
        border-bottom: 1px solid #ddd;
        padding: 15px;
        margin-bottom: 30px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .control-form {
        max-width: 800px;
        margin: 0 auto;
        display: flex;
        gap: 20px;
        align-items: center;
        flex-wrap: wrap;
    }
    .checkbox-group {
        display: flex;
        gap: 15px;
    }
    .checkbox-item {
        display: flex;
        align-items: center;
        gap: 5px;
        cursor: pointer;
    }
    .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        font-weight: bold;
    }
    .btn-update { background: #6c757d; color: white; }
    .btn-print { background: #0d6efd; color: white; }
    
    /* --- Report Content --- */
    .header { text-align: center; margin-bottom: 30px; }
    .header h1 { font-size: 20pt; margin: 0; font-weight: bold; }
    .header p { font-size: 16pt; margin: 5px 0 0; }
    
    .section { margin-bottom: 30px; }
    .section-title { 
        font-size: 16pt; font-weight: bold; 
        border-bottom: 2px solid #000; 
        margin-bottom: 10px; padding-bottom: 5px; 
    }
    
    table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    th, td { border: 1px solid #000; padding: 6px 10px; vertical-align: top; }
    th { background: #f0f0f0; text-align: center; font-weight: bold; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    
    .page-break { page-break-before: always; }

    /* ซ่อน Control Panel เวลาสั่งพิมพ์ */
    @media print {
        .no-print { display: none !important; }
        body { margin: 0; }
    }
</style>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>

<div class="control-panel no-print">
    <form method="GET" class="control-form">
        <div style="font-weight: bold; margin-right: 10px;">เลือกข้อมูล:</div>
        
        <div class="checkbox-group">
            <label class="checkbox-item">
                <input type="checkbox" name="show_area" value="1" <?= $show_area ? 'checked' : '' ?>>
                1.สรุปรายด้าน
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="show_status" value="1" <?= $show_status ? 'checked' : '' ?>>
                2.สถานะ
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="show_users" value="1" <?= $show_users ? 'checked' : '' ?>>
                3.รายบุคคล
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="show_list" value="1" <?= $show_list ? 'checked' : '' ?>>
                4.ละเอียดทั้งหมด
            </label>
        </div>

        <div style="margin-left: auto; display:flex; gap:10px;">
            <button type="submit" class="btn btn-update">🔄 อัปเดตข้อมูล</button>
            <button type="button" class="btn btn-print" onclick="window.print()">🖨 พิมพ์รายงาน</button>
        </div>
    </form>
</div>

<div class="report-content">
    <div class="header">
        <h1>รายงานสรุปภาระงานคณาจารย์</h1>
        <p>ประจำปีการศึกษา <?= date("Y")+543 ?></p>
        <div style="font-size:12pt; margin-top:5px;">พิมพ์เมื่อ: <?= date("d/m/Y H:i") ?></div>
    </div>

    <?php if ($show_area): ?>
    <div class="section">
        <div class="section-title">1. สรุปชั่วโมงตามด้าน (เฉพาะที่อนุมัติแล้ว)</div>
        <table>
            <thead>
                <tr>
                    <th style="width:70%">ด้านภาระงาน</th>
                    <th style="width:30%">ชั่วโมงรวม</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($mainAreaNames as $id=>$name): ?>
                <tr>
                    <td><?= $name ?></td>
                    <td class="text-right"><?= number_format($hours[$id], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr style="background:#eee; font-weight:bold;">
                    <td class="text-right">รวมทั้งหมด</td>
                    <td class="text-right"><?= number_format($grandTotal, 2) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($show_status): ?>
    <div class="section">
        <div class="section-title">2. สถานะการดำเนินการ</div>
        <table>
            <thead>
                <tr>
                    <th>สถานะ</th>
                    <th>จำนวนรายการ</th>
                    <th>ชั่วโมง (โดยประมาณ)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>รอการตรวจสอบ</td>
                    <td class="text-center"><?= number_format($total['pending']) ?></td>
                    <td class="text-right"><?= number_format($hoursStatus['pending'], 2) ?></td>
                </tr>
                <tr>
                    <td>อนุมัติแล้ว</td>
                    <td class="text-center"><?= number_format($total['approved']) ?></td>
                    <td class="text-right"><?= number_format($hoursStatus['approved'], 2) ?></td>
                </tr>
                <tr>
                    <td>ไม่อนุมัติ / แก้ไข</td>
                    <td class="text-center"><?= number_format($total['rejected']) ?></td>
                    <td class="text-right"><?= number_format($hoursStatus['rejected'], 2) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($show_users): ?>
    <div class="section">
        <div class="section-title">3. สรุปชั่วโมงรายบุคคล (เรียงตามมากไปน้อย)</div>
        <table>
            <thead>
                <tr>
                    <th style="width:10%">ลำดับ</th>
                    <th style="width:60%">ชื่อ - นามสกุล</th>
                    <th style="width:30%">ชั่วโมงสะสม</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $i=1; 
                if(count($userStats) > 0):
                    foreach($userStats as $u): 
                ?>
                <tr>
                    <td class="text-center"><?= $i++ ?></td>
                    <td><?= htmlspecialchars($u['name']) ?></td>
                    <td class="text-right"><?= number_format($u['total_hours'], 2) ?></td>
                </tr>
                <?php 
                    endforeach;
                else: 
                ?>
                <tr><td colspan="3" class="text-center">ไม่มีข้อมูล</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($show_list): ?>
    <div class="page-break"></div>
    <div class="section">
        <div class="section-title">4. รายละเอียดภาระงานที่อนุมัติแล้ว (ทั้งหมด)</div>
        <table>
            <thead>
                <tr>
                    <th style="width:20%">ผู้ปฏิบัติงาน</th>
                    <th style="width:15%">ด้าน</th>
                    <th style="width:35%">รายการ</th>
                    <th style="width:15%">วันที่</th>
                    <th style="width:15%">ชั่วโมง</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if(count($workList) > 0):
                    foreach($workList as $row): 
                ?>
                <tr>
                    <td><?= htmlspecialchars($row['user_name']) ?></td>
                    <td><?= $mainAreaNames[$row['main_area']] ?? '-' ?></td>
                    <td><?= htmlspecialchars($row['category_name']) ?></td>
                    <td class="text-center">
                        <?= date("d/m/y", strtotime($row['start_date'])) ?>
                    </td>
                    <td class="text-right"><?= number_format($row['computed_hours'], 2) ?></td>
                </tr>
                <?php 
                    endforeach;
                else:
                ?>
                <tr><td colspan="5" class="text-center">ไม่มีรายการ</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

</body>
</html>