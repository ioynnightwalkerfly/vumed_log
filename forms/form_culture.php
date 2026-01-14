<?php
// forms/form_culture.php
// ===== ฟอร์มด้านที่ 4: ทำนุบำรุงศิลปวัฒนธรรม 

// รับค่าจากหน้าหลัก
$academic_year = $academic_year ?? (date("Y") + 543);
$term_id = $term_no ?? ($term_id ?? 1); 
$is_edit = $is_edit ?? false;

// 1. เตรียมตัวแปร Input
$input = [
    'category_id'       => $_POST['category_id'] ?? ($is_edit ? $item['category_id'] : null),
    'attachment_link'   => $_POST['attachment_link'] ?? ($is_edit ? ($item['attachment_link'] ?? '') : ''),
    'quantity'          => $_POST['quantity'] ?? ($is_edit ? $item['actual_hours'] : 0),
    'description'       => $_POST['description'] ?? ($is_edit ? $item['description'] : ''),
];

// เตรียมข้อมูลเก่า (Parse Description)
$existing_role_weight = 0;
$existing_level_weight = 0; // เพิ่มตัวแปรสำหรับ 4.2
$existing_projects = [];

if ($is_edit && !empty($item['description'])) {
    $lines = explode("\n", $item['description']);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, "- ") === 0) {
            $existing_projects[] = trim(substr($line, 2));
        }
        // ดึงคะแนนบทบาท (แบบเดิม)
        if (strpos($line, "บทบาท:") !== false && preg_match('/คะแนน\s*(\d+)/', $line, $m)) {
            $existing_role_weight = $m[1];
        }
        // ดึงคะแนนระดับเผยแพร่ (แบบใหม่ 4.2)
        if (strpos($line, "ระดับ:") !== false && preg_match('/คะแนน\s*(\d+)/', $line, $m)) {
            $existing_level_weight = $m[1];
        }
    }
}
if (empty($existing_projects)) $existing_projects[] = "";

// โหลดหมวดหมู่
$stmt_cat = $conn->prepare("SELECT id, code, name_th FROM workload_categories WHERE main_area = 4 AND is_active = 1 AND target_group = 'teacher' ORDER BY code ASC");
$stmt_cat->execute();
$all_categories = $stmt_cat->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_cat->close();

// ===== บันทึกข้อมูล (POST) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token.");
    }

    $category_id     = $_POST['category_id'];
    $attachment_link = trim($_POST['attachment_link']);
    
    // รับค่าจาก 2 แบบ (บทบาท หรือ ระดับเผยแพร่)
    $role_weight     = floatval($_POST['role_weight'] ?? 0);
    $role_name       = $_POST['role_name'] ?? '';
    
    $level_weight    = floatval($_POST['level_weight'] ?? 0);
    $level_name      = $_POST['level_name'] ?? '';

    // เช็ค Code เพื่อเลือกใช้ Logic
    $selected_code = '';
    foreach($all_categories as $c) {
        if ($c['id'] == $category_id) {
            $selected_code = trim($c['code']);
            break;
        }
    }

    $errors = [];
    $active_weight = 0;
    $description_prefix = "";

    // --- Logic 4.2 (ระดับเผยแพร่) ---
    if ($selected_code == '4.2') {
        if ($level_weight <= 0) $errors[] = "กรุณาเลือกระดับการเผยแพร่";
        $active_weight = $level_weight;
        $description_prefix = "ระดับ: $level_name (คะแนน $level_weight/ผลงาน)";
    } 
    // --- Logic อื่นๆ (บทบาท) ---
    else {
        if ($role_weight <= 0) $errors[] = "กรุณาเลือกบทบาทในโครงการ";
        $active_weight = $role_weight;
        $description_prefix = "บทบาท: $role_name (คะแนน $role_weight/โครงการ)";
    }

    // จัดการชื่อโครงการ
    $project_names = $_POST['project_names'] ?? [];
    $project_names = array_filter($project_names, function ($val) { return !empty(trim($val)); });
    $quantity      = count($project_names);

    if ($quantity <= 0) $errors[] = "กรุณากรอกรายชื่อผลงาน/โครงการอย่างน้อย 1 รายการ";
    if (empty($attachment_link)) $errors[] = "กรุณาระบุลิงก์เอกสารหลักฐาน";

    // คำนวณคะแนนรวม
    $computed = $quantity * $active_weight;

    // สร้าง Description Text
    $description_text = $description_prefix;
    if (!empty($project_names)) {
        $description_text .= "\nรายชื่อ ($quantity รายการ):\n- " . implode("\n- ", $project_names);
    }

    $title_text = "งานทำนุบำรุง/สร้างสรรค์ ($quantity รายการ)";
    $status = 'pending';

    if (empty($errors)) {
        if ($is_edit) {
            // Update
            if ($user['role'] === 'admin' || $user['role'] === 'manager') {
                $sql = "UPDATE workload_items SET category_id=?, title=?, actual_hours=?, computed_hours=?, description=?, attachment_link=?, updated_at=NOW() WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isddssi", $category_id, $title_text, $quantity, $computed, $description_text, $attachment_link, $item['id']);
            } else {
                $sql = "UPDATE workload_items SET category_id=?, title=?, actual_hours=?, computed_hours=?, description=?, attachment_link=?, updated_at=NOW(), status='pending' WHERE id=? AND user_id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isddssii", $category_id, $title_text, $quantity, $computed, $description_text, $attachment_link, $item['id'], $user['id']);
            }
            $msg = "แก้ไขข้อมูลสำเร็จ";
        } else {
            // Insert
            $stmt = $conn->prepare("INSERT INTO workload_items (user_id, academic_year, term_id, category_id, title, actual_hours, computed_hours, description, status, attachment_link) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isiisddsss", $user['id'], $academic_year, $term_id, $category_id, $title_text, $quantity, $computed, $description_text, $status, $attachment_link);
            $msg = "เพิ่มภาระงานสำเร็จ";
        }

        if ($stmt->execute()) {
            // Log
            $target_id = $is_edit ? $item['id'] : $stmt->insert_id;
            $log_action  = $is_edit ? 'update' : 'create';
            $log_comment = $is_edit ? 'บันทึกงานศิลปวัฒนธรรม/สร้างสรรค์' : 'เพิ่มงานศิลปวัฒนธรรม/สร้างสรรค์';
            
            $stmt_log = $conn->prepare("INSERT INTO workload_logs (work_log_id, user_id, action, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt_log->bind_param("iiss", $target_id, $user['id'], $log_action, $log_comment);
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
            <h2 class="mb-0 text-primary">
                <i class="bi bi-palette"></i> <?= $is_edit ? "แก้ไขข้อมูล (ศิลปวัฒนธรรม)" : "บันทึกงานศิลปวัฒนธรรม" ?>
            </h2>
            <p class="muted mb-0 mt-2">เลือกประเภทงานและระบุรายละเอียด</p>
        </div>
        <div class="text-right">
            <small class="muted">คะแนนรวม (คำนวณอัตโนมัติ)</small>
            <div class="text-primary font-bold" style="font-size:2rem;">
                <span id="grandTotalDisplay">0.00</span>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert error mb-4">
            <strong><i class="bi bi-exclamation-triangle"></i> พบข้อผิดพลาด:</strong>
            <ul class="mb-0 pl-4"><?php foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
        <input type="hidden" name="academic_year" value="<?= htmlspecialchars($academic_year) ?>">
        <input type="hidden" name="term_id" value="<?= htmlspecialchars($term_id) ?>">

        <div class="mb-4">
            <label class="font-bold">1. หัวข้อภาระงาน <span class="text-danger">*</span></label>
            <select name="category_id" id="categorySelect" required class="input w-full bg-light text-lg">
                <option value="">-- กรุณาเลือก --</option>
                <?php foreach ($all_categories as $c): ?>
                    <option value="<?= $c['id']; ?>" 
                        data-code="<?= trim($c['code']) ?>"
                        <?= ($c['id'] == $input['category_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['code'] . " : " . $c['name_th']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="roleSection" class="mb-4" style="display:block;">
            <label class="font-bold">2. บทบาทในโครงการ <span class="text-danger">*</span></label>
            <select name="role_weight" id="roleSelect" class="input w-full border-primary" style="font-weight:500;">
                <option value="" data-name="">-- เลือกบทบาท --</option>
                <option value="20" data-name="ประธาน/เลขานุการ" <?= ($existing_role_weight == 20) ? 'selected' : '' ?>>ประธาน / เลขานุการ (20 คะแนน/โครงการ)</option>
                <option value="15" data-name="กรรมการ" <?= ($existing_role_weight == 15) ? 'selected' : '' ?>>กรรมการ (15 คะแนน/โครงการ)</option>
                <option value="3" data-name="ผู้เข้าร่วม" <?= ($existing_role_weight == 3) ? 'selected' : '' ?>>ผู้เข้าร่วมโครงการ (3 คะแนน/โครงการ)</option>
            </select>
            <input type="hidden" name="role_name" id="roleNameInput" value="">
        </div>

        <div id="levelSection" class="mb-4" style="display:none; background:#f0fdf4; padding:15px; border-radius:8px; border:1px solid #bbf7d0;">
            <label class="font-bold text-success"><i class="bi bi-broadcast"></i> 2. ระดับการเผยแพร่ (สำหรับงานสร้างสรรค์/นวัตกรรม) <span class="text-danger">*</span></label>
            <select name="level_weight" id="levelSelect" class="input w-full border-success" style="font-weight:500;">
                <option value="" data-name="">-- เลือกระดับการเผยแพร่ --</option>
                <option value="100" data-name="ระดับนานาชาติ" <?= ($existing_level_weight == 100) ? 'selected' : '' ?>>๑) ระดับนานาชาติ (100 คะแนน/ผลงาน)</option>
                <option value="80" data-name="ระดับระหว่างประเทศ" <?= ($existing_level_weight == 80) ? 'selected' : '' ?>>๒) ระหว่างประเทศ (80 คะแนน/ผลงาน)</option>
                <option value="60" data-name="ระดับชาติ" <?= ($existing_level_weight == 60) ? 'selected' : '' ?>>๓) ระดับชาติ (60 คะแนน/ผลงาน)</option>
                <option value="40" data-name="ระดับสถาบัน" <?= ($existing_level_weight == 40) ? 'selected' : '' ?>>๔) ระดับสถาบัน (40 คะแนน/ผลงาน)</option>
                <option value="20" data-name="สื่ออิเล็กทรอนิกส์" <?= ($existing_level_weight == 20) ? 'selected' : '' ?>>๕) สื่ออิเล็กทรอนิกส์ออนไลน์ (20 คะแนน/ผลงาน)</option>
            </select>
            <input type="hidden" name="level_name" id="levelNameInput" value="">
        </div>

        <div class="mb-5">
            <label class="font-bold text-primary"><i class="bi bi-link-45deg"></i> ลิงก์หลักฐาน (Google Drive) <span class="text-danger">*</span></label>
            <input type="url" name="attachment_link" class="input w-full" placeholder="https://drive.google.com/..."
                value="<?= htmlspecialchars($input['attachment_link']) ?>" required>
        </div>

        <hr class="mb-6 opacity-50">

        <h4 class="text-dark mb-3"><i class="bi bi-list-check"></i> <span id="listTitle">3. รายชื่อโครงการ</span></h4>
        <div class="alert info mb-4">
            <i class="bi bi-info-circle"></i> ระบบจะคำนวณจาก: <strong>จำนวนรายการ x คะแนนตามเกณฑ์ที่เลือก</strong>
        </div>

        <div id="projectListContainer" class="mb-3">
            <?php foreach ($existing_projects as $p_name): ?>
                <div class="project-item stack mb-2 gap-2">
                    <span class="badge pending row-num" style="width:35px; height:35px; display:flex; align-items:center; justify-content:center; border-radius:50%;">1</span>
                    <input type="text" name="project_names[]" class="input w-full"
                        placeholder="ระบุชื่อ..." value="<?= htmlspecialchars($p_name) ?>">
                    <button type="button" class="btn btn-outline-danger remove-project-btn" tabindex="-1">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="stack-between align-center">
            <button type="button" id="addProjectBtn" class="btn btn-outline-primary">
                <i class="bi bi-plus-lg"></i> เพิ่มรายการ
            </button>
            <div class="text-right">
                <span class="muted" style="font-size:1.1rem;">จำนวน: </span>
                <span id="quantityDisplay" class="font-bold text-dark" style="font-size:1.5rem;">0</span>
            </div>
        </div>

        <input type="hidden" name="quantity" id="quantityInput" value="<?= count($existing_projects) ?>">

        <hr class="my-6 opacity-50">

        <div class="stack-between">
            <a href="workloads.php" class="btn btn-light btn-lg px-4">ยกเลิก</a>
            <button type="submit" class="btn btn-primary btn-lg px-6 shadow-sm"><i class="bi bi-save"></i> บันทึกข้อมูล</button>
        </div>
    </form>
</div>

<script>
    const categorySelect = document.getElementById('categorySelect');
    const roleSection = document.getElementById('roleSection');
    const levelSection = document.getElementById('levelSection');
    const roleSelect = document.getElementById('roleSelect');
    const levelSelect = document.getElementById('levelSelect');
    
    const projectListContainer = document.getElementById('projectListContainer');
    const addProjectBtn = document.getElementById('addProjectBtn');
    const quantityInput = document.getElementById('quantityInput');
    const quantityDisplay = document.getElementById('quantityDisplay');
    const roleNameInput = document.getElementById('roleNameInput');
    const levelNameInput = document.getElementById('levelNameInput');
    const grandTotalDisplay = document.getElementById('grandTotalDisplay');
    const listTitle = document.getElementById('listTitle');

    // 1. Logic การเปลี่ยน Dropdown ตาม Category
    function updateCategoryUI() {
        const option = categorySelect.options[categorySelect.selectedIndex];
        const code = option ? option.getAttribute('data-code') : '';

        if (code === '4.2') {
            // โชว์ Level, ซ่อน Role
            roleSection.style.display = 'none';
            levelSection.style.display = 'block';
            roleSelect.removeAttribute('required');
            levelSelect.setAttribute('required', 'required');
            
            // เปลี่ยน Placeholder
            listTitle.innerText = '3. รายชื่อผลงานนวัตกรรม/สร้างสรรค์';
            updatePlaceholders('ระบุชื่อผลงานนวัตกรรม...');
        } else {
            // โชว์ Role, ซ่อน Level
            roleSection.style.display = 'block';
            levelSection.style.display = 'none';
            roleSelect.setAttribute('required', 'required');
            levelSelect.removeAttribute('required');
            
            // เปลี่ยน Placeholder
            listTitle.innerText = '3. รายชื่อโครงการ';
            updatePlaceholders('ระบุชื่อโครงการ...');
        }
        calculate();
    }

    function updatePlaceholders(text) {
        document.querySelectorAll('input[name="project_names[]"]').forEach(inp => inp.placeholder = text);
    }

    // 2. Logic คำนวณคะแนน
    function calculate() {
        const count = parseInt(quantityInput.value) || 0;
        let weight = 0;

        // ดูว่าอันไหนโชว์อยู่ ให้เอาคะแนนจากอันนั้น
        if (levelSection.style.display !== 'none') {
            weight = parseFloat(levelSelect.value) || 0;
            const sel = levelSelect.options[levelSelect.selectedIndex];
            if(sel) levelNameInput.value = sel.getAttribute('data-name');
        } else {
            weight = parseFloat(roleSelect.value) || 0;
            const sel = roleSelect.options[roleSelect.selectedIndex];
            if(sel) roleNameInput.value = sel.getAttribute('data-name');
        }

        const total = count * weight;
        grandTotalDisplay.innerText = total.toFixed(2);
    }

    // 3. จัดการ List รายการ
    function updateList() {
        const items = projectListContainer.querySelectorAll('.project-item');
        items.forEach((item, index) => {
            item.querySelector('.row-num').innerText = index + 1;
        });
        const count = items.length;
        quantityInput.value = count;
        quantityDisplay.innerText = count;
        calculate();
    }

    addProjectBtn.addEventListener('click', () => {
        const div = document.createElement('div');
        div.className = 'project-item stack mb-2 gap-2';
        
        // เช็ค Placeholder ปัจจุบัน
        const is42 = categorySelect.options[categorySelect.selectedIndex]?.getAttribute('data-code') === '4.2';
        const ph = is42 ? 'ระบุชื่อผลงานนวัตกรรม...' : 'ระบุชื่อโครงการ...';

        div.innerHTML = `
        <span class="badge pending row-num" style="width:35px; height:35px; display:flex; align-items:center; justify-content:center; border-radius:50%;"></span>
        <input type="text" name="project_names[]" class="input w-full" placeholder="${ph}">
        <button type="button" class="btn btn-outline-danger remove-project-btn" tabindex="-1">
            <i class="bi bi-trash"></i>
        </button>
        `;
        projectListContainer.appendChild(div);
        updateList();
    });

    projectListContainer.addEventListener('click', (e) => {
        if (e.target.closest('.remove-project-btn')) {
            const item = e.target.closest('.project-item');
            if (projectListContainer.querySelectorAll('.project-item').length > 1) {
                item.remove();
                updateList();
            } else {
                item.querySelector('input').value = '';
                item.querySelector('input').focus();
            }
        }
    });

    // Event Listeners
    categorySelect.addEventListener('change', updateCategoryUI);
    roleSelect.addEventListener('change', calculate);
    levelSelect.addEventListener('change', calculate);

    // Init
    document.addEventListener('DOMContentLoaded', () => {
        updateCategoryUI();
        updateList();
    });
</script>