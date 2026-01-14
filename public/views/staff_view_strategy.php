<?php
$is_days = strpos($item['description'], '[หน่วย: วัน]') !== false;
$desc_clean = str_replace(['[หน่วย: วัน]', '[หน่วย: ชั่วโมง]'], '', $item['description']);
?>
<div class="section-title">ข้อมูลงานยุทธศาสตร์</div>

<div class="detail-grid">
    <div class="detail-label">บทบาท / ผลสำเร็จ</div>
    <div class="detail-value">
        <?= htmlspecialchars($item['category_name']) ?>
    </div>
</div>

<div class="detail-grid">
    <div class="detail-label">ชื่อโครงการ (KPI)</div>
    <div class="detail-value font-bold text-lg">
        <?= htmlspecialchars($item['title']) ?>
    </div>
</div>

<div class="detail-grid">
    <div class="detail-label">รายละเอียด / การรับรอง</div>
    <div class="detail-value">
        <?= nl2br(htmlspecialchars($desc_clean)) ?>
    </div>
</div>

<div class="detail-grid" style="background:#fffbeb;">
    <div class="detail-label">เวลาที่ใช้จริง</div>
    <div class="detail-value">
        <?php if ($is_days): ?>
            <strong><?= number_format($item['actual_hours'], 1) ?> วันทำการ</strong> 
            <span class="text-muted">(คิดเป็น <?= number_format($item['computed_hours'], 2) ?> ชม.)</span>
        <?php else: ?>
            <strong><?= number_format($item['actual_hours'], 2) ?> ชั่วโมง</strong>
        <?php endif; ?>
    </div>
</div>

<div class="detail-grid" style="background:#f0fdf4; border-radius:8px; padding:10px; border:1px solid #bbf7d0;">
    <div class="detail-label text-success">ภาระงานสุทธิ</div>
    <div class="detail-value text-success font-bold text-xl">
        <?= number_format($item['computed_hours'], 2) ?> ชม.
    </div>
</div>