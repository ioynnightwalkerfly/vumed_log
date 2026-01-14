<div class="card">
    <div class="card-header">
        <h3 class="text-primary"><i class="bi bi-people-fill"></i> รายละเอียดภาระงานบริหาร</h3>
    </div>
    <div class="card-body">
        <table class="table table-bordered">
            <tr>
                <th style="width: 30%;">ตำแหน่ง/หน้าที่</th>
                <td><?php echo htmlspecialchars($workload['category_name']); ?></td>
            </tr>
            <tr>
                <th>รายละเอียดเพิ่มเติม</th>
                <td>
                    <?php 
                        // ลบ tag [sub_role:xxx] ออกจากสายตาผู้ใช้
                        $clean_desc = preg_replace('/\[sub_role:[^\]]+\]/', '', $workload['description']);
                        // ลบช่องว่างส่วนเกิน
                        $clean_desc = trim(preg_replace('/\n+/', "\n", $clean_desc));
                        echo nl2br(htmlspecialchars($clean_desc)); 
                    ?>
                </td>
            </tr>
            <tr>
                <th>ระยะเวลาปฏิบัติงาน</th>
                <td>
                    <?php echo number_format($workload['actual_hours'], 2); ?> 
                    <?php echo ($workload['code'] == '5.4') ? 'ชั่วโมง' : 'สัปดาห์'; ?>
                </td>
            </tr>
            <tr>
                <th>คะแนนที่ได้</th>
                <td class="text-success font-bold" style="font-size:1.2rem;">
                    <?php echo number_format($workload['computed_hours'], 2); ?> คะแนน
                </td>
            </tr>
            <tr>
                <th>เอกสารคำสั่งแต่งตั้ง</th>
                <td>
                    <a href="<?php echo htmlspecialchars($workload['attachment_link']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-link-45deg"></i> เปิดดูคำสั่ง
                    </a>
                </td>
            </tr>
        </table>
        <div class="mt-4 text-center">
            <a href="workloads.php" class="btn btn-secondary">ย้อนกลับ</a>
        </div>
    </div>
</div>