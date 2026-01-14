<?php
// forms/staff/form_staff_development.php
// ===== ด้านที่ 2: งานพัฒนางาน (สายสนับสนุน) - แบบใหม่ =====

$is_edit = $is_edit ?? false;
$errors = [];

// เตรียมข้อมูลเก่า (แกะ Description)
$default_unit = 'hours';
$dev_format_val = '';
$dev_details_val = '';

if ($is_edit) {
    if (strpos($item['description'], '[หน่วย: วัน]') !== false) {
        $default_unit = 'days';
        $item['description'] = str_replace(' [หน่วย: วัน]', '', $item['description']);
    }
    $validFormats = ['Self-Learning', 'On the Job Training', 'KM', 'Other'];
    if (in_array($item['description'], $validFormats)) {
        $dev_format_val = $item['description'];
    } else {
        $dev_details_val = $item['description'];
    }
}

$input = [
    'category_id' => $_POST['category_id'] ?? ($is_edit ? $item['category_id'] : null),
    'title'       => $_POST['title'] ?? ($is_edit ? $item['title'] : ''),
    'actual_hours'=> $_POST['actual_hours'] ?? ($is_edit ? $item['actual_hours'] : 0),
    'unit_type'   => $_POST['unit_type'] ?? $default_unit,
    'dev_format'  => $_POST['dev_format'] ?? $dev_format_val,
    'dev_details' => $_POST['dev_details'] ?? $dev_details_val,
    'attachment_link' => $_POST['attachment_link'] ?? ($is_edit ? ($item['attachment_link'] ?? '') : ''),
    'organizer'   => $_POST['organizer'] ?? '', 
];

// ดึงหมวดหมู่ (เฉพาะด้าน 2)
$stmt = $conn->prepare("SELECT id, code, name_th, weight FROM workload_categories WHERE (main_area = 2 OR code LIKE '2.%') AND is_active = 1 AND target_group = 'staff' ORDER BY code ASC");
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Post Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) die("Invalid CSRF token.");

    $category_id = $input['category_id'];
    $title = $input['title'];
    $quantity = floatval($input['actual_hours']);
    $unit_type = $input['unit_type'];
    $attachment_link = trim($input['attachment_link']);
    $status = 'pending';

    if (empty($category_id)) $errors[] = "กรุณาเลือกประเภทงาน";
    if (empty($title)) $errors[] = "กรุณากรอกหัวข้อ/ชื่องาน";
    if ($quantity <= 0) $errors[] = "กรุณากรอกจำนวนเวลา";
    if (empty($attachment_link)) $errors[] = "กรุณาแนบลิงก์หลักฐาน";

    // หา Code เพื่อเลือกสูตร
    $catCode = ''; 
    foreach($categories as $c) {
        if($c['id'] == $category_id) { $catCode = $c['code']; break; }
    }

    $description = "";
    $multiplier = 1;

    if (strpos($catCode, '2.1') === 0) {
        // 2.1: บังคับชั่วโมง (x1)
        $description = $_POST['dev_format'];
        if (empty($description)) $errors[] = "กรุณาเลือกรูปแบบการพัฒนา";
        $multiplier = 1; 
    } else {
        // 2.2, 2.3: เลือกหน่วยได้
        $description = $_POST['dev_details'];
        if ($unit_type === 'days') {
            $multiplier = 7;
            $description .= " [หน่วย: วัน]";
        }
    }

    if (!empty($_POST['organizer'])) {
        $title .= " (จัดโดย: " . htmlspecialchars($_POST['organizer']) . ")";
    }

    $computed = $quantity * $multiplier; // Weight ปกติ = 1

    if (empty($errors)) {
        if ($is_edit) {
            $stmt = $conn->prepare("UPDATE workload_items SET category_id=?, title=?, actual_hours=?, computed_hours=?, description=?, attachment_link=?, updated_at=NOW() WHERE id=? AND user_id=?");
            $stmt->bind_param("isddssii", $category_id, $title, $quantity, $computed, $description, $attachment_link, $item['id'], $user['id']);
            $success_msg = "แก้ไขงานพัฒนาสำเร็จ";
        } else {
            $term_id = $term_id ?? 1;
            $stmt = $conn->prepare("INSERT INTO workload_items (user_id, academic_year, term_id, category_id, title, actual_hours, computed_hours, description, status, attachment_link) VALUES (?, YEAR(CURDATE()), ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiisddsss", $user['id'], $term_id, $category_id, $title, $quantity, $computed, $description, $status, $attachment_link);
            $success_msg = "บันทึกงานพัฒนาสำเร็จ";
        }

        if ($stmt->execute()) {
            echo "<script>window.location.href = 'staff_workloads.php?success=" . urlencode($success_msg) . "';</script>";
            exit;
        } else {
            $errors[] = "Database Error: " . htmlspecialchars($stmt->error);
        }
    }
}
?>

<div class="card p-6">
    
    <div class="stack-between mb-4 border-bottom pb-4">
        <div>
            <h2 class="mb-0 text-primary">
                <i class="bi bi-graph-up-arrow"></i> <?= $is_edit ? "แก้ไข" : "บันทึก" ?> (งานพัฒนางาน)
            </h2>
            <p class="muted mt-2" style="font-size:1.1rem;">การพัฒนาตนเอง, พัฒนางานประจำ, และพัฒนาองค์กร</p>
        </div>
        <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('criteriaModal').classList.add('show')">
            <i class="bi bi-info-circle"></i> ดูเกณฑ์
        </button>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert error mb-4">
            <strong>พบข้อผิดพลาด:</strong>
            <ul><?php foreach($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <form method="POST" class="grid grid-2" style="gap:30px;">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
        
        <div class="full" style="grid-column: span 2;">
            <label>ประเภทงานพัฒนา <span class="text-danger">*</span></label>
            <select name="category_id" id="categorySelect" required onchange="toggleFields()" class="bg-muted">
                <option value="">-- เลือกประเภท --</option>
                <?php foreach($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" data-code="<?= $c['code'] ?>" <?= ($c['id']==$input['category_id'])?'selected':'' ?>>
                        <?= htmlspecialchars($c['code']." : ".$c['name_th']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="full" style="grid-column: span 2;">
            <label>หัวข้อ / ชื่องาน / โครงการ <span class="text-danger">*</span></label>
            <input type="text" name="title" value="<?= htmlspecialchars($input['title']) ?>" required placeholder="ระบุชื่อหลักสูตร หรือ ชื่องาน">
        </div>
        
        <div class="full">
            <label>หน่วยงานที่จัด / สถานที่ (ถ้ามี)</label>
            <input type="text" name="organizer" value="<?= htmlspecialchars($input['organizer']) ?>">
        </div>

        <div id="block_2_1" class="full" style="display:none; background:#f0fdf4; padding:20px; border-radius:12px; border:1px solid #bbf7d0;">
            <label class="text-success fw-bold mb-2">รูปแบบการพัฒนา (สำหรับข้อ 2.1)</label>
            <select name="dev_format" id="devFormat" class="bg-white">
                <option value="">-- เลือกรูปแบบ --</option>
                <option value="Self-Learning" <?= $input['dev_format']=='Self-Learning'?'selected':'' ?>>1. เรียนรู้ด้วยตนเอง (Self-Learning)</option>
                <option value="On the Job Training" <?= $input['dev_format']=='On the Job Training'?'selected':'' ?>>2. เรียนรู้จากการปฏิบัติงานจริง (OJT)</option>
                <option value="KM" <?= $input['dev_format']=='KM'?'selected':'' ?>>3. เข้าร่วมกิจกรรม KM</option>
                <option value="Other" <?= $input['dev_format']=='Other'?'selected':'' ?>>อื่นๆ</option>
            </select>
        </div>

        <div id="block_other" class="full" style="display:none; background:#eff6ff; padding:20px; border-radius:12px; border:1px solid #bfdbfe;">
            <div class="grid grid-2" style="gap:20px;">
                <div>
                    <label class="text-primary fw-bold mb-2">เลือกหน่วยนับเวลา</label>
                    <select name="unit_type" id="unitType" onchange="calculate()" class="bg-white">
                        <option value="hours" <?= $input['unit_type']=='hours'?'selected':'' ?>>ระบุเป็น ชั่วโมง (x1)</option>
                        <option value="days" <?= $input['unit_type']=='days'?'selected':'' ?>>ระบุเป็น วันทำการ (x7)</option>
                    </select>
                </div>
                <div class="full">
                    <label class="text-primary fw-bold mb-2">รายละเอียดเพิ่มเติม</label>
                    <textarea name="dev_details" rows="2" class="bg-white"><?= htmlspecialchars($input['dev_details']) ?></textarea>
                </div>
            </div>
        </div>

        <div class="full p-4 rounded bg-surface border shadow-sm mt-2">
            <div class="grid grid-2" style="align-items:center;">
                <div>
                    <label id="quantityLabel">จำนวนเวลาที่ใช้ <span class="text-danger">*</span></label>
                    <input type="number" step="0.5" min="0.5" name="actual_hours" id="quantityInput" 
                           class="text-center font-bold text-primary" 
                           style="font-size:1.5rem !important;"
                           value="<?= htmlspecialchars($input['actual_hours']) ?>" required oninput="calculate()">
                </div>
                <div class="text-center">
                    <small class="muted" style="font-size:1.1rem;">คะแนนที่ได้</small>
                    <div class="text-primary font-bold" style="font-size:3rem;">
                        <span id="computedDisplay"><?= number_format($is_edit ? $item['computed_hours'] : 0, 2) ?></span>
                    </div>
                </div>
            </div>
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
            <h3 class="m-0 text-primary">เกณฑ์: งานพัฒนางาน</h3>
            <span class="close" onclick="document.getElementById('criteriaModal').classList.remove('show')" style="cursor:pointer; font-size:1.5rem;">&times;</span>
        </div>
        
        <div style="max-height:60vh; overflow-y:auto; padding-right:10px;">
            <div class="mb-4">
                <h4 class="bg-light p-2 rounded">2.1 การพัฒนาตนเอง (อบรม/สัมมนา)</h4>
                <ul class="text-muted ml-4">
                    <li>คิดตามชั่วโมงจริง (1 ชม. = 1 คะแนน)</li>
                </ul>
            </div>
            <div class="mb-4">
                <h4 class="bg-light p-2 rounded">2.2 พัฒนางานประจำ / 2.3 พัฒนาองค์กร</h4>
                <ul class="text-muted ml-4">
                    <li>คิดตามเวลาปฏิบัติจริง (นิยมคิดเป็นวัน)</li>
                    <li><strong>สูตร:</strong> 1 วันทำการ = 7 คะแนน</li>
                </ul>
            </div>
        </div>
        
        <div class="mt-4 text-right">
            <button class="btn btn-primary" onclick="document.getElementById('criteriaModal').classList.remove('show')">ปิด</button>
        </div>
    </div>
</div>

<script>
const categorySelect = document.getElementById('categorySelect');
const block21 = document.getElementById('block_2_1');
const blockOther = document.getElementById('block_other');
const unitType = document.getElementById('unitType');
const quantityInput = document.getElementById('quantityInput');
const quantityLabel = document.getElementById('quantityLabel');
const computedDisplay = document.getElementById('computedDisplay');

function toggleFields() {
    const option = categorySelect.options[categorySelect.selectedIndex];
    if (!option.value) return;
    const code = option.getAttribute('data-code');
    if (code && code.startsWith('2.1')) {
        block21.style.display = 'block';
        blockOther.style.display = 'none';
        quantityLabel.innerText = "จำนวนชั่วโมง";
        calculate(true); 
    } else {
        block21.style.display = 'none';
        blockOther.style.display = 'block';
        updateUnitLabel();
        calculate(false);
    }
}
function updateUnitLabel() {
    if (unitType.value === 'days') quantityLabel.innerText = "จำนวนวันทำการ";
    else quantityLabel.innerText = "จำนวนชั่วโมง";
}
function calculate(forceHours = false) {
    let qty = parseFloat(quantityInput.value) || 0;
    let multiplier = 1;
    if (!forceHours && unitType.value === 'days' && blockOther.style.display !== 'none') {
        multiplier = 7;
    }
    let total = qty * multiplier;
    computedDisplay.innerText = total.toFixed(2);
}
categorySelect.addEventListener('change', toggleFields);
unitType.addEventListener('change', () => { updateUnitLabel(); calculate(); });
quantityInput.addEventListener('input', () => calculate());
document.addEventListener('DOMContentLoaded', () => { toggleFields(); });
</script>