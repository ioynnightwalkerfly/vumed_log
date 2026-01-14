<?php
// public/user_add.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// 1. ตรวจสอบสิทธิ์ (Admin หรือ Manager เท่านั้น)
if (!in_array($user['role'], ['admin', 'manager'])) {
    header("Location: index.php");
    exit;
}

$errors = [];
$input = [
    'name' => $_POST['name'] ?? '',
    'email' => $_POST['email'] ?? '',
    'role' => $_POST['role'] ?? 'user'
];

// 2. สร้าง CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ===== Process Form =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 3. ตรวจสอบ CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token.");
    }

    // 4. Validate Input
    if (empty($input['name'])) $errors[] = "กรุณากรอกชื่อ-นามสกุล";
    if (empty($input['email'])) $errors[] = "กรุณากรอกอีเมล";
    if (empty($_POST['password'])) $errors[] = "กรุณากรอกรหัสผ่าน";
    
    if (strlen($_POST['password']) < 4) $errors[] = "รหัสผ่านต้องมีอย่างน้อย 4 ตัวอักษร";

    // เช็คอีเมลซ้ำ
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $input['email']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "อีเมลนี้มีผู้ใช้งานแล้ว";
    }

    // 5. บันทึกข้อมูล
    if (empty($errors)) {
        $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssss", $input['name'], $input['email'], $password_hash, $input['role']);

        if ($stmt->execute()) {
            header("Location: users.php?success=เพิ่มผู้ใช้เรียบร้อยแล้ว");
            exit;
        } else {
            $errors[] = "Database Error: " . $stmt->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เพิ่มผู้ใช้ | MedUI System</title>
    <link rel="stylesheet" href="../medui/medui.css">
    <link rel="stylesheet" href="../medui/medui.components.css">
    <link rel="stylesheet" href="../medui/medui.layout.css">
    <link rel="stylesheet" href="../medui/medui.theme.medical.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>

<div class="app">
    
    <?php include_once '../inc/nav.php'; ?>

    <div class="app-content">
        
        <header class="topbar">
            <div class="container">
                <div class="topbar-content">
                    <div class="topbar-left">
                        <h3 style="margin:0;">เพิ่มผู้ใช้ใหม่</h3>
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
                            <ul>
                                <?php foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="grid" style="gap:20px;">
                        
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                        <div class="full">
                            <label>ชื่อ - นามสกุล</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($input['name']) ?>" required placeholder="เช่น สมชาย ใจดี">
                        </div>

                        <div class="full">
                            <label>อีเมล (Login)</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($input['email']) ?>" required placeholder="email@example.com">
                        </div>

                        <div class="full">
                            <label>รหัสผ่าน</label>
                            <input type="password" name="password" required placeholder="กำหนดรหัสผ่านอย่างน้อย 4 ตัวอักษร">
                        </div>

                        <div class="full">
                            <label>สิทธิ์การใช้งาน</label>
                            <select name="role" required>
                                <option value="user" <?= $input['role']=='user'?'selected':'' ?>>User (อาจารย์/สายวิชาการ)</option>
                                
                                <option value="staff" <?= $input['role']=='staff'?'selected':'' ?>>Staff (สายสนับสนุน)</option>
                                <option value="staff_lead" <?= $input['role']=='staff_lead'?'selected':'' ?>>Staff Lead (หัวหน้างาน/ผู้ตรวจ)</option>
                                <option value="manager" <?= $input['role']=='manager'?'selected':'' ?>>Manager (ผู้บริหาร)</option>
                                <option value="admin" <?= $input['role']=='admin'?'selected':'' ?>>Admin (ผู้ดูแลระบบ)</option>
                            </select>
                        </div>

                        <div class="full stack-between mt-4">
                            <a href="users.php" class="btn btn-muted">ยกเลิก</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> บันทึกข้อมูล
                            </button>
                        </div>

                    </form>
                </div>

            </div>
        </main>

    </div>
</div>

</body>
</html>