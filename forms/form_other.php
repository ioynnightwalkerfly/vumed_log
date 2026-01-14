<?php
// forms/form_other.php
// ===== ฟอร์มด้านที่ 6: ภาระงานอื่นๆ 

// 1. ค่าเริ่มต้น
$academic_year = $academic_year ?? (date("Y") + 543);
$term_id = $term_no ?? ($term_id ?? 1); 
$is_edit = $is_edit ?? false;

// 2. เตรียมตัวแปร
$input = [
    'category_id'       => $_POST['category_id'] ?? ($is_edit ? $item['category_id'] : null),
    'attachment_link'   => $_POST['attachment_link'] ?? ($is_edit ? ($item['attachment_link'] ?? '') : ''),
    'quantity'          => $_POST['quantity'] ?? ($is_edit ? $item['actual_hours'] : 0),
    'description'       => $_POST['description'] ?? ($is_edit ? $item['description'] : ''),
];

// 3. เตรียมข้อมูลเก่า
$existing_items = []; 
$existing_title = "";
$existing_hours = 0;
$existing_role = ""; 

if ($is_edit) {
    $existing_title = $item['title'];
    $existing_hours = $item['actual_hours'];
    
    if (preg_match('/\[role:(.*?)\]/', $item['description'], $m)) {
        $existing_role = $m[1];
    }

    if (strpos($item['description'], "- ") !== false) {
        $lines = explode("\n", $item['description']);
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, "- ") === 0) $existing_items[] = trim(substr($line, 2));
        }
    }
}
if (empty($existing_items)) $existing_items[] = "";

// 4. โหลดหมวดหมู่
$target_group = ($user['role'] == 'staff') ? 'staff' : 'teacher'; 
if ($user['role'] == 'admin') $target_group = 'teacher'; 

$stmt_cat = $conn->prepare("SELECT id, code, name_th, weight FROM workload_categories WHERE main_area = 6 AND is_active = 1 AND target_group = ? ORDER BY code ASC");
$stmt_cat->bind_param("s", $target_group);
$stmt_cat->execute();
$all_categories = $stmt_cat->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_cat->close();

// หา Code ที่เลือกไว้ (แก้ Error Undefined variable)
$selected_code = '';
$current_weight = 0;
if (!empty($input['category_id'])) {
    foreach($all_categories as $c) {
        if ($c['id'] == $input['category_id']) {
            $selected_code = trim($c['code']);
            $current_weight = floatval($c['weight']);
            break;
        }
    }
}

// 5. กำหนดกลุ่ม (Logic การแสดงผล)

$codes_role   = ['6.1', '6.2']; 


$codes_hourly = ['6.3', '6.4', '6.5', '6.6']; 


$codes_list   = []; 


// ===== POST Process =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token.");
    }

    $category_id     = $_POST['category_id'];
    $attachment_link = trim($_POST['attachment_link']);
    $weight = $current_weight; 

    $quantity = 0;
    $computed = 0;
    $description_text = "";
    $title_text = "";
    $errors = [];

    // Validation
    if (empty($category_id)) $errors[] = "กรุณาเลือกประเภทภาระงาน";
    if (empty($attachment_link)) $errors[] = "กรุณาระบุลิงก์เอกสารหลักฐาน";

    // Logic Role Based: 6.1, 6.2
    if (in_array($selected_code, $codes_role)) {
        $role_val = $_POST['role_selected'] ?? '';
        $title_input = trim($_POST['title_input_role'] ?? '');
        $hours_input = floatval($_POST['hours_input_role'] ?? 0);

        if (empty($role_val)) $errors[] = "กรุณาเลือกบทบาท";
        if (empty($title_input)) $errors[] = "กรุณาระบุชื่อโครงการ";
        if ($hours_input <= 0) $errors[] = "กรุณาระบุชั่วโมงปฏิบัติจริง";

        $quantity = $hours_input;
        $factor = ($role_val == 'chair') ? 2 : 1;
        $role_label = ($role_val == 'chair') ? "ประธาน/เลขานุการ" : "รองประธาน/กรรมการ";

        $computed = $hours_input * $factor;
        $title_text = $title_input;
        $description_text = "บทบาท: $role_label [role:$role_val]\nชั่วโมงจริง: $hours_input ชม. (x$factor)";
    }
    // Logic Hourly: 6.3, 6.4, 6.5, 6.6
    else if (in_array($selected_code, $codes_hourly)) {
        $title_input = trim($_POST['title_input'] ?? '');
        $hours_input = floatval($_POST['hours_input'] ?? 0);
        
        if (empty($title_input)) $errors[] = "กรุณาระบุชื่องาน";
        if ($hours_input <= 0) $errors[] = "กรุณาระบุจำนวนชั่วโมง";

        $quantity = $hours_input;
        // คิดตามชั่วโมงจริง (คูณ 1) หรือคูณตาม Weight ถ้ามี
        $computed = $hours_input * ($weight > 0 ? $weight : 1); 
        $description_text = $_POST['description'] ?? ''; 
        $title_text = $title_input;
    }
    // Logic List
    else if (in_array($selected_code, $codes_list)) {
        $items = $_POST['items'] ?? [];
        $items = array_filter($items, function ($val) { return !empty(trim($val)); });
        $quantity = count($items);
        $computed = $quantity * $weight;
        
        if ($quantity <= 0) $errors[] = "กรุณาระบุรายชื่ออย่างน้อย 1 รายการ";
        
        $unit_text = "รายการ"; 
        $description_text = "รายชื่อ ($quantity $unit_text):\n- " . implode("\n- ", $items);
        $title_text = "ภาระงานด้านอื่น ๆ ($quantity $unit_text)";
    }
    // Fallback
    else {
        $title_input = trim($_POST['title_input'] ?? '');
        $hours_input = floatval($_POST['hours_input'] ?? 0);
        $quantity = $hours_input;
        $computed = $hours_input * 1;
        $description_text = $_POST['description'] ?? '';
        $title_text = $title_input;
    }

    // Save
    if (empty($errors)) {
        $status = 'pending';
        if ($is_edit) {
            $sql = "UPDATE workload_items SET category_id=?, title=?, actual_hours=?, computed_hours=?, description=?, attachment_link=?, updated_at=NOW()";
            if (!in_array($user['role'], ['admin', 'manager'])) $sql .= ", status='pending' WHERE id=? AND user_id=?";
            else $sql .= " WHERE id=?";
            $stmt = $conn->prepare($sql);
            if (!in_array($user['role'], ['admin', 'manager'])) 
                $stmt->bind_param("isddssii", $category_id, $title_text, $quantity, $computed, $description_text, $attachment_link, $item['id'], $user['id']);
            else 
                $stmt->bind_param("isddssi", $category_id, $title_text, $quantity, $computed, $description_text, $attachment_link, $item['id']);
            $msg = "แก้ไขข้อมูลสำเร็จ";
        } else {
            $stmt = $conn->prepare("INSERT INTO workload_items (user_id, academic_year, term_id, category_id, title, actual_hours, computed_hours, description, status, attachment_link) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isiisddsss", $user['id'], $academic_year, $term_id, $category_id, $title_text, $quantity, $computed, $description_text, $status, $attachment_link);
            $msg = "เพิ่มภาระงานสำเร็จ";
        }

        if ($stmt->execute()) {
             $target_id = $is_edit ? $item['id'] : $stmt->insert_id;
             $stmt_log = $conn->prepare("INSERT INTO workload_logs (work_log_id, user_id, action, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
             $action = $is_edit ? 'update' : 'create';
             $comment = "บันทึกงานด้านที่ 6 ($selected_code)";
             $stmt_log->bind_param("iiss", $target_id, $user['id'], $action, $comment);
             $stmt_log->execute();
            echo "<script>window.location.href='workloads.php?success=" . urlencode($msg) . "';</script>";
            exit;
        } else {
            $errors[] = "Database Error: " . htmlspecialchars($stmt->error);
        }
    }
}
?>

<div class="card p-6" style="max-width:1200px; margin:auto;">
    <div class="stack-between mb-4 border-bottom pb-4">
        <div>
            <h2 class="mb-0 text-primary"><i class="bi bi-three-dots"></i> <?= $is_edit ? "แก้ไขข้อมูล (ด้านที่ 6)" : "ภาระงานอื่นๆ / กิจกรรม" ?></h2>
        </div>
        <div class="text-right">
            <small class="muted">คะแนนรวม</small>
            <div class="text-primary font-bold" style="font-size:2rem;"><span id="grandTotalDisplay">0.00</span></div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert error mb-4"><ul><?php foreach ($errors as $e) echo "<li>$e</li>"; ?></ul></div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
        <input type="hidden" name="academic_year" value="<?= htmlspecialchars($academic_year) ?>">
        <input type="hidden" name="term_id" value="<?= htmlspecialchars($term_id) ?>">

        <div class="mb-5">
            <label class="font-bold">ประเภทงาน <span class="text-danger">*</span></label>
            <select name="category_id" id="categorySelect" class="input w-full bg-light text-lg">
                <option value="">-- กรุณาเลือก --</option>
                <?php foreach ($all_categories as $c): ?>
                    <option value="<?= $c['id']; ?>" 
                        data-code="<?= trim($c['code']) ?>"
                        data-weight="<?= floatval($c['weight']) ?>"
                        <?= ($c['id'] == $input['category_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['code'] . " : " . $c['name_th']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="section_role" style="display:none; background:#f0f9ff; padding:20px; border-radius:10px; border-left:5px solid #0ea5e9;">
            <div class="alert info mb-3"><i class="bi bi-info-circle"></i> <strong>เกณฑ์:</strong> ประธาน/เลขาฯ (x2), กรรมการ (x1) ตามชั่วโมงจริง</div>
            <div class="mb-4">
                <label class="font-bold block mb-2">บทบาทหน้าที่ <span class="text-danger">*</span></label>
                <div class="flex gap-4">
                    <label class="border p-3 rounded bg-white cursor-pointer hover:bg-light flex items-center">
                        <input type="radio" name="role_selected" value="chair" class="mr-2" <?= ($existing_role=='chair')?'checked':'' ?>> <span>ประธาน / เลขานุการ (x 2.0)</span>
                    </label>
                    <label class="border p-3 rounded bg-white cursor-pointer hover:bg-light flex items-center">
                        <input type="radio" name="role_selected" value="member" class="mr-2" <?= ($existing_role=='member')?'checked':'' ?>> <span>รองประธาน / กรรมการ (x 1.0)</span>
                    </label>
                </div>
            </div>
            <div class="mb-3">
                <label class="font-bold">ชื่อโครงการ <span class="text-danger">*</span></label>
                <input type="text" name="title_input_role" id="titleInput_role" class="input w-full" value="<?= (in_array($selected_code, $codes_role)) ? htmlspecialchars($existing_title) : '' ?>">
            </div>
            <div class="mb-3">
                <label class="font-bold">ชั่วโมงปฏิบัติจริง <span class="text-danger">*</span></label>
                <input type="number" step="0.5" min="0" name="hours_input_role" id="hoursInput_role" class="input w-full text-center font-bold text-lg border-primary" value="<?= (in_array($selected_code, $codes_role)) ? ($existing_hours > 0 ? $existing_hours : '') : '' ?>">
            </div>
        </div>

        <div id="listSection" style="display:none; background:#f8f9fa; padding:20px; border-radius:10px;">
            <div class="alert info mb-3"><i class="bi bi-info-circle"></i> <strong>เกณฑ์:</strong> <span id="listCriteriaText" class="font-bold"></span> คะแนน/รายการ</div>
            <div id="listContainer" class="mb-3">
                <?php foreach ($existing_items as $itm): ?>
                    <div class="list-item stack mb-2 gap-2">
                        <span class="badge pending row-num" style="width:30px; justify-content:center;">1</span>
                        <input type="text" name="items[]" class="input w-full" value="<?= htmlspecialchars($itm) ?>">
                        <button type="button" class="btn btn-outline-danger remove-btn" tabindex="-1"><i class="bi bi-trash"></i></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="addListBtn" class="btn btn-sm btn-outline-primary">เพิ่มรายการ</button>
        </div>

        <div id="hourlySection" style="display:none; background:#f0fdf4; padding:20px; border-radius:10px;">
            <div class="alert success mb-3"><i class="bi bi-clock"></i> <strong>เกณฑ์:</strong> คิดตามชั่วโมงจริง</div>
            <div class="mb-3">
                <label class="font-bold">ชื่องาน / กิจกรรม <span class="text-danger">*</span></label>
                <input type="text" name="title_input" id="titleInput" class="input w-full" value="<?= (!in_array($selected_code, $codes_role)) ? htmlspecialchars($existing_title) : '' ?>">
            </div>
            <div class="mb-3">
                <label class="font-bold">จำนวนชั่วโมงปฏิบัติงาน <span class="text-danger">*</span></label>
                <input type="number" step="0.5" min="0" name="hours_input" id="hoursInput" class="input w-full text-center font-bold text-lg border-success" value="<?= (!in_array($selected_code, $codes_role)) ? ($existing_hours > 0 ? $existing_hours : '') : '' ?>">
            </div>
            <div class="mb-3">
                <label class="font-bold">รายละเอียดเพิ่มเติม</label>
                <textarea name="description" class="input w-full" rows="2"><?= htmlspecialchars($input['description']) ?></textarea>
            </div>
        </div>

        <div class="mt-6 mb-6">
            <label class="font-bold text-primary">ลิงก์หลักฐาน <span class="text-danger">*</span></label>
            <input type="url" name="attachment_link" class="input w-full" value="<?= htmlspecialchars($input['attachment_link']) ?>">
        </div>

        <hr class="mb-6 opacity-50">
        <div class="stack-between">
            <a href="workloads.php" class="btn btn-light btn-lg px-4">ยกเลิก</a>
            <button type="submit" class="btn btn-primary btn-lg px-6 shadow-sm">บันทึกข้อมูล</button>
        </div>
    </form>
</div>

<script>
const categorySelect = document.getElementById('categorySelect');
const listSection = document.getElementById('listSection');
const hourlySection = document.getElementById('hourlySection');
const sectionRole = document.getElementById('section_role');
const listCriteriaText = document.getElementById('listCriteriaText');
const grandTotalDisplay = document.getElementById('grandTotalDisplay');

const codesList = <?= json_encode($codes_list); ?>; 
const codesHourly = <?= json_encode($codes_hourly); ?>;
const codesRole = <?= json_encode($codes_role); ?>; 
let currentWeight = 0;

function updateUI() {
    const option = categorySelect.options[categorySelect.selectedIndex];
    const code = option ? option.getAttribute('data-code') : '';
    currentWeight = parseFloat(option ? option.getAttribute('data-weight') : 0);

    listSection.style.display = 'none';
    hourlySection.style.display = 'none';
    sectionRole.style.display = 'none';

    if (codesRole.includes(code)) {
        sectionRole.style.display = 'block';
        updateRoleCalculation();
    } else if (codesList.includes(code)) {
        listSection.style.display = 'block';
        listCriteriaText.innerText = currentWeight;
        updateListCalculation();
    } else if (codesHourly.includes(code)) {
        hourlySection.style.display = 'block';
        updateHourlyCalculation();
    } else {
        grandTotalDisplay.innerText = '0.00';
    }
}

function updateRoleCalculation() {
    const hours = parseFloat(document.getElementById('hoursInput_role').value) || 0;
    const role = document.querySelector('input[name="role_selected"]:checked')?.value;
    const factor = (role === 'chair') ? 2 : 1;
    grandTotalDisplay.innerText = (hours * factor).toFixed(2);
}
document.querySelectorAll('input[name="role_selected"]').forEach(el => el.addEventListener('change', updateRoleCalculation));
document.getElementById('hoursInput_role').addEventListener('input', updateRoleCalculation);

const listContainer = document.getElementById('listContainer');
function updateListCalculation() {
    const items = listContainer.querySelectorAll('.list-item');
    items.forEach((item, index) => item.querySelector('.row-num').innerText = index + 1);
    grandTotalDisplay.innerText = (items.length * currentWeight).toFixed(2);
}
document.getElementById('addListBtn').onclick = () => {
    const div = document.createElement('div');
    div.className = 'list-item stack mb-2 gap-2';
    div.innerHTML = `<span class="badge pending row-num" style="width:30px; justify-content:center;"></span><input type="text" name="items[]" class="input w-full"><button type="button" class="btn btn-outline-danger remove-btn" tabindex="-1"><i class="bi bi-trash"></i></button>`;
    listContainer.appendChild(div);
    updateListCalculation();
};
listContainer.onclick = (e) => {
    if (e.target.closest('.remove-btn')) {
        if (listContainer.querySelectorAll('.list-item').length > 1) {
            e.target.closest('.list-item').remove();
            updateListCalculation();
        } else {
            e.target.closest('.list-item').querySelector('input').value = '';
        }
    }
};

function updateHourlyCalculation() {
    const h = parseFloat(document.getElementById('hoursInput').value) || 0;
    const factor = currentWeight > 0 ? currentWeight : 1; 
    grandTotalDisplay.innerText = (h * factor).toFixed(2);
}
document.getElementById('hoursInput').addEventListener('input', updateHourlyCalculation);

categorySelect.addEventListener('change', updateUI);
listContainer.addEventListener('input', (e) => { if (e.target.name === 'items[]') updateListCalculation(); });

document.querySelector('form').addEventListener('submit', function(e) {
    const hiddenSections = [listSection, hourlySection, sectionRole];
    hiddenSections.forEach(section => {
        if (section.style.display === 'none') {
            section.querySelectorAll('input, select, textarea').forEach(input => input.removeAttribute('required'));
        }
    });
});

document.addEventListener('DOMContentLoaded', updateUI);
</script>