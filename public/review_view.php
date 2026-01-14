<?php
// public/review_view.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php'; 

// 1. [แก้ไข] ปรับสิทธิ์: ให้ Admin และ Staff Lead เข้าได้
if (!in_array($user['role'], ['admin', 'staff_lead', 'manager'])) {
    header("Location: index.php?error=AccessDenied");
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: review_admin.php");
    exit;
}

// 2. CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// 3. ดึงข้อมูล
$stmt = $conn->prepare("
    SELECT wi.*, u.name AS user_name, u.role AS owner_role,
           wc.name_th AS category_name, wc.main_area, wc.weight, wc.code, wc.calc_type, wc.target_group
    FROM workload_items wi
    LEFT JOIN workload_categories wc ON wi.category_id = wc.id
    JOIN users u ON wi.user_id = u.id
    WHERE wi.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    header("Location: review_admin.php?error=ไม่พบข้อมูล");
    exit;
}

// [เพิ่ม] กำหนดไฟล์ Action ปลายทางตามบทบาทเจ้าของงาน
// ถ้าเป็นงาน Staff ให้ส่งไป review_action_staff.php, ถ้าเป็น User ให้ส่งไป review_action_admin.php
$actionFile = ($item['target_group'] == 'staff' || $item['owner_role'] == 'staff') 
              ? 'review_action_staff.php' 
              : 'review_action_admin.php';

// [เพิ่ม] ลิงก์ย้อนกลับ
$backLink = ($item['target_group'] == 'staff' || $item['owner_role'] == 'staff')
            ? 'review_staff.php'
            : 'review_admin.php';

// 4. ดึง Log
$stmtLog = $conn->prepare("
    SELECT wl.*, u.name AS reviewer_name 
    FROM workload_logs wl 
    LEFT JOIN users u ON wl.user_id = u.id 
    WHERE wl.work_log_id = ? 
    ORDER BY wl.created_at ASC
");
$stmtLog->bind_param("i", $id);
$stmtLog->execute();
$logs = $stmtLog->get_result()->fetch_all(MYSQLI_ASSOC);

// Helper Function
function safe_substr($str, $start, $length) {
    if (function_exists('mb_substr')) {
        return mb_substr($str, $start, $length, 'UTF-8');
    }
    return substr($str, $start, $length); 
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ตรวจรายละเอียด</title>
    <link rel="stylesheet" href="../medui/medui.css">
    <link rel="stylesheet" href="../medui/medui.components.css">
    <link rel="stylesheet" href="../medui/medui.layout.css">
    <link rel="stylesheet" href="../medui/medui.theme.medical.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8fafc; }
        .detail-card { max-width: 900px; margin: 30px auto; padding: 40px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .timeline { margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
        .timeline-item { position: relative; padding-left: 30px; margin-bottom: 20px; }
        .timeline-item::before { 
            content: ""; position: absolute; left: 6px; top: 6px; width: 12px; height: 12px; 
            background: #cbd5e1; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 0 0 2px #cbd5e1;
        }
        .timeline-date { font-size: 0.85rem; color: #64748b; margin-bottom: 2px; }
        .user-info-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        /* Modal Fix */
        .modal { display: none; background: rgba(0,0,0,0.5); }
        .modal.show { display: flex !important; align-items: center; justify-content: center; }
    </style>
</head>
<body>

<div class="app">
    <?php include '../inc/nav.php'; ?>

    <main class="main">
        <div style="padding: 0 20px;">
            <div class="card detail-card bg-white">
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="m-0 text-primary"><i class="bi bi-file-earmark-check"></i> ตรวจรายละเอียด</h3>
                    <a href="<?= $backLink ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> ย้อนกลับ
                    </a>
                </div>

                <div class="user-info-box">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-circle bg-primary text-white d-flex align-items-center justify-content-center rounded-circle" style="width:50px; height:50px; font-size:1.5rem;">
                            <?= safe_substr($item['user_name'], 0, 1) ?>
                        </div>
                        <div>
                            <div class="text-muted text-sm">ผู้เสนอขอ (<?= ucfirst($item['owner_role']) ?>)</div>
                            <div class="font-bold text-dark" style="font-size:1.2rem;"><?= htmlspecialchars($item['user_name']) ?></div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-muted text-sm mb-1">สถานะปัจจุบัน</div>
                        <?php 
                            $st = $item['status'];
                            if ($st == 'approved') {
                                echo '<span class="badge success px-3 py-2"><i class="bi bi-check-circle"></i> อนุมัติแล้ว</span>';
                            } 
                            elseif ($st == 'verified' || $st == 'approved_admin') { // [แก้ไข] รองรับ approved_admin
                                echo '<span class="badge info px-3 py-2"><i class="bi bi-check"></i> ผ่านขั้นต้น</span>';
                            }
                            elseif ($st == 'rejected') {
                                echo '<span class="badge danger px-3 py-2"><i class="bi bi-x-circle"></i> ส่งคืนแก้ไข</span>';
                            } 
                            else {
                                echo '<span class="badge warning px-3 py-2"><i class="bi bi-clock"></i> รอตรวจสอบ</span>';
                            }
                        ?>
                    </div>
                </div>

                <?php
                    $workload = $item; 
                    $viewFile = '';

                    // [แก้ไข] Logic การเลือกไฟล์ View ให้ครอบคลุม ID ของ Staff
                    if ($item['target_group'] === 'staff' || $item['owner_role'] === 'staff') {
                        // ใช้ main_area หรือเดาจาก ID (6-11)
                        $ma = $item['main_area'];
                        
                        // Mapping IDs (สมมติว่า ID ของ Staff รันต่อจาก 5)
                        if ($ma == 1) $viewFile = 'views/staff_view_routine.php'; // เผื่อใช้ ID ซ้ำ
                        elseif ($ma == 6) $viewFile = 'views/staff_view_routine.php';
                        elseif ($ma == 7) $viewFile = 'views/staff_view_strategy.php';
                        elseif ($ma == 8) $viewFile = 'views/staff_view_assigned.php';
                        elseif ($ma == 9) $viewFile = 'views/staff_view_development.php';
                        elseif ($ma == 10) $viewFile = 'views/staff_view_activity.php';
                        elseif ($ma == 11) $viewFile = 'views/staff_view_admin.php';
                        // Fallback: ถ้าชื่อไฟล์มีคำว่า staff อยู่แล้ว
                        elseif (file_exists("views/staff_view_routine.php")) $viewFile = 'views/staff_view_routine.php'; 
                    } 
                    // อาจารย์ (User)
                    else {
                        switch ($item['main_area']) {
                            case 1: $viewFile = 'views/view_teaching.php'; break;
                            case 2: $viewFile = 'views/view_research.php'; break;
                            case 3: $viewFile = 'views/view_service.php'; break;
                            case 4: $viewFile = 'views/view_culture.php'; break;
                            case 5: $viewFile = 'views/view_admin.php'; break;
                            case 6: $viewFile = 'views/view_other.php'; break;
                        }
                    }
                    
                    if (!empty($viewFile) && file_exists($viewFile)) {
                        include $viewFile;
                    } else {
                        // Fallback Display เมื่อหาไฟล์ไม่เจอ
                        echo "<div class='p-4 border rounded bg-light'>";
                        echo "<h4 class='mt-0 text-primary'>" . htmlspecialchars($item['title']) . "</h4>";
                        echo "<div class='mb-2'><strong>หมวดงาน:</strong> ".htmlspecialchars($item['category_name'])."</div>";
                        echo "<div class='mb-2'><strong>รายละเอียด:</strong></div>";
                        echo "<p class='text-muted p-3 bg-white border rounded'>" . nl2br(htmlspecialchars($item['description']?:"-")) . "</p>";
                        echo "<div class='mt-3 font-bold text-right' style='font-size:1.2rem;'>คะแนนที่คำนวณได้: <span class='text-primary'>" . number_format($item['computed_hours'], 2) . "</span></div>";
                        echo "</div>";
                    }
                ?>

                <div class="mt-5">
                    <h4 class="text-dark mb-3" style="font-size: 1.1rem;">
                        <i class="bi bi-folder2-open text-primary"></i> หลักฐานแนบ
                    </h4>
                    <div class="grid grid-2 gap-3">
                        <?php if (!empty($item['attachment_link'])): ?>
                            <a href="<?= htmlspecialchars($item['attachment_link']) ?>" target="_blank" 
                               class="p-3 border rounded d-flex align-items-center gap-3 hover-shadow transition text-decoration-none bg-white">
                                <div class="bg-light p-3 rounded text-primary"><i class="bi bi-link-45deg fs-4"></i></div>
                                <div style="overflow: hidden;">
                                    <div class="font-bold text-dark">ลิงก์เอกสาร</div>
                                    <div class="text-muted text-sm text-truncate"><?= htmlspecialchars($item['attachment_link']) ?></div>
                                </div>
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($item['evidence'])): ?>
                            <a href="../uploads/<?= htmlspecialchars($item['evidence']) ?>" target="_blank" 
                               class="p-3 border rounded d-flex align-items-center gap-3 hover-shadow transition text-decoration-none bg-white">
                                <div class="bg-light p-3 rounded text-danger"><i class="bi bi-file-earmark-pdf fs-4"></i></div>
                                <div>
                                    <div class="font-bold text-dark">ไฟล์แนบ</div>
                                    <div class="text-muted text-sm">คลิกเพื่อดาวน์โหลด</div>
                                </div>
                            </a>
                        <?php endif; ?>

                        <?php if(empty($item['evidence']) && empty($item['attachment_link'])): ?>
                            <div class="p-3 border rounded bg-light text-muted d-flex align-items-center gap-3 opacity-75 w-full">
                                <i class="bi bi-inbox fs-4"></i> <span>ไม่ได้แนบหลักฐาน</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="timeline">
                    <h4 class="mb-4">ประวัติการตรวจสอบ</h4>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach ($logs as $log): ?>
                            <div class="timeline-item">
                                <div class="timeline-date"><i class="bi bi-clock"></i> <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></div>
                                <div>
                                    <span class="font-bold text-dark"><?= htmlspecialchars($log['reviewer_name'] ?? 'System') ?></span>
                                    <span class="mx-1 text-muted">:</span>
                                    <span class="text-primary"><?= htmlspecialchars($log['action']) ?></span>
                                    <?php if($log['comment']): ?> 
                                        <div class="mt-1 p-2 bg-light rounded text-muted small border">"<?= htmlspecialchars($log['comment']) ?>"</div> 
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">- ยังไม่มีประวัติ -</p>
                    <?php endif; ?>
                </div>

                <?php if ($item['status'] === 'pending'): ?>
                    <div class="mt-5 pt-4 border-top d-flex justify-content-end gap-3">
                        <button class="btn btn-danger btn-lg px-4" type="button" onclick="openModal()">
                            <i class="bi bi-x-circle"></i> ส่งคืนแก้ไข
                        </button>

                        <form method="POST" action="<?= $actionFile ?>" onsubmit="return confirm('ยืนยันความถูกต้อง?');">
                            <input type="hidden" name="action" value="verify"> 
                            <?php if($item['target_group']=='staff'): ?>
                                <input type="hidden" name="id" value="<?= $item['id']; ?>">
                            <?php else: ?>
                                <input type="hidden" name="workload_id" value="<?= $item['id']; ?>">
                            <?php endif; ?>
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
                            
                            <button type="submit" class="btn btn-primary btn-lg px-5 shadow-sm">
                                <i class="bi bi-check-circle-fill"></i> ผ่านการตรวจสอบ
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </main>
</div>

<div class="modal" id="rejectModal">
    <div class="modal-content bg-white" style="max-width:450px; padding:30px; border-radius:12px; width:90%;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="text-danger m-0"><i class="bi bi-reply-fill"></i> ส่งคืนแก้ไข</h3>
            <span class="close" onclick="closeModal()" style="cursor:pointer; font-size:1.5rem;">&times;</span>
        </div>
        <form method="POST" action="<?= $actionFile ?>">
            <?php if($item['target_group']=='staff'): ?>
                <input type="hidden" name="id" value="<?= $item['id'] ?>">
            <?php else: ?>
                <input type="hidden" name="workload_id" value="<?= $item['id'] ?>">
            <?php endif; ?>
            
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
            
            <div class="mb-4">
                <label class="font-bold d-block mb-2">ระบุสาเหตุ:</label>
                <textarea name="comment" class="input w-full p-3 border rounded" rows="4" required placeholder="ระบุเหตุผลที่ไม่อนุมัติ..."></textarea>
            </div>
            
            <div class="stack-between">
                <button type="button" class="btn btn-light w-full mr-2" onclick="closeModal()">ยกเลิก</button>
                <button type="submit" class="btn btn-danger w-full">ยืนยัน</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal() {
        document.getElementById('rejectModal').classList.add('show');
        document.getElementById('rejectModal').style.display = 'flex';
    }
    function closeModal() {
        document.getElementById('rejectModal').classList.remove('show');
        document.getElementById('rejectModal').style.display = 'none';
    }
    // ปิดเมื่อคลิกข้างนอก
    window.onclick = function(event) {
        let modal = document.getElementById('rejectModal');
        if (event.target == modal) {
            closeModal();
        }
    }
</script>

</body>
</html>