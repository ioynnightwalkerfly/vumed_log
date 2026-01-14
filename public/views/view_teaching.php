<div class="card">
    <div class="card-header">
        <h3 class="text-primary"><i class="bi bi-book"></i> รายละเอียดภาระงานสอน</h3>
    </div>
    <div class="card-body">
        <table class="table table-bordered">
            <tr>
                <th style="width: 30%;">ประเภทงาน</th>
                <td>
                    <span class="badge bg-primary text-white" style="font-size:1rem;">
                        <?php echo htmlspecialchars($workload['category_name'] ?? ''); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>รหัสวิชา - ชื่อวิชา / เรื่อง</th>
                <td>
                    <strong><?php echo htmlspecialchars($workload['course_code'] ?? ''); ?></strong> 
                    <?php echo htmlspecialchars($workload['title'] ?? ''); ?>
                </td>
            </tr>
            
            <tr>
                <th>รายละเอียดการปฏิบัติงาน</th>
                <td>
                    <?php 
                        $desc = $workload['description'] ?? '';
                        // 1.6 Modular: แกะ "หน่วยกิต: X, บทบาท: Y"
                        if (strpos($desc, 'หน่วยกิต:') !== false) {
                            $lines = explode("\n", $desc);
                            echo "<ul class='mb-0'>";
                            foreach($lines as $line) {
                                echo "<li>" . htmlspecialchars($line) . "</li>";
                            }
                            echo "</ul>";
                        }
                        // 1.7 CLC: แกะชื่อ CLC และตำแหน่ง
                        elseif (strpos($desc, 'CLC Name:') !== false) {
                            echo nl2br(htmlspecialchars($desc));
                        }
                        // 1.3 โครงงาน: แสดงเป็นรายการ
                        elseif (strpos($desc, 'รายชื่อโครงงาน') !== false) {
                            echo nl2br(htmlspecialchars($desc));
                        }
                        // ทั่วไป
                        else {
                            echo nl2br(htmlspecialchars($desc));
                        }
                    ?>
                </td>
            </tr>

            <tr>
                <th>ปริมาณงานจริง</th>
                <td>
                    <?php echo number_format($workload['actual_hours'] ?? 0, 2); ?> 
                    <?php 
                        // 🔥 จุดที่แก้: ใช้ตัวแปร $catCode ที่ดึงมาจาก 'code'
                        $catCode = $workload['code'] ?? ''; 
                        
                        if(strpos($catCode, '1.3') !== false) echo "โปรเจค";
                        elseif(strpos($catCode, '1.6') !== false) echo "หน่วยกิต";
                        else echo "ชั่วโมง/สัปดาห์";
                    ?>
                </td>
            </tr>
            <tr>
                <th>คะแนนที่คำนวณได้</th>
                <td class="text-success font-bold" style="font-size:1.2rem;">
                    <?php echo number_format($workload['computed_hours'] ?? 0, 2); ?> คะแนน
                </td>
            </tr>
            <tr>
                <th>หลักฐานอ้างอิง</th>
                <td>
                    <?php if(!empty($workload['attachment_link'])): ?>
                        <a href="<?php echo htmlspecialchars($workload['attachment_link']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-link-45deg"></i> เปิดดูเอกสาร
                        </a>
                    <?php else: ?>
                        <span class="text-muted">- ไม่มี -</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        
        <div class="mt-4 text-center">
            <?php 
                // เช็คว่าย้อนกลับไปหน้าไหน (staff หรือ user)
                $backUrl = 'workloads.php';
                if (isset($workload['owner_role']) && $workload['owner_role'] == 'staff') {
                    $backUrl = 'staff_workloads.php';
                }
            ?>
            <a href="<?php echo $backUrl; ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> ย้อนกลับ</a>
        </div>
    </div>
</div>