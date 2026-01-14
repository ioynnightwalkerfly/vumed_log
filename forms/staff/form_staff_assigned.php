<?php
// forms/staff/form_staff_assigned.php
// ===== ด้านที่ 4: งานอื่นๆ ที่ได้รับมอบหมาย (สายสนับสนุน) - แบบใหม่ =====

$is_edit = $is_edit ?? false;
$errors = [];

// แกะข้อมูลหน่วยนับ
$default_unit = 'hours';
if ($is_edit) {
    if (strpos($item['description'], '[หน่วย: วัน]') !== false) {
        $default_unit = 'days';
        $item['description'] = str_replace(' [หน่วย: วัน]', '', $item['description']);
    } else {
        $item['description'] = str_replace(' [หน่วย: ชั่วโมง]', '', $item['description']);
    }
}

$input = [
    'category_id' => $_POST['category_id'] ?? ($is_edit ? $item['category_id'] : null),
    'title'       => $_POST['title'] ?? ($is_edit ? $item['title'] : ''),
    'actual_hours'=> $_POST['actual_hours'] ?? ($is_edit ? $item['actual_hours'] : 0),
    'unit_type'   => $_POST['unit_type'] ?? $default_unit,
    'description' => $_POST['description'] ?? ($is_edit ? $item['description'] : ''),
    'attachment_link' => $_POST['attachment_link'] ?? ($is_edit ? ($item['attachment_link'] ?? '') : ''),
];

// ดึงหมวดหมู่ (Area 4)
$stmt = $conn->prepare("SELECT id, code, name_th FROM workload_categories WHERE main_area = 4 AND is_active = 1 AND target_group = 'staff' ORDER BY code ASC");
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Post Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) die("Invalid CSRF token.");

    $category_id = $input['category_id'];
    $title = $input['title'];
    $quantity = floatval($input['actual_hours']);
    $unit_type = $input['unit_type'];
    $description = $input['description'];
    $attachment_link = $input['attachment_link'];
    $status = 'pending';

    if (empty($category_id)) $errors[] = "กรุณาเลือกประเภทงาน";
    if (empty($title)) $errors[] = "กรุณากรอกชื่องาน";
    if ($quantity <= 0) $errors[] = "กรุณากรอกจำนวนเวลา";
    if (empty($attachment_link)) $errors[] = "กรุณาแนบลิงก์หลักฐาน";

    // คำนวณ (เน้นชั่วโมงเป็นหลัก)
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
            echo "<script>window.location.href = 'staff_workloads.php?success=" . urlencode("บันทึกงานที่ได้รับมอบหมายสำเร็จ") . "';</script>";
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
                <i class="bi bi-person-check-fill"></i> <?= $is_edit ? "แก้ไข" : "บันทึก" ?> (งานที่ได้รับมอบหมาย)
            </h2>
            <p class="muted mt-2" style="font-size:1.1rem;">งานเฉพาะกิจ, แทนหัวหน้างาน, กรรมการข้ามสายงาน</p>
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
            <label>ประเภทการปฏิบัติงาน <span class="text-danger">*</span></label>
            <select name="category_id" required class="bg-muted">
                <option value="">-- เลือกประเภท --</option>
                <?php foreach($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($c['id']==$input['category_id'])?'selected':'' ?>>
                        <?= htmlspecialchars($c['code']." : ".$c['name_th']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="grid-column: span 2;">
            <label>ชื่องาน / การประชุม / กิจกรรม <span class="text-danger">*</span></label>
            <input type="text" name="title" value="<?= htmlspecialchars($input['title']) ?>" required 
                   placeholder="เช่น ประชุมวางแผนกลยุทธ์, ช่วยงานวันแม่">
        </div>

        <div>
            <label>เลือกหน่วยนับเวลา</label>
            <select name="unit_type" id="unitType" class="input w-full bg-light">
                <option value="hours" <?= $input['unit_type']=='hours'?'selected':'' ?>>ระบุเป็น ชั่วโมง (x1)</option>
                <option value="days" <?= $input['unit_type']=='days'?'selected':'' ?>>ระบุเป็น วันทำการ (x7)</option>
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
                    <small class="text-muted" id="formulaText">(จำนวนชั่วโมง x 1)</small>
                </div>
            </div>
        </div>

        <div class="full">
            <label>รายละเอียดเพิ่มเติม / คำสั่งแต่งตั้ง</label>
            <textarea name="description" rows="2" placeholder="ระบุเลขที่คำสั่ง หรือรายละเอียดเพิ่มเติม"><?= htmlspecialchars($input['description']) ?></textarea>
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
            <h3 class="m-0 text-primary">เกณฑ์และตัวอย่าง: งานที่ได้รับมอบหมาย</h3>
            <span class="close" onclick="document.getElementById('criteriaModal').classList.remove('show')" style="cursor:pointer; font-size:1.5rem;">&times;</span>
        </div>
        
        <div style="max-height:60vh; overflow-y:auto; padding-right:10px;">
            <div class="alert info mb-4">
                <strong><i class="bi bi-calculator"></i> สูตรคำนวณ:</strong> 1 ชั่วโมงปฏิบัติงาน = 1 คะแนนภาระงาน
            </div>

            <div class="mb-4 p-3 bg-light rounded border">
                <strong class="text-primary">ตัวอย่างที่ 1: ประชุมแทนหัวหน้า</strong>
                <p class="text-muted mt-2">
                    นายทาน ได้รับมอบหมายให้ไปประชุมวางแผนกลยุทธ์ วันที่ 20 ม.ค. ใช้เวลา 6 ชั่วโมง
                </p>
                <div class="bg-white p-2 rounded border">
                    👉 จำนวน: <strong>6 ชั่วโมง</strong> <br>
                    ✅ คำนวณ: 6 x 1 = <strong>6</strong> คะแนน
                </div>
            </div>

            <div class="mb-4 p-3 bg-light rounded border">
                <strong class="text-success">ตัวอย่างที่ 2: กรรมการข้ามสายงาน</strong>
                <p class="text-muted mt-2">
                    ช่วยงานกิจกรรมวันแม่ (มีคำสั่งแต่งตั้ง) ปฏิบัติงาน 10 ชั่วโมง
                </p>
                <div class="bg-white p-2 rounded border">
                    👉 จำนวน: <strong>10 ชั่วโมง</strong> <br>
                    ✅ คำนวณ: 10 x 1 = <strong>10</strong> คะแนน
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