<?php
require_once '../config/db.php';

// ป้องกันคนอื่นมากดเล่น (ใส่รหัสผ่านง่ายๆ ไว้ใน code)
$secret_key = $_GET['key'] ?? '';
if ($secret_key !== 'delete1234') { // <-- เปลี่ยนรหัสตรงนี้ได้
    die("Access Denied! <br>กรุณาใส่ ?key=delete1234 ต่อท้าย URL");
}


// clear_test_data.php?key=delete1234
// คำสั่งล้างข้อมูล
$conn->query("SET FOREIGN_KEY_CHECKS = 0");
$conn->query("TRUNCATE TABLE workload_logs");
$conn->query("TRUNCATE TABLE workload_items");
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

echo "<h1 style='color:green;'>✅ ล้างข้อมูลเรียบร้อยแล้ว!</h1>";
echo "<p>ตาราง workload_items และ workload_logs ว่างเปล่า</p>";
echo "<a href='workloads.php'>กลับไปหน้าภาระงาน</a>";
?>