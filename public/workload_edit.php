<?php
// public/workload_edit.php
// หน้าแก้ไขภาระงานแบบรวมศูนย์ (รองรับทั้ง Teacher และ Staff)

// 1. Start Buffer
ob_start();

require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php'; 

$id = $_GET['id'] ?? 0;

// 2. ดึงข้อมูล & ตรวจสอบสิทธิ์
// ปรับ Logic: ถ้าเป็น Admin/Manager ให้ดูได้ทุกรายการ แต่ถ้าเป็น User ธรรมดา ให้ดูได้เฉพาะของตัวเอง
if ($user['role'] === 'admin' || $user['role'] === 'manager') {
    // Admin/Manager: แก้ไขได้ทุกรายการ
    $sql = "SELECT i.*, c.main_area, c.name_th AS category_name, c.weight, u.role AS owner_role
            FROM workload_items i
            LEFT JOIN workload_categories c ON i.category_id = c.id
            LEFT JOIN users u ON i.user_id = u.id
            WHERE i.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
} else {
    // User ทั่วไป (Teacher/Staff): แก้ไขได้เฉพาะของตัวเอง
    $sql = "SELECT i.*, c.main_area, c.name_th AS category_name, c.weight, u.role AS owner_role
            FROM workload_items i
            LEFT JOIN workload_categories c ON i.category_id = c.id
            LEFT JOIN users u ON i.user_id = u.id
            WHERE i.id = ? AND i.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $id, $user['id']);
}

$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

// ถ้าไม่เจอข้อมูล หรือไม่มีสิทธิ์
if (!$item) {
    // ส่งกลับหน้ารายการของตัวเอง
    $redirect = ($user['role'] === 'staff') ? 'staff_workloads.php' : 'workloads.php';
    header("Location: $redirect?error=" . urlencode("ไม่พบข้อมูล หรือคุณไม่มีสิทธิ์แก้ไขรายการนี้"));
    exit;
}

// 3. ตรวจสอบสถานะ (Lock)
// ถ้าอนุมัติขั้นสุดท้ายแล้ว (approved_final) ห้ามแก้ ยกเว้น Admin
if ($item['status'] === 'approved_final' && $user['role'] !== 'admin') {
    $redirect = ($user['role'] === 'staff') ? 'staff_workloads.php' : 'workloads.php';
    header("Location: $redirect?error=" . urlencode("รายการนี้อนุมัติสมบูรณ์แล้ว ไม่สามารถแก้ไขได้"));
    exit;
}

// กำหนดตัวแปรสำหรับฟอร์มลูก
$area = $item['main_area'] ?? 1;
$is_edit = true; // บอกฟอร์มลูกว่าเป็นโหมดแก้ไข

// หาว่าเจ้าของงานเป็น Role อะไร (เพื่อเลือกฟอร์มให้ถูก)
// ถ้า Admin มาแก้ของ Staff ระบบต้องรู้ว่าควรโหลดฟอร์ม Staff
$owner_role = $item['owner_role'] ?? $user['role']; 

// 4. CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขภาระงาน | MedUI System</title>
    <link rel="stylesheet" href="../medui/medui.css">
    <link rel="stylesheet" href="../medui/medui.components.css">
    <link rel="stylesheet" href="../medui/medui.layout.css">
    <link rel="stylesheet" href="../medui/medui.theme.medical.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>

<div class="app">
    
    <?php include_once '../inc/nav.php'; ?>

    <div class="app-content">
        
        <header class="topbar">
            <div class="container">
                <div class="topbar-content stack-between">
                    <div class="topbar-left">
                        <h3 style="margin:0;">แก้ไขภาระงาน</h3>
                        <p class="muted m-0">แก้ไขข้อมูล: <?= htmlspecialchars($item['title']) ?></p>
                    </div>
                    <div class="topbar-right">
                         <?php 
                            // ปุ่มย้อนกลับให้ฉลาด (Smart Back Button)
                            if ($user['role'] === 'admin') {
                                $backLink = 'review_admin.php';
                            } elseif ($user['role'] === 'manager') {
                                $backLink = 'review_manager.php';
                            } elseif ($user['role'] === 'staff') {
                                $backLink = 'staff_workloads.php';
                            } else {
                                $backLink = 'workloads.php';
                            }
                         ?>
                         <a href="<?= $backLink ?>" class="btn btn-sm btn-muted">
                             <i class="bi bi-x-lg"></i> ยกเลิก
                         </a>
                    </div>
                </div>
            </div>
        </header>

        <main class="main">
            <div class="container" style="max-width:1000px;">
                
                <?php include '../inc/alert.php'; ?>

                <?php if ($item['status'] === 'rejected'): ?>
                    <div class="card p-4 mb-4" style="background-color:#fef2f2; border:1px solid #fca5a5; color:#991b1b; border-left: 5px solid #dc2626;">
                        <h4 class="m-0 mb-2" style="color:#b91c1c;"><i class="bi bi-exclamation-triangle-fill"></i> รายการนี้ต้องแก้ไข</h4>
                        <div style="background: #fff; padding: 10px; border-radius: 6px; border: 1px solid #fecaca;">
                            <?php
                                // ดึงคอมเมนต์ล่าสุดจาก Log (ถ้าใน items ไม่มี field นี้)
                                $rejectMsg = "กรุณาตรวจสอบความถูกต้องและแก้ไขข้อมูล";
                                $logQ = $conn->query("SELECT comment FROM workload_logs WHERE work_log_id = $id AND action = 'reject' ORDER BY id DESC LIMIT 1");
                                if ($logQ->num_rows > 0) {
                                    $rejectMsg = $logQ->fetch_assoc()['comment'];
                                }
                            ?>
                            <strong>เหตุผลจากผู้ตรวจสอบ:</strong> <br>
                            <?= nl2br(htmlspecialchars($rejectMsg)) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php
                
                // ===== Logic เลือกไฟล์ฟอร์ม (Router) =====
                
                $fileToLoad = null;

                if ($owner_role === 'staff') {
                    // --- ฟอร์มของสายสนับสนุน ---
                    $formMap = [
                        1 => '../forms/staff/form_staff_routine.php',
                        2 => '../forms/staff/form_staff_development.php',
                        3 => '../forms/staff/form_staff_strategy.php',
                        4 => '../forms/staff/form_staff_assigned.php',
                        5 => '../forms/staff/form_staff_activity.php',
                        6 => '../forms/staff/form_staff_admin.php',
                    ];
                } else {
                    // --- ฟอร์มของสายวิชาการ (อาจารย์) ---
                    $formMap = [
                        1 => '../forms/form_teaching.php',
                        2 => '../forms/form_research.php',
                        3 => '../forms/form_service.php',
                        4 => '../forms/form_culture.php',
                        5 => '../forms/form_admin.php',
                        6 => '../forms/form_other.php',
                    ];
                }

                $fileToLoad = $formMap[$area] ?? null;

                if ($fileToLoad && file_exists($fileToLoad)) {
                    include $fileToLoad;
                } else {
                    echo "<div class='alert error text-center p-5'>";
                    echo "<i class='bi bi-bug-fill' style='font-size:3rem;'></i><br>";
                    echo "<h3>ไม่พบไฟล์แบบฟอร์ม</h3>";
                    echo "ระบบไม่พบไฟล์: " . htmlspecialchars($fileToLoad ?? "Unknown Area ($area)");
                    echo "</div>";
                }
                ?>
                
            </div>
        </main>
    </div>
</div>

</body>
</html>
<?php
// ปล่อย Buffer
ob_end_flush();
?>