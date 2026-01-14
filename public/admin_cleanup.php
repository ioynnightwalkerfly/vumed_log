<?php
// public/admin_cleanup.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// 1. สงวนสิทธิ์เฉพาะ Admin
if ($user['role'] !== 'admin') {
    header("Location: index.php?error=Access Denied");
    exit;
}

$uploadDir = "../uploads/";
$orphans = []; // เก็บชื่อไฟล์ขยะ
$totalSize = 0; // เก็บขนาดรวม
$state = 'idle'; // สถานะ: idle (ว่าง), scanned (สแกนแล้ว), done (ลบแล้ว)

// ฟังก์ชันช่วยแปลงขนาดไฟล์
function formatSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

// ---------------------------------------------------
// Logic: การทำงาน
// ---------------------------------------------------

// ถ้ามีการกด "สแกน" หรือ "ยืนยันลบ"
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. ดึงรายชื่อไฟล์ที่ "ใช้งานอยู่" จาก DB
    $activeFiles = [];
    $result = $conn->query("SELECT evidence FROM workload_items WHERE evidence IS NOT NULL AND evidence != ''");
    while ($row = $result->fetch_assoc()) {
        $activeFiles[] = $row['evidence'];
    }

    // 2. สแกนไฟล์ทั้งหมดในโฟลเดอร์
    $allFiles = scandir($uploadDir);

    // 3. หาไฟล์ส่วนเกิน (Orphans)
    foreach ($allFiles as $file) {
        if ($file === '.' || $file === '..') continue;
        
        // ถ้าไฟล์นี้ ไม่อยู่ใน DB
        if (!in_array($file, $activeFiles) && is_file($uploadDir . $file)) {
            $orphans[] = $file;
            $totalSize += filesize($uploadDir . $file);
        }
    }

    // --- กรณี A: กดสแกน ---
    if (isset($_POST['scan'])) {
        $state = 'scanned';
    }

    // --- กรณี B: กดยืนยันลบ ---
    if (isset($_POST['confirm_delete'])) {
        $deletedCount = 0;
        foreach ($orphans as $file) {
            if (unlink($uploadDir . $file)) {
                $deletedCount++;
            }
        }
        $state = 'done'; // จบการทำงาน
        $msg = "ลบไฟล์ขยะเรียบร้อยแล้ว จำนวน $deletedCount ไฟล์ (ประหยัดพื้นที่ " . formatSize($totalSize) . ")";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ระบบล้างไฟล์ขยะ | Admin</title>
    <link rel="stylesheet" href="../medui/medui.css">
    <link rel="stylesheet" href="../medui/medui.components.css">
    <link rel="stylesheet" href="../medui/medui.layout.css">
    <link rel="stylesheet" href="../medui/medui.theme.medical.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>

<div class="app">
    <?php include '../inc/nav.php'; ?>

    <div class="app-content">
        <header class="topbar">
            <div class="container">
                <div class="topbar-content">
                    <div class="topbar-left">
                        <h3 style="margin:0;">ระบบล้างไฟล์ขยะ (Cleanup)</h3>
                    </div>
                    <div class="topbar-right">
                        <a href="admin_dashboard.php" class="btn btn-sm btn-muted">กลับ Dashboard</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="main">
            <div class="container" style="max-width: 800px;">

                <?php if ($state === 'done'): ?>
                    <div class="alert success mb-4">
                        <i class="bi bi-check-circle-fill"></i> <?= $msg ?>
                    </div>
                    <div class="text-center mt-4">
                        <a href="admin_cleanup.php" class="btn btn-outline">กลับไปหน้าสแกน</a>
                    </div>
                <?php endif; ?>

                <?php if ($state !== 'done'): ?>
                <div class="card p-6">
                    <div class="text-center mb-4">
                        <div style="font-size: 3rem; color: var(--primary); margin-bottom: 1rem;">
                            <i class="bi bi-hdd-network"></i>
                        </div>
                        <h2 class="mb-2">จัดการพื้นที่ Server</h2>
                        <p class="muted">
                            ระบบจะตรวจสอบไฟล์ที่ตกค้าง (ไม่มีเจ้าของในฐานข้อมูล) <br>
                            เพื่อให้คุณตรวจสอบก่อนทำการลบ
                        </p>
                    </div>

                    <?php if ($state === 'idle'): ?>
                        <form method="POST" class="text-center">
                            <button type="submit" name="scan" class="btn btn-primary btn-lg">
                                <i class="bi bi-search"></i> สแกนหาไฟล์ขยะ
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($state === 'scanned'): ?>
                        <?php if (count($orphans) > 0): ?>
                            <div class="alert warning mb-4">
                                <strong>พบไฟล์ขยะ:</strong> <?= count($orphans) ?> ไฟล์ 
                                <strong>ขนาดรวม:</strong> <?= formatSize($totalSize) ?>
                                <p class="m-0 text-sm mt-1">รายการไฟล์เหล่านี้ไม่มีการเชื่อมโยงกับภาระงานใดๆ ในระบบ</p>
                            </div>

                            <div class="table-wrap mb-4" style="max-height: 300px; overflow-y: auto; border:1px solid #eee;">
                                <table class="table table-row-hover">
                                    <thead>
                                        <tr>
                                            <th>ชื่อไฟล์</th>
                                            <th class="text-right">ขนาด</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orphans as $f): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($f) ?></td>
                                                <td class="text-right"><?= formatSize(filesize($uploadDir . $f)) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <form method="POST" class="stack-between">
                                <a href="admin_cleanup.php" class="btn btn-muted">ยกเลิก</a>
                                <button type="submit" name="confirm_delete" class="btn btn-danger" onclick="return confirm('ยืนยันการลบไฟล์เหล่านี้ถาวร?');">
                                    <i class="bi bi-trash3"></i> ลบไฟล์ขยะทั้งหมด
                                </button>
                            </form>

                        <?php else: ?>
                            <div class="alert success text-center p-5">
                                <i class="bi bi-check-circle" style="font-size: 2rem;"></i><br>
                                <strong>ไม่พบไฟล์ขยะ</strong><br>
                                ระบบของคุณสะอาดเรียบร้อยดี
                            </div>
                            <div class="text-center mt-4">
                                <a href="admin_cleanup.php" class="btn btn-muted">ตกลง</a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                </div>
                <?php endif; ?>

            </div>
        </main>
    </div>
</div>

</body>
</html>