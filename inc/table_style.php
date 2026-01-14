<?php
/**
 * MedUI Table Style — ใช้รวม CSS และสไตล์มาตรฐานของทุกหน้าที่มีตาราง
 * แค่ include_once '../inc/table_style.php'; ไว้ใน <head> ของหน้า
 */
?>
<style>
/* === Layout ขนาดและโครงสร้าง === */
.table-card {
  max-width: 1100px;
  margin: auto;
  padding: 0;
  background: var(--surface);
  border-radius: 14px;
  box-shadow: 0 2px 6px rgba(0,0,0,.05);
}

.table-wrap {
  overflow-x: auto;
}

.table {
  width: 100%;
  border-collapse: collapse;
  min-width: 800px;
}

.table th, .table td {
  padding: 12px 14px;
  border-bottom: 1px solid #e5e7eb66;
  text-align: left;
  vertical-align: middle;
}

.table th {
  background: #f3f4f6;
  font-weight: 600;
  color: #374151;
  font-size: 15px;
  position: sticky;
  top: 0;
  z-index: 1;
}

.table-row-hover tbody tr:hover {
  background: #f9fafb;
  transition: background .2s ease;
}

.text-center {
  text-align: center;
}
.muted {
  color: #6b7280;
}

/* === Badge สีสถานะ === */
.badge {
  display: inline-block;
  padding: 4px 10px;
  font-size: 13px;
  font-weight: 500;
  border-radius: 999px;
  text-align: center;
  min-width: 80px;
}
.badge.approved {
  background: #d1fae5;
  color: #065f46;
}
.badge.rejected {
  background: #fee2e2;
  color: #991b1b;
}
.badge.pending {
  background: #fef3c7;
  color: #92400e;
}
.badge.draft {
  background: #e0f2fe;
  color: #075985;
}

/* === ปุ่มในตาราง === */
.table td .btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 10px;
  font-size: 14px;
  border-radius: 8px;
}
.table td .btn i {
  font-size: 14px;
}

/* === Modal มาตรฐาน === */
.modal {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.4);
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}
.modal.show {
  display: flex;
}
.modal-content {
  background: white;
  padding: 24px;
  border-radius: 14px;
  width: 100%;
  max-width: 400px;
  box-shadow: 0 6px 20px rgba(0,0,0,.2);
  text-align: center;
}
.modal-content h3 {
  margin-bottom: 12px;
  font-size: 20px;
}
.modal-content p {
  margin-bottom: 16px;
  color: #4b5563;
}

/* === Responsive Table === */
@media (max-width: 800px) {
  .table th, .table td {
    padding: 10px;
    font-size: 14px;
  }
}

.detail-value,
.table td {
  word-wrap: break-word;
  overflow-wrap: break-word;
  word-break: break-word;
}

</style>
