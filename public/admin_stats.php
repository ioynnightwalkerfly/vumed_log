<?php
// public/admin_stats.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// 1. จำกัดสิทธิ์
if (!in_array($user['role'], ['admin', 'manager'])) {
    header("Location: index.php?error=สิทธิ์ไม่เพียงพอ");
    exit;
}

// 2. กำหนดเป้าหมาย (Config)
$GOAL_YEAR = 1330; // เป้าหมายต่อปี (ปรับได้ตามจริง)

// 3. คำนวณภาพรวมทั้งระบบ (KPIs)
$totalUsers = 0;
$passCount = 0;
$failCount = 0;
$totalHoursSystem = 0;

// ดึงข้อมูลสรุปรายคน (Group by User)
// นับเฉพาะสถานะที่อนุมัติแล้ว (approved_admin, approved_final)
$sql = "
    SELECT 
        u.id, u.name, u.email,
        SUM(CASE WHEN wi.status IN ('approved_admin', 'approved_final') THEN wi.computed_hours ELSE 0 END) as total_hours,
        SUM(CASE WHEN wi.status = 'pending' THEN wi.computed_hours ELSE 0 END) as pending_hours
    FROM users u
    LEFT JOIN workload_items wi ON u.id = wi.user_id
    WHERE u.role = 'user'
    GROUP BY u.id
    ORDER BY total_hours DESC
";
$result = $conn->query($sql);

// เก็บข้อมูลใส่ Array เพื่อนำไปแสดงผลและคำนวณยอดรวม
$usersData = [];
while($row = $result->fetch_assoc()) {
    $hours = floatval($row['total_hours']);
    $pending = floatval($row['pending_hours']);
    $percent = ($hours / $GOAL_YEAR) * 100;
    
    $totalHoursSystem += $hours;
    if ($percent >= 100) {
        $passCount++;
    } else {
        $failCount++;
    }
    
    $usersData[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'email' => $row['email'],
        'total' => $hours,
        'pending' => $pending,
        'percent' => $percent
    ];
}
$totalUsers = count($usersData);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานความสำเร็จ | MedUI System</title>
    <link rel="stylesheet" href="../medui/medui.css">
    <link rel="stylesheet" href="../medui/medui.components.css">
    <link rel="stylesheet" href="../medui/medui.layout.css">
    <link rel="stylesheet" href="../medui/medui.theme.medical.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        .progress-wrapper { background: #e0e0e0; border-radius: 4px; height: 10px; width: 100px; overflow: hidden; display: inline-block; vertical-align: middle; margin-right: 8px; }
        .progress-fill { height: 100%; background: var(--success); border-radius: 4px; transition: width 0.3s; }
        .progress-fill.mid { background: var(--warning); }
        .progress-fill.low { background: var(--danger); }
        
        .kpi-card { text-align: center; padding: 20px; background: #fff; border-radius: 12px; border: 1px solid #eee; }
        .kpi-num { font-size: 2.5rem; font-weight: 700; line-height: 1.2; }
        .kpi-label { color: #666; font-size: 0.9rem; }
    </style>
</head>
<body>

<div class="app">
    <?php include '../inc/nav.php'; ?>

    <header class="topbar">
        <div class="left">
            <h3 style="margin:0;">รายงานสรุปความสำเร็จ</h3>
            <p class="muted" style="margin:0;">เปรียบเทียบภาระงานรายบุคคลกับเกณฑ์มาตรฐาน</p>
        </div>
        <div class="right">
            <a href="admin_stats_print.php" class="btn btn-outline" target="_blank">
                <i class="bi bi-printer"></i> พิมพ์รายงาน
            </a>
        </div>
    </header>

    <main class="main">
        
        <div class="grid grid-4 mb-6" style="gap: 20px;">
            <div class="kpi-card">
                <div class="kpi-num text-primary"><?= $totalUsers ?></div>
                <div class="kpi-label">อาจารย์ทั้งหมด (คน)</div>
            </div>
            <div class="kpi-card" style="background: #f0fdf4; border-color: #bbf7d0;">
                <div class="kpi-num text-success"><?= $passCount ?></div>
                <div class="kpi-label">ผ่านเกณฑ์แล้ว (คน)</div>
            </div>
            <div class="kpi-card" style="background: #fef2f2; border-color: #fecaca;">
                <div class="kpi-num text-danger"><?= $failCount ?></div>
                <div class="kpi-label">ยังไม่ถึงเกณฑ์ (คน)</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-num"><?= number_format($totalHoursSystem) ?></div>
                <div class="kpi-label">ชั่วโมงรวมทั้งคณะ (ชม.)</div>
            </div>
        </div>

        <div class="card table-card">
            <div class="card-header p-4 border-bottom stack-between">
                <div>
                    <h4 class="m-0">สถานะการผ่านเกณฑ์รายบุคคล</h4>
                    <small class="muted">เกณฑ์ขั้นต่ำ: <strong><?= number_format($GOAL_YEAR) ?></strong> ชั่วโมง/ปี</small>
                </div>
                <input type="text" id="searchUser" class="input input-sm" placeholder="🔍 ค้นหาชื่อ..." style="width: 220px;">
            </div>
            
            <div class="table-wrap">
                <table class="table table-row-hover" id="statsTable">
                    <thead>
                        <tr>
                            <th>ชื่อ-นามสกุล</th>
                            <th class="text-right">ชั่วโมงสะสม</th>
                            <th class="text-center">ความคืบหน้า</th>
                            <th class="text-center">สถานะ</th>
                            <th class="text-center">รอตรวจสอบ</th>
                            <th class="text-center">เพิ่มเติม</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($usersData as $u): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($u['name']) ?></strong>
                            </td>
                            <td class="text-right">
                                <span style="font-size: 1.1rem; font-weight: 600;">
                                    <?= number_format($u['total'], 2) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php
                                    $pct = min(100, $u['percent']);
                                    $colorClass = 'low';
                                    if ($pct >= 100) $colorClass = ''; // สีเขียว (default css var success)
                                    elseif ($pct >= 50) $colorClass = 'mid'; // สีเหลือง
                                ?>
                                <div style="display:flex; align-items:center; justify-content:center;">
                                    <div class="progress-wrapper">
                                        <div class="progress-fill <?= $colorClass ?>" style="width: <?= $pct ?>%;"></div>
                                    </div>
                                    <span style="font-size: 0.85rem; width: 45px; text-align: left;">
                                        <?= number_format($u['percent'], 0) ?>%
                                    </span>
                                </div>
                            </td>
                            <td class="text-center">
                                <?php if ($u['percent'] >= 100): ?>
                                    <span class="badge approved">ผ่านเกณฑ์</span>
                                <?php else: ?>
                                    <span class="badge rejected">
                                        ขาด <?= number_format($GOAL_YEAR - $u['total'], 0) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($u['pending'] > 0): ?>
                                    <span class="text-warning" title="รอตรวจ">+<?= number_format($u['pending'], 2) ?></span>
                                <?php else: ?>
                                    <span class="muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <a href="admin_user_stats.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline" title="ดูรายละเอียด">
                                    <i class="bi bi-graph-up"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<script>
// ระบบค้นหา
document.getElementById('searchUser').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('#statsTable tbody tr');
    rows.forEach(row => {
        let text = row.cells[0].textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>

</body>
</html>