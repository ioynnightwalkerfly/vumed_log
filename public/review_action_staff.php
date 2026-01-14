<?php
// public/review_action_staff.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// รับค่าจาก Form
$action = $_POST['action'] ?? '';
$id = $_POST['id'] ?? ''; 
$comment = $_POST['comment'] ?? '';

if (!$id || !$action) {
    header("Location: review_staff.php?error=InvalidRequest");
    exit;
}

$conn->begin_transaction();

try {
    // กรณี "ผ่านการตรวจสอบ" -> เปลี่ยนเป็น approved_admin
    if ($action == 'verify') {
        $sql = "UPDATE workload_items SET status = 'approved_admin', updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();

        // Log
        $logSql = "INSERT INTO workload_logs (work_log_id, user_id, action, comment) VALUES (?, ?, 'approved_admin', 'ผ่านการตรวจสอบโดย Staff Lead')";
        $stmtLog = $conn->prepare($logSql);
        $stmtLog->bind_param("ii", $id, $user['id']);
        $stmtLog->execute();
        
        $msg = "บันทึกผลการตรวจสอบเรียบร้อย";
        $redirectTab = "approved"; // ส่งไปหน้าตรวจแล้ว
    }
    // กรณี "ส่งคืน"
    elseif ($action == 'reject') {
        $sql = "UPDATE workload_items SET status = 'rejected', reject_reason = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $comment, $id);
        $stmt->execute();

        // Log
        $logSql = "INSERT INTO workload_logs (work_log_id, user_id, action, comment) VALUES (?, ?, 'rejected', ?)";
        $stmtLog = $conn->prepare($logSql);
        $stmtLog->bind_param("iis", $id, $user['id'], $comment);
        $stmtLog->execute();

        $msg = "ส่งคืนแก้ไขเรียบร้อย";
        $redirectTab = "rejected";
    }

    $conn->commit();
    header("Location: review_staff.php?status=$redirectTab&success=" . urlencode($msg));

} catch (Exception $e) {
    $conn->rollback();
    header("Location: review_staff.php?error=" . urlencode("Database Error: " . $e->getMessage()));
}