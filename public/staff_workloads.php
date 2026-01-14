<?php
// public/staff_workloads.php


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

// --- 3. เตรียม Query ---
$sql = "
    SELECT wi.*, wc.name_th AS category_name, wc.code AS category_code, wc.main_area
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

// --- 5. Logic กรองปี/เทอม/ด้าน ---
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

$sql .= " ORDER BY COALESCE(wi.updated_at, wi.created_at) DESC";

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

// --- 7. นับจำนวน ---
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

$mainAreaNames = [
    1 => "งานประจำ", 2 => "พัฒนางาน", 3 => "บริการวิชาการ", 
    4 => "ทำนุบำรุงฯ", 5 => "กิจกรรม ม.", 6 => "บริหาร/อื่นๆ"
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ภาระงานของฉัน (สายสนับสนุน)</title>
    <link rel="stylesheet" href="../medui/medui.css">
    <link rel="stylesheet" href="../medui/medui.components.css">
    <link rel="stylesheet" href="../medui/medui.layout.css">
    <link rel="stylesheet" href="../medui/medui.theme.medical.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    
    <style>
        body { font-size: 16px; background-color: #f8fafc; }

        /* ✅ Full Width Settings */
        .app-content { padding: 0 !important; }
        .container {
            max-width: 100% !important; 
            width: 100% !important;
            padding-left: 25px; 
            padding-right: 25px; 
        }
        
        .table th, .table td { padding: 15px !important; vertical-align: middle !important; }
        .table th { background-color: #f1f5f9 !important; font-weight: 600; color: #475569; font-size: 0.95rem; }
        
        /* Tabs Style */
        .tabs-container {
            display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0;
            overflow-x: auto; white-space: nowrap;
        }
        .tab-item {
            padding: 12px 24px; font-size: 1rem; font-weight: 500; color: #64748b;
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

        /* Filter Bar (Compact) */
        .filter-bar {
            display: flex; gap: 10px; align-items: center; background: #fff; padding: 12px; 
            border-radius: 8px; border:1px solid #eee; margin-bottom: 20px; flex-wrap: wrap;
        }
        .input-sm {
            padding: 6px 12px; font-size: 0.95rem; border-radius: 4px; border: 1px solid #ddd; width: auto;
        }
        
        /* Action Buttons Group */
        .btn-action-group { display: flex; gap: 5px; justify-content: center; }
        .btn-icon {
            width: 34px; height: 34px; display: flex; align-items: center; justify-content: center;
            border-radius: 6px; border: 1px solid transparent; transition: all 0.2s;
        }
        .btn-icon:hover { transform: translateY(-2px); }
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
                            <i class="bi bi-briefcase"></i> ภาระงาน (สายสนับสนุน)
                        </h2>
                    </div>
                    <div class="right" style="display:flex; gap:10px; align-items:center;">
                        <div class="score-box no-print" style="background:var(--primary-50); padding:8px 15px; border-radius:50px; font-weight:600; color:var(--primary-700); border:1px solid var(--primary-100);">
                            รวม: <?= number_format($totalScore, 2) ?> คะแนน
                        </div>
                        
                        <a href="print_workloads.php?year=<?= $filter_year ?>&term=<?= $filter_term ?>&cat=<?= $filter_cat ?>" target="_blank" class="btn btn-outline btn-sm no-print">
                            <i class="bi bi-printer"></i>
                        </a>

                        <a href="staff_workload_add.php" class="btn btn-primary btn-sm no-print shadow-sm">
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
                    <a href="staff_workloads.php?tab=all&year=<?= $filter_year ?>&term=<?= $filter_term ?>&cat=<?= $filter_cat ?>" class="tab-item <?= $current_tab == 'all' ? 'active' : '' ?>">
                        ทั้งหมด <span class="tab-count"><?= $counts['all'] ?></span>
                    </a>
                    <a href="staff_workloads.php?tab=pending&year=<?= $filter_year ?>&term=<?= $filter_term ?>&cat=<?= $filter_cat ?>" class="tab-item <?= $current_tab == 'pending' ? 'active' : '' ?>">
                        <i class="bi bi-hourglass-split text-warning"></i> รอตรวจสอบ <span class="tab-count"><?= $counts['pending'] ?></span>
                    </a>
                    <a href="staff_workloads.php?tab=approved&year=<?= $filter_year ?>&term=<?= $filter_term ?>&cat=<?= $filter_cat ?>" class="tab-item <?= $current_tab == 'approved' ? 'active' : '' ?>">
                        <i class="bi bi-check-circle-fill text-success"></i> อนุมัติแล้ว <span class="tab-count"><?= $counts['approved'] ?></span>
                    </a>
                    <a href="staff_workloads.php?tab=rejected&year=<?= $filter_year ?>&term=<?= $filter_term ?>&cat=<?= $filter_cat ?>" class="tab-item <?= $current_tab == 'rejected' ? 'active' : '' ?>">
                        <i class="bi bi-x-circle-fill text-danger"></i> แก้ไข <span class="tab-count"><?= $counts['rejected'] ?></span>
                    </a>
                </div>

                <form method="GET" class="filter-bar no-print">
                    <input type="hidden" name="tab" value="<?= $current_tab ?>">
                    <strong class="text-muted mr-1" style="font-size:0.9rem;"><i class="bi bi-funnel"></i> กรอง:</strong>
                    
                    <select name="year" class="input-sm" onchange="this.form.submit()">
                        <option value="">- ปี -</option>
                        <?php while($y = $yearsQuery->fetch_assoc()): ?>
                            <option value="<?= $y['academic_year'] ?>" <?= $filter_year == $y['academic_year'] ? 'selected' : '' ?>>
                                <?= $y['academic_year'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    
                    <select name="term" class="input-sm" onchange="this.form.submit()">
                        <option value="">- รอบ -</option>
                        <option value="1" <?= $filter_term == '1' ? 'selected' : '' ?>>รอบ 1</option>
                        <option value="2" <?= $filter_term == '2' ? 'selected' : '' ?>>รอบ 2</option>
                    </select>

                    <select name="cat" class="input-sm" style="width:auto;" onchange="this.form.submit()">
                        <option value="">- ด้านภาระงาน -</option>
                        <?php foreach ($mainAreaNames as $key => $name): ?>
                            <option value="<?= $key ?>" <?= $filter_cat == $key ? 'selected' : '' ?>>
                                <?= $key ?>. <?= $name ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if($filter_year || $filter_term || $filter_cat): ?>
                        <a href="staff_workloads.php?tab=<?= $current_tab ?>" class="btn btn-sm text-danger" style="text-decoration:none; padding: 4px 8px;">ล้างค่า</a>
                    <?php endif; ?>
                </form>

                <div class="card table-card shadow-sm border-0" style="width:100%; border-radius:12px; overflow:hidden;">
                    <div class="table-wrap">
                        <table class="table table-hover mb-0" style="width:100%;">
                            <thead>
                                <tr>
                                    <th style="width:8%">ปี/รอบ</th>
                                    <th style="width:12%">ด้าน</th>
                                    <th style="width:20%">หมวดงาน</th>
                                    <th style="width:30%">รายละเอียดงาน</th>
                                    <th style="width:8%" class="text-right">คะแนน</th>
                                    <th style="width:10%" class="text-center">สถานะ</th>
                                    <th class="no-print text-center" style="width:12%">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($data_rows)): ?>
                                <?php foreach($data_rows as $row): 
                                    $area = (int)$row['main_area']; 
                                    
                                    // ✅ แก้ไข: ใช้ function_exists เช็ค mb_substr
                                    $desc = $row['description'];
                                    $short_desc = function_exists('mb_substr') ? mb_substr($desc, 0, 100) : substr($desc, 0, 100);
                                ?>
                                <tr>
                                    <td><span class="text-muted font-weight-bold"><?= $row['academic_year'] ?>/<?= $row['term_id'] ?></span></td>
                                    <td><span class="badge-cat"><?= $mainAreaNames[$area] ?? '-' ?></span></td>
                                    <td>
                                        <div class="text-dark font-weight-bold" style="font-size:0.9rem;"><?= htmlspecialchars($row['category_name']); ?></div>
                                        <small class="text-muted"><?= $row['category_code'] ?></small>
                                    </td>
                                    <td>
                                        <div class="text-primary font-weight-bold" style="font-size:1.05rem;"><?= htmlspecialchars($row['title']) ?></div>
                                        <div class="text-muted small text-truncate" style="max-width:300px;">
                                            <?= htmlspecialchars(str_replace("\n", ", ", $short_desc)) ?>...
                                        </div>
                                    </td>
                                    <td class="text-right"><strong class="text-dark" style="font-size:1.15rem;"><?= number_format($row['computed_hours'], 2); ?></strong></td>
                                    <td class="text-center">
                                        <?php 
                                            $st = $row['status'];
                                            if ($st == 'approved_final') echo '<span class="badge success px-3 py-1">อนุมัติแล้ว</span>';
                                            elseif ($st == 'approved_admin') echo '<span class="badge info px-3 py-1">ผ่าน Admin</span>';
                                            elseif ($st == 'rejected') echo '<span class="badge danger px-3 py-1">แก้ไข</span>';
                                            else echo '<span class="badge warning px-3 py-1">รอตรวจ</span>';
                                        ?>
                                    </td>
                                    <td class="no-print text-center">
                                        <div class="btn-action-group">
                                            <a href="workload_view.php?id=<?= $row['id']; ?>" class="btn-icon btn-view" title="ดูรายละเอียด">
                                                <i class="bi bi-eye"></i>
                                            </a>

                                            <?php if ($row['status'] !== 'approved_final'): ?>
                                                <a href="workload_edit.php?id=<?= $row['id']; ?>" class="btn-icon btn-edit" title="แก้ไข">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                
                                                <button type="button" class="btn-icon btn-delete delete-btn" 
                                                        data-id="<?= $row['id']; ?>" 
                                                        data-name="<?= htmlspecialchars($row['title']); ?>"
                                                        title="ลบ">
                                                    <i class="bi bi-trash"></i>
                                                </button>
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

<div class="modal" id="deleteModal">
    <div class="modal-content" style="max-width:400px; text-align:center; padding:30px; border-radius:16px;">
        <div class="mb-3 text-danger"><i class="bi bi-exclamation-circle" style="font-size:3rem;"></i></div>
        <h3 class="text-dark mb-2">ยืนยันการลบ?</h3>
        <p class="text-muted mb-4">คุณต้องการลบรายการ <br><strong id="deleteName" class="text-dark"></strong><br>ใช่หรือไม่? การกระทำนี้ไม่สามารถย้อนกลับได้</p>
        <form method="POST" action="workload_delete.php">
            <input type="hidden" name="id" id="deleteId">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
            <input type="hidden" name="redirect_to" value="staff_workloads.php">
            
            <div class="stack-between gap-3">
                <button type="button" class="btn btn-light w-full" id="cancelDelete">ยกเลิก</button>
                <button type="submit" name="delete" class="btn btn-danger w-full">ยืนยันลบ</button>
            </div>
        </form>
    </div>
</div>

<script>
const modal = document.getElementById("deleteModal");
const deleteId = document.getElementById("deleteId");
const deleteName = document.getElementById("deleteName");

document.addEventListener('click', function(e) {
    if (e.target.closest('.delete-btn')) {
        const btn = e.target.closest('.delete-btn');
        deleteId.value = btn.dataset.id;
        deleteName.textContent = btn.dataset.name;
        modal.classList.add("show");
    }
});

document.getElementById("cancelDelete").onclick = () => modal.classList.remove("show");
</script>

</body>
</html>