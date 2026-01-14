<?php
// public/admin_user_stats.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// 1. จำกัดสิทธิ์
if (!in_array($user['role'], ['admin', 'manager'])) {
    header("Location: index.php?error=สิทธิ์ไม่เพียงพอ");
    exit;
}

// 2. รับค่า Input
$uid = $_GET['id'] ?? $_GET['uid'] ?? 0; // รองรับทั้ง id และ uid
$filter_year = $_GET['year'] ?? ''; 

// ดึงรายชื่อทั้งหมด (เผื่อเปลี่ยนคน)
$users = $conn->query("SELECT id, name FROM users ORDER BY name ASC");

$selectedUser = null;
$hours = [1=>0, 2=>0, 3=>0, 4=>0, 5=>0, 6=>0];
$totalHours = 0;
$yearsList = [];

// ตัวแปร Workflow
$workflowStats = [
    'pending' => ['count'=>0, 'hours'=>0],
    'deputy'  => ['count'=>0, 'hours'=>0],
    'dean'    => ['count'=>0, 'hours'=>0],
    'reject'  => ['count'=>0, 'hours'=>0]
];

$mainAreaNames = [
    1 => "การสอน", 2 => "วิจัย", 3 => "บริการวิชาการ",
    4 => "ทำนุบำรุงฯ", 5 => "บริหาร", 6 => "อื่นๆ"
];

if ($uid) {
    // ดึงข้อมูลผู้ใช้
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $selectedUser = $stmt->get_result()->fetch_assoc();

    if ($selectedUser) {
        // หาปีการศึกษา
        $yQ = $conn->prepare("SELECT DISTINCT academic_year FROM workload_items WHERE user_id = ? ORDER BY academic_year DESC");
        $yQ->bind_param("i", $uid);
        $yQ->execute();
        $resY = $yQ->get_result();
        while($y = $resY->fetch_assoc()) $yearsList[] = $y['academic_year'];

        if (empty($filter_year) && count($yearsList) > 0) $filter_year = $yearsList[0];

        // 3.1 สรุปชั่วโมงรายด้าน
        $sql = "SELECT wc.main_area, SUM(wi.computed_hours) AS total 
                FROM workload_items wi 
                LEFT JOIN workload_categories wc ON wc.id = wi.category_id 
                WHERE wi.user_id = ?";
        $params = [$uid]; $types = "i";
        if (!empty($filter_year)) { $sql .= " AND wi.academic_year = ?"; $params[] = $filter_year; $types .= "s"; }
        $sql .= " GROUP BY wc.main_area";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $hours[$r['main_area']] = floatval($r['total']);
        $totalHours = array_sum($hours);

        // 3.2 Workflow Data
        $sqlFlow = "SELECT status, COUNT(*) as count, SUM(computed_hours) as hours FROM workload_items WHERE user_id = ?";
        $pFlow = [$uid]; $tFlow = "i";
        if (!empty($filter_year)) { $sqlFlow .= " AND academic_year = ?"; $pFlow[] = $filter_year; $tFlow .= "s"; }
        $sqlFlow .= " GROUP BY status";
        
        $stmtFlow = $conn->prepare($sqlFlow);
        $stmtFlow->bind_param($tFlow, ...$pFlow);
        $stmtFlow->execute();
        $resFlow = $stmtFlow->get_result();
        while($row = $resFlow->fetch_assoc()) {
            $st = $row['status'];
            $key = ($st=='pending') ? 'pending' : (($st=='approved_admin') ? 'deputy' : (($st=='approved_final') ? 'dean' : 'reject'));
            if(isset($workflowStats[$key])) {
                $workflowStats[$key]['count'] = intval($row['count']);
                $workflowStats[$key]['hours'] = floatval($row['hours']);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สถิติรายบุคคล (<?= $selectedUser['name'] ?? '' ?>)</title>
    <link rel="stylesheet" href="../medui/medui.css">
    <link rel="stylesheet" href="../medui/medui.components.css">
    <link rel="stylesheet" href="../medui/medui.layout.css">
    <link rel="stylesheet" href="../medui/medui.theme.medical.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .summary-card { background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 24px; border: 1px solid #eee; }
        .workflow-container { display: flex; gap: 10px; margin-top: 20px; position: relative; }
        .workflow-step { flex: 1; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 15px; text-align: center; position: relative; z-index: 2; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .step-icon { width: 40px; height: 40px; border-radius: 50%; margin: 0 auto 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: #fff; }
        .icon-pending { background: #f59e0b; } 
        .icon-deputy { background: #3b82f6; } 
        .icon-dean { background: #10b981; }
        .icon-reject { background: #ef4444; }
        @media print { .no-print, .sidebar, .topbar { display: none !important; } .app { display: block; } .main { padding: 0; } }
    </style>
</head>
<body>

<div class="app">
    <?php include '../inc/nav.php'; ?>

    <div class="app-content">
        <header class="topbar no-print">
            <div class="container">
                <div class="topbar-content stack-between">
                    <div class="topbar-left">
                        <h3 class="m-0">สถิติและผลการประเมิน</h3>
                        <?php if($selectedUser): ?>
                            <p class="muted m-0">ของ: <?= htmlspecialchars($selectedUser['name']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="topbar-right">
                        <form method="GET" style="display:flex; gap:10px;">
                            <select name="id" class="input input-sm" onchange="this.form.submit()" style="width:200px;">
                                <option value="">-- เปลี่ยนคน --</option>
                                <?php if($users) { $users->data_seek(0); while($u=$users->fetch_assoc()): ?>
                                    <option value="<?= $u['id'] ?>" <?= ($uid==$u['id'])?'selected':'' ?>><?= htmlspecialchars($u['name']) ?></option>
                                <?php endwhile; } ?>
                            </select>
                            <a href="admin_dashboard.php" class="btn btn-sm btn-outline">กลับ Dashboard</a>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="main">
            <div class="container" style="max-width: 900px;">

                <?php if ($selectedUser): ?>
                    
                    <div class="stack-between mb-4">
                        <div>
                            <h2 class="m-0 text-primary"><?= htmlspecialchars($selectedUser['name']) ?></h2>
                            <p class="muted m-0">
                                <span class="badge bg-light text-dark"><?= ucfirst($selectedUser['role']) ?></span> 
                                <?= htmlspecialchars($selectedUser['email']) ?>
                            </p>
                        </div>
                        <div class="text-right no-print">
                            <form method="GET" style="display:inline-block;">
                                <input type="hidden" name="id" value="<?= $uid ?>">
                                <select name="year" class="input input-sm" onchange="this.form.submit()">
                                    <?php if(empty($yearsList)): ?>
                                        <option value="">ไม่มีข้อมูลปี</option>
                                    <?php else: foreach($yearsList as $y): ?>
                                        <option value="<?= $y ?>" <?= ($filter_year==$y)?'selected':'' ?>>ปีการศึกษา <?= $y ?></option>
                                    <?php endforeach; endif; ?>
                                </select>
                            </form>
                            <a href="admin_user_stats_print.php?uid=<?= $uid ?>&year=<?= $filter_year ?>" target="_blank" class="btn btn-sm btn-secondary ml-2">
                                <i class="bi bi-printer"></i> Print
                            </a>
                        </div>
                    </div>

                    <div class="summary-card">
                        <h4 class="mb-3">📌 สถานะงานปัจจุบัน (ปี <?= $filter_year ?>)</h4>
                        <div class="workflow-container">
                            <div class="workflow-step">
                                <div class="step-icon icon-pending"><i class="bi bi-clock-history"></i></div>
                                <strong>รอตรวจ</strong>
                                <div class="text-warning font-bold"><?= $workflowStats['pending']['count'] ?> รายการ</div>
                            </div>
                            <div class="workflow-step">
                                <div class="step-icon icon-deputy"><i class="bi bi-person-check"></i></div>
                                <strong>ผ่านขั้นต้น</strong>
                                <div class="text-primary font-bold"><?= $workflowStats['deputy']['count'] ?> รายการ</div>
                            </div>
                            <div class="workflow-step" style="border-color:#10b981; background:#ecfdf5;">
                                <div class="step-icon icon-dean"><i class="bi bi-check-circle-fill"></i></div>
                                <strong>อนุมัติแล้ว</strong>
                                <div class="text-success font-bold"><?= number_format($workflowStats['dean']['hours'], 2) ?> ชม.</div>
                            </div>
                        </div>
                        <?php if ($workflowStats['reject']['count'] > 0): ?>
                            <div class="alert error mt-3 text-center">
                                <i class="bi bi-exclamation-triangle"></i> มีงานถูกตีกลับ <strong><?= $workflowStats['reject']['count'] ?> รายการ</strong>
                                <a href="admin_user_workloads.php?user_id=<?= $uid ?>" class="text-danger underline ml-2">ไปจัดการ</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="summary-card">
                        <h4 class="mb-4 border-bottom pb-2">📊 สรุปภาระงานรายด้าน (รวมทุกสถานะ)</h4>
                        <div class="grid grid-2" style="gap: 30px; align-items: center;">
                            <div>
                                <ul style="list-style:none; padding:0;">
                                <?php foreach ($mainAreaNames as $id => $name): ?>
                                    <li class="stack-between py-2 border-bottom-dashed">
                                        <span><i class="bi bi-dot"></i> <?= $name ?></span>
                                        <strong><?= number_format($hours[$id], 2) ?></strong>
                                    </li>
                                <?php endforeach; ?>
                                </ul>
                                <div class="stack-between pt-3 mt-2 border-top">
                                    <span class="text-primary font-bold">รวมทั้งหมด</span>
                                    <span class="text-primary font-bold text-xl"><?= number_format($totalHours, 2) ?></span>
                                </div>
                            </div>
                            <div style="height: 220px; position: relative;">
                                <canvas id="userAreaChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <?php
                        $required = 1330; // เกณฑ์ขั้นต่ำ
                        $percent = ($totalHours / $required) * 100;
                        $colorClass = ($percent >= 100) ? 'success' : (($percent >= 80) ? 'warning' : 'danger');
                    ?>
                    <div class="summary-card">
                        <h4 class="mb-3">🎯 ผลการประเมิน (เทียบเกณฑ์ <?= number_format($required) ?> ชม.)</h4>
                        <div class="mb-2 stack-between">
                            <span>ความคืบหน้า</span>
                            <span class="text-<?= $colorClass ?> font-bold"><?= number_format($percent, 1) ?>%</span>
                        </div>
                        <div style="background:#eee; height:15px; border-radius:10px; overflow:hidden;">
                            <div style="width:<?= min(100, $percent) ?>%; height:100%;" class="bg-<?= $colorClass ?>"></div>
                        </div>
                        <p class="text-center mt-3 text-muted">
                            <?= ($percent >= 100) ? 'ผ่านเกณฑ์เรียบร้อย (ดีมาก)' : 'ยังต่ำกว่าเกณฑ์ที่กำหนด' ?>
                        </p>
                    </div>

                    <div class="text-center mt-4 mb-5 no-print">
                        <a href="admin_user_workloads.php?user_id=<?= $uid ?>&year=<?= $filter_year ?>" class="btn btn-primary btn-lg">
                            <i class="bi bi-search"></i> ตรวจสอบรายการภาระงานคนนี้
                        </a>
                    </div>

                    <script>
                        new Chart(document.getElementById('userAreaChart'), {
                            type: 'doughnut',
                            data: {
                                labels: <?= json_encode(array_values($mainAreaNames)) ?>,
                                datasets: [{
                                    data: <?= json_encode(array_values($hours)) ?>,
                                    backgroundColor: ['#3b82f6', '#ef4444', '#f59e0b', '#10b981', '#8b5cf6', '#6b7280'],
                                    borderWidth: 0
                                }]
                            },
                            options: { plugins: { legend: { position: 'right' } } }
                        });
                    </script>

                <?php else: ?>
                    <div class="card p-6 text-center">
                        <i class="bi bi-person-x text-muted" style="font-size:3rem;"></i>
                        <h3 class="mt-3">ไม่พบข้อมูลบุคลากร</h3>
                        <a href="admin_dashboard.php" class="btn btn-primary mt-2">กลับหน้าหลัก</a>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>
</div>
</body>
</html>