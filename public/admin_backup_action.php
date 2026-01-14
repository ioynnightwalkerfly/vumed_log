<?php
// public/admin_backup_action.php
// ระบบสำรองฐานข้อมูล (Export Database to SQL) - Fixed for PHP 8.1+

require_once '../config/app.php';
require_once '../config/db.php';
require_once '../middleware/require_login.php';
require_once '../middleware/require_admin.php';

// ตั้งค่าชื่อไฟล์
$date = date('Y-m-d_H-i-s');
$filename = "backup_vumedhr_{$date}.sql";

// เริ่มกระบวนการดึงข้อมูล
$return_var = "";

// 1. ดึงรายชื่อตารางทั้งหมด
$tables = array();
$result = $conn->query("SHOW TABLES");
while($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

// 2. วนลูปเพื่อสร้างคำสั่ง SQL สำหรับแต่ละตาราง
foreach($tables as $table) {
    // ดึงโครงสร้างตาราง
    $row2 = $conn->query("SHOW CREATE TABLE $table")->fetch_row();
    $return_var .= "\n\n" . $row2[1] . ";\n\n";

    // ดึงข้อมูลในตาราง
    $result = $conn->query("SELECT * FROM $table");
    $num_fields = $result->field_count;

    for ($i = 0; $i < $num_fields; $i++) {
        while($row = $result->fetch_row()) {
            $return_var .= "INSERT INTO $table VALUES(";
            for($j=0; $j<$num_fields; $j++) {
                // 🔥 แก้ไขจุดที่ Error: เช็ค isset ก่อนส่งเข้า real_escape_string
                if (isset($row[$j])) {
                    $row[$j] = $conn->real_escape_string($row[$j]);
                    $return_var .= '"' . $row[$j] . '"';
                } else {
                    $return_var .= '""';
                }
                
                if ($j < ($num_fields - 1)) {
                    $return_var .= ',';
                }
            }
            $return_var .= ");\n";
        }
    }
    $return_var .= "\n\n\n";
}

// 3. ส่งไฟล์ให้ Browser ดาวน์โหลด
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($return_var));

echo $return_var;
exit;
?>