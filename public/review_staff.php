<?php
// public/review_staff.php

require_once '../config/app.php';
require_once '../middleware/require_login.php';

if ($user['role'] !== 'admin' && $user['role'] !== 'staff_lead') {
    header("Location: index.php?error=AccessDenied");
    exit;
}

require_once '../config/db.php';

// CSRF
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf_token = $_SESSION['csrf_token'];

// --- Helper Function: นับจำนวน ---
function countWorkload($conn, $statusGroup) {
    $sql = "SELECT COUNT(*) as cnt 
            FROM workload_items wi
            JOIN workload_categories wc ON wi.category_id = wc.id
            WHERE wc.target_group IN ('staff', 'both') ";
            
    if ($statusGroup == 'pending') {
        $sql .= "AND wi.status = 'pending'";
    } elseif ($statusGroup == 'approved') {
        // [แก้ไข] นับรวม approved_admin และ approved (Final)
        $sql .= "AND wi.status IN ('approved_admin', 'approved')";
    } elseif ($statusGroup == 'rejected') {
        $sql .= "AND wi.status = 'rejected'";
    }
    
    $res = $conn->query($sql);
    return $res->fetch_assoc()['cnt'] ?? 0;
}

$cntPending  = countWorkload($conn, 'pending');
$cntApproved = countWorkload($conn, 'approved');
$cntRejected = countWorkload($conn, 'rejected');

// --- Filters ---
$filter_status = $_GET['status'] ?? 'pending';
$filter_year   = $_GET['year'] ?? '';
$filter_cat    = $_GET['cat'] ?? '';

$where = [];

// [แก้ไข] เงื่อนไขการกรองสถานะ
if ($filter_status !== 'all') {
    if ($filter_status == 'approved') {
        // แสดงทั้งที่ผ่าน Admin แล้ว และผ่าน Manager แล้ว
        $where[] = "wi.status IN ('approved_admin', 'approved')";
    } elseif ($filter_status == 'rejected') {
        $where[] = "wi.status = 'rejected'";
    } else {
        $where[] = "wi.status = 'pending'";
    }
}

if (!empty($filter_year)) $where[] = "wi.academic_year = " . intval($filter_year);
if (!empty($filter_cat))  $where[] = "wc.main_area = " . intval($filter_cat);

$whereClause = count($where) > 0 ? implode(" AND ", $where) : "1=1";

$sql = "
    SELECT wi.*, u.name AS user_name, wc.name_th AS category_name, wc.code AS category_code
    FROM workload_items wi
    JOIN users u ON wi.user_id = u.id
    JOIN workload_categories wc ON wi.category_id = wc.id  
    WHERE $whereClause
    AND wc.target_group IN ('staff', 'both')  
    ORDER BY wi.updated_at DESC, wi.created_at DESC
";
$result = $conn->query($sql);
$yearsQuery = $conn->query("SELECT DISTINCT academic_year FROM workload_items ORDER BY academic_year DESC");
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ตรวจภาระงาน (สายสนับสนุน)</title>
    <link rel="stylesheet" href="../medui/medui.css">
    <link rel="stylesheet" href="../medui/medui.components.css">
    <link rel="stylesheet" href="../medui/medui.layout.css">
    <link rel="stylesheet" href="../medui/medui.theme.medical.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        .filter-bar { background: #fff; padding: 12px; border-radius: 8px; border: 1px solid #eee; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 20px; }
        .tabs { display: flex; gap: 5px; margin-bottom: 15px; border-bottom: 1px solid #ddd; }
        .tabs a { 
            padding: 10px 15px; text-decoration: none; color: #666; font-weight: 500; 
            border-bottom: 3px solid transparent; display: flex; align-items: center; gap: 6px;
        }
        .tabs a:hover { background: #f9fafb; }
        .tabs a.active { color: var(--primary); border-bottom-color: var(--primary); font-weight: bold; background: #eff6ff; }
        .badge-count { background: #e2e8f0; color: #475569; font-size: 0.75rem; padding: 1px 6px; border-radius: 10px; }
        .active .badge-count { background: var(--primary); color: white; }
        .badge-verified { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
    </style>
</head>
<body>
<div class="app">
    <?php include '../inc/nav.php'; ?>

    <div class="app-content">
        <header class="topbar">
            <div class="container stack-between">
                <div>
                    <h3 class="m-0"><i class="bi bi-people-fill text-primary"></i> ตรวจภาระงาน (สายสนับสนุน)</h3>
                    <p class="muted m-0">ตรวจสอบความถูกต้องเบื้องต้น (Staff Lead)</p>
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
                            global $filter_year, $filter_cat;
                            return "?status=$st" . ($filter_year?"&year=$filter_year":'') . ($filter_cat?"&cat=$filter_cat":'');
                        }
                    ?>
                    <a href="<?= getLink('pending') ?>" class="<?= $filter_status=='pending'?'active':'' ?>">
                        <i class="bi bi-hourglass-split"></i> รอตรวจ 
                        <?php if($cntPending>0): ?><span class="badge-count"><?= $cntPending ?></span><?php endif; ?>
                    </a>
                    <a href="<?= getLink('approved') ?>" class="<?= $filter_status=='approved'?'active':'' ?>">
                        <i class="bi bi-check-circle"></i> ตรวจแล้ว 
                        <?php if($cntApproved>0): ?><span class="badge-count"><?= $cntApproved ?></span><?php endif; ?>
                    </a>
                    <a href="<?= getLink('rejected') ?>" class="<?= $filter_status=='rejected'?'active':'' ?>">
                        <i class="bi bi-x-circle"></i> ส่งคืน
                        <?php if($cntRejected>0): ?><span class="badge-count"><?= $cntRejected ?></span><?php endif; ?>
                    </a>
                    <a href="<?= getLink('all') ?>" class="<?= $filter_status=='all'?'active':'' ?>">
                        <i class="bi bi-list"></i> ทั้งหมด
                    </a>
                </div>

                <form method="GET" class="filter-bar">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                    <strong class="text-muted"><i class="bi bi-funnel"></i> ตัวกรอง:</strong>

                    <select name="year" class="input input-sm" style="width:auto;" onchange="this.form.submit()">
                        <option value="">- ปีงบประมาณ -</option>
                        <?php while($y = $yearsQuery->fetch_assoc()): ?>
                            <option value="<?= $y['academic_year'] ?>" <?= $filter_year == $y['academic_year'] ? 'selected' : '' ?>>
                                <?= $y['academic_year'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>

                    <select name="cat" class="input input-sm" style="width:auto;" onchange="this.form.submit()">
                        <option value="">- หมวดงาน -</option>
                        <option value="6" <?= $filter_cat == '6' ? 'selected' : '' ?>>งานประจำ</option>
                        <option value="7" <?= $filter_cat == '7' ? 'selected' : '' ?>>เชิงกลยุทธ์</option>
                        <option value="8" <?= $filter_cat == '8' ? 'selected' : '' ?>>งานมอบหมาย</option>
                        <option value="9" <?= $filter_cat == '9' ? 'selected' : '' ?>>พัฒนาตนเอง</option>
                    </select>

                    <?php if($filter_year || $filter_cat): ?>
                        <a href="review_staff.php?status=<?= $filter_status ?>" class="btn btn-sm text-danger" style="text-decoration:none;">
                            <i class="bi bi-x"></i> ล้างค่า
                        </a>
                    <?php endif; ?>
                </form>

                <div class="card table-card" style="width:100%;">
                    <div class="table-wrap">
                        <table class="table table-row-hover" style="width:100%;">
                            <thead>
                                <tr>
                                    <th width="15%">ผู้ส่ง / เวลา</th>
                                    <th width="15%">หมวดงาน</th>
                                    <th width="30%">ชื่องาน</th>
                                    <th width="15%" class="text-center">คะแนน</th>
                                    <th width="10%" class="text-center">สถานะ</th>
                                    <th width="15%" class="text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($row['user_name']) ?></strong>
                                        <div class="text-muted text-sm">
                                            <?= date('d/m/y H:i', strtotime($row['created_at'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge" style="background:#f3f4f6; color:#333; border:1px solid #e5e7eb;">
                                            <?= htmlspecialchars($row['category_code']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="font-bold text-dark"><?= htmlspecialchars($row['title']) ?></div>
                                        <?php if (!empty($row['attachment_link'])): ?>
                                            <a href="<?= htmlspecialchars($row['attachment_link']) ?>" target="_blank" class="text-sm" style="color:var(--primary); text-decoration:none;">
                                                <i class="bi bi-paperclip"></i> หลักฐานแนบ
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center font-bold text-primary">
                                        <?= number_format($row['computed_hours'], 2) ?>
                                    </td>
                                    <td class="text-center">
                                        <?php 
                                            $st = $row['status'];
                                            if ($st == 'pending') echo '<span class="badge warning">รอตรวจสอบ</span>';
                                            elseif ($st == 'approved_admin') echo '<span class="badge-verified"><i class="bi bi-check"></i> ผ่านขั้นต้น</span>';
                                            elseif ($st == 'approved') echo '<span class="badge success">อนุมัติแล้ว</span>';
                                            elseif ($st == 'rejected') echo '<span class="badge danger">ส่งคืน</span>';
                                            else echo '<span class="badge">'.$st.'</span>';
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-action-group">
                                            <a href="review_view.php?id=<?= $row['id'] ?>&back=review_staff" class="btn btn-sm btn-outline btn-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($st == 'pending'): ?>
                                                <button onclick="approveWork(<?= $row['id'] ?>)" class="btn btn-sm btn-success">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                                <button onclick="openRejectModal(<?= $row['id'] ?>)" class="btn btn-sm btn-warning">
                                                    <i class="bi bi-arrow-counterclockwise"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center py-5 text-muted">ไม่พบข้อมูลในสถานะนี้</td></tr>
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
    <div class="modal-content" style="max-width:400px; border-radius:12px;">
        <span class="close" onclick="closeRejectModal()">&times;</span>
        <h3 class="text-danger m-0 mb-3">ส่งคืนแก้ไข</h3>
        <form method="POST" action="review_action_staff.php">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="id" id="rejectWorkloadId">
            <input type="hidden" name="action" value="reject">
            <textarea name="comment" rows="4" class="input w-full" placeholder="ระบุเหตุผล..." required></textarea>
            <div class="text-right stack-between mt-3">
                <button type="button" class="btn btn-muted" onclick="closeRejectModal()">ยกเลิก</button>
                <button type="submit" class="btn btn-danger">ยืนยัน</button>
            </div>
        </form>
    </div>
</div>

<script>
const csrfToken = "<?= $csrf_token ?>";
function approveWork(id) {
    if(!confirm('ยืนยันว่ารายการนี้ถูกต้อง?')) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'review_action_staff.php';
    form.innerHTML = `<input type="hidden" name="id" value="${id}"><input type="hidden" name="action" value="verify"><input type="hidden" name="csrf_token" value="${csrfToken}">`;
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