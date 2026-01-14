<?php
require_once '../config/app.php';

// รับข้อความ Error จาก URL (ถ้าไม่มีให้ใช้ค่า Default)
$msg = $_GET['msg'] ?? 'เกิดข้อผิดพลาดบางอย่าง';
$code = $_GET['code'] ?? '404'; // รหัส Error เช่น 403, 404

// กำหนดหัวข้อและไอคอนตามรหัส
if ($code == '403') {
    $title = "Access Denied";
    $sub = "คุณไม่มีสิทธิ์เข้าถึงหน้านี้";
    $icon = "bi-shield-lock-fill";
    $color = "text-danger";
} elseif ($code == '404') {
    $title = "Page Not Found";
    $sub = "ไม่พบหน้าที่คุณต้องการ";
    $icon = "bi-exclamation-triangle-fill";
    $color = "text-warning";
} else {
    $title = "Error";
    $sub = $msg;
    $icon = "bi-x-circle-fill";
    $color = "text-muted";
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Error | MedUI System</title>
    <link rel="stylesheet" href="../medui/medui.css">
    <link rel="stylesheet" href="../medui/medui.components.css">
    <link rel="stylesheet" href="../medui/medui.layout.css">
    <link rel="stylesheet" href="../medui/medui.theme.medical.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body style="display: flex; align-items: center; justify-content: center; min-height: 100vh; background: var(--bg);">

    <div class="card text-center p-6" style="max-width: 400px; width: 90%;">
        
        <div style="font-size: 4rem; margin-bottom: 1rem;" class="<?= $color ?>">
            <i class="bi <?= $icon ?>"></i>
        </div>

        <h2 class="mb-2"><?= $title ?></h2>
        <p class="muted mb-6"><?= htmlspecialchars($sub) ?></p>

        <div class="stack-center gap-2">
            <button onclick="history.back()" class="btn btn-outline">
                <i class="bi bi-arrow-left"></i> ย้อนกลับ
            </button>

            <a href="index.php" class="btn btn-primary">
                <i class="bi bi-house-door"></i> หน้าหลัก
            </a>
        </div>
    </div>

</body>
</html>