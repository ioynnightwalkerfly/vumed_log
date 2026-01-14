<?php
// public/workload_delete.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// รองรับทั้ง POST (จาก Modal) และ GET (เผื่อลิงก์ธรรมดา)
$request_method = $_SERVER['REQUEST_METHOD'];

// 1. รับค่า ID
$id = $_REQUEST['id'] ?? 0;

// 2. รับค่าหน้าปลายทาง (แก้ให้ตรงกับ form คือ redirect_to)
$redirect_to = $_REQUEST['redirect_to'] ?? '';

// ถ้าไม่มีค่าส่งมา ให้เดาจาก Role
if (empty($redirect_to)) {
    if ($user['role'] === 'staff') {
        $redirect_to = 'staff_workloads.php';
    } elseif ($user['role'] === 'admin') {
        $redirect_to = 'review_admin.php';
    } else {
        $redirect_to = 'workloads.php'; // อาจารย์
    }
}

// ตรวจสอบ ID
if ($id <= 0) {
    header("Location: $redirect_to?error=ไม่พบรหัสรายการ");
    exit;
}

// 3. ถ้าเป็น POST ต้องเช็ค CSRF Token เพื่อความปลอดภัย
if ($request_method === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token");
    }
}

// 4. ดึงข้อมูลเดิม (เพื่อตรวจสอบสิทธิ์และลบไฟล์)
$stmt = $conn->prepare("SELECT user_id, status, evidence FROM workload_items WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    header("Location: $redirect_to?error=ไม่พบข้อมูลในระบบ");
    exit;
}

// 5. ตรวจสอบสิทธิ์
if ($user['role'] !== 'admin') {
    // ต้องเป็นเจ้าของเท่านั้น
    if ($item['user_id'] != $user['id']) {
        header("Location: $redirect_to?error=คุณไม่มีสิทธิ์ลบรายการนี้");
        exit;
    }

    
    // เพิ่ม 'approved' เข้าไปในรายการที่อนุญาตให้ลบ
if (!in_array($item['status'], ['pending', 'rejected', 'approved'])) { 
    header("Location: $redirect_to?error=ไม่สามารถลบรายการที่อนุมัติแล้วได้");
    exit;
}
}

// 6. ลบไฟล์แนบ (ถ้ามี)
if (!empty($item['evidence'])) {
    $filePath = "../uploads/" . $item['evidence'];
    if (file_exists($filePath)) {
        @unlink($filePath);
    }
}

// 7. ลบข้อมูลใน DB
$delStmt = $conn->prepare("DELETE FROM workload_items WHERE id = ?");
$delStmt->bind_param("i", $id);

if ($delStmt->execute()) {
    // ลบ Log ที่เกี่ยวข้อง
    $conn->query("DELETE FROM workload_logs WHERE work_log_id = $id");
    
    // สำเร็จ: กลับไปหน้าที่ส่งมา
    header("Location: $redirect_to?success=" . urlencode("ลบรายการเรียบร้อยแล้ว"));
} else {
    // ผิดพลาด
    header("Location: $redirect_to?error=" . urlencode("เกิดข้อผิดพลาดในการลบ"));
}
exit;
?>