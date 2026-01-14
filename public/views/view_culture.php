<?php
// public/views/view_culture.php
// สำหรับแสดงผลด้านที่ 4: ทำนุบำรุงศิลปวัฒนธรรม

// แกะข้อมูลจาก Description
$lines = explode("\n", $item['description']);
$role_info = "";
$projects = [];

foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line)) continue;

    if (strpos($line, "บทบาท:") === 0) {
        // ดึงบรรทัดบทบาท
        $role_info = $line; 
    } elseif (strpos($line, "- ") === 0) {
        // ดึงรายการโครงการ (ตัดขีดข้างหน้าออก)
        $projects[] = substr($line, 2);
    }
}
?>

<div class="card p-4 border mb-4">
    <h4 class="text-primary mb-3"><i class="bi bi-info-circle"></i> รายละเอียดการปฏิบัติงาน</h4>
    
    <div class="alert info mb-3">
        <i class="bi bi-person-badge"></i> <strong><?= htmlspecialchars($role_info) ?></strong>
    </div>

    <div class="detail-grid">
        <div class="detail-label">รายชื่อโครงการ</div>
        <div class="detail-value">
            <?php if (count($projects) > 0): ?>
                <ul class="pl-4" style="margin:0; list-style-type: disc;">
                    <?php foreach ($projects as $proj): ?>
                        <li class="mb-1"><?= htmlspecialchars($proj) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <span class="text-muted">- ไม่ระบุรายชื่อ -</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="detail-grid mt-3 pt-3 border-top">
        <div class="detail-label">จำนวนโครงการ</div>
        <div class="detail-value"><?= number_format($item['actual_hours']) ?> โครงการ</div>
    </div>
    
    <div class="detail-grid">
        <div class="detail-label text-primary font-bold">คะแนนภาระงาน</div>
        <div class="detail-value text-primary font-bold" style="font-size:1.2rem;">
            <?= number_format($item['computed_hours'], 2) ?> คะแนน
        </div>
    </div>
</div>