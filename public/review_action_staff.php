<?php
// public/review_action_staff.php
require_once '../config/app.php';
require_once '../middleware/require_staff_lead.php'; // เช็คสิทธิ์ Staff Lead
require_once '../config/db.php';

// ตรวจสอบ Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: review_staff.php");
    exit;
}

// ตรวจสอบ CSRF Token (เพื่อความปลอดภัย)
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die("Invalid CSRF token.");
}

$workload_id = $_POST['workload_id'] ?? 0;
$action      = $_POST['action'] ?? '';
$comment     = trim($_POST['comment'] ?? '');

// โหลดข้อมูลเดิม
$stmt = $conn->prepare("SELECT status FROM workload_items WHERE id = ?");
$stmt->bind_param("i", $workload_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item || $item['status'] !== 'pending') {
    header("Location: review_staff.php?error=รายการไม่ถูกต้องหรือถูกดำเนินการไปแล้ว");
    exit;
}

// ดำเนินการ (Verify หรือ Reject)
if ($action === 'verify') {
    // เปลี่ยนสถานะเป็น 'verified' (ส่งต่อ Manager)
    $stmt = $conn->prepare("UPDATE workload_items SET status = 'verified', updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $workload_id);
    
    if ($stmt->execute()) {
        // Log
        $stmtLog = $conn->prepare("INSERT INTO workload_logs (work_log_id, user_id, action, comment, created_at) VALUES (?, ?, 'verify', 'Staff Lead Verified', NOW())");
        $stmtLog->bind_param("ii", $workload_id, $user['id']);
        $stmtLog->execute();
        
        header("Location: review_staff.php?success=ตรวจสอบเรียบร้อยแล้ว (ส่งต่อผู้บริหาร)");
    } else {
        header("Location: review_staff.php?error=Database Error");
    }

} elseif ($action === 'reject') {
    // เปลี่ยนสถานะเป็น 'rejected' (ส่งคืนแก้ไข)
    $stmt = $conn->prepare("UPDATE workload_items SET status = 'rejected', reject_reason = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $comment, $workload_id);
    
    if ($stmt->execute()) {
        // Log
        $stmtLog = $conn->prepare("INSERT INTO workload_logs (work_log_id, user_id, action, comment, created_at) VALUES (?, ?, 'reject', ?, NOW())");
        $stmtLog->bind_param("iis", $workload_id, $user['id'], $comment);
        $stmtLog->execute();
        
        header("Location: review_staff.php?success=ส่งคืนรายการเรียบร้อยแล้ว");
    } else {
        header("Location: review_staff.php?error=Database Error");
    }
}
?>