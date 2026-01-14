<?php
// forms/form_research.php
// ===== ฟอร์มด้านที่ 2: งานวิจัยและวิชาการ (เพิ่มการคำนวณสัดส่วน %) =====

// รับค่าจากหน้าหลัก
$academic_year = $academic_year ?? (date("Y") + 543);
$term_id = $term_no ?? ($term_id ?? 1); 
$is_edit = $is_edit ?? false;

// 1. เตรียมตัวแปร Input
$errors = [];
$input = [
    'category_id'     => $_POST['category_id'] ?? ($is_edit ? $item['category_id'] : null),
    'title'           => $_POST['title'] ?? ($is_edit ? $item['title'] : ''),
    'attachment_link' => $_POST['attachment_link'] ?? ($is_edit ? ($item['attachment_link'] ?? '') : ''),
    'quantity'        => $_POST['quantity'] ?? ($is_edit ? $item['actual_hours'] : 0),
    'description'     => $_POST['description'] ?? ($is_edit ? $item['description'] : ''),
];

// โหลดหมวดหมู่
$stmt = $conn->prepare("
    SELECT id, code, name_th, weight 
    FROM workload_categories 
    WHERE main_area = 2 AND is_active = 1 AND target_group = 'teacher'
    ORDER BY CAST(SUBSTRING_INDEX(code, '.', 1) AS UNSIGNED) ASC, 
             CAST(SUBSTRING_INDEX(code, '.', -1) AS UNSIGNED) ASC
");
$stmt->execute();
$all_categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ===== เมื่อบันทึก (POST) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token.");
    }

    $category_id     = $input['category_id'];
    $attachment_link = trim($input['attachment_link']);
    
    // รับค่ารายชื่อ และ เปอร์เซ็นต์
    $project_names = $_POST['project_names'] ?? [];
    $project_percents = $_POST['project_percents'] ?? [];
    
    // Weight ของหมวดที่เลือก
    $weight = 0;
    foreach($all_categories as $c) {
        if ($c['id'] == $category_id) {
            $weight = floatval($c['weight']);
            break;
        }
    }

    // คำนวณคะแนนรวมตามสัดส่วน
    $total_computed = 0;
    $valid_items = [];
    $description_lines = [];

    foreach ($project_names as $index => $name) {
        if (!empty(trim($name))) {
            $percent = floatval($project_percents[$index] ?? 100);
            // ป้องกันค่าเกินขอบเขต
            if ($percent < 0) $percent = 0;
            if ($percent > 100) $percent = 100;

            // สูตร: คะแนนเต็ม x (เปอร์เซ็นต์ / 100)
            $item_score = $weight * ($percent / 100);
            $total_computed += $item_score;

            $valid_items[] = $name;
            // บันทึกชื่อพร้อม % ไว้ใน description
            $description_lines[] = "- " . trim($name) . " (" . $percent . "%)";
        }
    }

    $quantity = count($valid_items);
    $computed = $total_computed; // คะแนนสุทธิที่คำนวณแล้ว

    // สร้าง Description Text
    $description_text = "";
    if (!empty($valid_items)) {
        $label = "รายชื่อผลงาน";
        $unit  = "เรื่อง";
        
        // เช็ค Code เพื่อเปลี่ยนคำนำหน้า (เหมือนเดิม)
        foreach($all_categories as $c) {
            if ($c['id'] == $category_id) {
                $code = trim($c['code']);
                if ($code == '2.5') { $label = "รายชื่อหนังสือ/ตำรา"; $unit = "เล่ม"; } 
                elseif ($code == '2.7') { $label = "รายชื่อเอกสารประกอบการสอน"; $unit = "เล่ม"; } 
                elseif (in_array($code, ['2.11', '2.12', '2.16', '2.17'])) { $label = "รายชื่อบทความวิจัย (Proceeding)"; } 
                elseif (in_array($code, ['2.13', '2.14', '2.15'])) { $label = "รายชื่อบทความวิชาการ"; } 
                elseif (in_array($code, ['2.8', '2.9', '2.10'])) { $label = "รายชื่อบทความวิจัย"; } 
                elseif (in_array($code, ['2.18', '2.19'])) { $label = "รายชื่อผลงานนวัตกรรม"; $unit = "ผลงาน"; } 
                elseif (in_array($code, ['2.20', '2.21', '2.22', '2.23', '2.24'])) { $label = "รายชื่อทรัพย์สินทางปัญญา"; $unit = "เรื่อง/ชิ้น"; }
                break;
            }
        }
        
        $description_text = "$label ($quantity $unit):\n" . implode("\n", $description_lines);
    } else {
        $description_text = $input['description'];
    }
    
    $title_text = "บันทึกภาระงานวิจัย ($quantity รายการ)";
    $status = 'pending';

    // Validation
    if (empty($category_id)) $errors[] = "กรุณาเลือกประเภทงานวิจัย";
    if ($quantity <= 0) $errors[] = "กรุณากรอกข้อมูลอย่างน้อย 1 รายการ";
    if (empty($attachment_link)) $errors[] = "กรุณาระบุลิงก์เอกสารหลักฐาน";

    // Upload Logic (เหมือนเดิม)
    $evidence = $item['evidence'] ?? null;
    if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
        $targetDir = "../uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        $ext = strtolower(pathinfo($_FILES["evidence"]["name"], PATHINFO_EXTENSION));
        $newFilename = "evd_res_" . $user['id'] . "_" . time() . "." . $ext;
        if (move_uploaded_file($_FILES["evidence"]["tmp_name"], $targetDir . $newFilename)) {
            $evidence = $newFilename;
        }
    }

    // Save Logic (เหมือนเดิม)
    if (empty($errors)) {
        if ($is_edit) {
            $sql = "UPDATE workload_items SET category_id=?, title=?, actual_hours=?, computed_hours=?, description=?, evidence=?, attachment_link=?, updated_at=NOW()";
            if ($user['role'] === 'admin' || $user['role'] === 'manager') {
                $sql .= " WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isddsssi", $category_id, $title_text, $quantity, $computed, $description_text, $evidence, $attachment_link, $item['id']);
            } else {
                $sql .= ", status='pending' WHERE id=? AND user_id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isddssii", $category_id, $title_text, $quantity, $computed, $description_text, $evidence, $attachment_link, $item['id'], $user['id']);
            }
            $success_msg = "แก้ไขข้อมูลสำเร็จ";
        } else {
            $stmt = $conn->prepare("INSERT INTO workload_items (user_id, academic_year, term_id, category_id, title, actual_hours, computed_hours, description, evidence, status, attachment_link) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isiisddssss", $user['id'], $academic_year, $term_id, $category_id, $title_text, $quantity, $computed, $description_text, $evidence, $status, $attachment_link);
            $success_msg = "เพิ่มภาระงานสำเร็จ";
        }

        if ($stmt->execute()) {
            // Log
            $target_id = $is_edit ? $item['id'] : $stmt->insert_id;
            $log_action  = $is_edit ? 'update' : 'create';
            $log_comment = $is_edit ? "แก้ไขงานวิจัย ($title_text)" : "เพิ่มงานวิจัย ($title_text)";
            $stmt_log = $conn->prepare("INSERT INTO workload_logs (work_log_id, user_id, action, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt_log->bind_param("iiss", $target_id, $user['id'], $log_action, $log_comment);
            $stmt_log->execute();
            
            echo "<script>window.location.href='workloads.php?success=".urlencode($success_msg)."';</script>";
            exit;
        } else {
            $errors[] = "Database Error: " . htmlspecialchars($stmt->error);
        }
    }
}

// เตรียมข้อมูลเก่า (Parse Description เพื่อเอาชื่อและ %)
$existing_data = [];
if ($is_edit && !empty($item['description'])) {
    $lines = explode("\n", $item['description']);
    foreach ($lines as $line) {
        if (strpos($line, "- ") === 0) {
            $raw = substr($line, 2); // ตัด "- " ออก
            // เช็คว่ามีวงเล็บเปอร์เซ็นต์ไหม เช่น "Project A (50%)"
            if (preg_match('/^(.*) \((\d+(\.\d+)?)%\)$/', $raw, $matches)) {
                $existing_data[] = ['name' => $matches[1], 'percent' => $matches[2]];
            } else {
                // ของเดิมไม่มี % ให้เป็น 100
                $existing_data[] = ['name' => $raw, 'percent' => 100];
            }
        }
    }
}
if (empty($existing_data)) $existing_data[] = ['name' => '', 'percent' => 100];
?>

<div class="card p-6" style="max-width:1200px; margin:auto;">
    
    <div class="stack-between mb-4 border-bottom pb-4">
        <div>
            <h2 class="mb-0 text-primary">
                <?= $is_edit ? "แก้ไขข้อมูล (ด้านที่ 2 : งานวิจัย)" : "บันทึกงานวิจัย/วิชาการ" ?>
            </h2>
            <div class="stack mt-2">
                <p class="muted mb-0">กรอกข้อมูลและระบุสัดส่วนการมีส่วนร่วม (%)</p>
                <button type="button" id="openCriteriaBtn" class="btn btn-outline btn-sm">
                    <i class="bi bi-info-circle"></i> ดูเกณฑ์คะแนน
                </button>
            </div>
        </div>
        <div class="text-right">
            <small class="muted">ภาระงานสุทธิ (คิดตามสัดส่วน)</small>
            <div class="text-primary font-bold" style="font-size:28px;">
                <span id="grandTotalDisplayTop">0.00</span>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert error mb-4">
            <strong>พบข้อผิดพลาด:</strong>
            <ul><?php foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
        <input type="hidden" name="academic_year" value="<?= htmlspecialchars($academic_year) ?>">
        <input type="hidden" name="term_id" value="<?= htmlspecialchars($term_id) ?>">

        <h4 class="text-primary mb-3"><i class="bi bi-bookmark-star"></i> 1. ประเภทผลงาน</h4>
        <div class="grid grid-2 mb-6" style="gap:20px;">
            <div class="full" style="grid-column: span 2;">
                <label>เลือกประเภท (ตามประกาศ) <span class="text-danger">*</span></label>
                <select name="category_id" id="categorySelect" required class="bg-muted w-full p-2 rounded border">
                    <option value="">-- กรุณาเลือก --</option>
                    <?php foreach($all_categories as $c): ?>
                        <option value="<?= $c['id']; ?>" 
                            data-weight="<?= $c['weight']; ?>"
                            data-code="<?= trim($c['code']); ?>"
                            <?= ($c['id'] == $input['category_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['code']." : ".$c['name_th']); ?> 
                            (<?= number_format($c['weight'],0) ?> คะแนนเต็ม)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="full" style="grid-column: span 2;">
                <label class="text-primary" style="font-size:16px;">
                    <i class="bi bi-link-45deg"></i> ลิงก์หลักฐาน / ไฟล์งานวิจัย (Google Drive) <span class="text-danger">*</span>
                </label>
                <input type="url" name="attachment_link" class="w-full p-2 rounded border" 
                       placeholder="วางลิงก์ที่นี่ (จำเป็นต้องระบุ)" 
                       value="<?= htmlspecialchars($input['attachment_link']) ?>" required>
                
                <div id="vuMailWarning" class="alert warning mt-2" style="display:none;">
                    <i class="bi bi-exclamation-triangle-fill"></i> 
                    <strong>ข้อควรระวัง:</strong> ต้องใช้ <u>อีเมลมหาวิทยาลัย (VU Mail)</u> ในการติดต่อ/ตีพิมพ์เท่านั้น
                </div>
            </div>
        </div>

        <hr class="mb-6 opacity-50">

        <h4 class="text-primary mb-3"><i class="bi bi-list-check"></i> <span id="listTitleLabel">2. รายชื่อโครงการ / ผลงาน</span></h4>
        <div class="alert info mb-4">
            <i class="bi bi-calculator"></i> <strong>การคำนวณ:</strong> คะแนนจะคิดจาก <code>(ค่าน้ำหนักหมวด x สัดส่วน %)</code>
        </div>

        <div id="projectListContainer" class="mb-3">
            <?php foreach($existing_data as $data): ?>
                <div class="project-item stack mb-2 gap-2 align-start">
                    <span class="badge pending row-num mt-1" style="width:35px; height:35px; display:flex; align-items:center; justify-content:center; border-radius:50%;">1</span>
                    
                    <div style="flex-grow:1;">
                        <input type="text" name="project_names[]" class="project-name-input w-full p-2 rounded border" 
                               placeholder="ระบุชื่อโครงการวิจัย / ชื่อผลงาน" 
                               value="<?php echo htmlspecialchars($data['name']); ?>">
                    </div>
                    
                    <div style="width: 140px;" class="text-center">
                        <div class="input-group">
                            <input type="number" name="project_percents[]" class="percent-input w-full p-2 rounded border text-center font-bold" 
                                   min="0" max="100" step="1" 
                                   value="<?php echo htmlspecialchars($data['percent']); ?>" placeholder="100">
                            <span class="input-group-text bg-light border-l-0">%</span>
                        </div>
                        <small class="muted">สัดส่วน</small>
                    </div>

                    <button type="button" class="btn btn-outline-danger remove-project-btn mt-1" tabindex="-1">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="stack-between align-center">
            <button type="button" id="addProjectBtn" class="btn btn-outline-primary">
                <i class="bi bi-plus-lg"></i> <span id="addBtnLabel">เพิ่มรายการ</span>
            </button>
            <div class="text-right">
                <span class="muted" id="unitLabel">จำนวนเรื่อง: </span>
                <span id="quantityDisplay" class="font-bold text-dark" style="font-size:1.5rem;">0</span>
            </div>
        </div>

        <input type="hidden" name="quantity" id="quantityInput" value="<?= count($existing_data) ?>">
        <input type="hidden" id="weightHidden" value="0">

        <hr class="mb-6 opacity-50">

        <div class="mb-6">
            <label class="muted" style="font-size:14px;">แนบไฟล์สำรอง (PDF/รูปภาพ - ถ้ามี)</label>
            <input type="file" name="evidence" accept=".pdf,.jpg,.png" style="font-size:14px;">
            <?php if ($is_edit && !empty($item['evidence'])): ?>
                <span class="text-sm ml-2">ไฟล์เดิม: <a href="../uploads/<?= htmlspecialchars($item['evidence']) ?>" target="_blank">เปิดดู</a></span>
            <?php endif; ?>
        </div>

        <div class="stack-between p-4 rounded bg-muted">
            <a href="workloads.php" class="btn btn-muted text-dark"><i class="bi bi-arrow-left"></i> ย้อนกลับ</a>
            <button type="submit" class="btn btn-primary btn-lg px-6"><i class="bi bi-save"></i> บันทึกข้อมูล</button>
        </div>
    </form>
</div>

<div class="modal" id="criteriaModal">
    <div class="modal-content" style="max-width: 750px;">
        <span class="close" id="closeCriteriaModal" style="float:right; cursor:pointer; font-size:1.5rem;">&times;</span>
        <h3 class="mb-3 text-primary"><i class="bi bi-info-circle-fill"></i> เกณฑ์ภาระงานด้านที่ 2 (วิจัย)</h3>
        <div class="table-card border rounded" style="max-height:60vh; overflow-y:auto;">
            <table class="table table-sm">
                <thead class="bg-muted">
                    <tr><th>หมวด</th><th>รายละเอียด</th><th class="text-right">คะแนน (100%)</th></tr>
                </thead>
                <tbody>
                    <tr><td>2.1-2.4</td><td>ทุนวิจัยภายใน/ภายนอก</td><td class="text-right">150-300</td></tr>
                    <tr><td>2.5</td><td>ตำรา (ภาษาไทย)</td><td class="text-right">300</td></tr>
                    <tr><td>2.7</td><td>เอกสารประกอบการสอน</td><td class="text-right">100</td></tr>
                    <tr><td>2.8</td><td>บทความวิจัย (Inter/ISI)</td><td class="text-right">200</td></tr>
                    <tr><td>2.9</td><td>บทความวิจัย (TCI 1)</td><td class="text-right">160</td></tr>
                    <tr><td>2.10</td><td>บทความวิจัย (TCI 2)</td><td class="text-right">120</td></tr>
                    <tr><td>2.11-2.12</td><td>Proceeding</td><td class="text-right">40-80</td></tr>
                    <tr><td>2.13-2.15</td><td>บทความวิชาการ</td><td class="text-right">60-100</td></tr>
                    <tr><td>2.18-2.19</td><td>นวัตกรรม</td><td class="text-right">60-100</td></tr>
                    <tr><td>2.20-2.24</td><td>ทรัพย์สินทางปัญญา</td><td class="text-right">50-300</td></tr>
                </tbody>
            </table>
        </div>
        <div class="text-right mt-3"><button class="btn btn-muted" id="closeCriteriaBtn">ปิดหน้าต่าง</button></div>
    </div>
</div>

<script>
// Modal Logic
const modal = document.getElementById("criteriaModal");
if (document.getElementById("openCriteriaBtn")) {
    document.getElementById("openCriteriaBtn").onclick = () => modal.classList.add("show");
    const closeModal = () => modal.classList.remove("show");
    document.getElementById("closeCriteriaBtn").onclick = closeModal;
    document.getElementById("closeCriteriaModal").onclick = closeModal;
    modal.onclick = (e) => { if(e.target === modal) closeModal(); };
}

// Elements
const projectListContainer = document.getElementById('projectListContainer');
const addProjectBtn = document.getElementById('addProjectBtn');
const quantityInput = document.getElementById('quantityInput');
const quantityDisplay = document.getElementById('quantityDisplay');
const categorySelect = document.getElementById('categorySelect');
const weightHidden = document.getElementById('weightHidden');
const grandTotalDisplayTop = document.getElementById('grandTotalDisplayTop');
const vuMailWarning = document.getElementById('vuMailWarning');

// Labels
const listTitleLabel = document.getElementById('listTitleLabel');
const addBtnLabel = document.getElementById('addBtnLabel');
const unitLabel = document.getElementById('unitLabel');

let currentPlaceholder = 'ระบุชื่อโครงการวิจัย / ชื่อผลงาน';

// Update List Numbers and Calculate Total
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

function calculate() {
    const weight = parseFloat(weightHidden.value) || 0;
    let totalScore = 0;
    
    const rows = projectListContainer.querySelectorAll('.project-item');
    rows.forEach(row => {
        const percentInput = row.querySelector('.percent-input');
        const p = parseFloat(percentInput.value) || 0;
        // สูตร: คะแนน = น้ำหนัก * (เปอร์เซ็นต์ / 100)
        totalScore += weight * (p / 100);
    });

    if(grandTotalDisplayTop) grandTotalDisplayTop.innerText = totalScore.toFixed(2);
}

// Add New Row
addProjectBtn.addEventListener('click', () => {
    const div = document.createElement('div');
    div.className = 'project-item stack mb-2 gap-2 align-start';
    div.innerHTML = `
        <span class="badge pending row-num mt-1" style="width:35px; height:35px; display:flex; align-items:center; justify-content:center; border-radius:50%;"></span>
        <div style="flex-grow:1;">
            <input type="text" name="project_names[]" class="project-name-input w-full p-2 rounded border" placeholder="${currentPlaceholder}">
        </div>
        <div style="width: 140px;" class="text-center">
            <div class="input-group">
                <input type="number" name="project_percents[]" class="percent-input w-full p-2 rounded border text-center font-bold" 
                       min="0" max="100" step="1" value="100" placeholder="100">
                <span class="input-group-text bg-light border-l-0">%</span>
            </div>
            <small class="muted">สัดส่วน</small>
        </div>
        <button type="button" class="btn btn-outline-danger remove-project-btn mt-1" tabindex="-1"><i class="bi bi-trash"></i></button>
    `;
    projectListContainer.appendChild(div);
    updateList();
});

// Remove Row
projectListContainer.addEventListener('click', (e) => {
    if (e.target.closest('.remove-project-btn')) {
        const item = e.target.closest('.project-item');
        if (projectListContainer.querySelectorAll('.project-item').length > 1) {
            item.remove();
            updateList();
        } else {
            item.querySelector('.project-name-input').value = '';
            item.querySelector('.percent-input').value = 100;
            calculate();
        }
    }
});

// Real-time calculation on percentage change
projectListContainer.addEventListener('input', (e) => {
    if (e.target.classList.contains('percent-input')) {
        let val = parseFloat(e.target.value);
        if (val > 100) e.target.value = 100;
        if (val < 0) e.target.value = 0;
        calculate();
    }
});

// Change Logic (Category)
categorySelect.addEventListener('change', () => {
    const selected = categorySelect.selectedOptions[0];
    const w = selected.getAttribute('data-weight') || 0;
    const code = selected.getAttribute('data-code') || '';
    
    weightHidden.value = w;
    vuMailWarning.style.display = 'none';

    // กำหนดข้อความ Placeholder และ Label ตามหมวดหมู่
    if (code === '2.5') {
        currentPlaceholder = 'ระบุชื่อเรื่อง / ชื่อหนังสือ';
        listTitleLabel.innerText = '2. รายชื่อหนังสือ / ตำรา';
        addBtnLabel.innerText = 'เพิ่มชื่อหนังสือ';
        unitLabel.innerText = 'จำนวนเล่ม: ';
    } else if (code === '2.7') {
        currentPlaceholder = 'ระบุชื่อเอกสารประกอบการสอน';
        listTitleLabel.innerText = '2. รายชื่อเอกสารประกอบการสอน';
        addBtnLabel.innerText = 'เพิ่มชื่อเอกสาร';
        unitLabel.innerText = 'จำนวนเล่ม: ';
    } else if (['2.8', '2.9', '2.10', '2.11', '2.12', '2.13', '2.14', '2.15', '2.16', '2.17'].includes(code)) {
        currentPlaceholder = 'ระบุชื่อบทความ';
        listTitleLabel.innerText = '2. รายชื่อบทความ';
        addBtnLabel.innerText = 'เพิ่มบทความ';
        unitLabel.innerText = 'จำนวนเรื่อง: ';
        if(['2.8','2.9','2.10','2.13','2.14','2.15'].includes(code)) vuMailWarning.style.display = 'block';
    } else if (['2.18', '2.19'].includes(code)) {
        currentPlaceholder = 'ระบุชื่อผลงานนวัตกรรม';
        listTitleLabel.innerText = '2. รายชื่อผลงานนวัตกรรม';
        addBtnLabel.innerText = 'เพิ่มผลงาน';
        unitLabel.innerText = 'จำนวนผลงาน: ';
    } else if (['2.20', '2.21', '2.22', '2.23', '2.24'].includes(code)) {
        currentPlaceholder = 'ระบุชื่อทรัพย์สินทางปัญญา';
        listTitleLabel.innerText = '2. รายชื่อทรัพย์สินทางปัญญา';
        addBtnLabel.innerText = 'เพิ่มรายการ';
        unitLabel.innerText = 'จำนวนรายการ: ';
    } else {
        currentPlaceholder = 'ระบุชื่อโครงการวิจัย / ชื่อผลงาน';
        listTitleLabel.innerText = '2. รายชื่อโครงการ / ผลงาน';
        addBtnLabel.innerText = 'เพิ่มชื่อโครงการ';
        unitLabel.innerText = 'จำนวนเรื่อง: ';
    }

    const existingInputs = projectListContainer.querySelectorAll('.project-name-input');
    existingInputs.forEach(input => input.placeholder = currentPlaceholder);

    calculate();
});

document.addEventListener('DOMContentLoaded', () => {
    const selected = categorySelect.selectedOptions[0];
    if (selected) {
        weightHidden.value = selected.getAttribute('data-weight') || 0;
        categorySelect.dispatchEvent(new Event('change'));
    }
    updateList();
});
</script>