<?php
// public/user_delete_action.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// เช็คสิทธิ์ Admin เท่านั้น
if ($user['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// รับ ID ที่จะลบ
$delete_id = $_POST['id'] ?? 0; // รับจาก Hidden Input แทน

if (!$delete_id) {
    header("Location: admin_users.php?error=invalid_id");
    exit;
}

// ป้องกันไม่ให้ลบตัวเอง
if ($delete_id == $user['id']) {
    header("Location: admin_users.php?error=" . urlencode("ไม่สามารถลบบัญชีตัวเองได้"));
    exit;
}

// เริ่มกระบวนการลบ (Transaction)
$conn->begin_transaction();

try {
    // 1. ลบ Logs ที่เกี่ยวข้องกับ User นี้ (ถ้ามีตาราง workload_logs)
    // ลบ Logs ที่ User นี้เป็นคนกระทำ
    $stmt1 = $conn->prepare("DELETE FROM workload_logs WHERE user_id = ?");
    $stmt1->bind_param("i", $delete_id);
    $stmt1->execute();
    $stmt1->close();

    // ลบ Logs ที่ผูกกับ Workload Items ของ User นี้
    // (ซับซ้อนหน่อย แต่จำเป็นถ้าระบบ Log ผูกกับ item_id)
    $stmt2 = $conn->prepare("DELETE FROM workload_logs WHERE work_log_id IN (SELECT id FROM workload_items WHERE user_id = ?)");
    $stmt2->bind_param("i", $delete_id);
    $stmt2->execute();
    $stmt2->close();

    // 2. ลบ Workload Items ของ User นี้ (ตัวการหลักที่ทำให้เกิด Error)
    $stmt3 = $conn->prepare("DELETE FROM workload_items WHERE user_id = ?");
    $stmt3->bind_param("i", $delete_id);
    $stmt3->execute();
    $stmt3->close();

    // 3. สุดท้าย ลบตัว User
    $stmt4 = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt4->bind_param("i", $delete_id);
    $stmt4->execute();
    $stmt4->close();

    // ถ้าทุกอย่างผ่าน ยืนยันการลบ
    $conn->commit();
    header("Location: users.php?success=" . urlencode("ลบผู้ใช้งานและข้อมูลที่เกี่ยวข้องเรียบร้อยแล้ว"));
    exit;

} catch (Exception $e) {
    // ถ้ามีอะไรผิดพลาด ให้ย้อนกลับ (ไม่ลบอะไรเลย)
    $conn->rollback();
    header("Location: users.php?error=" . urlencode("เกิดข้อผิดพลาด: " . $e->getMessage()));
    exit;
}
?>