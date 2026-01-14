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

// รับค่า (หน้า Admin บางทีใช้ workload_id บางทีใช้ id เช็คให้ครอบคลุม)
$action = $_POST['action'] ?? '';
$id = $_POST['workload_id'] ?? $_POST['id'] ?? ''; 
$comment = $_POST['comment'] ?? '';

if (!$id || !$action) {
    // ถ้าหา ID ไม่เจอ ให้ลองดูว่าส่งมาจากหน้าไหน แล้วเด้งกลับไป
    header("Location: review_admin.php?error=InvalidRequest");
    exit;
}

$conn->begin_transaction();

try {
    // 1. Admin กดอนุมัติ (Verify) -> สถานะเป็น 'verified'
    // หมายเหตุ: Admin ตรวจเสร็จ = Verified (รอ Manager อนุมัติ Final อีกทีตาม Flow)
    if ($action == 'verify') {
        $sql = "UPDATE workload_items SET status = 'verified', updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();

        // Log
        $logSql = "INSERT INTO workload_logs (work_log_id, user_id, action, comment) VALUES (?, ?, 'verified', 'ตรวจสอบแล้วโดย Admin')";
        $stmtLog = $conn->prepare($logSql);
        $stmtLog->bind_param("ii", $id, $user['id']);
        $stmtLog->execute();

        $msg = "ยืนยันความถูกต้องเรียบร้อย";
    }

    // 2. Admin ส่งคืน (Reject)
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
    }
    
    // 3. (แถม) กรณีลบ (Delete)
    elseif ($action == 'delete') {
        $sql = "DELETE FROM workload_items WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $msg = "ลบรายการเรียบร้อย";
    }

    $conn->commit();
    header("Location: review_admin.php?success=" . urlencode($msg));

} catch (Exception $e) {
    $conn->rollback();
    header("Location: review_admin.php?error=" . urlencode("DB Error: " . $e->getMessage()));
}