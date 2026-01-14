<div class="section-title">ภาระงานบริหาร (สายสนับสนุน)</div>

<div class="detail-grid">
    <div class="detail-label">ตำแหน่งบริหาร</div>
    <div class="detail-value font-bold text-lg">
        <?= htmlspecialchars($item['category_name']) ?>
    </div>
</div>

<div class="detail-grid">
    <div class="detail-label">หน่วยงาน / สังกัด</div>
    <div class="detail-value">
        <?= htmlspecialchars($item['title']) ?>
    </div>
</div>

<div class="detail-grid">
    <div class="detail-label">เลขที่คำสั่ง / รายละเอียด</div>
    <div class="detail-value">
        <?= nl2br(htmlspecialchars($item['description'])) ?>
    </div>
</div>

<div class="detail-grid">
    <div class="detail-label">ระยะเวลาดำรงตำแหน่ง</div>
    <div class="detail-value">
        <strong><?= number_format($item['actual_hours'], 0) ?> สัปดาห์</strong>
        <span class="text-muted ml-2">(คะแนน: <?= number_format($item['weight'], 0) ?> ชม./สัปดาห์)</span>
    </div>
</div>

<div class="detail-grid" style="background:#f0fdf4; border-radius:8px; padding:10px; border:1px solid #bbf7d0;">
    <div class="detail-label text-success">ภาระงานสุทธิ</div>
    <div class="detail-value text-success font-bold text-xl">
        <?= number_format($item['computed_hours'], 2) ?> ชม.
    </div>
</div>