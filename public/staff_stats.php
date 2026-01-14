<?php
// public/staff_stats.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// 1. ตรวจสอบสิทธิ์ (Staff Only)
if ($user['role'] !== 'staff') {
    header("Location: index.php");
    exit;
}

// 2. Config เกณฑ์สายสนับสนุน
$GOAL_YEAR = 1645;

// 3. ตัวกรองปีการศึกษา
$filter_year = $_GET['year'] ?? '';

// ดึงปีที่มีข้อมูล
$yearsList = [];
$yQ = $conn->prepare("SELECT DISTINCT academic_year FROM workload_items WHERE user_id = ? ORDER BY academic_year DESC");
$yQ->bind_param("i", $user['id']);
$yQ->execute();
$resY = $yQ->get_result();
while($y = $resY->fetch_assoc()) {
    $yearsList[] = $y['academic_year'];
}

// ถ้าไม่เลือกปี ให้ใช้ปีล่าสุด
if (empty($filter_year) && count($yearsList) > 0) {
    $filter_year = $yearsList[0];
}

// 4. ดึงข้อมูลสรุปรายด้าน
$hours = [1=>0, 2=>0, 3=>0, 4=>0, 5=>0, 6=>0];
$mainAreaNames = [
    1 => "งานประจำ (Routine)", 2 => "งานพัฒนางาน", 3 => "งานยุทธศาสตร์",
    4 => "งานมอบหมาย", 5 => "กิจกรรม ม.", 6 => "งานบริหาร"
];

// Query
$sql = "
    SELECT wc.main_area, SUM(wi.computed_hours) AS total
    FROM workload_items wi
    LEFT JOIN workload_categories wc ON wc.id = wi.category_id
    WHERE wi.user_id = ? 
";
$params = [$user['id']];
$types = "i";

if (!empty($filter_year)) {
    $sql .= " AND wi.academic_year = ?";
    $params[] = $filter_year;
    $types .= "s";
}

$sql .= " GROUP BY wc.main_area";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

while ($r = $res->fetch_assoc()) {
    $hours[$r['main_area']] = floatval($r['total']);
}
$totalHours = array_sum($hours);

// คำนวณความสำเร็จ
$percent = ($totalHours > 0) ? ($totalHours / $GOAL_YEAR) * 100 : 0;

// สถานะความสำเร็จ
if ($totalHours >= $GOAL_YEAR) {
    $statusText = "ผ่านเกณฑ์มาตรฐาน (ดีมาก)";
    $statusClass = "text-success";
    $barColor = "bg-success";
} elseif ($totalHours >= $GOAL_YEAR * 0.8) {
    $statusText = "ใกล้ถึงเกณฑ์ (ควรเร่งผลงาน)";
    $statusClass = "text-warning";
    $barColor = "bg-warning";
} else {
    $statusText = "ต่ำกว่าเกณฑ์";
    $statusClass = "text-danger";
    $barColor = "bg-danger";
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานสถิติของฉัน | MedUI</title>
    <link rel="stylesheet" href="../medui/medui.css">
    <link rel="stylesheet" href="../medui/medui.components.css">
    <link rel="stylesheet" href="../medui/medui.layout.css">
    <link rel="stylesheet" href="../medui/medui.theme.medical.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .summary-card {
            background: #fff; padding: 24px; border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 24px; border: 1px solid #f0f0f0;
        }
        /* สีหลอดพลัง */
        .bg-success { background-color: #10b981 !important; }
        .bg-warning { background-color: #f59e0b !important; }
        .bg-danger  { background-color: #ef4444 !important; }
        
        /* สีธีม Staff */
        .text-purple { color: #6366f1; }
        .border-purple { border-color: #6366f1 !important; }
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
                        <h3 style="margin:0;">รายงานสถิติของฉัน</h3>
                        <p class="muted m-0">สรุปผลการปฏิบัติงานประจำปี</p>
                    </div>
                    <div class="topbar-right">
                        <a href="staff_stats_print.php?year=<?= $filter_year ?>" target="_blank" class="btn btn-outline">
        <i class="bi bi-printer"></i> พิมพ์รายงานสรุป
    </a>
                    </div>
                </div>
            </div>
        </header>

        <main class="main">
            <div class="container" style="max-width: 1000px;">
                
                <div class="card p-4 mb-4" style="border-left: 5px solid #6366f1;">
                    <form method="GET" style="display:flex; align-items:center; gap:15px;">
                        <label class="m-0 font-bold text-muted"><i class="bi bi-calendar-event"></i> เลือกปีการศึกษา:</label>
                        <select name="year" class="input" onchange="this.form.submit()" style="min-width: 200px;">
                            <?php if(empty($yearsList)): ?>
                                <option value="">-- ไม่มีข้อมูล --</option>
                            <?php else: ?>
                                <?php foreach($yearsList as $y): ?>
                                    <option value="<?= $y ?>" <?= ($filter_year==$y)?'selected':'' ?>>
                                        ปีการศึกษา <?= $y ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </form>
                </div>

                <div class="summary-card">
                    <h4 class="mb-4 border-bottom pb-2 text-purple">
                        <i class="bi bi-speedometer2"></i> ภาพรวมผลงาน (เกณฑ์ <?= number_format($GOAL_YEAR) ?> ชม.)
                    </h4>
                    
                    <div class="mb-6">
                        <div class="stack-between mb-2">
                            <span class="text-muted">ความก้าวหน้า</span>
                            <strong class="<?= $statusClass ?>" style="font-size:1.2rem;"><?= number_format($percent, 1) ?>%</strong>
                        </div>
                        <div style="background:#f3f4f6; height:20px; border-radius:10px; overflow:hidden;">
                            <div style="width:<?= min(100, $percent) ?>%; height:100%;" class="<?= $barColor ?>"></div>
                        </div>
                        <div class="mt-2 text-right <?= $statusClass ?>">
                            <i class="bi bi-info-circle"></i> <?= $statusText ?>
                        </div>
                    </div>

                    <div class="grid grid-3" style="gap:20px;">
                        <div class="p-4 bg-light rounded text-center border">
                            <div class="text-muted text-sm">ชั่วโมงสะสมจริง</div>
                            <div class="text-2xl font-bold text-dark"><?= number_format($totalHours, 2) ?></div>
                        </div>
                        <div class="p-4 bg-light rounded text-center border">
                            <div class="text-muted text-sm">เป้าหมายขั้นต่ำ</div>
                            <div class="text-2xl font-bold text-muted"><?= number_format($GOAL_YEAR) ?></div>
                        </div>
                        <div class="p-4 bg-light rounded text-center border border-purple" style="background:#e0e7ff;">
                            <div class="text-muted text-sm text-purple">ผลต่าง</div>
                            <div class="text-2xl font-bold text-purple">
                                <?= ($totalHours >= $GOAL_YEAR) ? '+'.number_format($totalHours-$GOAL_YEAR,2) : '-'.number_format($GOAL_YEAR-$totalHours,2) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-2" style="gap: 24px; align-items: start;">
                    
                    <div class="summary-card h-full">
                        <h4 class="mb-3 text-purple">รายละเอียดแยกรายด้าน</h4>
                        <div class="table-wrap">
                            <table class="table table-sm">
                                <tbody>
                                <?php foreach ($mainAreaNames as $id => $name): 
                                    $val = $hours[$id];
                                    $p = ($val > 0 && $totalHours > 0) ? ($val / $totalHours) * 100 : 0;
                                ?>
                                    <tr>
                                        <td style="width:60%;">
                                            <div class="font-bold text-sm"><?= $name ?></div>
                                            <div style="background:#eee; height:4px; width:100%; margin-top:4px; border-radius:2px;">
                                                <div style="background:#6366f1; height:100%; width:<?= $p ?>%;"></div>
                                            </div>
                                        </td>
                                        <td class="text-right align-middle">
                                            <strong class="text-dark"><?= number_format($val, 2) ?></strong> 
                                            <small class="text-muted">ชม.</small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="summary-card h-full text-center">
                        <h4 class="mb-3 text-purple">สัดส่วนภาระงาน</h4>
                        <div style="position: relative; height: 250px;">
                            <canvas id="myChart"></canvas>
                        </div>
                    </div>

                </div>

                <div class="card p-4 mt-4 text-center border-purple" style="background:#f5f3ff; border: 1px dashed #8b5cf6;">
                    <h4 class="text-purple m-0 mb-2">ต้องการดูรายละเอียดรายการทั้งหมด?</h4>
                    <p class="text-muted text-sm">ตรวจสอบรายการ วันที่ และสถานะการอนุมัติของปีการศึกษานี้</p>
                    <a href="staff_workloads.php?year=<?= $filter_year ?>" class="btn btn-purple">
                        <i class="bi bi-list-ul"></i> ไปยังหน้ารายการบันทึก
                    </a>
                </div>

            </div>
        </main>
    </div>
</div>

<script>
const ctx = document.getElementById('myChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_values($mainAreaNames)) ?>,
        datasets: [{
            data: <?= json_encode(array_values($hours)) ?>,
            backgroundColor: [
                '#6366f1', '#ec4899', '#f59e0b', '#10b981', '#8b5cf6', '#64748b'
            ],
            borderWidth: 2,
            borderColor: '#ffffff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true, font: { size: 11 } } }
        }
    }
});
</script>

</body>
</html>