<?php
// เช็คหน่วยนับจาก Description
$is_days = strpos($item['description'], '[หน่วย: วัน]') !== false;
$desc_clean = str_replace(['[หน่วย: วัน]', '[หน่วย: ชั่วโมง]'], '', $item['description']);
?>

<div class="section-title">ข้อมูลการพัฒนางาน</div>

<div class="detail-grid">
    <div class="detail-label">ประเภท</div>
    <div class="detail-value">
        <span class="badge bg-light text-dark"><?= htmlspecialchars($item['code']) ?></span> 
        <?= htmlspecialchars($item['category_name']) ?>
    </div>
</div>

<div class="detail-grid">
    <div class="detail-label">หัวข้อ / ชื่องาน</div>
    <div class="detail-value font-bold text-lg">
        <?= htmlspecialchars($item['title']) ?>
    </div>
</div>

<div class="detail-grid">
    <div class="detail-label">รายละเอียด / รูปแบบ</div>
    <div class="detail-value">
        <?= nl2br(htmlspecialchars($desc_clean)) ?>
    </div>
</div>

<div class="detail-grid" style="background:#fffbeb;">
    <div class="detail-label">การคำนวณ</div>
    <div class="detail-value">
        <?php if ($is_days): ?>
            <strong><?= number_format($item['actual_hours'], 1) ?> วันทำการ</strong> 
            <span class="text-muted">x 7 ชั่วโมง</span>
        <?php else: ?>
            <strong><?= number_format($item['actual_hours'], 2) ?> ชั่วโมง</strong> 
            <span class="text-muted">x 1</span>
        <?php endif; ?>
    </div>
</div>

<div class="detail-grid" style="background:#f0fdf4; border-radius:8px; padding:10px; border:1px solid #bbf7d0;">
    <div class="detail-label text-success">ภาระงานสุทธิ</div>
    <div class="detail-value text-success font-bold text-xl">
        <?= number_format($item['computed_hours'], 2) ?> ชม.
    </div>
</div>