<?php
// public/workloads.php


require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// 1. CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// --- 2. รับค่า Tab และตัวกรอง ---
$current_tab = $_GET['tab'] ?? 'all';
$filter_year = $_GET['year'] ?? '';
$filter_term = $_GET['term'] ?? '';
$filter_cat  = $_GET['cat'] ?? ''; 

// --- 3. เตรียม Query พื้นฐาน ---
$sql = "
    SELECT wi.*, wc.name_th AS category_name, wc.main_area, wc.code AS category_code
    FROM workload_items wi
    LEFT JOIN workload_categories wc ON wi.category_id = wc.id
    WHERE wi.user_id = ?
";
$params = [$user['id']];
$types = "i";

// --- 4. Logic กรองตาม Tab ---
switch ($current_tab) {
    case 'pending':
        $sql .= " AND wi.status = 'pending'";
        break;
    case 'approved':
        $sql .= " AND wi.status IN ('approved_admin', 'approved_final')";
        break;
    case 'rejected':
        $sql .= " AND wi.status = 'rejected'";
        break;
}

// --- 5. Logic กรองตาม ปี / เทอม / ด้าน ---
if (!empty($filter_year)) {
    $sql .= " AND wi.academic_year = ?";
    $params[] = $filter_year;
    $types .= "s";
}
if (!empty($filter_term)) {
    $sql .= " AND wi.term_id = ?";
    $params[] = $filter_term;
    $types .= "i";
}
if (!empty($filter_cat)) {
    $sql .= " AND wc.main_area = ?";
    $params[] = $filter_cat;
    $types .= "i";
}

// เรียงลำดับ
$sql .= " ORDER BY COALESCE(wi.updated_at, wi.created_at) DESC";

// Execute Main Query
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$items = $stmt->get_result();

// --- 6. คำนวณยอดรวม ---
$totalScore = 0;
$data_rows = [];
while($row = $items->fetch_assoc()) {
    $totalScore += $row['computed_hours'];
    $data_rows[] = $row;
}

// --- 7. หาจำนวนนับ (Count) ---
$countSql = "SELECT status, COUNT(*) as cnt FROM workload_items WHERE user_id = ? GROUP BY status";
$stmtCount = $conn->prepare($countSql);
$stmtCount->bind_param("i", $user['id']);
$stmtCount->execute();
$resCount = $stmtCount->get_result();

$counts = ['all' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
while($r = $resCount->fetch_assoc()) {
    $st = $r['status'];
    $cnt = $r['cnt'];
    $counts['all'] += $cnt; 
    
    if ($st == 'pending') $counts['pending'] += $cnt;
    elseif ($st == 'rejected') $counts['rejected'] += $cnt;
    elseif ($st == 'approved_admin' || $st == 'approved_final') $counts['approved'] += $cnt;
}

// ดึงปีการศึกษา
$yearsQuery = $conn->query("SELECT DISTINCT academic_year FROM workload_items WHERE user_id = {$user['id']} ORDER BY academic_year DESC");

$mainAreaNames = [1 => "ด้านการสอน", 2 => "วิจัย", 3 => "บริการวิชาการ", 4 => "ทำนุบำรุงฯ", 5 => "บริหาร", 6 => "อื่นๆ"];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ภาระงานของฉัน</title>
    <link rel="stylesheet" href="../medui/medui.css">
    <link rel="stylesheet" href="../medui/medui.components.css">
    <link rel="stylesheet" href="../medui/medui.layout.css">
    <link rel="stylesheet" href="../medui/medui.theme.medical.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    
    <style>
        body { font-size: 16px; background-color: #f8fafc; }
        
        /* Layout เต็มจอ */
        .app-content { padding: 0 !important; }
        .container { max-width: 100% !important; width: 100% !important; padding: 0 25px; }

        .table th, .table td { padding: 15px !important; vertical-align: middle !important; }
        .table th { background-color: #f1f5f9 !important; font-weight: 600; color: #475569; font-size: 0.9rem; }
        
        /* Tabs */
        .tabs-container {
            display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0;
            overflow-x: auto; white-space: nowrap;
        }
        .tab-item {
            padding: 10px 20px; font-size: 1rem; font-weight: 500; color: #64748b;
            text-decoration: none; border-bottom: 3px solid transparent; transition: all 0.2s;
            display: flex; align-items: center; gap: 8px;
        }
        .tab-item:hover { color: var(--primary); background: #f1f5f9; border-radius: 8px 8px 0 0; }
        .tab-item.active {
            color: var(--primary); border-bottom-color: var(--primary); background: #fff;
            border-radius: 8px 8px 0 0; font-weight: 600;
        }
        .tab-count { background: #e2e8f0; color: #64748b; font-size: 0.75rem; padding: 2px 8px; border-radius: 12px; }
        .tab-item.active .tab-count { background: var(--primary-100); color: var(--primary-700); }

        /* Filter Bar */
        .filter-bar { 
            display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; align-items: center; 
            background: #fff; padding: 12px; border-radius: 8px; border: 1px solid #eee; 
        }
        .input-sm {
            padding: 6px 12px; font-size: 0.95rem; border-radius: 4px; border: 1px solid #ddd; width: auto;
        }
        
        /* Action Buttons */
        .btn-action-group { display: flex; gap: 6px; justify-content: center; align-items: center; }
        .btn-icon {
            width: 34px; height: 34px; display: flex; align-items: center; justify-content: center;
            border-radius: 6px; border: 1px solid transparent; transition: all 0.2s; cursor: pointer;
            text-decoration: none;
        }
        .btn-icon:hover { transform: translateY(-2px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        
        .btn-view { color: #2e7d32; background: #e8f5e9; border-color: #c8e6c9; }
        .btn-edit { color: #1565c0; background: #e3f2fd; border-color: #bbdefb; }
        .btn-delete { color: #c62828; background: #ffebee; border-color: #ffcdd2; }
        
        .badge-cat { font-size: 0.8rem; padding: 4px 8px; border-radius: 4px; background: #e2e8f0; color: #475569; }

        @media print { .no-print { display: none !important; } }
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
                        <h2 class="text-primary" style="margin:0; font-size:1.8rem; font-weight:700;">
                            <i class="bi bi-briefcase"></i> ภาระงานของฉัน
                        </h2>
                    </div>
                    <div class="right" style="display:flex; gap:10px; align-items:center;">
                        <div class="score-box no-print" style="background:var(--primary-50); padding:8px 15px; border-radius:50px; font-weight:600; color:var(--primary-700); border:1px solid var(--primary-100);">
                            รวม: <?= number_format($totalScore, 2) ?> คะแนน
                        </div>
                        <a href="print_workloads.php?year=<?= $filter_year ?>&term=<?= $filter_term ?>" target="_blank" class="btn btn-outline btn-sm no-print">
                            <i class="bi bi-printer"></i>
                        </a>
                        <a href="workload_select.php" class="btn btn-primary btn-sm no-print shadow-sm">
                            <i class="bi bi-plus-lg"></i> เพิ่มงานใหม่
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <main class="main">
            <div class="container">
                
                <?php include '../inc/alert.php'; ?>
                
                <div class="tabs-container no-print">
                    <a href="workloads.php?tab=all&year=<?= $filter_year ?>&term=<?= $filter_term ?>&cat=<?= $filter_cat ?>" 
                       class="tab-item <?= $current_tab == 'all' ? 'active' : '' ?>">
                        ทั้งหมด <span class="tab-count"><?= $counts['all'] ?></span>
                    </a>
                    <a href="workloads.php?tab=pending&year=<?= $filter_year ?>&term=<?= $filter_term ?>&cat=<?= $filter_cat ?>" 
                       class="tab-item <?= $current_tab == 'pending' ? 'active' : '' ?>">
                        <i class="bi bi-hourglass-split text-warning"></i> รอตรวจสอบ <span class="tab-count"><?= $counts['pending'] ?></span>
                    </a>
                    <a href="workloads.php?tab=approved&year=<?= $filter_year ?>&term=<?= $filter_term ?>&cat=<?= $filter_cat ?>" 
                       class="tab-item <?= $current_tab == 'approved' ? 'active' : '' ?>">
                        <i class="bi bi-check-circle-fill text-success"></i> อนุมัติแล้ว <span class="tab-count"><?= $counts['approved'] ?></span>
                    </a>
                    <a href="workloads.php?tab=rejected&year=<?= $filter_year ?>&term=<?= $filter_term ?>&cat=<?= $filter_cat ?>" 
                       class="tab-item <?= $current_tab == 'rejected' ? 'active' : '' ?>">
                        <i class="bi bi-x-circle-fill text-danger"></i> แก้ไข <span class="tab-count"><?= $counts['rejected'] ?></span>
                    </a>
                </div>

                <form method="GET" class="filter-bar no-print">
                    <input type="hidden" name="tab" value="<?= $current_tab ?>">
                    <strong class="text-muted mr-1" style="font-size:0.9rem;"><i class="bi bi-funnel"></i> กรอง:</strong>
                    
                    <select name="year" class="input-sm" onchange="this.form.submit()">
                        <option value="">- ปี -</option>
                        <?php while($y = $yearsQuery->fetch_assoc()): ?>
                            <option value="<?= $y['academic_year'] ?>" <?= $filter_year == $y['academic_year'] ? 'selected' : '' ?>><?= $y['academic_year'] ?></option>
                        <?php endwhile; ?>
                    </select>
                    
                    <select name="term" class="input-sm" onchange="this.form.submit()">
                        <option value="">- เทอม -</option>
                        <option value="1" <?= $filter_term == '1' ? 'selected' : '' ?>>1</option>
                        <option value="2" <?= $filter_term == '2' ? 'selected' : '' ?>>2</option>
                    </select>

                    <select name="cat" class="input-sm" style="width:auto;" onchange="this.form.submit()">
                        <option value="">- ด้านภาระงาน -</option>
                        <option value="1" <?= $filter_cat == '1' ? 'selected' : '' ?>>1. การสอน</option>
                        <option value="2" <?= $filter_cat == '2' ? 'selected' : '' ?>>2. วิจัย</option>
                        <option value="3" <?= $filter_cat == '3' ? 'selected' : '' ?>>3. บริการวิชาการ</option>
                        <option value="4" <?= $filter_cat == '4' ? 'selected' : '' ?>>4. ทำนุบำรุงฯ</option>
                        <option value="5" <?= $filter_cat == '5' ? 'selected' : '' ?>>5. บริหาร</option>
                        <option value="6" <?= $filter_cat == '6' ? 'selected' : '' ?>>6. อื่นๆ</option>
                    </select>

                    <?php if($filter_year || $filter_term || $filter_cat): ?>
                        <a href="workloads.php?tab=<?= $current_tab ?>" class="btn btn-sm text-danger" style="text-decoration:none; padding:4px 8px;">ล้างค่า</a>
                    <?php endif; ?>
                </form>

                <div class="card table-card shadow-sm border-0" style="width:100%; border-radius:12px; overflow:hidden;">
                    <div class="table-wrap">
                        <table class="table table-hover mb-0" style="width:100%;">
                            <thead>
                                <tr>
                                    <th style="width:8%">ปี/เทอม</th>
                                    <th style="width:12%">ด้าน</th>
                                    <th style="width:20%">ประเภท</th>
                                    <th style="width:30%">รายการภาระงาน</th>
                                    <th style="width:10%" class="text-right">คะแนน</th>
                                    <th style="width:10%" class="text-center">สถานะ</th>
                                    <th class="no-print text-center" style="width:10%">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($data_rows)): ?>
                                <?php foreach($data_rows as $row): 
                                    $area = (int)$row['main_area'];
                                    $desc = $row['description'];
                                    $short_desc = function_exists('mb_substr') ? mb_substr($desc, 0, 100) : substr($desc, 0, 100);
                                ?>
                                <tr style="cursor: pointer;" onclick="window.location='workload_view.php?id=<?= $row['id'] ?>'">
                                    <td><span class="text-muted font-weight-bold"><?= $row['academic_year'] ?>/<?= $row['term_id'] ?></span></td>
                                    <td><span class="badge-cat"><?= $mainAreaNames[$area] ?? '-' ?></span></td>
                                    <td>
                                        <div class="text-dark font-weight-bold" style="font-size:0.9rem;"><?= htmlspecialchars($row['category_name']); ?></div>
                                        <small class="text-muted"><?= $row['category_code'] ?></small>
                                    </td>
                                    <td>
                                        <div class="text-primary font-weight-bold" style="font-size:1.05rem;"><?= htmlspecialchars($row['title']) ?></div>
                                        <small class="text-muted text-truncate d-block" style="max-width:300px;">
                                            <?= htmlspecialchars(str_replace("\n", ", ", $short_desc)) ?>...
                                        </small>
                                    </td>
                                    <td class="text-right"><strong class="text-dark" style="font-size:1.15rem;"><?= number_format($row['computed_hours'], 2); ?></strong></td>
                                    <td class="text-center">
                                        <?php 
                                            $st = $row['status'];
                                            if ($st == 'pending') echo '<span class="badge warning px-3 py-1">รอตรวจ</span>';
                                            elseif (strpos($st,'approved')!==false) echo '<span class="badge success px-3 py-1">อนุมัติ</span>';
                                            elseif ($st == 'rejected') echo '<span class="badge danger px-3 py-1">แก้ไข</span>';
                                        ?>
                                    </td>
                                    
                                    <td class="no-print text-center" onclick="event.stopPropagation();">
                                        <div class="btn-action-group">
                                            <a href="workload_view.php?id=<?= $row['id']; ?>" class="btn-icon btn-view" title="ดูรายละเอียด">
                                                <i class="bi bi-eye"></i>
                                            </a>

                                            <?php if ($row['status'] !== 'approved_final'): ?>
                                                <a href="workload_edit.php?id=<?= $row['id']; ?>" class="btn-icon btn-edit" title="แก้ไข">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                
                                                <form method="POST" action="workload_delete.php" style="display:inline;" onsubmit="return confirm('ยืนยันที่จะลบรายการนี้ใช่หรือไม่?');">
                                                    <input type="hidden" name="id" value="<?= $row['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
                                                    <input type="hidden" name="redirect_to" value="workloads.php">
                                                    <button type="submit" class="btn-icon btn-delete" title="ลบ">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                                
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center py-5 text-muted">ไม่พบรายการในหมวดนี้</td></tr>
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