<?php
// inc/nav.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);
$sessionUser = $_SESSION['user'] ?? null;
$userRole = $sessionUser['role'] ?? 'guest';

function isActive($pageName) {
    global $currentPage;
    if (is_array($pageName)) {
        return in_array($currentPage, $pageName) ? 'active' : '';
    }
    return $currentPage === $pageName ? 'active' : '';
}
?>

<aside class="sidebar no-print">
  <div class="sidebar-header">
      <div class="brand">MedHR System</div>
  </div>
  
  <nav class="nav sidebar-nav">
    <ul>
        
        <?php if ($userRole === 'staff'): ?>
            <li>
                <a href="staff_index.php" class="<?= isActive('staff_index.php') ?>">
                    <i class="bi bi-house-door-fill"></i><span class="text">หน้าหลัก (สนับสนุน)</span>
                </a>
            </li>
            <li>
                <a href="staff_workloads.php" class="<?= isActive(['staff_workloads.php', 'staff_workload_add.php', 'staff_workload_edit.php']) ?>">
                    <i class="bi bi-clipboard-data"></i><span class="text">บันทึกการปฏิบัติงาน</span>
                </a>
            </li>
            <li>
                <a href="staff_stats.php" target="_blank">
                    <i class="bi bi-printer"></i><span class="text">รายงานสรุป</span>
                </a>
            </li>

        <?php elseif ($userRole === 'staff_lead'): ?>
            <li class="menu-header">ส่วนหัวหน้างาน</li>
            <li>
                <a href="review_staff.php" class="<?= isActive('review_staff.php') ?>">
                    <i class="bi bi-check-square"></i><span class="text">ตรวจภาระงาน (Staff)</span>
                </a>
            </li>

        <?php else: ?>
            <li>
                <a href="index.php" class="<?= isActive('index.php') ?>">
                    <i class="bi bi-house-door"></i><span class="text">ภาพรวม</span>
                </a>
            </li>
            <li>
                <a href="workloads.php" class="<?= isActive(['workloads.php', 'workload_add.php', 'workload_view.php', 'workload_edit.php', 'workload_select.php']) ?>">
                    <i class="bi bi-list-task"></i><span class="text">ภาระงานของฉัน</span>
                </a>
            </li>
            <li>
                <a href="stats.php" class="<?= isActive('stats.php') ?>">
                    <i class="bi bi-pie-chart"></i> <span class="text">รายงานสถิติ</span>
                </a>
            </li>
        <?php endif; ?>


        <?php if ($userRole === 'admin' || $userRole === 'manager'): ?>
            <li class="menu-header">ส่วนการบริหาร</li>
            <li>
                <a href="admin_dashboard.php" class="<?= isActive(['admin_dashboard.php', 'admin_user_stats.php']) ?>">
                    <i class="bi bi-speedometer2"></i><span class="text">แดชบอร์ดบริหาร</span>
                </a>
            </li>
            <li>
                <a href="admin_stats.php" class="<?= isActive('admin_stats.php') ?>">
                    <i class="bi bi-bar-chart"></i><span class="text">รายงานภาพรวม</span>
                </a>
            </li>
        <?php endif; ?>


        <?php if ($userRole === 'admin'): ?>
            <li>
                <a href="review_admin.php" class="<?= isActive(['review_admin.php', 'review_view.php']) ?>">
                    <i class="bi bi-search"></i><span class="text">ตรวจงาน (เจ้าหน้าที่)</span>
                </a>
            </li>
            <li>
                <a href="users.php" class="<?= isActive(['users.php', 'user_add.php', 'user_edit.php', 'admin_user_workloads.php']) ?>">
                    <i class="bi bi-people"></i><span class="text">จัดการผู้ใช้</span>
                </a>
            </li>
            <li>
                <a href="admin_cleanup.php" class="<?= isActive('admin_cleanup.php') ?>">
                    <i class="bi bi-trash3"></i><span class="text">ล้างไฟล์ขยะ</span>
                </a>
            </li>
        <?php endif; ?>


        <?php if ($userRole === 'manager'): ?>
            <li>
                <a href="review_manager.php" class="<?= isActive(['review_manager.php', 'review_view_manager.php']) ?>">
                    <i class="bi bi-check2-all"></i><span class="text">อนุมัติงาน (หัวหน้า)</span>
                </a>
            </li>
        <?php endif; ?>

        <li style="margin-top: 20px; border-top: 1px solid rgba(0,0,0,0.05); padding-top: 10px;">
            <a href="profile.php" class="<?= isActive('profile.php') ?>">
                <i class="bi bi-person-gear"></i><span class="text">ข้อมูลส่วนตัว</span>
            </a>
        </li>
        <li>
            <a href="logout.php" class="text-danger">
                <i class="bi bi-box-arrow-right"></i><span class="text">ออกจากระบบ</span>
            </a>
        </li>

    </ul>
  </nav>
</aside>