<?php
// public/review_action_admin.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// 1. ตรวจสอบสิทธิ์ Admin
if ($user['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: review_admin.php");
    exit;
}

// 3. ตรวจสอบ CSRF Token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die("Invalid CSRF token.");
}

$workload_id = $_POST['workload_id'] ?? 0;
$action = $_POST['action'] ?? '';
$comment = $_POST['comment'] ?? '';

if (!$workload_id || !is_numeric($workload_id)) {
    header("Location: review_admin.php?error=รหัสรายการไม่ถูกต้อง");
    exit;
}

// 5. ดึงข้อมูลเดิม
$stmt = $conn->prepare("SELECT status FROM workload_items WHERE id = ?");
$stmt->bind_param("i", $workload_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    header("Location: review_admin.php?error=ไม่พบรายการ");
    exit;
}

if ($item['status'] !== 'pending') {
    header("Location: review_admin.php?error=รายการนี้ถูกดำเนินการไปแล้ว");
    exit;
}

// 6. ดำเนินการ (แก้ไขให้รองรับทั้ง approve และ verify)
if ($action === 'approve' || $action === 'verify') {
    
    // *** แก้ไขจุดสำคัญ: เปลี่ยนสถานะเป้าหมายเป็น 'verified' ***
    $new_status = 'verified'; 
    
    $stmt = $conn->prepare("UPDATE workload_items SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $new_status, $workload_id);
    
    if ($stmt->execute()) {
        // Log: ใช้ action 'verify'
        $stmtLog = $conn->prepare("INSERT INTO workload_logs (work_log_id, user_id, action, comment, created_at) VALUES (?, ?, 'verify', 'Admin Verified', NOW())");
        $stmtLog->bind_param("ii", $workload_id, $user['id']);
        $stmtLog->execute();

        header("Location: review_admin.php?success=ตรวจสอบรายการเรียบร้อยแล้ว (ส่งต่อผู้บริหาร)");
    } else {
        header("Location: review_admin.php?error=เกิดข้อผิดพลาดฐานข้อมูล");
    }

} elseif ($action === 'reject') {
    // กรณี Reject (เหมือนเดิม)
    $new_status = 'rejected';
    $stmt = $conn->prepare("UPDATE workload_items SET status = ?, reject_reason = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("ssi", $new_status, $comment, $workload_id);

    if ($stmt->execute()) {
        // Log
        $stmtLog = $conn->prepare("INSERT INTO workload_logs (work_log_id, user_id, action, comment, created_at) VALUES (?, ?, 'reject', ?, NOW())");
        $stmtLog->bind_param("iis", $workload_id, $user['id'], $comment);
        $stmtLog->execute();

        header("Location: review_admin.php?success=ส่งคืนรายการเรียบร้อยแล้ว");
    } else {
        header("Location: review_admin.php?error=เกิดข้อผิดพลาดฐานข้อมูล");
    }

} else {
    header("Location: review_admin.php?error=คำสั่งไม่ถูกต้อง");
}
exit;
?>