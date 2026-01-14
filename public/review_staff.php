<?php
// vumedhr/public/review_staff.php
require_once '../config/app.php';
require_once '../middleware/require_staff_lead.php';


// ดึงงาน Staff ที่ "รอตรวจสอบ"
$sql = "SELECT i.*, u.name as user_name, c.name_th as category_name, c.code as category_code
        FROM workload_items i
        JOIN users u ON i.user_id = u.id
        JOIN workload_categories c ON i.category_id = c.id
        WHERE i.status = 'pending' 
          AND c.target_group = 'staff'
        ORDER BY i.created_at ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ตรวจภาระงาน (สายสนับสนุน) | MedUI</title>
    <?php require_once '../inc/header.php'; ?>
    <link rel="stylesheet" href="../medui/medui.css">
</head>
<body>
<div class="app">
    <?php require_once '../inc/nav.php'; ?>
    <main class="main">
        <div class="container">
            <h2 class="text-primary"><i class="bi bi-people"></i> ตรวจภาระงาน (สายสนับสนุน)</h2>
            <p class="muted">รายการรอตรวจสอบเบื้องต้น ก่อนส่งต่อผู้บริหาร</p>
            
            <div class="card table-card">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>วันที่</th>
                                <th>เจ้าหน้าที่</th>
                                <th>งาน</th>
                                <th>สถานะ</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($row['created_at'])) ?></td>
                                <td><?= htmlspecialchars($row['user_name']) ?></td>
                                <td><?= htmlspecialchars($row['title']) ?></td>
                                <td><span class="badge pending">รอตรวจสอบ</span></td>
                                <td>
                                    <form action="review_action_staff.php" method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <input type="hidden" name="action" value="verify"> <button class="btn btn-sm btn-primary">ผ่านการตรวจสอบ</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>