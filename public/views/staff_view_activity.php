<div class="section-title">กิจกรรมมหาวิทยาลัย</div>

<div class="detail-grid">
    <div class="detail-label">ประเภท</div>
    <div class="detail-value"><?= htmlspecialchars($item['category_name']) ?></div>
</div>

<div class="detail-grid">
    <div class="detail-label">ชื่อกิจกรรม / โครงการ</div>
    <div class="detail-value font-bold text-lg">
        <?= htmlspecialchars($item['title']) ?>
    </div>
</div>

<div class="detail-grid">
    <div class="detail-label">สถานที่ / รายละเอียด</div>
    <div class="detail-value">
        <?= nl2br(htmlspecialchars($item['description'])) ?>
    </div>
</div>

<div class="detail-grid">
    <div class="detail-label">เวลาที่เข้าร่วม</div>
    <div class="detail-value">
        <?= number_format($item['actual_hours'], 2) ?> ชั่วโมง
    </div>
</div>

<div class="detail-grid" style="background:#f0fdf4; border-radius:8px; padding:10px; border:1px solid #bbf7d0;">
    <div class="detail-label text-success">ภาระงานสุทธิ</div>
    <div class="detail-value text-success font-bold text-xl">
        <?= number_format($item['computed_hours'], 2) ?> ชม.
    </div>
</div>