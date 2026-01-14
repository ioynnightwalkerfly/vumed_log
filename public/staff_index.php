<?php
// public/staff_index.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// 1. ตรวจสอบสิทธิ์: ต้องเป็น 'staff' เท่านั้น
if ($user['role'] !== 'staff') {
    if (in_array($user['role'], ['admin', 'manager'])) {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

// 2. ดึงรูปโปรไฟล์
$userImg = !empty($_SESSION['user']['profile_image']) 
    ? "../uploads/" . $_SESSION['user']['profile_image'] 
    : "https://via.placeholder.com/150?text=STAFF";

// 3. กำหนดเกณฑ์สายสนับสนุน
$GOAL_YEAR = 1645; // (35 ชม. x 47 สัปดาห์)

// 4. ดึงสถิติรวม (ปรับให้รองรับ status ที่เป็น 0 หรือ pending)
$stmt = $conn->prepare("
    SELECT 
        SUM(computed_hours) as total_hours,
        COUNT(*) as total_items,
        SUM(CASE WHEN status IN ('pending', '0') THEN 1 ELSE 0 END) as pending_count
    FROM workload_items 
    WHERE user_id = ?
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

$total_hours = $stats['total_hours'] ?? 0;
$percent = ($total_hours > 0) ? ($total_hours / $GOAL_YEAR) * 100 : 0;

// 5. ดึงรายการล่าสุด
$stmtRecent = $conn->prepare("
    SELECT wi.*, wc.name_th AS category_name, wc.main_area
    FROM workload_items wi
    LEFT JOIN workload_categories wc ON wi.category_id = wc.id
    WHERE wi.user_id = ?
    ORDER BY wi.created_at DESC LIMIT 5
");
$stmtRecent->bind_param("i", $user['id']);
$stmtRecent->execute();
$recentItems = $stmtRecent->get_result();

// ชื่อหมวดงานหลัก
$mainAreaNames = [
    1 => "งานประจำ (Routine)",
    2 => "งานพัฒนางาน (Dev)",
    3 => "บริการวิชาการ",
    4 => "ทำนุบำรุงศิลปฯ",
    5 => "งานกิจกรรม (Participation)",
    6 => "งานบริหาร (Admin)"
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>หน้าหลัก (สายสนับสนุน) | MedUI System</title>
    <link rel="stylesheet" href="../medui/medui.css">
    <link rel="stylesheet" href="../medui/medui.components.css">
    <link rel="stylesheet" href="../medui/medui.layout.css">
    <link rel="stylesheet" href="../medui/medui.theme.medical.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        /* ใช้ธีมสีม่วง (Indigo) เพื่อแยกจากสายวิชาการ */
        .staff-banner {
            background: linear-gradient(135deg, #f138e8ff 0%, #e9c1e9ff 100%);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }
        .progress-bg {
            background: rgba(167, 154, 154, 0.2);
            height: 10px;
            border-radius: 5px;
            overflow: hidden;
            margin-top: 12px;
        }
        .progress-bar {
            background: #fff;
            height: 100%;
            transition: width 0.5s;
        }
        
        .stat-card {
            background: #fff; padding: 20px; border-radius: 12px; 
            border: 1px solid #eee; text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .stat-num { font-size: 2rem; font-weight: bold; color: #4f46e5; } 
        
        .user-profile { display:flex; align-items:center; gap:10px; }
        .user-img { width:40px; height:40px; border-radius:50%; object-fit:cover; border:2px solid #eee; }
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
                        <h3 style="margin:0;">หน้าหลัก (สายสนับสนุน)</h3>
                    </div>
                    <div class="topbar-right">
                        <div class="user-profile" style="padding-left:10px; border-left:1px solid #eee;">
                            <div style="text-align:right; line-height:1.2;">
                                <div style="font-weight:bold; font-size:0.9rem;"><?= htmlspecialchars($user['name']) ?></div>
                                <small class="text-muted">เจ้าหน้าที่</small>
                            </div>
                            <img src="<?= $userImg ?>" class="user-img">
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="main">
            <div class="container">
                
                <div class="staff-banner">
                    <div class="grid grid-2" style="align-items:center; gap:20px;">
                        <div style="display:flex; align-items:center; gap:20px;">
                            <img src="<?= $userImg ?>" style="width:80px; height:80px; border-radius:50%; object-fit:cover; border:3px solid rgba(255,255,255,0.5);">
                            <div style="flex:1;">
                                <h2 class="m-0">สวัสดี คุณ<?= htmlspecialchars($user['name']) ?></h2>
                                <p style="opacity:0.9; margin-top:4px;">ระบบประเมินภาระงาน (Support Staff)</p>
                                
                                <div style="margin-top: 15px;">
                                    <div class="stack-between text-sm">
                                        <span>เป้าหมายปีนี้ (<?= number_format($total_hours, 2) ?> / <?= number_format($GOAL_YEAR) ?> ชม.)</span>
                                        <strong><?= number_format($percent, 1) ?>%</strong>
                                    </div>
                                    <div class="progress-bg">
                                        <div class="progress-bar" style="width: <?= min(100, $percent) ?>%;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-right">
                             <a href="staff_workload_select.php" class="btn btn-light text-primary fw-bold btn-lg" style="color: #4f46e5 !important;">
                                <i class="bi bi-plus-circle-fill"></i> บันทึกงานใหม่
                             </a>
                             <div style="margin-top:10px;">
                                <a href="profile.php" style="color:rgba(255,255,255,0.8); text-decoration:underline; font-size:0.9rem;">
                                    <i class="bi bi-person-gear"></i> ข้อมูลส่วนตัว
                                </a>
                             </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-3 mb-6" style="gap:20px;">
                    <div class="stat-card">
                        <div class="stat-num"><?= number_format($total_hours, 2) ?></div>
                        <div class="text-muted">ชั่วโมงสะสมจริง</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-num" style="color:#333;"><?= $stats['total_items'] ?></div>
                        <div class="text-muted">รายการทั้งหมด</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-num text-warning"><?= $stats['pending_count'] ?></div>
                        <div class="text-muted">รอหัวหน้าตรวจสอบ</div>
                    </div>
                </div>

                <div class="card table-card">
                    <div class="card-header p-4 border-bottom stack-between">
                        <h4 class="m-0"><i class="bi bi-clock-history"></i> ประวัติการบันทึกล่าสุด</h4>
                        <a href="staff_workloads.php" class="btn btn-sm btn-outline">ดูทั้งหมด</a>
                    </div>
                    <div class="table-wrap">
                        <table class="table table-row-hover">
                            <thead>
                                <tr>
                                    <th style="width: 20%;">ประเภทงาน</th>
                                    <th style="width: 40%;">รายการ</th>
                                    <th style="width: 10%;">ชม.</th>
                                    <th style="width: 15%;">สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($recentItems->num_rows > 0): ?>
                                <?php while($row = $recentItems->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <?= $mainAreaNames[$row['main_area']] ?? 'อื่นๆ' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-weight:600;"><?= htmlspecialchars($row['title']) ?></div>
                                    </td>
                                   
                                    <td>
                                        <strong><?= number_format($row['computed_hours'], 2) ?></strong>
                                    </td>
                                    <td>
                                        <?php
                                            // ปรับ Logic Status ให้ครอบคลุม
                                            $st = $row['status'];
                                            if ($st == 'approved' || $st == 'approved_final') {
                                                echo '<span class="badge approved">อนุมัติแล้ว</span>';
                                            } elseif ($st == 'rejected') {
                                                echo '<span class="badge rejected">แก้ไข</span>';
                                            } else {
                                                // รวม pending และ 0
                                                echo '<span class="badge pending">รอตรวจ</span>';
                                            }
                                        ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center muted p-5">ยังไม่มีรายการบันทึก</td></tr>
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