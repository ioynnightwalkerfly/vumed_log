<?php
$start = microtime(true);

echo "1. เริ่มต้น...<br>";

// ลองการโหลด Config
require_once '../config/app.php';
echo "2. โหลด Config เสร็จ: " . (microtime(true) - $start) . " วินาที<br>";

// ลองการต่อฐานข้อมูล
require_once '../config/db.php';
echo "3. ต่อฐานข้อมูลเสร็จ: " . (microtime(true) - $start) . " วินาที<br>";

// ลอง Query ง่ายๆ
$conn->query("SELECT 1");
echo "4. Query ทดสอบเสร็จ: " . (microtime(true) - $start) . " วินาที<br>";
?>