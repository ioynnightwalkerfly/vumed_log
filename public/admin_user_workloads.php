<?php
// public/admin_user_workloads.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// 1. ตรวจสอบสิทธิ์
if (!in_array($user['role'], ['admin', 'manager'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_GET['user_id'] ?? null;
if (!$user_id || !is_numeric($user_id)) {
    header("Location: admin_dashboard.php?error=ไม่พบข้อมูลผู้ใช้");
    exit;
}

// ดึงข้อมูลผู้ใช้
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$userInfo = $stmt->get_result()->fetch_assoc();

if (!$userInfo) {
    header("Location: admin_dashboard.php?error=ไม่พบผู้ใช้นี้");
    exit;
}

// ---------------------------------------------------------
// 2. รับค่าตัวกรอง (Filter Logic)
// ---------------------------------------------------------
$filter_status = $_GET['filter'] ?? 'all';
$filter_year   = $_GET['year'] ?? '';
$filter_term   = $_GET['term'] ?? '';

$validStatus = ['all','pending','approved_admin','approved_final','rejected'];
if (!in_array($filter_status, $validStatus)) $filter_status = 'all';

// 3. สร้าง Query แบบ Dynamic
$sql = "
    SELECT wi.*, wc.name_th AS category_name, wc.main_area
    FROM workload_items wi
    JOIN workload_categories wc ON wi.category_id = wc.id
    WHERE wi.user_id = ?
";

$params = [$user_id];
$types = "i";

// กรองตามสถานะ
if ($filter_status !== 'all') {
    $sql .= " AND wi.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

// กรองตามปีการศึกษา
if (!empty($filter_year)) {
    $sql .= " AND wi.academic_year = ?";
    $params[] = $filter_year;
    $types .= "s";
}

// กรองตามเทอม
if (!empty($filter_term)) {
    $sql .= " AND wi.term_id = ?";
    $params[] = $filter_term;
    $types .= "i";
}

$sql .= " ORDER BY wi.academic_year DESC, wi.term_id DESC, wi.start_date DESC";

// Execute
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$items = $stmt->get_result();

// 4. ดึงรายการปีการศึกษาที่มีของคนนี้ (เพื่อทำ Dropdown)
$yearsQuery = $conn->query("SELECT DISTINCT academic_year FROM workload_items WHERE user_id = $user_id ORDER BY academic_year DESC");

$mainAreaNames = [
    1 => "ด้านการสอน", 2 => "วิจัย/วิชาการ", 3 => "บริการวิชาการ",
    4 => "ทำนุบำรุงฯ", 5 => "บริหาร", 6 => "อื่นๆ"
];

// Helper Function สร้าง Link สำหรับ Tabs
function getTabLink($status, $uid, $y, $t) {
    $link = "?user_id=$uid&filter=$status";
    if ($y) $link .= "&year=$y";
    if ($t) $link .= "&term=$t";
    return $link;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>งานของผู้ใช้: <?= htmlspecialchars($userInfo['name']) ?> | Admin</title>
    <link rel="stylesheet" href="../medui/medui.css">
    <link rel="stylesheet" href="../medui/medui.components.css">
    <link rel="stylesheet" href="../medui/medui.layout.css">
    <link rel="stylesheet" href="../medui/medui.theme.medical.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <style>
        .status-tabs { display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap; }
        .status-tabs a {
            padding:6px 12px; background:#f4f5f7; border-radius:8px; 
            text-decoration:none; color:#444; font-size: 0.9rem; transition: all 0.2s;
        }
        .status-tabs a.active { background:var(--primary); color:white; }
        .status-tabs a:hover:not(.active) { background:#e2e8f0; }

        .filter-bar {
            display: flex; gap: 10px; align-items: center; 
            background: #fff; padding: 12px 16px; 
            border-radius: 8px; border: 1px solid #eee;
            margin-bottom: 16px; flex-wrap: wrap;
        }
        
        /* Print Styles */
        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            body { background: white; font-family: "Sarabun", sans-serif; }
            .app { display: block; } 
            .sidebar, .topbar .left, .topbar .btn-muted, .status-tabs, .btn, .no-print, .filter-bar { display: none !important; }
            .main { padding: 0; margin: 0; }
            .card { box-shadow: none; border: none; padding: 0; }
            .table-card { border: 1px solid #ddd; }
            table { width: 100%; border-collapse: collapse; font-size: 12px; }
            th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
            th { background-color: #f0f0f0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .print-header { display: block !important; text-align: center; margin-bottom: 20px; }
            .print-header h2 { margin: 0; font-size: 18px; }
            .print-header p { margin: 5px 0 0; font-size: 14px; color: #555; }
        }
        .print-header { display: none; }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>

<div class="app">

    <?php include '../inc/nav.php'; ?>

    <div class="app-content">

        <header class="topbar">
            <div class="container">
                <div class="topbar-content">
                    <div class="topbar-left">
                        <h3 style="margin:0;">งานของผู้ใช้</h3>
                        <p class="muted" style="margin:0;"><?= htmlspecialchars($userInfo['name']); ?></p>
                    </div>

                    <div class="right" style="display:flex; gap:8px;">
                        <button onclick="window.print()" class="btn btn-outline btn-sm">
                            <i class="bi bi-printer"></i> พิมพ์
                        </button>
                        <a href="admin_dashboard.php" class="btn btn-sm btn-muted">
                            <i class="bi bi-arrow-left"></i> กลับ
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <main class="main">
            <div class="container">

                <div class="print-header">
                    <h2>รายงานรายการภาระงานรายบุคคล</h2>
                    <p>
                        <strong>ชื่อ:</strong> <?= htmlspecialchars($userInfo['name']); ?> &nbsp;|&nbsp; 
                        <strong>สถานะ:</strong> <?= ucfirst($filter_status) ?> &nbsp;|&nbsp;
                        <strong>ปี/เทอม:</strong> <?= $filter_year ? $filter_year : 'ทั้งหมด' ?>/<?= $filter_term ? $filter_term : 'ทั้งหมด' ?>
                    </p>
                </div>

                <div class="card p-4 mb-4 no-print">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <h4 style="margin:0 0 6px 0;">ข้อมูลผู้ใช้</h4>
                            <p style="margin:0; color:#666;">
                                <i class="bi bi-person"></i> <?= htmlspecialchars($userInfo['name']); ?> &nbsp;
                                <i class="bi bi-envelope"></i> <?= htmlspecialchars($userInfo['email']); ?> &nbsp;
                                <span class="badge"><?= ucfirst($userInfo['role']); ?></span>
                            </p>
                        </div>
                        
                        <div style="display:flex; gap:10px;">
                            <button onclick="window.print()" class="btn btn-primary">
                                <i class="bi bi-printer-fill"></i> พิมพ์หน้านี้
                            </button>
                            
                            <a href="admin_user_stats.php?id=<?= $user_id ?>&year=<?= $filter_year ?>" class="btn btn-outline">
                                <i class="bi bi-bar-chart"></i> ดูสถิติสรุป
                            </a>
                        </div>
                    </div>
                </div>

                <form method="GET" class="filter-bar no-print">
                    <input type="hidden" name="user_id" value="<?= $user_id ?>">
                    <input type="hidden" name="filter" value="<?= $filter_status ?>">

                    <div class="text-muted"><i class="bi bi-funnel"></i> ตัวกรอง:</div>

                    <div>
                        <select name="year" class="input input-sm" onchange="this.form.submit()">
                            <option value="">-- ปีการศึกษา (ทั้งหมด) --</option>
                            <?php while($y = $yearsQuery->fetch_assoc()): ?>
                                <option value="<?= $y['academic_year'] ?>" <?= $filter_year == $y['academic_year'] ? 'selected' : '' ?>>
                                    <?= $y['academic_year'] ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div>
                        <select name="term" class="input input-sm" onchange="this.form.submit()">
                            <option value="">-- ภาคการศึกษา --</option>
                            <option value="1" <?= $filter_term == '1' ? 'selected' : '' ?>>เทอม 1</option>
                            <option value="2" <?= $filter_term == '2' ? 'selected' : '' ?>>เทอม 2</option>
                            <option value="3" <?= $filter_term == '3' ? 'selected' : '' ?>>ภาคฤดูร้อน</option>
                        </select>
                    </div>

                    <?php if($filter_year || $filter_term): ?>
                        <a href="admin_user_workloads.php?user_id=<?= $user_id ?>&filter=<?= $filter_status ?>" class="btn btn-sm btn-link text-danger" style="text-decoration:none;">ล้างค่า</a>
                    <?php endif; ?>
                </form>

                <div class="status-tabs no-print">
                    <a href="<?= getTabLink('all', $user_id, $filter_year, $filter_term) ?>" class="<?= $filter_status=='all'?'active':'' ?>">ทั้งหมด</a>
                    <a href="<?= getTabLink('pending', $user_id, $filter_year, $filter_term) ?>" class="<?= $filter_status=='pending'?'active':'' ?>">รอตรวจ</a>
                    <a href="<?= getTabLink('approved_admin', $user_id, $filter_year, $filter_term) ?>" class="<?= $filter_status=='approved_admin'?'active':'' ?>">รอบเจ้าหน้าที่</a>
                    <a href="<?= getTabLink('approved_final', $user_id, $filter_year, $filter_term) ?>" class="<?= $filter_status=='approved_final'?'active':'' ?>">หัวหน้าอนุมัติ</a>
                    <a href="<?= getTabLink('rejected', $user_id, $filter_year, $filter_term) ?>" class="<?= $filter_status=='rejected'?'active':'' ?>">ปฏิเสธ</a>
                </div>

                <div class="card table-card">
                    <div class="table-wrap">
                        <table class="table table-row-hover">
                            <thead>
                                <tr>
                                    <th style="width:10%">ปี/เทอม</th>
                                    <th style="width:15%">ด้าน</th>
                                    <th style="width:20%">ประเภท</th>
                                    <th style="width:30%">รายการ</th>
                                    <th style="width:10%">ชม.</th>
                                    <th style="width:10%">สถานะ</th>
                                    <th class="no-print" style="width:5%"></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($items->num_rows > 0): ?>
                                <?php while($row = $items->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <span class="text-muted small">
                                            <?= $row['academic_year'] ?>/<?= $row['term_id'] ?>
                                        </span>
                                    </td>
                                    <td><?= $mainAreaNames[$row['main_area']] ?? '-' ?></td>
                                    <td><?= htmlspecialchars($row['category_name']); ?></td>
                                    <td><?= htmlspecialchars($row['title']); ?></td>
                                    <td style="font-weight:bold;">
                                        <?= number_format($row['computed_hours'], 2); ?>
                                    </td>
                                    <td>
                                        <span class="badge 
                                            <?= $row['status']=='approved_final'?'approved':
                                            ($row['status']=='approved_admin'?'pending':
                                            ($row['status']=='rejected'?'rejected':'pending')) ?>">
                                            <?= 
                                            $row['status']=='approved_final'?'อนุมัติแล้ว':
                                            ($row['status']=='approved_admin'?'รอผู้บริหาร':
                                            ($row['status']=='rejected'?'แก้/ปฏิเสธ':'รอตรวจ')) ?>
                                        </span>
                                    </td>
                                    <td class="no-print">
                                        <a href="review_view.php?id=<?= $row['id']; ?>" class="btn btn-sm btn-icon text-primary" title="ดูรายละเอียด">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center muted">ไม่มีรายการภาระงานในหมวดนี้</td>
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