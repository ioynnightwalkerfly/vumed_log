<?php
// forms/form_management.php
// ===== ฟอร์มด้านที่ 5: ภาระงานบริหาร (แก้ไข: ใช้ target_group คัดกรองแม่นยำ)

// รับค่าจากหน้าหลัก
$academic_year = $academic_year ?? (date("Y") + 543);
$term_id = $term_no ?? ($term_id ?? 1); 
$is_edit = $is_edit ?? false;
$errors = [];

// --- Config ตัวเลือกย่อย (Sub-roles) ---

// 5.5 คณะกรรมการฝ่าย
$roles_5_5 = [
    'comm'       => ['name' => 'กรรมการ', 'weight' => 3],
    'secretary'  => ['name' => 'เลขานุการ', 'weight' => 3],
    'assist_sec' => ['name' => 'ผู้ช่วยเลขานุการ', 'weight' => 3]
];

// 5.7 ผู้บริหาร
$roles_5_7 = [
    'deputy_dean' => ['name' => 'รองคณบดี', 'weight' => 11],
    'asst_dean'   => ['name' => 'ผู้ช่วยคณบดี', 'weight' => 10]
];

// 1. เตรียมตัวแปร Input
$input = [
    'category_id'     => $_POST['category_id'] ?? ($is_edit ? $item['category_id'] : null),
    'sub_role'        => $_POST['sub_role'] ?? '', 
    'input_value'     => $_POST['input_value'] ?? ($is_edit ? $item['actual_hours'] : 0),
    'attachment_link' => $_POST['attachment_link'] ?? ($is_edit ? ($item['attachment_link'] ?? '') : ''),
];

// ดึง sub_role เก่าจาก description (กรณีแก้ไข)
if ($is_edit && empty($input['sub_role'])) {
    if (preg_match('/\[sub_role:([^\]]+)\]/', $item['description'], $m)) {
        $input['sub_role'] = $m[1];
    }
}

// ===== 2. โหลดหมวดหมู่ (แก้ไข: ใช้ target_group คัดกรอง) =====
// เลือกเฉพาะงานบริหาร (Area 5) ที่เป็นของอาจารย์ (TEACHER)
// จะทำให้ 5.1 ที่เป็นของอาจารย์แสดง แต่ 5.1 ของ Staff หายไปเอง
$stmt = $conn->prepare("
    SELECT id, code, name_th, weight 
    FROM workload_categories 
    WHERE main_area = 5 
      AND is_active = 1 
      AND target_group = 'TEACHER' 
    ORDER BY code ASC
");
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ===== 3. บันทึกข้อมูล (POST) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token.");
    }

    $category_id     = $input['category_id'];
    $sub_role        = $_POST['sub_role'] ?? '';
    $input_value     = floatval($_POST['input_value']);
    $attachment_link = trim($input['attachment_link']);
    
    // หาข้อมูลหมวดที่เลือก
    $selected_cat = null;
    foreach ($categories as $c) {
        if ($c['id'] == $category_id) { $selected_cat = $c; break; }
    }

    if (!$selected_cat) $errors[] = "กรุณาเลือกหมวดงาน";
    if ($input_value <= 0) $errors[] = "กรุณากรอกจำนวนให้ถูกต้อง";

    // คำนวณคะแนน
    $factor = 0;
    $unit_label = "สัปดาห์"; // ค่าเริ่มต้น
    $code = trim($selected_cat['code'] ?? '');
    $title = $selected_cat['name_th']; 

    // --- Logic การคำนวณ ---
    if ($code == '5.5') {
        // 5.5 คณะกรรมการฝ่าย (3 ชม./สัปดาห์)
        if (isset($roles_5_5[$sub_role])) {
            $factor = $roles_5_5[$sub_role]['weight'];
            $title .= " (" . $roles_5_5[$sub_role]['name'] . ")";
        } else {
            $errors[] = "กรุณาระบุบทบาท (กรรมการ/เลขาฯ/ผู้ช่วย)";
        }
        
    } elseif ($code == '5.6') {
        // 5.6 อาจารย์ประจำชั้นปี
        $factor = 15;
        
    } elseif ($code == '5.7') {
        // 5.7 ผู้บริหาร
        if (isset($roles_5_7[$sub_role])) {
            $factor = $roles_5_7[$sub_role]['weight'];
            $title .= " (" . $roles_5_7[$sub_role]['name'] . ")";
        } else {
            $errors[] = "กรุณาระบุตำแหน่งบริหาร";
        }
        
    } elseif ($code == '5.4') {
        // *** 5.4 สูตรใหม่: ไม่ต้องเลือกบทบาท / คูณ 10 ***
        $factor = 10; 
        $unit_label = "สัปดาห์";

    } elseif ($code == '5.1') {
        // 5.1 เข้าร่วมโครงการ (ของอาจารย์)
        // ถ้าหน่วยเป็น "โครงการ" อาจใช้น้ำหนักจาก DB
        $unit_label = "โครงการ";
        $factor = floatval($selected_cat['weight']);

    } else {
        // Fallback: ใช้น้ำหนักจาก DB (กรณีมีรหัสอื่นๆ)
        $factor = floatval($selected_cat['weight']);
        if ($factor == 0) $factor = 1; 
    }

    $computed = $input_value * $factor;

    // สร้างคำอธิบาย
    $desc = "หมวด: {$selected_cat['name_th']}\n";
    if (!empty($sub_role)) $desc .= "[sub_role:$sub_role]\n"; 
    $desc .= "ปฏิบัติงาน: $input_value $unit_label (x$factor = $computed)";

    if (empty($errors)) {
        if ($is_edit) {
            $sql = "UPDATE workload_items SET category_id=?, title=?, actual_hours=?, computed_hours=?, description=?, attachment_link=?, updated_at=NOW()";
            if (!in_array($user['role'], ['admin', 'manager'])) $sql .= ", status='pending' WHERE id=? AND user_id=?";
            else $sql .= " WHERE id=?";
            
            $stmt = $conn->prepare($sql);
            if (!in_array($user['role'], ['admin', 'manager'])) 
                $stmt->bind_param("isddssii", $category_id, $title, $input_value, $computed, $desc, $attachment_link, $item['id'], $user['id']);
            else 
                $stmt->bind_param("isddssi", $category_id, $title, $input_value, $computed, $desc, $attachment_link, $item['id']);
            $msg = "แก้ไขข้อมูลสำเร็จ";
        } else {
            $stmt = $conn->prepare("INSERT INTO workload_items (user_id, academic_year, term_id, category_id, title, actual_hours, computed_hours, description, status, attachment_link) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
            $stmt->bind_param("isiisddss", $user['id'], $academic_year, $term_id, $category_id, $title, $input_value, $computed, $desc, $attachment_link);
            $msg = "เพิ่มภาระงานสำเร็จ";
        }

        if ($stmt->execute()) {
            echo "<script>window.location.href='workloads.php?success=".urlencode($msg)."';</script>";
            exit;
        } else {
            $errors[] = "Database Error: " . $stmt->error;
        }
    }
}
?>

<div class="card p-6" style="max-width:1000px; margin:auto;">
    <div class="stack-between mb-4 border-bottom pb-4">
        <div>
            <h2 class="mb-0 text-primary"><?= $is_edit ? "แก้ไขข้อมูล (งานบริหาร)" : "บันทึกภาระงานบริหาร" ?></h2>
            <p class="muted mb-0 mt-2">คำนวณตามตำแหน่ง/หน้าที่ ที่ได้รับแต่งตั้ง</p>
        </div>
        <div class="text-right">
            <small class="muted">คะแนนรวม</small>
            <div class="text-primary font-bold" style="font-size:24px;">
                <span id="totalDisplay">0.00</span>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert error mb-4"><ul><?php foreach ($errors as $e) echo "<li>$e</li>"; ?></ul></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
        
        <div class="mb-4">
            <label class="font-bold">1. ประเภท/ตำแหน่งบริหาร <span class="text-danger">*</span></label>
            <select name="category_id" id="categorySelect" required class="w-full border p-2 rounded bg-muted text-lg font-bold" onchange="updateForm()">
                <option value="">-- กรุณาเลือก --</option>
                <?php foreach($categories as $c): ?>
                    <option value="<?= $c['id']; ?>" 
                            data-code="<?= trim($c['code']) ?>" 
                            data-weight="<?= $c['weight'] ?>"
                            <?= ($c['id'] == $input['category_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['code']." ".$c['name_th']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-4 p-4 bg-white border rounded" id="subRoleContainer" style="display:none;">
            <label class="font-bold text-primary mb-2 block">2. ระบุตำแหน่ง/บทบาท <span class="text-danger">*</span></label>
            
            <div id="options_5_5" class="role-options" style="display:none;">
                <?php foreach($roles_5_5 as $k => $v): ?>
                <label class="border p-3 rounded cursor-pointer hover:bg-light flex items-center mb-2">
                    <input type="radio" name="sub_role" value="<?= $k ?>" class="mr-2" <?= ($input['sub_role']==$k)?'checked':'' ?> onchange="calculate()">
                    <span><?= $v['name'] ?> (<?= $v['weight'] ?> คะแนน)</span>
                </label>
                <?php endforeach; ?>
            </div>

            <div id="options_5_7" class="role-options" style="display:none;">
                <?php foreach($roles_5_7 as $k => $v): ?>
                <label class="border p-3 rounded cursor-pointer hover:bg-light flex items-center mb-2">
                    <input type="radio" name="sub_role" value="<?= $k ?>" class="mr-2" <?= ($input['sub_role']==$k)?'checked':'' ?> onchange="calculate()">
                    <span><?= $v['name'] ?> (<?= $v['weight'] ?> คะแนน)</span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="p-4 bg-light rounded border mb-4">
            <label id="inputLabel" class="font-bold text-primary">2. จำนวน (สัปดาห์)</label>
            <div class="grid grid-2 gap-4 items-center">
                <div class="flex items-center gap-2">
                    <input type="number" step="0.01" min="0" name="input_value" id="inputValue" 
                           class="w-full border p-2 rounded font-bold text-center text-xl" 
                           value="<?= htmlspecialchars($input['input_value']) ?>" required oninput="calculate()">
                    <span id="unitLabel" class="text-muted whitespace-nowrap">หน่วย</span>
                </div>
                <div class="text-center">
                    <small class="muted">สูตร: <span id="formulaDisplay">0 x 0</span></small>
                    <div class="text-primary font-bold text-2xl">= <span id="calcResult">0.00</span></div>
                </div>
            </div>
        </div>

        <div class="mb-6">
            <label class="font-bold"><i class="bi bi-link-45deg"></i> ลิงก์คำสั่งแต่งตั้ง (Google Drive) <span class="text-danger">*</span></label>
            <input type="url" name="attachment_link" class="w-full border p-2 rounded" 
                   placeholder="วางลิงก์ไฟล์ที่นี่" value="<?= htmlspecialchars($input['attachment_link']) ?>" required>
        </div>

        <hr class="mb-6">
        <div class="flex justify-between" style="display:flex; justify-content:space-between;">
            <a href="workloads.php" class="btn btn-secondary">ย้อนกลับ</a>
            <button type="submit" class="btn btn-primary px-6">บันทึกข้อมูล</button>
        </div>
    </form>
</div>

<script>
    const categorySelect = document.getElementById('categorySelect');
    const subRoleContainer = document.getElementById('subRoleContainer');
    const inputValue = document.getElementById('inputValue');
    const unitLabel = document.getElementById('unitLabel');
    const formulaDisplay = document.getElementById('formulaDisplay');
    const calcResult = document.getElementById('calcResult');
    const totalDisplay = document.getElementById('totalDisplay');

    // Mappings
    const roles57 = <?= json_encode(array_map(function($r){ return $r['weight']; }, $roles_5_7)) ?>;
    const roles55 = <?= json_encode(array_map(function($r){ return $r['weight']; }, $roles_5_5)) ?>;

    function updateForm() {
        const option = categorySelect.options[categorySelect.selectedIndex];
        const code = option.getAttribute('data-code');
        
        // Reset UI
        subRoleContainer.style.display = 'none';
        document.querySelectorAll('.role-options').forEach(el => el.style.display = 'none');
        unitLabel.innerText = "สัปดาห์"; // Default

        // Show Options & Adjust Units
        if (code === '5.7') {
            subRoleContainer.style.display = 'block';
            document.getElementById('options_5_7').style.display = 'block';
        } else if (code === '5.5') {
            subRoleContainer.style.display = 'block';
            document.getElementById('options_5_5').style.display = 'block';
        } else if (code === '5.1') {
            unitLabel.innerText = "โครงการ";
        }
        
        calculate();
    }

    function calculate() {
        const val = parseFloat(inputValue.value) || 0;
        if(categorySelect.selectedIndex <= 0) return;

        const option = categorySelect.options[categorySelect.selectedIndex];
        const code = option.getAttribute('data-code');
        let factor = parseFloat(option.getAttribute('data-weight')) || 0;

        // Override logic in JS
        if (code === '5.7') {
            const sub = document.querySelector('#options_5_7 input[name="sub_role"]:checked');
            factor = sub ? (roles57[sub.value] || 0) : 0;
        } else if (code === '5.5') {
            const sub = document.querySelector('#options_5_5 input[name="sub_role"]:checked');
            factor = sub ? (roles55[sub.value] || 0) : 0;
        } else if (code === '5.4') {
            factor = 10; // 5.4 บังคับคูณ 10 ตามโจทย์
        } else if (code === '5.6') {
            factor = 15;
        }

        const total = val * factor;
        
        formulaDisplay.innerText = `${val} x ${factor}`;
        calcResult.innerText = total.toFixed(2);
        totalDisplay.innerText = total.toFixed(2);
    }

    document.addEventListener('DOMContentLoaded', () => {
        updateForm();
        if(inputValue.value > 0) calculate();
    });
    
    categorySelect.addEventListener('change', updateForm);
    inputValue.addEventListener('input', calculate);
</script>