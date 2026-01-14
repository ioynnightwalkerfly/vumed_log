<?php
// แก้ปัญหา Undefined variable
if (!isset($workload) && isset($item)) {
    $workload = $item;
}
$code = $workload['category_code'] ?? ($workload['code'] ?? '');
// กลุ่มที่มีบทบาท (ตัด 6.3 ออก)
$roles_codes = ['6.1', '6.2'];
?>
<div class="card">
    <div class="card-header">
        <h3 class="text-primary"><i class="bi bi-three-dots"></i> รายละเอียดภาระงานอื่นๆ</h3>
    </div>
    <div class="card-body">
        <table class="table table-bordered">
            <tr>
                <th style="width: 30%;">หมวดงาน</th>
                <td><?php echo htmlspecialchars($workload['category_name'] ?? ''); ?></td>
            </tr>
            <tr>
                <th>ชื่องาน / รายการ</th>
                <td>
                    <?php if (strpos($workload['title'] ?? '', 'ภาระงานด้านอื่น') !== false && !in_array($code, $roles_codes)): ?>
                        <em>(ระบุในรายละเอียด)</em>
                    <?php else: ?>
                        <strong><?php echo htmlspecialchars($workload['title'] ?? ''); ?></strong>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>รายละเอียด</th>
                <td>
                    <?php 
                        $desc = $workload['description'] ?? '';
                        // แสดงบทบาทเฉพาะ 6.1, 6.2
                        if (in_array($code, $roles_codes)) {
                            $lines = explode("\n", $desc);
                            foreach($lines as $line) {
                                if (strpos($line, 'บทบาท:') !== false) {
                                    echo "<div class='mb-1'><span class='badge bg-info text-dark'>" . htmlspecialchars($line) . "</span></div>";
                                } else {
                                    echo "<div>" . htmlspecialchars($line) . "</div>";
                                }
                            }
                        }
                        // แบบ List
                        else if (strpos($desc, '- ') !== false) {
                            echo "<ul class='mb-0'>";
                            $lines = explode("\n", $desc);
                            foreach($lines as $line) {
                                if (strpos(trim($line), '- ') === 0) {
                                    echo "<li>" . htmlspecialchars(substr(trim($line), 2)) . "</li>";
                                }
                            }
                            echo "</ul>";
                        } 
                        // ทั่วไป (รวม 6.3)
                        else {
                            echo nl2br(htmlspecialchars($desc));
                        }
                    ?>
                </td>
            </tr>
            <tr>
                <th>ปริมาณงาน</th>
                <td>
                    <?php echo number_format($workload['actual_hours'] ?? 0, 2); ?> 
                    <?php 
                        // หน่วยนับ
                        if (in_array($code, $roles_codes) || in_array($code, ['6.3','6.4','6.5','6.6'])) echo "ชั่วโมง";
                        else echo "รายการ";
                    ?>
                </td>
            </tr>
            <tr>
                <th>คะแนนรวม</th>
                <td class="text-success font-bold" style="font-size:1.2rem;">
                    <?php echo number_format($workload['computed_hours'] ?? 0, 2); ?> คะแนน
                </td>
            </tr>
            <tr>
                <th>หลักฐาน</th>
                <td>
                    <?php if (!empty($workload['attachment_link'])): ?>
                    <a href="<?php echo htmlspecialchars($workload['attachment_link']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-link-45deg"></i> Link
                    </a>
                    <?php else: ?>
                    -
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        
        <div class="mt-4 text-center">
            <?php 
                $backUrl = 'workloads.php';
                if (isset($workload['owner_role']) && $workload['owner_role'] == 'staff') {
                    $backUrl = 'staff_workloads.php';
                }
            ?>
            <a href="<?php echo $backUrl; ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> ย้อนกลับ</a>
        </div>
    </div>
</div>