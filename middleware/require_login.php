<?php
// middleware/require_login.php

// 1. เริ่มต้น session ถ้ายังไม่มี
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. ตรวจสอบว่ามีข้อมูลผู้ใช้ใน session หรือไม่
if (!isset($_SESSION['user'])) {
    // ถ้ายังไม่ได้ล็อกอิน → เด้งกลับหน้า login
    header("Location: ../public/login.php");
    exit;
}

// ---------------------------------------------------------------------
// 3.  เพิ่มส่วนนี้: สั่งเบราว์เซอร์ "ห้ามจำหน้าเก่า" (แก้ปัญหาปุ่ม Back)
// ---------------------------------------------------------------------
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // วันที่ในอดีต เพื่อบังคับหมดอายุทันที

// 4. ถ้ามี → ดึงข้อมูลเก็บในตัวแปร $user
$user = $_SESSION['user'];

// สำหรับหน้าอื่น ๆ จะสามารถเรียกใช้ $user['id'], $user['name'], $user['role'] ได้เลย
?>