<?php
// ===== ตรวจสิทธิ์การเข้าถึง (เฉพาะผู้บริหาร / หัวหน้า) =====

// เริ่ม session ถ้ายังไม่มี
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ถ้าไม่ได้ล็อกอิน → เด้งกลับหน้า login
if (!isset($_SESSION['user'])) {
    header("Location: ../public/login.php");
    exit;
}

$user = $_SESSION['user'];

// ตรวจ role: อนุญาตเฉพาะ admin หรือ manager เท่านั้น
if (!in_array($user['role'], ['admin', 'manager'])) {
    // ถ้าไม่ใช่ผู้มีสิทธิ์ → เด้งกลับหน้าหลัก
    header("Location: ../public/index.php?error=คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
    exit;
}
?>
