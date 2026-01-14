<?php
// public/index.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php'; // 




// 2. ดึงรูปโปรไฟล์ (แก้ไข path ให้ตรงกับโฟลเดอร์จริง)
$userImg = "https://ui-avatars.com/api/?name=" . urlencode($user['name']) . "&background=0D8ABC&color=fff";

if (!empty($_SESSION['user']['profile_image'])) {
    $profilePath = "../uploads/profiles/" . $_SESSION['user']['profile_image'];
    if (file_exists($profilePath)) {
        $userImg = $profilePath;
    }
}

// 3. Config เกณฑ์ขั้นต่ำ (ของเดิม)
$MIN_HOURS_YEAR = 1330;
$MIN_HOURS_SEM = 525;
$MIN_HOURS_WEEK = 35;

$areas = [
    1 => 'ด้านการสอน',
    2 => 'ด้านวิจัยและงานวิชาการ',
    3 => 'ด้านบริการวิชาการ',
    4 => 'ด้านทำนุบำรุงศิลปวัฒนธรรม',
    5 => 'ด้านบริหาร',
    6 => 'ภาระงานอื่น ๆ',
];

// 4. คำนวณชั่วโมงรวมแต่ละด้าน (ของเดิม)
$hours = array_fill_keys(array_keys($areas), 0);
$stmt = $conn->prepare("
    SELECT c.main_area, SUM(i.computed_hours) AS total
    FROM workload_items i
    LEFT JOIN workload_categories c ON c.id = i.category_id
    WHERE i.user_id = ?
    GROUP BY c.main_area
");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $hours[(int)$r['main_area']] = (float)$r['total'];
}

$total_hours = array_sum($hours);
$progress_percent = ($total_hours / $MIN_HOURS_YEAR) * 100;

// 5. (เพิ่มเติม) ดึงรายการล่าสุด 5 รายการ
$stmtRecent = $conn->prepare("
    SELECT wi.*, wc.name_th as category_name 
    FROM workload_items wi
    LEFT JOIN workload_categories wc ON wi.category_id = wc.id
    WHERE wi.user_id = ? 
    ORDER BY wi.created_at DESC LIMIT 5
");
$stmtRecent->bind_param('i', $user['id']);
$stmtRecent->execute();
$recentItems = $stmtRecent->get_result();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ภาพรวมภาระงาน | VUMEDHR System</title>
    <link rel="stylesheet" href="../medui/medui.css">
    <link rel="stylesheet" href="../medui/medui.components.css">
    <link rel="stylesheet" href="../medui/medui.layout.css">
    <link rel="stylesheet" href="../medui/medui.theme.medical.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        /* Style เดิมของคุณ */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }

        .summary-card {
            background: #fff;
            padding: 20px;
            border-radius: 14px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .06);
            text-align: center;
        }

        .summary-card h3 {
            margin: 0;
            font-size: 24px;
            color: var(--primary);
        }

        .summary-card p {
            margin: 4px 0 0;
            color: #777;
        }

        .bar-container {
            background: #e5e7eb;
            height: 12px;
            border-radius: 999px;
            overflow: hidden;
        }

        .bar-fill {
            height: 100%;
            background: var(--primary);
            transition: width .3s ease;
        }

        table.progress-table {
            width: 100%;
            border-collapse: collapse;
        }

        table.progress-table th,
        table.progress-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        table.progress-table th {
            background: var(--surface);
            color: var(--muted);
        }

        .badge-success {
            background: #28a745;
            color: #fff;
            padding: 2px 8px;
            border-radius: 8px;
            font-size: 13px;
        }

        .badge-warning {
            background: #ffc107;
            color: #333;
            padding: 2px 8px;
            border-radius: 8px;
            font-size: 13px;
        }

        /* (เพิ่มเติม) Style สำหรับรูปโปรไฟล์ */
        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #eee;
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
                            <h3 style="margin:0;">ภาพรวมภาระงานของฉัน</h3>
                        </div>
                        <div class="topbar-right" style="display:flex; gap:15px; align-items:center;">

                            <a href="print_workloads.php" target="_blank" class="btn btn-outline btn-sm">
                                <i class="bi bi-printer"></i> พิมพ์รายงาน
                            </a>

                            <a href="workload_select.php" class="btn btn-primary btn-sm">
                                <i class="bi bi-plus-lg"></i> เพิ่มภาระงาน
                            </a>

                            <div class="user-profile" style="margin-left:10px; padding-left:10px; border-left:1px solid #eee;">
                                <div style="text-align:right; line-height:1.2;">
                                    <div style="font-weight:bold; font-size:0.9rem;"><?= htmlspecialchars($user['name']) ?></div>
                                    <small class="text-muted">อาจารย์</small>
                                </div>
                                <img src="<?= $userImg ?>" class="user-img">
                            </div>

                        </div>
                    </div>
                </div>
            </header>

            <main class="main">
                <div class="container">

                    <div class="summary-grid">
                        <div class="summary-card">
                            <h3><?= number_format($total_hours, 2) ?> ชม.</h3>
                            <p>รวมชั่วโมงทั้งหมดในปีการศึกษา</p>
                            <div class="bar-container mt-2">
                                <div class="bar-fill" style="width: <?= min(100, $progress_percent) ?>%;"></div>
                            </div>
                            <p class="muted small">คิดเป็น <?= number_format($progress_percent, 1) ?>% จากเกณฑ์ขั้นต่ำ (<?= $MIN_HOURS_YEAR ?> ชม.)</p>
                        </div>
                        <div class="summary-card">
                            <h3><?= number_format($MIN_HOURS_WEEK, 0) ?> ชม.</h3>
                            <p>ภาระงานขั้นต่ำต่อสัปดาห์</p>
                        </div>
                        <div class="summary-card">
                            <h3><?= number_format($MIN_HOURS_SEM, 0) ?> ชม.</h3>
                            <p>ภาระงานขั้นต่ำต่อภาคการศึกษา</p>
                        </div>
                    </div>

                    <div class="grid grid-2" style="gap:20px; align-items:start;">

                        <div class="card p-6">
                            <h4 class="mb-3"><i class="bi bi-bar-chart"></i> สรุปชั่วโมงภาระงานแต่ละด้าน</h4>
                            <table class="progress-table">
                                <thead>
                                    <tr>
                                        <th>ด้านภาระงาน</th>
                                        <th style="width:120px; text-align:right;">ชั่วโมง</th>
                                        <th>ความก้าวหน้า</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($areas as $id => $label):
                                        $value = $hours[$id];
                                        // เทียบกับเป้าหมายรวม (Visual)
                                        $pct = ($value / $MIN_HOURS_YEAR) * 100;
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($label) ?></td>
                                            <td style="text-align:right; font-weight:bold;"><?= number_format($value, 2) ?></td>
                                            <td>
                                                <div class="bar-container" style="margin:0;">
                                                    <div class="bar-fill" style="width: <?= min(100, $pct) ?>%; background-color:#666;"></div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="card p-6">
                            <div class="stack-between mb-3">
                                <h4 class="m-0"><i class="bi bi-clock-history"></i> รายการล่าสุด</h4>
                                <a href="workloads.php" class="text-primary small" style="text-decoration:none;">ดูทั้งหมด &rarr;</a>
                            </div>
                            <table class="progress-table">
                                <thead>
                                    <tr>
                                        <th>รายการ</th>
                                 
                                        <th style="text-align:right;">สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recentItems->num_rows > 0): ?>
                                        <?php while ($row = $recentItems->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <div style="font-weight:500;"><?= htmlspecialchars($row['title']) ?></div>
                                                    <div class="text-muted small"><?= htmlspecialchars($row['category_name']) ?></div>
                                                </td>










                                                <td style="text-align:right;">
                                                    <?php
                                                    $st = $row['status'];
                                                    if ($st == 'approved_final') echo '<span class="text-success small"><i class="bi bi-check-circle"></i> อนุมัติ</span>';
                                                    elseif ($st == 'rejected') echo '<span class="text-danger small">แก้ไข</span>';
                                                    else echo '<span class="text-warning small">รอตรวจ</span>';
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center muted">ยังไม่มีรายการ</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </main>
        </div>
    </div>
</body>

</html>