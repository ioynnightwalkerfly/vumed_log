<?php
// config/db.php

// ใส่ค่าตรงๆ เพื่อความชัวร์ (Bypass .env ชั่วคราว)
$host   = '127.0.0.1';
$user   = 'root';
$pass   = '';
$dbname = 'vumedhr';

try {
    $conn = new mysqli($host, $user, $pass, $dbname);
} catch (mysqli_sql_exception $e) {
    // กรณีเชื่อมต่อไม่ได้ ให้แสดง Error ชัดๆ
    die("<div style='color:red; border:1px solid red; padding:20px; margin:20px;'>
            <h3>❌ เชื่อมต่อฐานข้อมูลไม่ได้</h3>
            <p><strong>Error:</strong> " . $e->getMessage() . "</p>
            <hr>
            <p>กรุณาตรวจสอบค่าในไฟล์ <code>config/db.php</code> อีกครั้ง</p>
         </div>");
}

$conn->set_charset("utf8");

// 🔥 [เพิ่มใหม่] ระบบติดตามผู้ใช้งาน Online (Tracker)
// ถ้ามีการ Login อยู่ ให้บันทึกเวลาปัจจุบันลง Database
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user']['id'])) {
    $uid = $_SESSION['user']['id'];
    $conn->query("UPDATE users SET last_activity = NOW() WHERE id = " . intval($uid));
}


if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}



$conn->set_charset("utf8");
?>