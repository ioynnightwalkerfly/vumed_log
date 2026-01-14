<?php
// public/views/view_service.php
// สำหรับแสดงผลด้านที่ 3: บริการวิชาการ (Clean View)

// 1. เตรียมข้อมูล
$raw_desc = $item['description'];
$code = trim($item['code'] ?? '');
$lines = explode("\n", $raw_desc);

// ตัวแปรสำหรับแยกข้อมูล
$list_items = []; 
$location_info = ""; 
$general_desc = ""; 
$list_header = "รายละเอียด";

// 2. แกะข้อมูล (Parsing Logic)
foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line)) continue;

    // กรณีเป็นรายวิชา
    if (preg_match('/^\d+\.\s*(?:\[(.*?)\]\s*)?(.*?)\s*\((.*?)\)/', $line, $m)) {
        $list_items[] = [
            'type' => 'course',
            'code' => $m[1],
            'name' => $m[2],
            'detail' => $m[3]
        ];
    }
    // กรณีเป็นรายการทั่วไป (- Item)
    elseif (strpos($line, "- ") === 0) {
        $list_items[] = [
            'type' => 'text',
            'text' => substr($line, 2)
        ];
    }
    // กรณีเป็นสถานที่
    elseif (strpos($line, "สถานที่") === 0) {
        $location_info = explode(":", $line)[1] ?? $line;
    }
    // กรณีเป็นหัวข้อ
    elseif (strpos($line, "รายชื่อ") === 0 || strpos($line, "รายวิชา") === 0) {
        $list_header = str_replace(":", "", $line);
    }
    else {
        $general_desc .= $line . "\n";
    }
}

// 3. กำหนดหน่วยนับ
$unit_label = "รายการ";
if (in_array($code, ['3.1', '3.2'])) $unit_label = "วิชา";
elseif (in_array($code, ['3.9', '3.10', '3.11', '3.12', '3.17', '3.21'])) $unit_label = "ชั่วโมง";
elseif ($code == '3.22') $unit_label = "สัปดาห์";
elseif (in_array($code, ['3.26', '3.27', '3.28'])) $unit_label = "คน";
?>

<div class="card p-5 border shadow-sm" style="border-radius: 12px;">

    <div class="mb-4">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span class="badge" style="background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd;">
                <?= htmlspecialchars($item['code']) ?>
            </span>
            <span class="text-muted text-sm">
                <?= htmlspecialchars($item['category_name']) ?>
            </span>
        </div>
        <h2 class="text-dark m-0" style="font-weight: 700; line-height: 1.3;">
            <?= htmlspecialchars($item['title']) ?>
        </h2>
        <?php if(!empty($location_info)): ?>
            <p class="text-muted mt-2">
                <i class="bi bi-geo-alt-fill text-danger"></i> 
                <strong>สถานที่:</strong> <?= htmlspecialchars(trim($location_info)) ?>
            </p>
        <?php endif; ?>
    </div>

    <div class="d-flex flex-wrap gap-4 p-4 rounded mb-5" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
        
        <div style="flex: 1; min-width: 120px; border-right: 1px solid #e2e8f0;">
            <small class="text-muted d-block mb-1">คะแนนภาระงาน</small>
            <div class="text-primary font-bold" style="font-size: 1.8rem; line-height: 1;">
                <?= number_format($item['computed_hours'], 2) ?>
            </div>
        </div>

        <div style="flex: 1; min-width: 120px; border-right: 1px solid #e2e8f0;">
            <small class="text-muted d-block mb-1">
                <?= ($code == '3.22') ? 'จำนวนสัปดาห์' : 'ปริมาณงาน' ?>
            </small>
            <div class="text-dark font-bold" style="font-size: 1.8rem; line-height: 1;">
                <?php 
                    if ($code == '3.22' && !empty($item['week_count'])) {
                        echo number_format($item['week_count']);
                    } else {
                        echo number_format($item['actual_hours']);
                    }
                ?>
                <span class="text-muted" style="font-size: 1rem; font-weight: normal;"><?= $unit_label ?></span>
            </div>
        </div>

        <div style="flex: 1; min-width: 120px;">
            <small class="text-muted d-block mb-1">สถานะ</small>
            <div>
                <?php if ($item['status'] == 'approved_final'): ?>
                    <span class="text-success font-bold" style="font-size: 1.2rem;"><i class="bi bi-check-circle-fill"></i> อนุมัติแล้ว</span>
                <?php elseif ($item['status'] == 'rejected'): ?>
                    <span class="text-danger font-bold" style="font-size: 1.2rem;"><i class="bi bi-x-circle-fill"></i> แก้ไข</span>
                <?php else: ?>
                    <span class="text-warning font-bold" style="font-size: 1.2rem;"><i class="bi bi-hourglass-split"></i> รอตรวจสอบ</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="mb-2">
        <h5 class="text-muted mb-3 border-bottom pb-2" style="font-size:1rem;">
            <?= htmlspecialchars($list_header) ?>
        </h5>

        <?php if (!empty($list_items)): ?>
            
            <?php if ($list_items[0]['type'] == 'course'): ?>
                <div class="table-wrap border rounded overflow-hidden">
                    <table class="table table-sm m-0">
                        <thead class="bg-light text-muted">
                            <tr>
                                <th width="15%">รหัสวิชา</th>
                                <th width="50%">ชื่อวิชา</th>
                                <th width="35%">รายละเอียดหน่วยกิต</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($list_items as $l): ?>
                                <tr>
                                    <td><span class="badge bg-white border text-dark"><?= htmlspecialchars($l['code']) ?></span></td>
                                    <td><strong><?= htmlspecialchars($l['name']) ?></strong></td>
                                    <td class="text-muted"><?= htmlspecialchars($l['detail']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>
                <ul class="pl-0 m-0" style="list-style: none;">
                    <?php foreach ($list_items as $index => $l): ?>
                        <li class="mb-3 d-flex align-items-start">
                            <span class="mr-3 text-muted font-bold" style="min-width: 25px;">
                                <?= $index + 1 ?>.
                            </span>
                            <span style="font-size: 1.1rem; color: #333; line-height: 1.6;">
                                <?= htmlspecialchars($l['text']) ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

        <?php else: ?>
            <?php if(!empty($general_desc)): ?>
                <div class="text-dark" style="font-size:1.1rem; line-height:1.6;">
                    <?= nl2br(htmlspecialchars(trim($general_desc))) ?>
                </div>
            <?php else: ?>
                <p class="text-muted">- ไม่ระบุรายละเอียดเพิ่มเติม -</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

</div>