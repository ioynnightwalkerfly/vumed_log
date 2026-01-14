<?php
// public/admin_report_select.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';

// ตรวจสิทธิ์
if (!in_array($user['role'], ['admin', 'manager'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เลือกหัวข้อรายงาน | MedUI System</title>
    <link rel="stylesheet" href="../medui/medui.css">
    <link rel="stylesheet" href="../medui/medui.components.css">
    <link rel="stylesheet" href="../medui/medui.layout.css">
    <link rel="stylesheet" href="../medui/medui.theme.medical.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>

<div class="app">
    <?php include '../inc/nav.php'; ?>

    <header class="topbar">
        <div class="left">
            <h3 style="margin:0;">ออกรายงานสรุปภาระงาน</h3>
            <p class="muted" style="margin:0;">เลือกหัวข้อข้อมูลที่ต้องการแสดงในรายงาน</p>
        </div>
    </header>

    <main class="main">
        <div class="card p-6" style="max-width: 600px; margin: auto;">
            <form action="admin_stats_print.php" method="GET" target="_blank">
                
                <h4 class="mb-4">ตัวเลือกการพิมพ์</h4>

                <div class="grid" style="gap: 16px;">
                    
                    <label class="card p-4 border" style="flex-direction: row; align-items: center; cursor: pointer;">
                        <input type="checkbox" name="show_area" value="1" checked style="width: 20px; height: 20px; margin-right: 12px;">
                        <div>
                            <strong>1. สรุปชั่วโมงรายด้าน</strong>
                            <p class="muted m-0 text-sm">กราฟและตารางสรุปแยกตาม 6 ด้านหลัก</p>
                        </div>
                    </label>

                    <label class="card p-4 border" style="flex-direction: row; align-items: center; cursor: pointer;">
                        <input type="checkbox" name="show_status" value="1" checked style="width: 20px; height: 20px; margin-right: 12px;">
                        <div>
                            <strong>2. สรุปสถานะการตรวจสอบ</strong>
                            <p class="muted m-0 text-sm">จำนวนรายการที่รอตรวจ, อนุมัติแล้ว, ปฏิเสธ</p>
                        </div>
                    </label>

                    <label class="card p-4 border" style="flex-direction: row; align-items: center; cursor: pointer;">
                        <input type="checkbox" name="show_users" value="1" checked style="width: 20px; height: 20px; margin-right: 12px;">
                        <div>
                            <strong>3. สรุปชั่วโมงรายบุคคล</strong>
                            <p class="muted m-0 text-sm">รายชื่ออาจารย์ทุกคนพร้อมชั่วโมงรวมสะสม</p>
                        </div>
                    </label>

                    <label class="card p-4 border" style="flex-direction: row; align-items: center; cursor: pointer;">
                        <input type="checkbox" name="show_list" value="1" style="width: 20px; height: 20px; margin-right: 12px;">
                        <div>
                            <strong>4. รายละเอียดภาระงานทั้งหมด</strong>
                            <p class="muted m-0 text-sm text-danger">⚠ ข้อมูลอาจมีจำนวนมาก (หลายหน้ากระดาษ)</p>
                        </div>
                    </label>

                </div>

                <div class="mt-6 pt-4 border-top stack-between">
                    <a href="admin_dashboard.php" class="btn btn-muted">ย้อนกลับ</a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-printer"></i> สร้างรายงาน PDF
                    </button>
                </div>

            </form>
        </div>
    </main>
</div>

</body>
</html>