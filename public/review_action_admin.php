<?php
// public/review_action_admin.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// ตรวจสิทธิ์ Admin
if ($user['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// รับค่า
$action = $_POST['action'] ?? '';
$id = $_POST['workload_id'] ?? $_POST['id'] ?? ''; 
$comment = $_POST['comment'] ?? '';

if (!$id || !$action) {
    header("Location: review_admin.php?error=InvalidRequest");
    exit;
}

$conn->begin_transaction();

try {
    // 1. Admin กดอนุมัติ (Verify) -> เปลี่ยนสถานะเป็น 'approved_admin'
    if ($action == 'verify') {
        // [แก้ไข] เปลี่ยนจาก 'verified' เป็น 'approved_admin' ให้ตรงกับ DB
        $sql = "UPDATE workload_items SET status = 'approved_admin', updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();

        // Log: บันทึก action เป็น approved_admin ด้วย
        $logSql = "INSERT INTO workload_logs (work_log_id, user_id, action, comment) VALUES (?, ?, 'approved_admin', 'ตรวจสอบแล้วโดย Admin')";
        $stmtLog = $conn->prepare($logSql);
        $stmtLog->bind_param("ii", $id, $user['id']);
        $stmtLog->execute();

        $msg = "ยืนยันความถูกต้องเรียบร้อย (ส่งต่อให้ผู้บริหาร)";
    }

    // 2. Admin ส่งคืน (Reject)
    elseif ($action == 'reject') {
        $sql = "UPDATE workload_items SET status = 'rejected', reject_reason = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $comment, $id);
        $stmt->execute();

        $logSql = "INSERT INTO workload_logs (work_log_id, user_id, action, comment) VALUES (?, ?, 'rejected', ?)";
        $stmtLog = $conn->prepare($logSql);
        $stmtLog->bind_param("iis", $id, $user['id'], $comment);
        $stmtLog->execute();

        $msg = "ส่งคืนแก้ไขเรียบร้อย";
    }
    
    // 3. ลบ (Delete)
    elseif ($action == 'delete') {
        $sql = "DELETE FROM workload_items WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $msg = "ลบรายการเรียบร้อย";
    }

    $conn->commit();
    
    // [เพิ่มเติม] ส่งกลับไปที่ Tab ที่เหมาะสม
    $redirectStatus = ($action == 'verify') ? 'approved' : (($action == 'reject') ? 'rejected' : 'pending');
    header("Location: review_admin.php?status=$redirectStatus&success=" . urlencode($msg));

} catch (Exception $e) {
    $conn->rollback();
    header("Location: review_admin.php?error=" . urlencode("DB Error: " . $e->getMessage()));
}