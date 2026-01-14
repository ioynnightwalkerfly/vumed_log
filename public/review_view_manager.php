<?php
// public/review_view_manager.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php'; 

// 1. ตรวจสิทธิ์ Manager
if ($user['role'] !== 'manager') {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: review_manager.php");
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
           wc.name_th AS category_name, wc.main_area, wc.weight, wc.code
    FROM workload_items wi
    LEFT JOIN workload_categories wc ON wi.category_id = wc.id
    JOIN users u ON wi.user_id = u.id
    WHERE wi.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    header("Location: review_manager.php?error=ไม่พบข้อมูล");
    exit;
}

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

// ชื่อด้าน (Default ของอาจารย์)
$mainAreaNames = [1=>"การสอน", 2=>"วิจัย", 3=>"บริการ", 4=>"ทำนุบำรุงฯ", 5=>"บริหาร", 6=>"อื่นๆ"];

// ถ้าเป็น Staff ปรับชื่อด้านให้ตรง
if ($item['owner_role'] === 'staff') {
    $mainAreaNames = [
        1 => "งานประจำ", 2 => "พัฒนางาน", 3 => "บริการวิชาการ",
        4 => "ทำนุบำรุงฯ", 5 => "กิจกรรม ม.", 6 => "บริหาร"
    ];
}

// Helper Function: ตัดคำภาษาไทยแบบปลอดภัย
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
    <title>พิจารณาอนุมัติ | Manager</title>
    <link rel="stylesheet" href="../medui/medui.css">
    <link rel="stylesheet" href="../medui/medui.components.css">
    <link rel="stylesheet" href="../medui/medui.layout.css">
    <link rel="stylesheet" href="../medui/medui.theme.medical.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8fafc; }
        .detail-card { max-width: 900px; margin: 30px auto; padding: 40px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .section-title { font-size: 1.1rem; font-weight: 700; margin: 30px 0 15px; color: #1e293b; border-bottom: 2px solid #f1f5f9; padding-bottom: 8px; }
        
        .timeline { margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
        .timeline-item { position: relative; padding-left: 30px; margin-bottom: 20px; }
        .timeline-item::before { 
            content: ""; position: absolute; left: 6px; top: 6px; width: 12px; height: 12px; 
            background: #cbd5e1; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 0 0 2px #cbd5e1;
        }
        .timeline-date { font-size: 0.85rem; color: #64748b; margin-bottom: 2px; }
        
        /* User Info Box */
        .user-info-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
    </style>
</head>
<body>

<div class="app">
    <?php include '../inc/nav.php'; ?>

    <main class="main">
        <div style="padding: 0 20px;">
            <div class="card detail-card bg-white">
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="m-0 text-primary"><i class="bi bi-person-check-fill"></i> พิจารณาอนุมัติ</h3>
                    <a href="review_manager.php" class="btn btn-outline-secondary btn-sm">
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
                            if ($st == 'approved_final') echo '<span class="badge success px-3 py-2">อนุมัติแล้ว</span>';
                            elseif ($st == 'approved_admin') echo '<span class="badge info px-3 py-2">ผ่าน Admin</span>';
                            elseif ($st == 'rejected') echo '<span class="badge danger px-3 py-2">ส่งคืนแก้ไข</span>';
                            else echo '<span class="badge warning px-3 py-2">รอตรวจสอบ</span>';
                        ?>
                    </div>
                </div>

                <?php
                    $viewFile = '';

                    // ถ้าเป็น Staff
                    if ($item['owner_role'] === 'staff') {
                        switch ($item['main_area']) {
                            case 1: $viewFile = 'views/staff_view_routine.php'; break;
                            case 2: $viewFile = 'views/staff_view_development.php'; break;
                            case 3: $viewFile = 'views/staff_view_strategy.php'; break;
                            case 4: $viewFile = 'views/staff_view_assigned.php'; break;
                            case 5: $viewFile = 'views/staff_view_activity.php'; break;
                            case 6: $viewFile = 'views/staff_view_admin.php'; break;
                        }
                    } 
                    // ถ้าเป็น อาจารย์ (User)
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
                        // Fallback
                        echo "<div class='p-4 border rounded bg-light'>";
                        echo "<h3 class='mt-0'>" . htmlspecialchars($item['title']) . "</h3>";
                        echo "<p class='text-muted'>" . nl2br(htmlspecialchars($item['description']?:"-")) . "</p>";
                        echo "<div class='mt-3 font-bold'>คะแนนสุทธิ: " . number_format($item['computed_hours'], 2) . "</div>";
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
                                <div class="bg-light p-3 rounded text-primary">
                                    <i class="bi bi-link-45deg" style="font-size: 1.5rem;"></i>
                                </div>
                                <div style="overflow: hidden;">
                                    <div class="font-bold text-dark">ลิงก์เอกสาร</div>
                                    <div class="text-muted text-sm text-truncate"><?= htmlspecialchars($item['attachment_link']) ?></div>
                                </div>
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($item['evidence'])): ?>
                            <a href="../uploads/<?= htmlspecialchars($item['evidence']) ?>" target="_blank" 
                               class="p-3 border rounded d-flex align-items-center gap-3 hover-shadow transition text-decoration-none bg-white">
                                <div class="bg-light p-3 rounded text-danger">
                                    <i class="bi bi-file-earmark-pdf" style="font-size: 1.5rem;"></i>
                                </div>
                                <div>
                                    <div class="font-bold text-dark">ไฟล์สำรอง</div>
                                    <div class="text-muted text-sm">ดาวน์โหลดไฟล์</div>
                                </div>
                            </a>
                        <?php endif; ?>

                        <?php if(empty($item['evidence']) && empty($item['attachment_link'])): ?>
                            <div class="p-3 border rounded bg-light text-muted d-flex align-items-center gap-3 opacity-75 w-full">
                                <i class="bi bi-inbox" style="font-size: 1.5rem;"></i>
                                <span>ไม่ได้แนบหลักฐาน</span>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>

                <div class="timeline">
                    <h4 class="mb-4">ประวัติการตรวจสอบ</h4>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach ($logs as $log): ?>
                            <div class="timeline-item">
                                <div class="timeline-date">
                                    <i class="bi bi-clock"></i> <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                                </div>
                                <div>
                                    <span class="font-bold text-dark"><?= htmlspecialchars($log['reviewer_name'] ?? 'System') ?></span>
                                    <span class="mx-1 text-muted">:</span>
                                    <span class="text-primary"><?= htmlspecialchars($log['action']) ?></span>
                                    <?php if($log['comment']): ?> 
                                        <div class="mt-1 p-2 bg-light rounded text-muted small border">
                                            <i class="bi bi-chat-quote-fill mr-1"></i> "<?= htmlspecialchars($log['comment']) ?>"
                                        </div> 
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">- ยังไม่มีประวัติ -</p>
                    <?php endif; ?>
                </div>

                <?php if ($item['status'] === 'approved_admin'): ?>
                    <div class="mt-5 pt-4 border-top d-flex justify-content-end gap-3">
                        <button class="btn btn-danger btn-lg px-4" onclick="document.getElementById('rejectModal').classList.add('show')">
                            <i class="bi bi-x-circle"></i> ส่งคืนแก้ไข
                        </button>
                        
                        <form method="POST" action="review_action_manager.php" onsubmit="return confirm('ยืนยันการอนุมัติขั้นสุดท้าย?');">
                            <input type="hidden" name="action" value="approve_final">
                            <input type="hidden" name="workload_id" value="<?= $item['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
                            <button type="submit" class="btn btn-success btn-lg px-5 shadow-sm">
                                <i class="bi bi-check-all"></i> อนุมัติสมบูรณ์
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <?php if ($item['status'] === 'pending'): ?>
                        <div class="alert warning mt-4">
                            <i class="bi bi-hourglass-split"></i> รายการนี้ยังรอการตรวจสอบจากเจ้าหน้าที่ (Admin) ก่อน
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
        </div>
    </main>
</div>

<div class="modal" id="rejectModal">
    <div class="modal-content" style="max-width:450px; padding:30px; border-radius:12px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="text-danger m-0"><i class="bi bi-reply-fill"></i> ส่งคืนแก้ไข</h3>
            <span class="close" onclick="document.getElementById('rejectModal').classList.remove('show')" style="cursor:pointer; font-size:1.5rem;">&times;</span>
        </div>
        <form method="POST" action="review_action_manager.php">
            <input type="hidden" name="workload_id" value="<?= $item['id'] ?>">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
            
            <div class="mb-4">
                <label class="font-bold d-block mb-2">ระบุสาเหตุ / สิ่งที่ต้องแก้ไข:</label>
                <textarea name="comment" class="input w-full p-3 border rounded" rows="4" required placeholder="เช่น ข้อมูลไม่ครบถ้วน, กรุณาแนบไฟล์เพิ่ม..."></textarea>
            </div>
            
            <div class="stack-between">
                <button type="button" class="btn btn-light w-full mr-2" onclick="document.getElementById('rejectModal').classList.remove('show')">ยกเลิก</button>
                <button type="submit" class="btn btn-danger w-full">ยืนยันส่งคืน</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>