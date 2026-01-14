<?php
// public/review_manager.php

require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// 1. ตรวจสอบสิทธิ์ Manager
if ($user['role'] !== 'manager') { header("Location: index.php"); exit; }

// 2. CSRF Token
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf_token = $_SESSION['csrf_token'];

// --- Filters ---
$filter_status = $_GET['status'] ?? 'pending'; // 'pending' ในมุม Manager คือรอ Manager อนุมัติ
$filter_year   = $_GET['year'] ?? '';
$filter_term   = $_GET['term'] ?? '';
$filter_cat    = $_GET['cat'] ?? '';

// สร้างเงื่อนไข SQL
$where = [];

// --- แก้ไขจุดที่ 1: ปรับชื่อสถานะให้ตรงกับ System Workflow ---
if ($filter_status == 'approved') {
    // รายการที่ Manager อนุมัติไปแล้ว
    $where[] = "wi.status = 'approved'"; 
} elseif ($filter_status == 'rejected') {
    $where[] = "wi.status = 'rejected'";
} elseif ($filter_status == 'all') {
    // ดูทั้งหมด (Verified, Approved, Rejected)
    $where[] = "wi.status IN ('verified', 'approved', 'rejected')";
} else {
    // Default: pending (รายการที่ผ่านด่านแรกมาแล้ว = verified)
    $where[] = "wi.status = 'verified'"; 
}
// -------------------------------------------------------

// กรองอื่นๆ
if (!empty($filter_year)) $where[] = "wi.academic_year = " . intval($filter_year);
if (!empty($filter_term)) $where[] = "wi.term_id = " . intval($filter_term);
if (!empty($filter_cat))  $where[] = "wc.main_area = " . intval($filter_cat);

$whereClause = count($where) > 0 ? implode(" AND ", $where) : "1=1";

// Query Data
$sql = "
    SELECT wi.*, u.name AS user_name, wc.name_th AS category_name, wc.code AS category_code
    FROM workload_items wi
    JOIN users u ON wi.user_id = u.id
    JOIN workload_categories wc ON wi.category_id = wc.id
    WHERE $whereClause
    ORDER BY wi.updated_at DESC
";
$result = $conn->query($sql);
$yearsQuery = $conn->query("SELECT DISTINCT academic_year FROM workload_items ORDER BY academic_year DESC");
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ตรวจภาระงาน (ผู้บริหาร)</title>
    <link rel="stylesheet" href="../medui/medui.css">
    <link rel="stylesheet" href="../medui/medui.components.css">
    <link rel="stylesheet" href="../medui/medui.layout.css">
    <link rel="stylesheet" href="../medui/medui.theme.medical.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        .filter-bar { 
            display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; align-items: center; 
            background: #fff; padding: 12px; border-radius: 8px; border: 1px solid #eee; 
        }
        .tabs { display: flex; gap: 10px; margin-bottom: 15px; border-bottom: 1px solid #ddd; }
        .tabs a { 
            padding: 10px 20px; text-decoration: none; color: #666; font-weight: 500; 
            border-bottom: 3px solid transparent; 
        }
        .tabs a.active { 
            color: var(--primary); border-bottom-color: var(--primary); font-weight: bold; background: #f9fafb;
        }
        .btn-action-group { display: flex; gap: 5px; justify-content: center; }
        .table-wrap { width: 100%; overflow-x: auto; }
        .table { width: 100%; }
    </style>
</head>
<body>
<div class="app">
    <?php include '../inc/nav.php'; ?>

    <div class="app-content">
        <header class="topbar">
            <div class="container stack-between">
                <div>
                    <h3 class="m-0">ตรวจภาระงาน (ผู้บริหาร)</h3>
                    <p class="muted m-0">ตรวจสอบและอนุมัติขั้นสุดท้าย</p>
                </div>
                <a href="admin_dashboard.php" class="btn btn-outline">กลับ Dashboard</a>
            </div>
        </header>

        <main class="main">
            <div style="padding: 0 25px; width: 100%;">
                <?php include '../inc/alert.php'; ?>

                <div class="tabs">
                    <?php 
                        function getLink($st) {
                            global $filter_year, $filter_term, $filter_cat;
                            return "?status=$st" . ($filter_year?"&year=$filter_year":'') . ($filter_term?"&term=$filter_term":'') . ($filter_cat?"&cat=$filter_cat":'');
                        }
                    ?>
                    <a href="<?= getLink('pending') ?>" class="<?= $filter_status=='pending'?'active':'' ?>">รออนุมัติ</a>
                    <a href="<?= getLink('approved') ?>" class="<?= $filter_status=='approved'?'active':'' ?>">อนุมัติแล้ว</a>
                    <a href="<?= getLink('rejected') ?>" class="<?= $filter_status=='rejected'?'active':'' ?>">ไม่อนุมัติ</a>
                    <a href="<?= getLink('all') ?>" class="<?= $filter_status=='all'?'active':'' ?>">ทั้งหมด</a>
                </div>

                <form method="GET" class="filter-bar">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                    <strong class="text-muted"><i class="bi bi-funnel"></i> กรอง:</strong>

                    <select name="year" class="input input-sm" style="width:auto;" onchange="this.form.submit()">
                        <option value="">- ปี -</option>
                        <?php while($y = $yearsQuery->fetch_assoc()): ?>
                            <option value="<?= $y['academic_year'] ?>" <?= $filter_year == $y['academic_year'] ? 'selected' : '' ?>><?= $y['academic_year'] ?></option>
                        <?php endwhile; ?>
                    </select>

                    <select name="term" class="input input-sm" style="width:auto;" onchange="this.form.submit()">
                        <option value="">- เทอม -</option>
                        <option value="1" <?= $filter_term == '1' ? 'selected' : '' ?>>1</option>
                        <option value="2" <?= $filter_term == '2' ? 'selected' : '' ?>>2</option>
                    </select>

                    <select name="cat" class="input input-sm" style="width:auto;" onchange="this.form.submit()">
                        <option value="">- ด้านงาน -</option>
                        <option value="1" <?= $filter_cat == '1' ? 'selected' : '' ?>>1. สอน</option>
                        <option value="2" <?= $filter_cat == '2' ? 'selected' : '' ?>>2. วิจัย</option>
                        <option value="3" <?= $filter_cat == '3' ? 'selected' : '' ?>>3. บริการ</option>
                        <option value="4" <?= $filter_cat == '4' ? 'selected' : '' ?>>4. ศิลปฯ</option>
                        <option value="5" <?= $filter_cat == '5' ? 'selected' : '' ?>>5. บริหาร</option>
                    </select>

                    <?php if($filter_year || $filter_term || $filter_cat): ?>
                        <a href="review_manager.php?status=<?= $filter_status ?>" class="btn btn-sm text-danger" style="text-decoration:none;">ล้างค่า</a>
                    <?php endif; ?>
                </form>

                <div class="card table-card" style="width:100%;">
                    <div class="table-wrap">
                        <table class="table table-row-hover" style="width:100%;">
                            <thead>
                                <tr>
                                    <th width="15%">ผู้ส่ง</th>
                                    <th width="20%">หมวดงาน</th>
                                    <th width="35%">ชื่องาน / หลักฐาน</th>
                                    <th width="10%" class="text-center">สถานะ</th>
                                    <th width="15%" class="text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($row['user_name']) ?></strong>
                                    </td>
                                    
                                    <td>
                                        <span class="badge" style="background:#f3f4f6; color:#333; border:1px solid #e5e7eb;">
                                            <?= htmlspecialchars($row['category_code']) ?>
                                        </span>
                                        <div class="text-sm mt-1 text-muted"><?= htmlspecialchars($row['category_name']) ?></div>
                                    </td>

                                    <td>
                                        <div class="font-bold text-dark"><?= htmlspecialchars($row['title']) ?></div>
                                        <?php if (!empty($row['attachment_link'])): ?>
                                            <div class="mt-1">
                                                <a href="<?= htmlspecialchars($row['attachment_link']) ?>" target="_blank" class="text-sm" style="color:var(--primary); text-decoration:none;">
                                                    <i class="bi bi-link-45deg"></i> ดูหลักฐานแนบ
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center">
                                        <?php 
                                            // --- แก้ไขจุดที่ 2: Badge ---
                                            $st = $row['status'];
                                            if ($st == 'verified') echo '<span class="badge info">รออนุมัติ</span>';
                                            elseif ($st == 'approved') echo '<span class="badge success">อนุมัติแล้ว</span>';
                                            elseif ($st == 'rejected') echo '<span class="badge danger">ไม่อนุมัติ</span>';
                                            else echo '<span class="badge warning">รอตรวจ</span>';
                                        ?>
                                    </td>

                                    <td class="text-center">
                                        <div class="btn-action-group">
                                            <a href="review_view_manager.php?id=<?= $row['id'] ?>" class="btn btn-sm  btn-primary" title="ดูรายละเอียด">
                                                <i class="bi bi-eye"></i>
                                            </a>

                                            <?php if ($st == 'verified'): // --- แก้ไขจุดที่ 3: ปุ่มแสดงเมื่อสถานะ verified --- ?>
                                                <button onclick="approveWork(<?= $row['id'] ?>)" class="btn btn-sm btn-success" title="อนุมัติ">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                                <button onclick="openRejectModal(<?= $row['id'] ?>)" class="btn btn-sm btn-warning" title="ไม่อนุมัติ">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">ไม่พบข้อมูล</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>
</div>

<div class="modal" id="rejectModal">
    <div class="modal-content" style="max-width:400px;">
        <span class="close" onclick="closeRejectModal()">&times;</span>
        <h3 class="text-danger m-0 mb-3">ระบุเหตุผลไม่อนุมัติ</h3>
        <form method="POST" action="review_action_manager.php">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="workload_id" id="rejectWorkloadId">
            <input type="hidden" name="action" value="reject">
            
            <div class="mb-3">
                <textarea name="comment" rows="3" class="input w-full" required placeholder="เหตุผล..."></textarea>
            </div>
            
            <div class="text-right stack-between">
                <button type="button" class="btn btn-muted" onclick="closeRejectModal()">ยกเลิก</button>
                <button type="submit" class="btn btn-danger">ยืนยัน</button>
            </div>
        </form>
    </div>
</div>

<script>
const csrfToken = "<?= $csrf_token ?>";

function approveWork(id) {
    if(!confirm('ยืนยันการอนุมัติ (Final)?')) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'review_action_manager.php';
    
    const params = { workload_id: id, action: 'approve', csrf_token: csrfToken };
    for(let key in params){
        let inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = key;
        inp.value = params[key];
        form.appendChild(inp);
    }
    document.body.appendChild(form);
    form.submit();
}

function openRejectModal(id) {
    document.getElementById('rejectWorkloadId').value = id;
    document.getElementById('rejectModal').classList.add("show");
}
function closeRejectModal() {
    document.getElementById('rejectModal').classList.remove("show");
}
</script>
</body>
</html>