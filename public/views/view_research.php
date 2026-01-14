<div class="card">
    <div class="card-header">
        <h3 class="text-primary"><i class="bi bi-flask"></i> รายละเอียดงานวิจัย/วิชาการ</h3>
    </div>
    <div class="card-body">
        <table class="table table-bordered">
            <tr>
                <th style="width: 30%;">หมวดหมู่ผลงาน</th>
                <td><?php echo htmlspecialchars($workload['category_name']); ?></td>
            </tr>
            <tr>
                <th>ชื่อชุดโครงการ / หัวเรื่อง</th>
                <td><?php echo htmlspecialchars($workload['title']); ?></td>
            </tr>
            <tr>
                <th>รายการผลงาน (และสัดส่วน %)</th>
                <td>
                    <?php 
                        // แปลง Text ที่บันทึกไว้ให้เป็นรายการสวยๆ
                        $lines = explode("\n", $workload['description']);
                        if(count($lines) > 0) {
                            echo "<ul class='list-group'>";
                            foreach ($lines as $line) {
                                if(trim($line) == '') continue;
                                // ตรวจจับบรรทัดที่มี %
                                if (preg_match('/^(.*) \((\d+(\.\d+)?)%\)$/', trim($line, "- "), $matches)) {
                                    echo "<li class='list-group-item d-flex justify-content-between align-items-center'>
                                            <span>{$matches[1]}</span>
                                            <span class='badge bg-info rounded-pill'>{$matches[2]}%</span>
                                          </li>";
                                } else {
                                    echo "<li class='list-group-item'>" . htmlspecialchars(trim($line, "- ")) . "</li>";
                                }
                            }
                            echo "</ul>";
                        } else {
                            echo "-";
                        }
                    ?>
                </td>
            </tr>
            <tr>
                <th>จำนวนรายการ</th>
                <td><?php echo number_format($workload['actual_hours'], 0); ?> เรื่อง/ชิ้น</td>
            </tr>
            <tr>
                <th>คะแนนสุทธิ (ตามสัดส่วน)</th>
                <td class="text-success font-bold" style="font-size:1.2rem;">
                    <?php echo number_format($workload['computed_hours'], 2); ?> คะแนน
                </td>
            </tr>
            <tr>
                <th>ไฟล์แนบ/หลักฐาน</th>
                <td>
                    <?php if(!empty($workload['evidence'])): ?>
                        <div class="mb-2">
                            <a href="../uploads/<?php echo htmlspecialchars($workload['evidence']); ?>" target="_blank" class="text-primary">
                                <i class="bi bi-file-earmark"></i> ไฟล์สำรอง (Upload)
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(!empty($workload['attachment_link'])): ?>
                        <a href="<?php echo htmlspecialchars($workload['attachment_link']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-google"></i> Google Drive Link
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <div class="mt-4 text-center">
            <a href="workloads.php" class="btn btn-secondary">ย้อนกลับ</a>
        </div>
    </div>
</div>