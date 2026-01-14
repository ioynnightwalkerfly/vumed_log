<?php
// public/login_verify.php
require_once '../config/app.php';
require_once '../config/db.php';

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

// ตรวจสอบค่าว่าง
if (!$email || !$password) {
    header("Location: login.php?error=กรุณากรอกข้อมูลให้ครบ");
    exit;
}

// 1. ดึงข้อมูลจากฐานข้อมูล 
$stmt = $conn->prepare("SELECT id, name, email, password, role, profile_image, role_position FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    // 2. ตรวจสอบรหัสผ่าน
    if (password_verify($password, $user['password'])) {
        
        // 3. ป้องกัน Session Fixation
        session_regenerate_id(true);

        // เก็บข้อมูลลง Session 
        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'profile_image' => $user['profile_image'], 
            'role_position' => $user['role_position']
        ];
        
        // 4. แยกทางไปตาม Role (Routing) - แก้ไขใหม่
        if ($user['role'] === 'admin') {
            header("Location: admin_dashboard.php");
        } 
        elseif ($user['role'] === 'manager') {
            // Manager ไปหน้าตรวจงานรวม (หรือจะไป admin_dashboard.php ก็ได้ตามดีไซน์)
            header("Location: review_manager.php");
        } 
        elseif ($user['role'] === 'staff_lead') {
            // Staff Lead ไปหน้าตรวจงานลูกน้อง
            header("Location: review_staff.php");
        } 
        elseif ($user['role'] === 'staff') {
            // Staff ทั่วไปไปหน้าบันทึกงานของตัวเอง
            header("Location: staff_index.php");
        } 
        else {
            // User ทั่วไป (Teacher) ไปหน้า Dashboard ตัวเอง
            header("Location: index.php");
        }
        exit;
    }
}

// กรณีไม่ผ่าน
header("Location: login.php?error=อีเมลหรือรหัสผ่านไม่ถูกต้อง");
exit;
?>