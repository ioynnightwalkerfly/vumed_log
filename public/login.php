<?php
// public/login.php
require_once '../config/app.php';

// 1. 
if (isset($_SESSION['user'])) {
    // ถ้าเป็น Admin/Manager ไปหน้า Admin
    if (in_array($_SESSION['user']['role'], ['admin', 'manager'])) {
        header("Location: admin_dashboard.php");
    } else {
        // ถ้าเป็น User ทั่วไป ไปหน้า User
        header("Location: index.php");
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>เข้าสู่ระบบ | MedUI System</title>
  <link rel="stylesheet" href="../medui/medui.css">
  <link rel="stylesheet" href="../medui/medui.components.css">
  <link rel="stylesheet" href="../medui/medui.layout.css">
  <link rel="stylesheet" href="../medui/medui.theme.medical.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;background:var(--bg);">

<div class="card" style="width:100%;max-width:400px;padding:32px;">
  <div class="text-center mb-4">
    <div style="font-size: 3rem; color: var(--primary); margin-bottom: 10px;">
       <img src="logo.png" alt="">
    </div>
    <h2 class="mt-2">ระบบจัดการภาระงาน</h2>
    <p class="muted">คณะแพทยศาสตร์ มหาวิทยาลัย</p>
  </div>

  <?php if (isset($_GET['error'])): ?>
    <div class="alert error mb-4">
        <i class="bi bi-exclamation-circle"></i> 
        <?php echo htmlspecialchars($_GET['error']); ?>
    </div>
  <?php endif; ?>

  <form action="login_verify.php" method="POST" class="grid" style="gap: 20px;">
    <div class="full">
      <label>อีเมล</label>
      <input type="email" name="email" placeholder="your@email.com" required autofocus>
    </div>
    <div class="full">
      <label>รหัสผ่าน</label>
      <input type="password" name="password" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn btn-primary w-100" style="margin-top:10px;">
        <i class="bi bi-box-arrow-in-right"></i> เข้าสู่ระบบ
    </button>
  </form>
</div>

</body>
</html>