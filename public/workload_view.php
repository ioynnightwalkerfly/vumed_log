<?php
// public/workload_view.php

require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php'; 

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: workloads.php?error=ไม่พบรายการภาระงาน");
    exit;
}

// 1. ดึงข้อมูลภาระงาน
if ($user['role'] === 'user') {
    $stmt = $conn->prepare("
        SELECT wi.*, wc.main_area, wc.name_th AS category_name, wc.weight, wc.calc_type, wc.code, 
               u.name AS user_name, u.role AS owner_role
        FROM workload_items wi
        LEFT JOIN workload_categories wc ON wi.category_id = wc.id
        JOIN users u ON wi.user_id = u.id
        WHERE wi.id = ? AND wi.user_id = ?
    ");
    $stmt->bind_param("ii", $id, $user['id']);
} else {
    $stmt = $conn->prepare("
        SELECT wi.*, wc.main_area, wc.name_th AS category_name, wc.weight, wc.calc_type, wc.code, 
               u.name AS user_name, u.role AS owner_role
        FROM workload_items wi
        LEFT JOIN workload_categories wc ON wi.category_id = wc.id
        JOIN users u ON wi.user_id = u.id
        WHERE wi.id = ?
    ");
    $stmt->bind_param("i", $id);
}
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    header("Location: workloads.php?error=ไม่พบข้อมูลหรือไม่มีสิทธิ์เข้าถึง");
    exit;
}

// 2. ดึง Timeline
$stmtLog = $conn->prepare("
    SELECT wl.*, u.name AS reviewer_name, u.role AS reviewer_role
    FROM workload_logs wl
    LEFT JOIN users u ON wl.user_id = u.id
    WHERE wl.work_log_id = ?
    ORDER BY wl.created_at ASC
");
$stmtLog->bind_param("i", $id);
$stmtLog->execute();
$allLogs = $stmtLog->get_result()->fetch_all(MYSQLI_ASSOC);

$reviewLogs = [];
$editLogs   = [];

foreach ($allLogs as $log) {
    if (in_array($log['action'], ['approve', 'approve_final', 'reject', 'verified', 'approved_admin', 'rejected'])) {
        $reviewLogs[] = $log;
    } else {
        $editLogs[] = $log;
    }
}

$mainAreaNames = [
    1 => "ด้านการสอน", 2 => "ด้านวิจัยและงานวิชาการ", 3 => "ด้านบริการวิชาการ",
    4 => "ด้านทำนุบำรุงศิลปวัฒนธรรม", 5 => "ด้านบริหาร", 6 => "ภาระงานอื่น ๆ"
];
$areaName = $mainAreaNames[$item['main_area']] ?? 'ไม่ทราบด้าน';
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>รายละเอียดภาระงาน</title>
  <link rel="stylesheet" href="../medui/medui.css">
  <link rel="stylesheet" href="../medui/medui.components.css">
  <link rel="stylesheet" href="../medui/medui.layout.css">
  <link rel="stylesheet" href="../medui/medui.theme.medical.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <?php include_once '../inc/table_style.php'; ?>
  <style>
    .detail-card { max-width: 950px; margin: auto; padding: 32px; }
    .detail-grid { display: grid; grid-template-columns: 200px 1fr; gap: 8px 20px; border-bottom: 1px solid #e5e7eb66; padding: 10px 0; }
    .detail-label { font-weight: 600; color: #555; }
    .detail-value { color: #1f2937; white-space: pre-line; word-wrap: break-word; }
    .section-title { font-size: 18px; font-weight: 600; margin-top: 24px; margin-bottom: 8px; color: #111827; border-bottom: 2px solid #eee; padding-bottom: 5px; }
    
    .reject-box { background-color: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; padding: 16px; border-radius: 8px; margin-bottom: 24px; }
    .reject-title { font-weight: bold; font-size: 1.1rem; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }

    .timeline { margin-top: 20px; padding-top: 10px; }
    .timeline-item { position: relative; padding-left: 30px; margin-bottom: 20px; }
    .timeline-item::before {
        content: ""; position: absolute; left: 8px; top: 6px;
        width: 12px; height: 12px; background: #ddd; border-radius: 50%;
        border: 2px solid #fff; box-shadow: 0 0 0 2px #ddd;
    }
    .timeline-date { font-size: 0.85rem; color: #888; margin-bottom: 4px; }
    .timeline-content { font-size: 1rem; color: #333; }
    .timeline-comment {
        margin-top: 8px; background: #f8f9fa; padding: 10px;
        border-radius: 6px; border-left: 3px solid var(--primary);
        font-size: 0.9rem; color: #555;
    }
    .badge-clickable { cursor: pointer; }

    /* [แก้ไข] เพิ่ม CSS นี้เพื่อให้ข้อความตัดบรรทัดเมื่อยาวเกิน */
    .text-break {
        word-wrap: break-word !important;
        overflow-wrap: break-word !important;
        word-break: break-word;
        white-space: pre-wrap;
        max-width: 100%;
    }
  </style>
</head>
<body>

<div class="app">
  <?php include '../inc/nav.php'; ?>

  <header class="topbar">
    <div class="left"><h3 style="margin:0;">รายละเอียดภาระงาน</h3></div>
    <div class="right">
      <?php $backLink = ($user['role'] === 'staff') ? 'staff_workloads.php' : 'workloads.php'; ?>
      <a href="<?= $backLink ?>" class="btn btn-muted"><i class="bi bi-arrow-left"></i> ย้อนกลับ</a>
    </div>
  </header>

  <main class="main">
    <?php include '../inc/alert.php'; ?>

    <div class="card detail-card">

      <?php if ($item['status'] === 'rejected'): ?>
        <div class="reject-box">
            <div class="reject-title">
                <i class="bi bi-exclamation-triangle-fill"></i> รายการนี้ถูกปฏิเสธ / ส่งกลับแก้ไข
            </div>
            <div class="text-break" style="padding-left: 28px; width: 100%;">
                <?= nl2br(htmlspecialchars($item['reject_reason'] ?? '-')) ?>
            </div>
        </div>
      <?php endif; ?>

      <h2 class="mb-2"><?= htmlspecialchars($item['title'] ?? ''); ?></h2>
      <p class="muted mb-4"><?= htmlspecialchars($areaName); ?> › <?= htmlspecialchars($item['category_name'] ?? ''); ?></p>

      <div class="detail-grid">
        <div class="detail-label">ผู้ส่งภาระงาน</div>
        <div class="detail-value">
            <?= htmlspecialchars($item['user_name'] ?? ''); ?>
            <span class="badge bg-light text-muted small"><?= ucfirst($item['owner_role'] ?? '') ?></span>
        </div>
      </div>

      <div class="detail-grid">
        <div class="detail-label">สถานะ</div>
        <div class="detail-value">
          <?php if ($item['status'] === 'rejected'): ?>
             <span class="badge rejected badge-clickable" onclick="openRejectModal()">
                 <i class="bi bi-exclamation-circle-fill"></i> ไม่อนุมัติ / แก้ไข (คลิกดูเหตุผล)
             </span>
          <?php else: ?>
             <span class="badge 
               <?= ($item['status']=='approved' || $item['status']=='approved_final')?'approved':
                  (($item['status']=='approved_admin' || $item['status']=='verified')?'info':
                  (($item['status']=='draft')?'draft':'pending')); ?>">
               <?php
                 if ($item['status']=='approved' || $item['status']=='approved_final') echo 'อนุมัติแล้ว';
                 elseif ($item['status']=='approved_admin' || $item['status']=='verified') echo 'ผ่านการตรวจสอบเบื้องต้น';
                 elseif ($item['status']=='draft') echo 'ฉบับร่าง';
                 else echo 'รออนุมัติ';
               ?>
             </span>
          <?php endif; ?>
        </div>
      </div>

      <?php
        $workload = $item; 
        $viewFile = '';
        
        if (isset($item['owner_role']) && $item['owner_role'] === 'staff') {
            switch ($item['main_area']) {
                case 1: $viewFile = 'views/staff_view_routine.php'; break;
                case 2: $viewFile = 'views/staff_view_development.php'; break;
                case 3: $viewFile = 'views/staff_view_strategy.php'; break;
                case 4: $viewFile = 'views/staff_view_assigned.php'; break; 
                case 5: $viewFile = 'views/staff_view_activity.php'; break; 
                case 6: $viewFile = 'views/staff_view_admin.php'; break;
                default:
                   if ($item['main_area'] == 6) $viewFile = 'views/staff_view_routine.php';
                   elseif ($item['main_area'] == 7) $viewFile = 'views/staff_view_strategy.php';
                   elseif ($item['main_area'] == 8) $viewFile = 'views/staff_view_assigned.php';
                   elseif ($item['main_area'] == 9) $viewFile = 'views/staff_view_development.php';
                   break;
            }
        } else {
            switch ($item['main_area']) {
                case 1: $viewFile = 'views/view_teaching.php'; break;
                case 2: $viewFile = 'views/view_research.php'; break;
                case 3: $viewFile = 'views/view_service.php'; break;
                case 4: $viewFile = 'views/view_culture.php'; break;
                case 5: $viewFile = 'views/view_admin.php'; break;
                case 6: $viewFile = 'views/view_other.php'; break;
            }
        }
        
        if (!empty($viewFile) && file_exists($viewFile)) {
            include $viewFile;
        } else {
             echo "<div class='section-title'>รายละเอียดเพิ่มเติม</div>";
             echo "<div class='detail-value mb-4'>" . nl2br(htmlspecialchars($item['description'] ?: '-')) . "</div>";
             echo "<div class='detail-grid'><div class='detail-label'>ชั่วโมงปฏิบัติจริง</div><div class='detail-value'>" . number_format($item['actual_hours'], 2) . " ชม.</div></div>";
             echo "<div class='detail-grid'><div class='detail-label'>รวมชั่วโมงภาระงาน</div><div class='detail-value'><strong>" . number_format($item['computed_hours'], 2) . " ชม.</strong></div></div>";
        }
      ?>

      <div class="section-title">หลักฐาน / ไฟล์แนบ</div>
      <div class="detail-value mb-4">
        <?php if (!empty($item['evidence'])): ?>
          <div class="mb-2">
              <a href="../uploads/<?= htmlspecialchars($item['evidence']); ?>" target="_blank" class="btn btn-sm btn-outline">
                <i class="bi bi-file-earmark-pdf"></i> เปิดไฟล์แนบ
              </a>
          </div>
        <?php endif; ?>
        
        <?php if (!empty($item['attachment_link'])): ?>
          <div>
              <a href="<?= htmlspecialchars($item['attachment_link']); ?>" target="_blank" class="btn btn-sm btn-outline text-primary" style="border-color:var(--primary);">
                <i class="bi bi-link-45deg"></i> เปิดลิงก์เพิ่มเติม
              </a>
          </div>
        <?php endif; ?>

        <?php if (empty($item['evidence']) && empty($item['attachment_link'])): ?>
          <span class="muted">- ไม่มีหลักฐานแนบ -</span>
        <?php endif; ?>
      </div>

      <hr class="mb-4">
      
      <div class="timeline-section">
          <div class="text-primary font-bold mb-3"><i class="bi bi-clock-history"></i> ประวัติการดำเนินการ</div>
          <div class="timeline">
              <?php if (count($allLogs) > 0): ?>
                  <?php foreach ($allLogs as $log): ?>
                      <div class="timeline-item">
                          <div class="timeline-date"><?= date('d/m/Y H:i', strtotime($log['created_at'])); ?></div>
                          <div class="timeline-content">
                              <strong><?= htmlspecialchars($log['reviewer_name'] ?? 'System') ?></strong> : 
                              <?php 
                                  $act = $log['action'];
                                  if ($act == 'create') echo 'สร้างรายการ';
                                  elseif ($act == 'update') echo 'แก้ไขข้อมูล';
                                  elseif ($act == 'approved_admin' || $act == 'verified') echo '<span class="text-info font-bold">ผ่านการตรวจสอบขั้นต้น</span>';
                                  elseif ($act == 'approve' || $act == 'approve_final') echo '<span class="text-success font-bold">อนุมัติแล้ว</span>';
                                  elseif ($act == 'rejected' || $act == 'reject') echo '<span class="text-danger font-bold">ส่งคืนแก้ไข</span>';
                                  else echo $act;
                              ?>
                          </div>
                          <?php if (!empty($log['comment'])): ?>
                              <div class="timeline-comment">
                                  <?= nl2br(htmlspecialchars($log['comment'])); ?>
                              </div>
                          <?php endif; ?>
                      </div>
                  <?php endforeach; ?>
              <?php else: ?>
                  <div class="text-muted small pl-4">- ยังไม่มีประวัติ -</div>
              <?php endif; ?>
          </div>
      </div>

      <div class="stack-between mt-6 pt-4 border-top">
        <a href="<?= $backLink ?>" class="btn btn-muted">ย้อนกลับ</a>
        <div class="stack-right">
          <?php if ($user['role'] === 'user' && !in_array($item['status'], ['approved', 'approved_final'])): ?>
            <a href="workload_edit.php?id=<?= $item['id']; ?>" class="btn btn-primary">แก้ไข</a>
            <a href="workload_delete.php?id=<?= $item['id']; ?>" class="btn btn-danger" onclick="return confirm('ยืนยันลบ?')">ลบ</a>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </main>
</div>

<div class="modal" id="rejectReasonModal">
    <div class="modal-content" style="max-width:500px;">
        <h3 class="text-danger mb-4" style="border-bottom:1px solid #eee; padding-bottom:10px;">
            <i class="bi bi-exclamation-triangle"></i> เหตุผลการปฏิเสธ
        </h3>
        
        <div class="p-4 bg-light rounded mb-4 text-break" style="font-size:1.1rem; line-height:1.6;">
            <?= nl2br(htmlspecialchars($item['reject_reason'] ?? 'ไม่ระบุเหตุผล')) ?>
        </div>
        
        <div class="text-right mt-4">
            <button class="btn btn-primary" onclick="closeRejectModal()">รับทราบ</button>
        </div>
    </div>
</div>

<script>
function openRejectModal() { document.getElementById('rejectReasonModal').classList.add('show'); }
function closeRejectModal() { document.getElementById('rejectReasonModal').classList.remove('show'); }
</script>

</body>
</html>