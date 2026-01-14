<?php
// forms/staff/form_staff_strategy.php
// ===== ด้านที่ 3: งานยุทธศาสตร์ (สายสนับสนุน) - แบบใหม่ =====

$is_edit = $is_edit ?? false;
$errors = [];

// แกะข้อมูลหน่วยนับ
$default_unit = 'days';
if ($is_edit) {
    if (strpos($item['description'], '[หน่วย: ชั่วโมง]') !== false) {
        $default_unit = 'hours';
        $item['description'] = str_replace(' [หน่วย: ชั่วโมง]', '', $item['description']);
    } else {
        $item['description'] = str_replace(' [หน่วย: วัน]', '', $item['description']);
    }
}

$input = [
    'category_id' => $_POST['category_id'] ?? ($is_edit ? $item['category_id'] : null),
    'title'       => $_POST['title'] ?? ($is_edit ? $item['title'] : ''),
    'actual_hours'=> $_POST['actual_hours'] ?? ($is_edit ? $item['actual_hours'] : 0),
    'unit_type'   => $_POST['unit_type'] ?? $default_unit,
    'description' => $_POST['description'] ?? ($is_edit ? $item['description'] : ''),
    'attachment_link' => $_POST['attachment_link'] ?? ($is_edit ? ($item['attachment_link'] ?? '') : ''),
    'project_code' => $_POST['project_code'] ?? '',
];

// ดึงหมวดหมู่ (เฉพาะด้าน 3)
$stmt = $conn->prepare("SELECT id, code, name_th FROM workload_categories WHERE main_area = 3 AND is_active = 1 AND target_group = 'staff' ORDER BY code ASC");
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Post Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) die("Invalid CSRF token.");

    $category_id = $input['category_id'];
    $title = $input['title'];
    $project_code = $input['project_code'];
    $quantity = floatval($input['actual_hours']);
    $unit_type = $input['unit_type'];
    $description = $input['description'];
    $attachment_link = $input['attachment_link'];
    $status = 'pending';

    // รวมรหัสโครงการ
    if (!empty($project_code) && strpos($title, "(รหัส:") === false) {
        $title .= " (รหัส: " . $project_code . ")";
    }

    if (empty($category_id)) $errors[] = "กรุณาเลือกประเภท";
    if (empty($title)) $errors[] = "กรุณากรอกชื่อโครงการ";
    if ($quantity <= 0) $errors[] = "กรุณากรอกจำนวนเวลา";
    if (empty($attachment_link)) $errors[] = "กรุณาแนบลิงก์หลักฐาน";

    // คำนวณ
    $multiplier = ($unit_type === 'days') ? 7 : 1;
    $unit_tag = ($unit_type === 'days') ? " [หน่วย: วัน]" : " [หน่วย: ชั่วโมง]";
    $computed = $quantity * $multiplier;
    $final_description = $description . $unit_tag;

    if (empty($errors)) {
        if ($is_edit) {
            $stmt = $conn->prepare("UPDATE workload_items SET category_id=?, title=?, actual_hours=?, computed_hours=?, description=?, attachment_link=?, updated_at=NOW() WHERE id=? AND user_id=?");
            $stmt->bind_param("isddssii", $category_id, $title, $quantity, $computed, $final_description, $attachment_link, $item['id'], $user['id']);
        } else {
            $term_id = $term_id ?? 1;
            $stmt = $conn->prepare("INSERT INTO workload_items (user_id, academic_year, term_id, category_id, title, actual_hours, computed_hours, description, status, attachment_link) VALUES (?, YEAR(CURDATE()), ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiisddsss", $user['id'], $term_id, $category_id, $title, $quantity, $computed, $final_description, $status, $attachment_link);
        }
        if ($stmt->execute()) {
            echo "<script>window.location.href = 'staff_workloads.php?success=" . urlencode("บันทึกงานยุทธศาสตร์สำเร็จ") . "';</script>";
            exit;
        } else {
            $errors[] = "DB Error: " . $stmt->error;
        }
    }
}
?>

<div class="card p-6">
    
    <div class="stack-between mb-4 border-bottom pb-4">
        <div>
            <h2 class="mb-0 text-primary">
                <i class="bi bi-flag"></i> <?= $is_edit ? "แก้ไข" : "บันทึก" ?> (งานยุทธศาสตร์)
            </h2>
            <p class="muted mt-2" style="font-size:1.1rem;">โครงการตามแผนยุทธศาสตร์และกลยุทธ์มหาวิทยาลัย (KPI)</p>
        </div>
        <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('criteriaModal').classList.add('show')">
            <i class="bi bi-info-circle"></i> ดูเกณฑ์และตัวอย่าง
        </button>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert error mb-4"><ul><?php foreach($errors as $e) echo "<li>$e</li>"; ?></ul></div>
    <?php endif; ?>

    <form method="POST" class="grid grid-2" style="gap:30px;">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">

        <div class="full" style="grid-column: span 2;">
            <label>ประเภทการดำเนินงาน <span class="text-danger">*</span></label>
            <select name="category_id" required class="bg-muted">
                <option value="">-- เลือกบทบาท --</option>
                <?php foreach($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($c['id']==$input['category_id'])?'selected':'' ?>>
                        <?= htmlspecialchars($c['code']." : ".$c['name_th']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="grid-column: span 2;">
            <label>ชื่อโครงการ / กิจกรรม <span class="text-danger">*</span></label>
            <input type="text" name="title" value="<?= htmlspecialchars($input['title']) ?>" required 
                   placeholder="เช่น โครงการพัฒนาศักยภาพบุคลากร...">
        </div>

        <div>
            <label>รหัสโครงการ (KPI) <span class="text-danger">*</span></label>
            <input type="text" name="project_code" value="<?= htmlspecialchars($input['project_code']) ?>" placeholder="เช่น STR-66-01">
        </div>

        <div>
            <label>เลือกหน่วยนับเวลา <span class="text-danger">*</span></label>
            <select name="unit_type" id="unitType" class="input w-full bg-light">
                <option value="days" <?= $input['unit_type']=='days'?'selected':'' ?>>ระบุเป็น วันทำการ (x7)</option>
                <option value="hours" <?= $input['unit_type']=='hours'?'selected':'' ?>>ระบุเป็น ชั่วโมง (x1)</option>
            </select>
        </div>

        <div class="full p-4 rounded bg-surface border shadow-sm mt-2">
            <div class="grid grid-2" style="align-items:center;">
                <div>
                    <label id="quantityLabel">จำนวนเวลาที่ใช้ <span class="text-danger">*</span></label>
                    <input type="number" step="0.5" min="0.1" name="actual_hours" id="quantityInput" 
                           class="text-center font-bold text-primary" 
                           style="font-size:1.5rem !important;"
                           value="<?= htmlspecialchars($input['actual_hours']) ?>" required oninput="calculate()">
                </div>
                <div class="text-center">
                    <small class="muted" style="font-size:1.1rem;">ภาระงานสุทธิ (Auto)</small>
                    <div class="text-primary font-bold" style="font-size:3rem;">
                        <span id="computedDisplay"><?= number_format($is_edit ? $item['computed_hours'] : 0, 2) ?></span>
                    </div>
                    <small class="text-muted" id="formulaText">(จำนวนวัน x 7 ชั่วโมง)</small>
                </div>
            </div>
        </div>

        <div class="full">
            <label>รายละเอียดเพิ่มเติม / การรับรอง</label>
            <textarea name="description" rows="2"><?= htmlspecialchars($input['description']) ?></textarea>
        </div>

        <div class="full" style="grid-column: span 2;">
            <label class="text-primary" style="font-size:1.3rem !important;">
                <i class="bi bi-link-45deg"></i> ลิงก์หลักฐาน / Google Drive <span class="text-danger">*</span>
            </label>
            <input type="url" name="attachment_link" class="w-full" 
                   style="border: 2px solid var(--primary); background-color: #f0f9ff;"
                   placeholder="วางลิงก์เอกสารที่นี่" 
                   value="<?= htmlspecialchars($input['attachment_link']) ?>" required>
        </div>

        <div class="full stack-between mt-4 p-4 bg-muted rounded">
            <a href="staff_workloads.php" class="btn btn-muted text-dark"><i class="bi bi-arrow-left"></i> ย้อนกลับ</a>
            <button type="submit" class="btn btn-primary btn-lg px-6"><i class="bi bi-save"></i> บันทึกข้อมูล</button>
        </div>
    </form>
</div>

<div class="modal" id="criteriaModal">
    <div class="modal-content" style="max-width: 900px;">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding-bottom:15px; margin-bottom:20px;">
            <h3 class="m-0 text-primary">เกณฑ์และตัวอย่าง: งานยุทธศาสตร์</h3>
            <span class="close" onclick="document.getElementById('criteriaModal').classList.remove('show')" style="cursor:pointer; font-size:1.5rem;">&times;</span>
        </div>
        
        <div style="max-height:60vh; overflow-y:auto; padding-right:10px;">
            <div class="alert info mb-4">
                <strong><i class="bi bi-calculator"></i> สูตรคำนวณ:</strong> 1 วันทำการ = 7 ชั่วโมงภาระงาน
            </div>

            <div class="mb-4 p-3 bg-light rounded border">
                <strong class="text-primary">ตัวอย่างที่ 1: ผู้รับผิดชอบหลัก (นายทาน)</strong>
                <p class="text-muted mt-2">
                    รับผิดชอบโครงการ A (บรรลุทุกเป้าหมาย) ใช้เวลา 3 เดือน (ประมาณ 60 วันทำการ)
                </p>
                <div class="bg-white p-2 rounded border">
                    👉 เลือก: <strong>3.1 ผู้รับผิดชอบหลัก</strong> <br>
                    👉 จำนวน: <strong>60 วัน</strong> <br>
                    ✅ คำนวณ: 60 x 7 = <strong>420</strong> คะแนน
                </div>
            </div>

            <div class="mb-4 p-3 bg-light rounded border">
                <strong class="text-success">ตัวอย่างที่ 2: ผู้ร่วมโครงการ (นายทาน)</strong>
                <p class="text-muted mt-2">
                    ร่วมโครงการ B (ช่วยงานบางส่วน) ใช้เวลาปฏิบัติจริง 2 วัน
                </p>
                <div class="bg-white p-2 rounded border">
                    👉 เลือก: <strong>3.2 ผู้ร่วมโครงการ</strong> <br>
                    👉 จำนวน: <strong>2 วัน</strong> <br>
                    ✅ คำนวณ: 2 x 7 = <strong>14</strong> คะแนน
                </div>
            </div>
        </div>
        
        <div class="mt-4 text-right">
            <button class="btn btn-primary" onclick="document.getElementById('criteriaModal').classList.remove('show')">ปิด</button>
        </div>
    </div>
</div>

<script>
const unitType = document.getElementById('unitType');
const quantityInput = document.getElementById('quantityInput');
const computedDisplay = document.getElementById('computedDisplay');
const formulaText = document.getElementById('formulaText');
const quantityLabel = document.getElementById('quantityLabel');

function calculate() {
    let qty = parseFloat(quantityInput.value) || 0;
    let multiplier = 1;
    
    if (unitType.value === 'days') {
        multiplier = 7;
        quantityLabel.innerText = "จำนวนวันทำการ";
        formulaText.innerText = "(จำนวนวัน x 7 ชั่วโมง)";
    } else {
        multiplier = 1;
        quantityLabel.innerText = "จำนวนชั่วโมง";
        formulaText.innerText = "(จำนวนชั่วโมง x 1)";
    }
    
    let total = qty * multiplier;
    computedDisplay.innerText = total.toFixed(2);
}

unitType.addEventListener('change', calculate);
quantityInput.addEventListener('input', calculate);
document.addEventListener('DOMContentLoaded', calculate);
</script>