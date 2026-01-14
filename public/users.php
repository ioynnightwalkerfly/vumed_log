<?php
// public/users.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// 1. ตรวจสอบสิทธิ์ (เฉพาะ Admin/Manager)
if (!in_array($user['role'], ['admin', 'manager'])) {
    header("Location: index.php");
    exit;
}

// 2. สร้าง CSRF Token (สำหรับ Modal ลบ)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// 3. ดึงรายชื่อผู้ใช้
$stmt = $conn->prepare("SELECT id, name, email, role, created_at FROM users ORDER BY id DESC");
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการผู้ใช้ | MedUI System</title>
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
                        <h3 style="margin:0;">การจัดการผู้ใช้</h3>
                    </div>
                    <div class="topbar-right">
                        <span class="pill">
                            <i class="bi bi-person-circle"></i> 
                            <?php echo htmlspecialchars($user['name']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </header>

        <main class="main">
            <div class="container">
                
                <?php include '../inc/alert.php'; ?>

                <div class="stack-between mb-4">
                    <div>
                        <h2>รายชื่อผู้ใช้</h2>
                        <p class="muted">จัดการบัญชีผู้ใช้งานในระบบ</p>
                    </div>
                    <a href="user_add.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> เพิ่มผู้ใช้
                    </a>
                </div>

                <div class="card table-card">
                    <div class="table-wrap">
                        <table class="table table-row-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>ชื่อ - นามสกุล</th>
                                    <th>อีเมล</th>
                                    <th>สิทธิ์</th>
                                    <th>วันที่สร้าง</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $row['id']; ?></td>
                                        <td>
                                            <div style="font-weight: 600;"><?php echo htmlspecialchars($row['name']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td>
                                            <span class="badge <?php 
                                                if ($row['role'] === 'admin') echo 'badge-danger';
                                                elseif ($row['role'] === 'manager') echo 'badge-warning';
                                                elseif ($row['role'] === 'staff_lead') echo 'badge-info';
                                                else echo 'badge-primary';
                                            ?>">
                                                <?php echo ucfirst($row['role']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="user_edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-muted">
                                                    <i class="bi bi-pencil"></i> แก้ไข
                                                </a>
                                                
                                                <?php if($row['id'] !== $user['id']): // ห้ามลบตัวเอง ?>
                                                    <button 
                                                        class="btn btn-sm btn-danger delete-btn" 
                                                        data-id="<?php echo $row['id']; ?>" 
                                                        data-name="<?php echo htmlspecialchars($row['name']); ?>"
                                                        data-email="<?php echo htmlspecialchars($row['email']); ?>"
                                                        data-role="<?php echo htmlspecialchars($row['role']); ?>">
                                                        <i class="bi bi-trash"></i> ลบ
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center muted">ไม่พบข้อมูลผู้ใช้</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    
    </div> 
</div> 

<div class="modal" id="deleteModal">
    <div class="modal-content" style="max-width:420px; position:relative;">
        <span class="close-modal" id="closeModal" style="position:absolute; top:16px; right:16px; cursor:pointer; font-size:1.2rem;">&times;</span>
        
        <h3 class="mb-2">ยืนยันการลบผู้ใช้</h3>
        <p class="muted mb-4">คุณแน่ใจหรือไม่ว่าต้องการลบผู้ใช้ต่อไปนี้? การกระทำนี้ไม่สามารถย้อนกลับได้</p>

        <div class="bg-light rounded p-4 mb-4 text-center border">
            <strong id="modalUserName" style="font-size:1.1rem; display:block; margin-bottom:4px;">Name</strong>
            <span id="modalUserEmail" class="muted" style="display:block; margin-bottom:8px;">email@example.com</span>
            <span id="modalUserRole" class="badge">ROLE</span>
        </div>

        <form method="POST" id="deleteForm" action="user_delete_action.php">
            <input type="hidden" name="id" id="deleteUserId"> 
            <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
            
            <div class="stack-between">
                <button type="button" class="btn btn-muted" onclick="hideModal()">ยกเลิก</button>
                <button type="submit" class="btn btn-danger">ยืนยันลบผู้ใช้</button>
            </div>
        </form>
    </div>
</div>

<script>
const modal = document.getElementById("deleteModal");
const closeModal = document.getElementById("closeModal");
const deleteButtons = document.querySelectorAll(".delete-btn");

// ฟังก์ชันเปิด Modal
deleteButtons.forEach(btn => {
    btn.onclick = () => {
        const id = btn.dataset.id;
        const name = btn.dataset.name;
        const email = btn.dataset.email;
        const role = btn.dataset.role;

        // ใส่ข้อมูลลงใน Modal
        document.getElementById("deleteUserId").value = id;
        document.getElementById("modalUserName").textContent = name;
        document.getElementById("modalUserEmail").textContent = email;
        
        const roleBadge = document.getElementById("modalUserRole");
        roleBadge.textContent = role.charAt(0).toUpperCase() + role.slice(1);
        
        // แก้ไขจุดที่ 2: เพิ่มเงื่อนไข staff_lead ใน JS
        roleBadge.className = "badge " + (
            role === "admin" ? "badge-danger" : 
            (role === "manager" ? "badge-warning" : 
            (role === "staff_lead" ? "badge-info" : "badge-primary"))
        );

        modal.classList.add("show"); // แสดง Modal
    };
});

// ฟังก์ชันปิด Modal
function hideModal() {
    modal.classList.remove("show");
}

closeModal.onclick = hideModal;
modal.onclick = e => { if (e.target === modal) hideModal(); };
</script>

</body>
</html>