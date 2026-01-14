<?php
// public/review_action_manager.php
require_once '../config/app.php';
require_once '../middleware/require_manager.php'; // ใช้ Middleware ตรวจสิทธิ์ Manager

// 1. ตรวจสอบว่าเป็น Manager จริงไหม
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'manager') {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['workload_id'])) {
    header("Location: review_manager.php?error=" . urlencode("การเข้าถึงไม่ถูกต้อง"));
    exit;
}
// เพิ่มส่วนนี้เพื่อตรวจสอบ CSRF Token
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header("Location: review_manager.php?error=" . urlencode("Token ไม่ถูกต้อง (CSRF Error)"));
    exit;
}
$workload_id = (int)$_POST['workload_id'];
$action      = $_POST['action'] ?? '';
$comment     = trim($_POST['comment'] ?? ''); // รับเหตุผล (ถ้ามี)

// อนุญาตแค่ 2 คำสั่ง
if (!in_array($action, ['approve', 'reject'], true)) {
    header("Location: review_manager.php?error=" . urlencode("คำสั่งไม่ถูกต้อง"));
    exit;
}

// 2. โหลดรายการภาระงานที่ต้องมีสถานะ 'verified' (ผ่านด่านแรกมาแล้ว)
$stmt = $conn->prepare("SELECT id, user_id, status FROM workload_items WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $workload_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    header("Location: review_manager.php?error=" . urlencode("ไม่พบรหัสภาระงาน"));
    exit;
}

// *** จุดแก้ไขสำคัญ: ต้องเช็คว่าเป็น 'verified' หรือไม่ ***
if ($item['status'] !== 'verified') {
    header("Location: review_manager.php?error=" . urlencode("รายการนี้ยังไม่ผ่านการตรวจสอบขั้นต้น (Verified)"));
    exit;
}

// กรณี Reject ต้องมีเหตุผล
if ($action === 'reject' && $comment === '') {
    header("Location: review_manager.php?error=" . urlencode("กรุณาระบุเหตุผลการปฏิเสธ"));
    exit;
}

$conn->begin_transaction();

try {
    if ($action === 'approve') {
        // --- กรณีอนุมัติ (Final) ---
        // เปลี่ยนสถานะเป็น 'approved' (จบกระบวนการ)
        $stmtUp = $conn->prepare("
            UPDATE workload_items
            SET status = 'approved', updated_at = NOW()
            WHERE id = ?
        ");
        $stmtUp->bind_param("i", $workload_id);
        $stmtUp->execute();

        // Log
        $stmtLog = $conn->prepare("INSERT INTO workload_logs (work_log_id, user_id, action, comment, created_at) VALUES (?, ?, 'approve_final', 'ผู้บริหารอนุมัติแล้ว', NOW())");
        $stmtLog->bind_param("ii", $workload_id, $user['id']);
        $stmtLog->execute();

        $msg = " อนุมัติรายการเรียบร้อยแล้ว";

    } else {
        // --- กรณีปฏิเสธ (Reject) ---
        // เปลี่ยนสถานะเป็น 'rejected' และบันทึกเหตุผลลง 'reject_reason'
        $stmtUp = $conn->prepare("
            UPDATE workload_items
            SET status = 'rejected',
                reject_reason = ?,  
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmtUp->bind_param("si", $comment, $workload_id);
        $stmtUp->execute();

        // Log
        $stmtLog = $conn->prepare("INSERT INTO workload_logs (work_log_id, user_id, action, comment, created_at) VALUES (?, ?, 'reject_final', ?, NOW())");
        $stmtLog->bind_param("iis", $workload_id, $user['id'], $comment);
        $stmtLog->execute();

        $msg = "❌ ส่งคืนรายการเรียบร้อยแล้ว";
    }

    $conn->commit();
    header("Location: review_manager.php?success=" . urlencode($msg));
    exit;

} catch (Exception $e) {
    $conn->rollback();
    header("Location: review_manager.php?error=" . urlencode("เกิดข้อผิดพลาด: " . $e->getMessage()));
    exit;
}
?>