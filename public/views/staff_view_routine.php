<div class="section-title">ข้อมูลภาระงานหลัก / งานประจำ</div>

<div class="detail-grid">
    <div class="detail-label">ประเภทงาน</div>
    <div class="detail-value">
        <span class="badge bg-light text-dark"><?= htmlspecialchars($item['code']) ?></span> 
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
    <div class="detail-label">รายละเอียดผลการดำเนินงาน</div>
    <div class="detail-value">
        <?= nl2br(htmlspecialchars($item['description'] ?? '-')) ?>
    </div>
</div>

<div class="detail-grid">
    <div class="detail-label">เวลาปฏิบัติงานจริง</div>
    <div class="detail-value">
        <?= number_format($item['actual_hours'], 2) ?> ชั่วโมง
    </div>
</div>

<div class="detail-grid" style="background:#f0fdf4; border-radius:8px; padding:10px; border:1px solid #bbf7d0;">
    <div class="detail-label text-success">ภาระงานสุทธิ (x1)</div>
    <div class="detail-value text-success font-bold text-xl">
        <?= number_format($item['computed_hours'], 2) ?> ชม.
    </div>
</div>