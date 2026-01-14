<?php
// public/stats.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// 1. รับค่าผู้ใช้ปัจจุบัน
$uid = $user['id'];
$role = $user['role'];
$printLink = ($user['role'] === 'staff') ? 'staff_print_state.php' : 'print_stats.php';
// 2. ตั้งค่าตาม Role (Config)
if ($role === 'staff') {
    // --- สายสนับสนุน ---
    $GOAL_YEAR = 1645;
    $pageTitle = "รายงานสถิติ (สายสนับสนุน)";
    $printLink = "staff_print_report.php"; // ลิงก์ไปหน้าพิมพ์ของ Staff
    $listLink  = "staff_workloads.php";    // ลิงก์ไปหน้ารายการของ Staff
    
    $mainAreaNames = [
        1 => "งานประจำ (Routine)", 
        2 => "งานพัฒนางาน", 
        3 => "งานยุทธศาสตร์",
        4 => "งานมอบหมาย", 
        5 => "กิจกรรม ม.", 
        6 => "งานบริหาร"
    ];
} else {
    // --- อาจารย์ (User) ---
    $GOAL_YEAR = 1330;
    $pageTitle = "รายงานสถิติ (สายวิชาการ)";
    $printLink = "print_stats.php";        // ลิงก์ไปหน้าพิมพ์ของอาจารย์
    $listLink  = "workloads.php";          // ลิงก์ไปหน้ารายการของอาจารย์
    
    $mainAreaNames = [
        1 => "ด้านการสอน", 
        2 => "วิจัย/วิชาการ", 
        3 => "บริการวิชาการ",
        4 => "ทำนุบำรุงศิลปฯ", 
        5 => "ด้านบริหาร", 
        6 => "ภาระงานอื่น ๆ"
    ];
}

// 3. ตัวกรองปีการศึกษา
$filter_year = $_GET['year'] ?? '';

// ดึงปีที่มีข้อมูลจาก DB
$yearsList = [];
$yQ = $conn->prepare("SELECT DISTINCT academic_year FROM workload_items WHERE user_id = ? ORDER BY academic_year DESC");
$yQ->bind_param("i", $uid);
$yQ->execute();
$resY = $yQ->get_result();
while($y = $resY->fetch_assoc()) {
    $yearsList[] = $y['academic_year'];
}

// ถ้าไม่เลือกปี ให้ใช้ปีล่าสุด หรือปีปัจจุบัน
if (empty($filter_year)) {
    if (count($yearsList) > 0) {
        $filter_year = $yearsList[0];
    } else {
        $filter_year = date("Y") + 543;
    }
}

// 4. สรุปชั่วโมงตามด้าน
$hours = [1=>0, 2=>0, 3=>0, 4=>0, 5=>0, 6=>0];

// Query ข้อมูล (รวมทุกสถานะ เพื่อให้เห็นภาพรวมงานที่ทำไป)
$sql = "
    SELECT wc.main_area, SUM(wi.computed_hours) AS total
    FROM workload_items wi
    LEFT JOIN workload_categories wc ON wc.id = wi.category_id
    WHERE wi.user_id = ? 
";
$params = [$uid];
$types = "i";

// แปลงปี พ.ศ. จากตัวกรอง เป็น ค.ศ. เพื่อค้นใน DB (ถ้า DB เก็บ ค.ศ.)
// แต่ถ้าใน DB เก็บเป็น พ.ศ. แล้ว ก็ใช้ค่าตรงๆ ได้เลย
// (สมมติว่าใช้ค่าตามที่เลือกมา)
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

// 5. คำนวณความสำเร็จ
$percent = ($totalHours > 0) ? ($totalHours / $GOAL_YEAR) * 100 : 0;

// กำหนดสีสถานะ
if ($totalHours >= $GOAL_YEAR) {
    $statusText = "ผ่านเกณฑ์มาตรฐาน (ดีมาก)";
    $statusClass = "text-success";
    $barColor = "bg-success";
    $cardBorder = "border-success";
    $cardBg = "bg-green-soft";
} elseif ($totalHours >= $GOAL_YEAR * 0.8) {
    $statusText = "ใกล้ถึงเกณฑ์ (ควรเพิ่มผลงาน)";
    $statusClass = "text-warning";
    $barColor = "bg-warning";
    $cardBorder = "border-warning";
    $cardBg = "bg-yellow-soft";
} else {
    $statusText = "ต่ำกว่าเกณฑ์ (เสี่ยงไม่ผ่าน)";
    $statusClass = "text-danger";
    $barColor = "bg-danger";
    $cardBorder = "border-danger";
    $cardBg = "bg-red-soft";
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?> | VUMEDHR</title>
    <link rel="stylesheet" href="../medui/medui.css">
    <link rel="stylesheet" href="../medui/medui.components.css">
    <link rel="stylesheet" href="../medui/medui.layout.css">
    <link rel="stylesheet" href="../medui/medui.theme.medical.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .summary-card {
            background: #fff; padding: 24px; border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 24px; border: 1px solid #eee;
        }
        .bg-green-soft { background-color: #ecfdf5; }
        .bg-yellow-soft { background-color: #fffbeb; }
        .bg-red-soft { background-color: #fef2f2; }
        
        .border-success { border-color: #10b981 !important; }
        .border-warning { border-color: #f59e0b !important; }
        .border-danger { border-color: #ef4444 !important; }

        .bg-success { background-color: #10b981 !important; }
        .bg-warning { background-color: #f59e0b !important; }
        .bg-danger  { background-color: #ef4444 !important; }
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
                        <h3 style="margin:0;"><?= $pageTitle ?></h3>
                        <p class="muted m-0">สรุปผลการปฏิบัติงาน ปีการศึกษา <?= htmlspecialchars($filter_year) ?></p>
                    </div>
                    <div class="topbar-right">
                        <a href="<?= $printLink ?>?year=<?= htmlspecialchars($filter_year) ?>" target="_blank" class="btn btn-outline">
    <i class="bi bi-printer"></i> พิมพ์รายงานสรุป
</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="main">
            <div class="container" style="max-width: 1000px;">
                
                <div class="card p-4 mb-4" style="border-left: 5px solid var(--primary);">
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

                <div class="grid grid-3" style="gap:20px; margin-bottom:24px;">
                    
                    <div class="summary-card text-center">
                        <div class="text-muted mb-2">ชั่วโมงสะสมจริง</div>
                        <div class="text-3xl font-bold text-dark"><?= number_format($totalHours, 2) ?></div>
                        <div class="text-sm text-muted">จากรายการทั้งหมด</div>
                    </div>

                    <div class="summary-card text-center">
                        <div class="text-muted mb-2">เกณฑ์ขั้นต่ำ</div>
                        <div class="text-3xl font-bold text-muted"><?= number_format($GOAL_YEAR) ?></div>
                        <div class="text-sm text-muted">ชั่วโมง / ปี</div>
                    </div>

                    <div class="summary-card text-center <?= $cardBg ?> <?= $cardBorder ?>" style="border-width:2px;">
                        <div class="text-muted mb-2">สถานะปัจจุบัน</div>
                        <div class="text-3xl font-bold <?= $statusClass ?>"><?= number_format($percent, 1) ?>%</div>
                        <div class="text-sm font-bold <?= $statusClass ?>"><?= $statusText ?></div>
                    </div>

                </div>

                <div class="card p-4 mb-4">
                    <div class="stack-between mb-2">
                        <span class="text-muted small">ความก้าวหน้าเทียบเกณฑ์</span>
                        <strong class="<?= $statusClass ?>"><?= number_format($percent, 1) ?>%</strong>
                    </div>
                    <div style="background:#eee; height:12px; border-radius:6px; overflow:hidden;">
                        <div style="width:<?= min(100, $percent) ?>%; height:100%;" class="<?= $barColor ?>"></div>
                    </div>
                </div>

                <div class="grid grid-2" style="gap: 24px; align-items: start;">
                    
                    <div class="summary-card h-full">
                        <h4 class="mb-3 text-primary">รายละเอียดแยกรายด้าน</h4>
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
                                            <div style="background:var(--primary); height:100%; width:<?= $p ?>%;"></div>
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

                    <div class="summary-card h-full text-center">
                        <h4 class="mb-3 text-primary">สัดส่วนภาระงาน</h4>
                        <div style="position: relative; height: 220px;">
                            <canvas id="myChart"></canvas>
                        </div>
                    </div>

                </div>

                <div class="card p-4 mt-4 text-center" style="border: 1px dashed var(--primary); background:#f8faff;">
                    <h4 class="text-primary m-0 mb-2">ต้องการดูรายการภาระงานอย่างละเอียด?</h4>
                    <a href="<?= $listLink ?>?year=<?= $filter_year ?>" class="btn btn-primary mt-2">
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
            backgroundColor: ['#3b82f6', '#ef4444', '#f59e0b', '#10b981', '#8b5cf6', '#64748b'],
            borderWidth: 2,
            borderColor: '#ffffff'
        }]
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: false, 
        plugins: { 
            legend: { position: 'right', labels: { boxWidth: 10, fontSize: 10 } } 
        } 
    }
});
</script>

</body>
</html>