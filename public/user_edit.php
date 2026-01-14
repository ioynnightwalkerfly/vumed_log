<?php
// public/user_edit.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// 1. ตรวจสอบสิทธิ์
if (!in_array($user['role'], ['admin', 'manager'])) {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'] ?? 0;

// ดึงข้อมูลเดิม
$stmt = $conn->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user_edit = $stmt->get_result()->fetch_assoc();

if (!$user_edit) {
    header("Location: users.php?error=ไม่พบข้อมูลผู้ใช้");
    exit;
}

// 2. CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$errors = [];

// ===== บันทึกข้อมูล =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 3. Check CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token.");
    }

    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($name)) $errors[] = "กรุณากรอกชื่อ-นามสกุล";
    if (empty($email)) $errors[] = "กรุณากรอกอีเมล";

    // เช็คอีเมลซ้ำ
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $email, $id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "อีเมลนี้มีผู้ใช้งานอื่นใช้แล้ว";
    }

    if (empty($errors)) {
        if (!empty($password)) {
            // กรณีเปลี่ยนรหัส
            if (strlen($password) < 4) {
                $errors[] = "รหัสผ่านต้องมีอย่างน้อย 4 ตัวอักษร";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET name=?, email=?, role=?, password=? WHERE id=?");
                $stmt->bind_param("ssssi", $name, $email, $role, $hash, $id);
            }
        } else {
            // ไม่เปลี่ยนรหัส
            $stmt = $conn->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?");
            $stmt->bind_param("sssi", $name, $email, $role, $id);
        }

        if (empty($errors)) {
            if ($stmt->execute()) {
                header("Location: users.php?success=แก้ไขข้อมูลสำเร็จ");
                exit;
            } else {
                $errors[] = "Database Error: " . $stmt->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขผู้ใช้ | MedUI System</title>
    <link rel="stylesheet" href="../medui/medui.css">
    <link rel="stylesheet" href="../medui/medui.components.css">
    <link rel="stylesheet" href="../medui/medui.layout.css">
    <link rel="stylesheet" href="../medui/medui.theme.medical.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        .password-wrapper {
            position: relative;
        }
        .password-wrapper input {
            padding-right: 40px;
        }
        .toggle-password {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            font-size: 1.2rem;
            z-index: 10;
        }
        .toggle-password:hover {
            color: #0d6efd;
        }
    </style>
</head>
<body>

<div class="app">
    <?php include_once '../inc/nav.php'; ?>

    <div class="app-content">
        
        <header class="topbar">
            <div class="container">
                <div class="topbar-content">
                    <div class="topbar-left">
                        <h3 style="margin:0;">แก้ไขผู้ใช้</h3>
                    </div>
                    <div class="topbar-right">
                         <a href="users.php" class="btn btn-sm btn-muted">
                             <i class="bi bi-arrow-left"></i> กลับหน้ารายชื่อ
                         </a>
                    </div>
                </div>
            </div>
        </header>

        <main class="main">
            <div class="container" style="max-width:600px;">
                
                <div class="card p-6">
                    <?php if (!empty($errors)): ?>
                        <div class="alert error mb-4">
                            <strong>พบข้อผิดพลาด:</strong>
                            <ul><?php foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="grid" style="gap:20px;">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                        <div class="full">
                            <label>ชื่อ - นามสกุล</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($user_edit['name']) ?>" required>
                        </div>

                        <div class="full">
                            <label>อีเมล</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($user_edit['email']) ?>" required>
                        </div>

                        <div class="full">
                            <label>เปลี่ยนรหัสผ่าน (ถ้าต้องการ)</label>
                            <div class="password-wrapper">
                                <input type="password" name="password" id="passwordInput" placeholder="ปล่อยว่างไว้ถ้าไม่ต้องการเปลี่ยน">
                                <i class="bi bi-eye-slash toggle-password" onclick="togglePassword('passwordInput', this)"></i>
                            </div>
                        </div>

                        <div class="full">
                            <label>สิทธิ์การใช้งาน</label>
                            <select name="role" required>
                                <option value="user" <?= $user_edit['role']=='user'?'selected':'' ?>>User (อาจารย์)</option>
                                
                                <option value="staff" <?= $user_edit['role']=='staff'?'selected':'' ?>>Staff (สายสนับสนุน)</option>
                                <option value="staff_lead" <?= $user_edit['role']=='staff_lead'?'selected':'' ?>>Staff Lead (หัวหน้างาน Staff)</option>
                                
                                
                                <option value="manager" <?= $user_edit['role']=='manager'?'selected':'' ?>>Manager (ผู้บริหาร)</option>
                                <option value="admin" <?= $user_edit['role']=='admin'?'selected':'' ?>>Admin (ผู้ดูแลระบบ)</option>
                            </select>
                        </div>

                        <div class="full stack-between mt-4">
                            <a href="users.php" class="btn btn-muted">ยกเลิก</a>
                            <button type="submit" class="btn btn-primary">บันทึกการเปลี่ยนแปลง</button>
                        </div>
                    </form>
                </div>

            </div>
        </main>
    </div>
</div>

<script>
    function togglePassword(inputId, icon) {
        const input = document.getElementById(inputId);
        if (input.type === "password") {
            input.type = "text";
            icon.classList.remove("bi-eye-slash");
            icon.classList.add("bi-eye");
        } else {
            input.type = "password";
            icon.classList.remove("bi-eye");
            icon.classList.add("bi-eye-slash");
        }
    }
</script>

</body>
</html>