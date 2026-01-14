<?php
// public/admin_dashboard.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// 1. ตรวจสอบสิทธิ์ (Admin/Manager เท่านั้น)
if (!in_array($user['role'], ['admin', 'manager'])) {
    header("Location: index.php?error=สิทธิ์ไม่เพียงพอ");
    exit;
}

// =============================================
// A. ส่วนดึงข้อมูลสรุป (KPIs)
// =============================================
$summary = ['total_users'=>0, 'total_items'=>0, 'pending'=>0, 'approved'=>0];

// นับจำนวนบุคลากร
$r = $conn->query("SELECT COUNT(*) as c FROM users"); 
$summary['total_users'] = $r->fetch_assoc()['c'];

// นับจำนวนภาระงานและสถานะ
$r = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status='approved_final' THEN 1 ELSE 0 END) as approved
    FROM workload_items
");
$row = $r->fetch_assoc();
$summary['total_items'] = $row['total'];
$summary['pending'] = $row['pending'];
$summary['approved'] = $row['approved'];


//  [เพิ่มใหม่] E. ส่วน System Monitor
// =============================================

// 1. นับคนออนไลน์ (Active ใน 10 นาทีล่าสุด)
$online_threshold = 10; // นาที
$sql_online = "SELECT COUNT(*) as c FROM users WHERE last_activity > NOW() - INTERVAL $online_threshold MINUTE";
$online_users = $conn->query($sql_online)->fetch_assoc()['c'];

// 2. คำนวณขนาด Database (MB)
$sql_size = "
    SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
    FROM information_schema.TABLES 
    WHERE table_schema = '$dbname'
";
$db_size = $conn->query($sql_size)->fetch_assoc()['size_mb'] ?? 0;

// 3. คำนวณขนาดไฟล์ Uploads (MB)
$upload_dir = '../uploads/';
$file_size = 0;
if (is_dir($upload_dir)) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($upload_dir)) as $file) {
        $file_size += $file->getSize();
    }
}
$file_size_mb = round($file_size / 1024 / 1024, 2);
$total_usage_mb = $db_size + $file_size_mb;


// =============================================
// B. ส่วนจัดอันดับ (Top 5 Users)
// =============================================
$sql_top = "
    SELECT u.name, SUM(wi.computed_hours) as total_hours
    FROM users u
    JOIN workload_items wi ON u.id = wi.user_id
    WHERE wi.status IN ('approved_admin', 'approved_final') 
    GROUP BY u.id
    ORDER BY total_hours DESC
    LIMIT 5
";
$topUsers = $conn->query($sql_top);

// =============================================
// C. ข้อมูลกราฟ (Area Distribution)
// =============================================
$areaData = array_fill(1, 6, 0);
$sql_area = "
    SELECT wc.main_area, SUM(wi.computed_hours) as h
    FROM workload_items wi
    JOIN workload_categories wc ON wi.category_id = wc.id
    WHERE wi.status IN ('approved_admin', 'approved_final')
    GROUP BY wc.main_area
";
$res_area = $conn->query($sql_area);
while($row = $res_area->fetch_assoc()) {
    $areaData[(int)$row['main_area']] = (float)$row['h'];
}
$areaLabels = ["การสอน", "วิจัย/วิชาการ", "บริการวิชาการ", "ทำนุบำรุงฯ", "บริหาร", "อื่นๆ"];

// =============================================
// D. ดึงรายชื่อผู้ใช้ทั้งหมด (สำหรับใส่ใน Dropdown)
// =============================================
$sql_users_dd = "SELECT id, name, role FROM users ORDER BY name ASC";
$allUsers = $conn->query($sql_users_dd);
$userList = [];
while($u = $allUsers->fetch_assoc()) {
    $userList[] = $u;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Executive Dashboard</title>
    <link rel="stylesheet" href="../medui/medui.css">
    <link rel="stylesheet" href="../medui/medui.components.css">
    <link rel="stylesheet" href="../medui/medui.layout.css">
    <link rel="stylesheet" href="../medui/medui.theme.medical.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* ลบ CSS ที่ไปบังคับโครงสร้างหลักออกแล้ว */
        body { font-size: 16px; background-color: #f8f9fa; }

        /* 1. Topbar Action Bar */
        .admin-action-bar {
            background: #fff; padding: 8px 15px; border-radius: 8px;
            display: flex; gap: 10px; align-items: center; border: 1px solid #e0e0e0;
        }
        .user-select {
            padding: 8px 12px; border: 1px solid #ccc; border-radius: 6px;
            font-size: 1rem; min-width: 280px;
        }

        /* 2. Smart Analysis Banner */
        .analysis-banner {
            background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
            border-radius: 16px; padding: 35px; color: white;
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
            margin-bottom: 30px; position: relative; overflow: hidden;
        }
        .analysis-banner::before {
            content: ''; position: absolute; top: -50px; right: -50px; width: 250px; height: 250px;
            background: rgba(255,255,255,0.1); border-radius: 50%;
        }
        .analysis-controls {
            background: rgba(255,255,255,0.2); padding: 15px; border-radius: 12px;
            backdrop-filter: blur(10px); display: flex; gap: 10px; align-items: center;
            border: 1px solid rgba(255,255,255,0.3);
        }
        .user-select-dark {
            background: transparent; border: none; color: white; width: 100%; font-size: 1.15rem;
            border-bottom: 1px solid rgba(255,255,255,0.5); padding-bottom: 5px;
        }
        .user-select-dark option { color: #333; }
        .btn-analyze {
            background: white; color: #4f46e5; font-weight: bold; border: none;
            padding: 12px 25px; border-radius: 8px; transition: all 0.2s; white-space: nowrap; font-size: 1.1rem;
        }
        .btn-analyze:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }

        /* 3. KPI Cards */
        .kpi-card { 
            background: #fff; padding: 25px; border-radius: 16px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 20px;
            transition: transform 0.2s; border: 1px solid #eee;
        }
        .kpi-card:hover { transform: translateY(-5px); }
        .kpi-icon { width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; }

        /* 4. Chart & Rank Styles */
        .rank-item { display: flex; align-items: center; padding: 15px 0; border-bottom: 1px dashed #eee; }
        .rank-num { width: 35px; height: 35px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 15px; color: #64748b; }
        .rank-1 { background: #fef3c7; color: #d97706; } 
        .rank-2 { background: #f1f5f9; color: #475569; } 
        .rank-3 { background: #ffedd5; color: #c2410c; }
        .chart-container { position: relative; height: 300px; width: 100%; display:flex; justify-content:center; }
    </style>
</head>
<body>

<div class="app">
    <?php include '../inc/nav.php'; ?>

    <div class="app-content">

        <header class="topbar">
            <div class="container">
                <div class="topbar-content stack-between">
                    <div class="topbar-left">
                        <h3 style="margin:0; font-weight:bold;">Executive Dashboard</h3>
                        <p class="muted" style="margin:0;">ภาพรวมระบบบริหารภาระงาน</p>
                    </div>

                    <div class="card p-4 mb-4 border-primary" style="border-left: 5px solid #0d6efd;">
    <div class="stack-between align-center">
        <div>
            <h4 class="mb-1 text-primary"><i class="bi bi-database-fill-gear"></i> ระบบสำรองข้อมูล (Backup)</h4>
            <p class="muted mb-0">ดาวน์โหลดฐานข้อมูลเก็บไว้</p>
        </div>
        <a href="admin_backup_action.php" class="btn btn-primary btn-lg shadow-sm">
            <i class="bi bi-download"></i> ดาวน์โหลด Backup (.sql)
        </a>
    </div>
</div>
                    
                    <div class="topbar-right">
                        <div class="admin-action-bar">
                            <i class="bi bi-search text-muted"></i>
                            
                            <select id="selectedUserHeader" class="user-select">
                                <option value="">-- ค้นหา/เลือกบุคลากร --</option>
                                <?php foreach($userList as $u): ?>
                                    <option value="<?= $u['id'] ?>">
                                        <?= htmlspecialchars($u['name']) ?> (<?= ucfirst($u['role']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <button onclick="goToPage('stats')" class="btn btn-sm btn-outline" title="ดูสถิติรายบุคคล">
                                <i class="bi bi-bar-chart"></i> สถิติ
                            </button>
                            <button onclick="goToPage('workloads')" class="btn btn-sm btn-primary" title="ตรวจรายการภาระงาน">
                                <i class="bi bi-check2-square"></i> ตรวจงาน
                            </button>
                        </div>

                        <a href="admin_stats_print.php" class="btn btn-sm btn-muted ml-2" target="_blank" title="พิมพ์รายงานภาพรวม">
                            <i class="bi bi-printer"></i>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <main class="main">
            <div style="padding: 0 25px; width: 100%;">
                
                <div class="analysis-banner grid grid-2" style="align-items: center; gap: 40px; margin-top: 20px;">
                    <div>
                        <h2 class="m-0 text-white" style="font-size: 2.2rem; font-weight:bold;">
                            <i class="bi bi-stars"></i> ข้อมูลเชิงลึกด้านประสิทธิภาพ
                        </h2>
                        <p style="opacity: 0.9; margin-top: 10px; font-size: 1.2rem; line-height: 1.6;">
                            ระบบวิเคราะห์ศักยภาพ ช่วยประเมินจุดแข็งและจุดที่ควรพัฒนาของบุคลากรรายบุคคล
                        </p>
                    </div>
                    <div>
                        <form action="admin_user_insight.php" method="GET" class="analysis-controls">
                            <i class="bi bi-person-bounding-box text-white pl-2" style="font-size:1.5rem;"></i>
                            <div style="flex:1;">
                                <small class="text-white" style="opacity:0.8;">เลือกบุคลากรที่ต้องการวิเคราะห์:</small>
                                <select name="uid" class="user-select-dark" required>
                                    <option value="" style="color:#aaa;">-- คลิกเพื่อเลือกรายชื่อ --</option>
                                    <?php foreach($userList as $u): ?>
                                        <option value="<?= $u['id'] ?>">
                                            <?= htmlspecialchars($u['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn-analyze">
                                <i class="bi bi-magic"></i> วิเคราะห์ผล
                            </button>
                        </form>
                    </div>
                </div>

                <h4 class="mb-3 text-muted" style="font-size:1.2rem;">ภาพรวมระบบประจำปี</h4>
                <div class="grid grid-4 mb-6" style="gap: 25px;">
                    <div class="kpi-card">
                        <div class="kpi-icon" style="background:#eff6ff; color:#3b82f6;"><i class="bi bi-people-fill"></i></div>
                        <div>
                            <h2 style="margin:0; font-size:2rem;"><?= number_format($summary['total_users']) ?></h2>
                            <span class="muted">บุคลากรทั้งหมด</span>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon" style="background:#f5f3ff; color:#8b5cf6;"><i class="bi bi-stack"></i></div>
                        <div>
                            <h2 style="margin:0; font-size:2rem;"><?= number_format($summary['total_items']) ?></h2>
                            <span class="muted">รายการภาระงาน</span>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon" style="background:#fff7ed; color:#f97316;"><i class="bi bi-clock-history"></i></div>
                        <div>
                            <h2 style="margin:0; font-size:2rem;"><?= number_format($summary['pending']) ?></h2>
                            <span class="muted">รอตรวจสอบ</span>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon" style="background:#ecfdf5; color:#10b981;"><i class="bi bi-check-circle-fill"></i></div>
                        <div>
                            <h2 style="margin:0; font-size:2rem;"><?= number_format($summary['approved']) ?></h2>
                            <span class="muted">อนุมัติสมบูรณ์</span>
                        </div>
                    </div>
                </div>

                

                <div class="grid grid-2 mb-6" style="gap: 30px; align-items: stretch;">
                    
                    <div class="card p-6" style="border-radius:16px;">
                        <h4 class="mb-4 text-center"><i class="bi bi-pie-chart-fill text-primary"></i> สัดส่วนภาระงานรายด้าน (อนุมัติแล้ว)</h4>
                        <div class="chart-container">
                            <canvas id="areaChart"></canvas>
                        </div>
                    </div>

                    <div class="card p-6" style="border-radius:16px;">
                        <h4 class="mb-4"><i class="bi bi-trophy-fill text-warning"></i> 5 อันดับภาระงานสูงสุด</h4>
                        <div class="ranking-list">
                            <?php 
                            $rank = 1;
                            if ($topUsers->num_rows > 0):
                                while($u = $topUsers->fetch_assoc()): 
                                    $rankClass = ($rank <= 3) ? "rank-$rank" : "";
                            ?>
                            <div class="rank-item">
                                <div class="rank-num <?= $rankClass ?>"><?= $rank ?></div>
                                <div style="flex:1;">
                                    <strong style="font-size:1.1rem;"><?= htmlspecialchars($u['name']) ?></strong>
                                </div>
                                <div class="text-right">
                                    <strong class="text-primary" style="font-size:1.2rem;"><?= number_format($u['total_hours'], 2) ?></strong>
                                    <small class="muted"> ชม.</small>
                                </div>
                            </div>
                            <?php $rank++; endwhile; 
                            else: ?>
                                <div class="text-center py-5 muted">
                                    <i class="bi bi-inbox" style="font-size:3rem; opacity:0.3;"></i>
                                    <p class="mt-2">ยังไม่มีข้อมูลภาระงานที่อนุมัติแล้ว</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>



                <div class="kpi-card" style="border-left: 5px solid #22c55e;">
    <div class="kpi-icon" style="background:#f0fdf4; color:#22c55e;">
        <i class="bi bi-broadcast"></i>
    </div>
    <div>
        <h2 style="margin:0; font-size:2rem;"><?= $online_users ?></h2>
        <span class="muted">กำลังใช้งาน (คน)</span>
        <div style="font-size:0.8rem; color:#888;">ใน 10 นาทีล่าสุด</div>
    </div>
</div>

<div class="kpi-card" style="border-left: 5px solid #64748b;">
    <div class="kpi-icon" style="background:#f1f5f9; color:#64748b;">
        <i class="bi bi-hdd-network"></i>
    </div>
    <div>
        <h2 style="margin:0; font-size:2rem;"><?= $total_usage_mb ?> <span style="font-size:1rem;">MB</span></h2>
        <span class="muted">พื้นที่จัดเก็บรวม</span>
        <div style="font-size:0.8rem; color:#888;">
            DB: <?= $db_size ?> | Files: <?= $file_size_mb ?> MB
        </div>
    </div>
</div>

            </div>
        </main>
    </div>
</div>

<script>
function goToPage(type) {
    const userId = document.getElementById('selectedUserHeader').value;
    if (!userId) {
        alert('กรุณาเลือกบุคลากรที่ต้องการจัดการจาก Dropdown ด้านบนก่อนครับ');
        return;
    }
    if (type === 'stats') {
        window.location.href = 'admin_user_stats.php?id=' + userId;
    } else if (type === 'workloads') {
        window.location.href = 'admin_user_workloads.php?user_id=' + userId;
    }
}

const ctx = document.getElementById('areaChart').getContext('2d');
const areaData = <?= json_encode(array_values($areaData)) ?>;
const areaLabels = <?= json_encode($areaLabels) ?>;

new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: areaLabels,
        datasets: [{
            data: areaData,
            backgroundColor: ['#3b82f6', '#ef4444', '#f59e0b', '#10b981', '#8b5cf6', '#6b7280'],
            borderWidth: 2,
            borderColor: '#ffffff',
            hoverOffset: 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { 
            legend: { position: 'right', labels: { boxWidth: 15, padding: 15, font: { size: 14 } } },
            tooltip: { 
                callbacks: {
                    label: function(context) {
                        let label = context.label || '';
                        if (label) label += ': ';
                        let value = context.raw;
                        let total = context.chart._metasets[context.datasetIndex].total;
                        let percentage = ((value / total) * 100).toFixed(1) + "%";
                        return label + value.toLocaleString() + " คะแนน (" + percentage + ")";
                    }
                }
            }
        }
    }
});
</script>

</body>
</html>