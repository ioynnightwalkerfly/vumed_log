<?php
// middleware/require_staff_lead.php

// ถ้ายังไม่ได้ Login ให้ไป Login ก่อน
require_once 'require_login.php';

// อนุญาตเฉพาะ 'staff_lead' (หรือ 'manager' ก็ได้ ถ้าอยากให้ manager เข้าได้ด้วย)
if ($user['role'] !== 'staff_lead' && $user['role'] !== 'manager') {
    // ถ้าไม่มีสิทธิ์ ให้เด้งกลับหน้า Index หรือแจ้งเตือน
    header("Location: ../public/index.php?error=" . urlencode("คุณไม่มีสิทธิ์เข้าถึงส่วนตรวจสอบของสายสนับสนุน"));
    exit;
}
?>