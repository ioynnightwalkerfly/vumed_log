<?php
$is_days = strpos($item['description'], '[หน่วย: วัน]') !== false;
$desc_clean = str_replace(['[หน่วย: วัน]', '[หน่วย: ชั่วโมง]'], '', $item['description']);
?>
<div class="section-title">งานอื่น ๆ ที่ได้รับมอบหมาย</div>

<div class="detail-grid">
    <div class="detail-label">ประเภทงาน</div>
    <div class="detail-value">
        <?= htmlspecialchars($item['category_name']) ?>
    </div>
</div>

<div class="detail-grid">
    <div class="detail-label">ชื่องาน / กิจกรรม</div>
    <div class="detail-value font-bold text-lg">
        <?= htmlspecialchars($item['title']) ?>
    </div>
</div>

<div class="detail-grid">
    <div class="detail-label">รายละเอียด / คำสั่ง</div>
    <div class="detail-value">
        <?= nl2br(htmlspecialchars($desc_clean)) ?>
    </div>
</div>

<div class="detail-grid">
    <div class="detail-label">เวลาปฏิบัติงาน</div>
    <div class="detail-value">
        <?php if ($is_days): ?>
            <strong><?= number_format($item['actual_hours'], 1) ?> วัน</strong> (x7)
        <?php else: ?>
            <strong><?= number_format($item['actual_hours'], 2) ?> ชั่วโมง</strong> (x1)
        <?php endif; ?>
    </div>
</div>

<div class="detail-grid" style="background:#f0fdf4; border-radius:8px; padding:10px; border:1px solid #bbf7d0;">
    <div class="detail-label text-success">ภาระงานสุทธิ</div>
    <div class="detail-value text-success font-bold text-xl">
        <?= number_format($item['computed_hours'], 2) ?> ชม.
    </div>
</div>