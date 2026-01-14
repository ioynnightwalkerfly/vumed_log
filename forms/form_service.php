<?php
// forms/form_service.php
// ===== ฟอร์มด้านที่ 3: บริการวิชาการ (ฉบับแก้ไข Variable Conflict) =====

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
    'week_count'      => $_POST['week_count'] ?? ($is_edit ? ($item['week_count'] ?? 0) : 0),
];

// 2. โหลดหมวดหมู่ (✅ เปลี่ยนชื่อตัวแปรเป็น $stmt_cat เพื่อไม่ให้ตีกัน)
$stmt_cat = $conn->prepare("
    SELECT id, code, name_th, weight 
    FROM workload_categories 
    WHERE main_area = 3 AND is_active = 1 AND target_group = 'teacher'
    ORDER BY CAST(SUBSTRING_INDEX(code, '.', 1) AS UNSIGNED) ASC, 
             CAST(SUBSTRING_INDEX(code, '.', -1) AS UNSIGNED) ASC
");
$stmt_cat->execute();
$all_categories = $stmt_cat->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_cat->close(); // ✅ ปิดตัวแปรนี้ทิ้งไปเลย

// --- จัดกลุ่ม Code ---
$codes_course = ['3.1', '3.2']; 
$codes_project = [
    '3.3', '3.4', '3.5', '3.6', '3.7', '3.8', 
    '3.13', '3.14', '3.15', '3.16', 
    '3.18', '3.19', '3.20', 
    '3.23', '3.24', '3.25', '3.26', '3.27', '3.28'
];
$codes_hourly  = ['3.9', '3.10', '3.11', '3.12', '3.17', '3.21'];
$code_advisor = '3.22';

// ===== เมื่อบันทึก (POST) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token.");
    }

    $category_id     = $input['category_id'];
    $attachment_link = trim($input['attachment_link']);
    
    // หา Code และ Weight
    $selected_code = '';
    $weight = 0;
    foreach($all_categories as $c) {
        if ($c['id'] == $category_id) {
            $selected_code = trim((string)$c['code']);
            $weight = floatval($c['weight']);
            break;
        }
    }

    $quantity = 0;
    $computed = 0;
    $description_text = "";
    $title_text = "";
    
    $week_count = floatval($_POST['week_count'] ?? 0);

    // --- CASE A: 3.1 & 3.2 ---
    if (in_array($selected_code, $codes_course, true)) {
        $items = $_POST['items'] ?? [];
        $valid_items = [];
        $total_score = 0;
        
        foreach ($items as $row) {
            $c_name = trim($row['name'] ?? '');
            $qty_theory = floatval($row['theory'] ?? 0);
            $qty_practical = floatval($row['practical'] ?? 0);
            
            if (!empty($c_name) && ($qty_theory > 0 || $qty_practical > 0)) {
                $row_score = ($qty_theory * 10) + ($qty_practical * 20);
                $total_score += $row_score;
                
                $details = [];
                if ($qty_theory > 0) $details[] = "ทฤษฎี: $qty_theory";
                if ($qty_practical > 0) $details[] = "ปฏิบัติ: $qty_practical";
                
                $valid_items[] = [
                    'code' => trim($row['code'] ?? ''),
                    'name' => $c_name,
                    'detail' => implode(", ", $details),
                    'score' => $row_score
                ];
            }
        }
        $quantity = count($valid_items);
        $computed = $total_score;
        
        if (!empty($valid_items)) {
            $description_text = "รายวิชาที่รับผิดชอบ ($quantity วิชา):";
            foreach ($valid_items as $idx => $v) {
                $num = $idx + 1;
                $code_display = !empty($v['code']) ? "[{$v['code']}] " : "";
                $description_text .= "\n{$num}. {$code_display}{$v['name']} ({$v['detail']}) = {$v['score']} คะแนน";
            }
        }
        $title_text = "ภาระงานผู้รับผิดชอบรายวิชา ($quantity วิชา)";
    } 
    // --- CASE B: Project Based ---
    else if (in_array($selected_code, $codes_project, true)) {
        $project_names = $_POST['project_names'] ?? [];
        $project_names = array_filter($project_names, function($value) { return !empty(trim($value)); });
        $quantity = count($project_names);
        
        $computed = $quantity * $weight;
        
        // หน่วยนับ
        $unit_text = "รายการ";
        if (in_array($selected_code, ['3.18', '3.19', '3.20'])) $unit_text = "ฉบับ";
        elseif (in_array($selected_code, ['3.13', '3.14'])) $unit_text = "เล่ม";
        elseif (in_array($selected_code, ['3.15', '3.16'])) $unit_text = "เรื่อง";
        elseif (in_array($selected_code, ['3.24', '3.25'])) $unit_text = "ครั้ง";
        elseif (in_array($selected_code, ['3.26', '3.27', '3.28'])) $unit_text = "คน";
        elseif ($selected_code == '3.23') $unit_text = "กลุ่ม/ชมรม";
        else $unit_text = "โครงการ";

        if (!empty($project_names)) {
            $description_text = "รายชื่อ ($quantity $unit_text):\n- " . implode("\n- ", $project_names);
        }
        $title_text = "ภาระงานบริการวิชาการ ($quantity $unit_text)";
    }
    // --- CASE C: Hourly Based ---
    else if (in_array($selected_code, $codes_hourly, true)) {
        $title_input = trim($_POST['title_input'] ?? '');
        $hours_input = floatval($_POST['hours_input'] ?? 0);
        $location_input = trim($_POST['location_input'] ?? '');
        
        $quantity = $hours_input;
        $computed = $quantity * $weight;
        
        if ($selected_code == '3.12' && $computed > 50) $computed = 50;

        if ($selected_code == '3.21' && !empty($location_input)) {
            $description_text = "สถานที่/หน่วยงาน: " . $location_input . "\n" . $input['description'];
        } else {
            $description_text = $input['description'];
        }

        $title_text = $title_input; 
        
        if (empty($title_input)) $errors[] = "กรุณากรอกหัวข้อ / ชื่อเรื่อง";
        if ($quantity <= 0) $errors[] = "กรุณากรอกจำนวนชั่วโมง";
    }
    // --- CASE D: 3.22 (Advisor) ---
    else if ($selected_code === $code_advisor) {
        $project_names = $_POST['project_names'] ?? [];
        $project_names = array_filter($project_names, function($value) { return !empty(trim($value)); });
        $student_count = count($project_names);
        
        $quantity = $week_count;
        $computed = $week_count * 3; 
        
        if (!empty($project_names)) {
            $description_text = "รายชื่อนักศึกษาในที่ปรึกษา ($student_count คน):\n- " . implode("\n- ", $project_names);
        }
        $title_text = "อาจารย์ที่ปรึกษา ($student_count คน / $week_count สัปดาห์)";
        
        if ($week_count <= 0) $errors[] = "กรุณากรอกจำนวนสัปดาห์";
    }
    // --- Default ---
    else {
        $description_text = $input['description'];
        $title_text = "ภาระงานบริการวิชาการ";
    }

    $status = 'pending';

    if (empty($category_id)) $errors[] = "กรุณาเลือกประเภทภาระงาน";
    if (empty($attachment_link)) $errors[] = "กรุณาระบุลิงก์เอกสารหลักฐาน";
    
    if ($quantity <= 0 && $selected_code !== $code_advisor && !in_array($selected_code, $codes_hourly, true)) {
        if (!in_array($selected_code, $codes_course, true))
             $errors[] = "กรุณากรอกข้อมูลอย่างน้อย 1 รายการ";
    }

    if (empty($errors)) {
        // Upload (ถ้ามี)
        $evidence = $item['evidence'] ?? null;
        if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
            $targetDir = "../uploads/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
            $ext = strtolower(pathinfo($_FILES["evidence"]["name"], PATHINFO_EXTENSION));
            $newFilename = "evd_service_" . $user['id'] . "_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES["evidence"]["tmp_name"], $targetDir . $newFilename)) {
                $evidence = $newFilename;
            }
        }

        // ✅ ใช้ตัวแปรใหม่ $stmt (ไม่ซ้ำกับ $stmt_cat)
        if ($is_edit) {
            
            // Logic แก้ไข
            $sql = "UPDATE workload_items SET category_id=?, title=?, actual_hours=?, computed_hours=?, description=?, evidence=?, attachment_link=?, week_count=?, updated_at=NOW()";
            
            if ($user['role'] === 'admin' || $user['role'] === 'manager') {
                $sql .= " WHERE id=?";
                $stmt = $conn->prepare($sql);
                // Admin: 9 ตัวแปร
                $stmt->bind_param("isddsssii", $category_id, $title_text, $quantity, $computed, $description_text, $evidence, $attachment_link, $week_count, $item['id']);
            } else {
                $sql .= ", status='pending' WHERE id=? AND user_id=?";
                $stmt = $conn->prepare($sql);
                // User: 10 ตัวแปร
                $stmt->bind_param("isddsssiii", $category_id, $title_text, $quantity, $computed, $description_text, $evidence, $attachment_link, $week_count, $item['id'], $user['id']);
            }
            $success_msg = "แก้ไขข้อมูลสำเร็จ";
            
        } else {
            // Logic เพิ่มใหม่
            $stmt = $conn->prepare("
                INSERT INTO workload_items
                (user_id, academic_year, term_id, category_id, title, 
                 actual_hours, computed_hours, description, 
                 evidence, status, attachment_link, week_count)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt === false) {
                die("Prepare failed: " . htmlspecialchars($conn->error));
            }

            // Types: i s i i s d d s s s s i (12 ตัว)
            $stmt->bind_param(
                "isiisddssssi", 
                $user['id'], $academic_year, $term_id, $category_id, $title_text, 
                $quantity, $computed, $description_text, 
                $evidence, $status, $attachment_link, $week_count
            );
            $success_msg = "เพิ่มภาระงานสำเร็จ";
        }

        if ($stmt->execute()) {
            
            // Log
            $target_id = $is_edit ? $item['id'] : $stmt->insert_id;
            $log_action  = $is_edit ? 'update' : 'create';
            $log_comment = $is_edit ? "แก้ไขงานบริการวิชาการ ($title_text)" : "เพิ่มงานบริการวิชาการ ($title_text)";

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

// เตรียมข้อมูลเก่า (ส่วนแสดงผล)
$existing_items = [];
$existing_projects = [];
$existing_title = "";
$existing_hours = 0;
$existing_location = ""; 

if ($is_edit) {
    if (strpos($item['description'], "รายวิชาที่รับผิดชอบ") !== false) {
        $lines = explode("\n", $item['description']);
        foreach ($lines as $line) {
            if (preg_match('/^\d+\.\s*(?:\[(.*?)\]\s*)?(.*?)\s*\((.*?)\)/', $line, $matches)) {
                $details = $matches[3];
                $t_val = 0; $p_val = 0;
                if (preg_match('/ทฤษฎี:\s*(\d+(\.\d+)?)/', $details, $m_t)) $t_val = $m_t[1];
                if (preg_match('/ปฏิบัติ:\s*(\d+(\.\d+)?)/', $details, $m_p)) $p_val = $m_p[1];
                $existing_items[] = ['code' => $matches[1]??'', 'name' => $matches[2]??'', 'theory' => $t_val, 'practical' => $p_val];
            }
        }
    } elseif (strpos($item['description'], "รายชื่อ") !== false || strpos($item['description'], "รายชื่อนักศึกษา") !== false) {
        $lines = explode("\n", $item['description']);
        foreach ($lines as $line) {
            if (strpos($line, "- ") === 0) {
                $existing_projects[] = substr($line, 2);
            }
        }
    } else {
        $existing_title = $item['title'];
        $existing_hours = $item['actual_hours'];
        if (strpos($item['description'], "สถานที่/หน่วยงาน:") !== false) {
            if (preg_match('/สถานที่\/หน่วยงาน:\s*(.*)/', $item['description'], $matches)) {
                $existing_location = trim($matches[1]);
            }
        }
    }
}

if (empty($existing_items)) $existing_items[] = ['code' => '', 'name' => '', 'theory' => '', 'practical' => ''];
if (empty($existing_projects)) $existing_projects[] = "";
?>

<div class="card p-6" style="max-width:1200px; margin:auto;">
    
    <div class="stack-between mb-4 border-bottom pb-4">
        <div>
            <h2 class="mb-0">
                <?= $is_edit ? "แก้ไขข้อมูล (ด้านที่ 3 : บริการวิชาการ)" : "บันทึกภาระงานบริการวิชาการ" ?>
            </h2>
            <div class="stack mt-2">
                <p class="muted mb-0">เลือกประเภทงานและกรอกข้อมูลตามเกณฑ์</p>
                <button type="button" id="openCriteriaBtn" class="btn btn-outline btn-sm">
                    <i class="bi bi-info-circle"></i> ดูเกณฑ์คะแนน
                </button>
            </div>
        </div>
        <div class="text-right">
            <small class="muted">ภาระงานสุทธิ</small>
            <div class="text-primary font-bold" style="font-size:24px;">
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

    <form method="POST" enctype="multipart/form-data" id="serviceForm">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
        <input type="hidden" name="academic_year" value="<?= htmlspecialchars($academic_year) ?>">
        <input type="hidden" name="term_id" value="<?= htmlspecialchars($term_id) ?>">

        <h4 class="text-primary mb-3"><i class="bi bi-bookmark-star"></i> 1. ประเภทภาระงาน</h4>
        <div class="grid grid-2 mb-6" style="gap:20px;">
            <div class="full" style="grid-column: span 2;">
                <label>เลือกประเภท <span class="text-danger">*</span></label>
                <select name="category_id" id="categorySelect" required class="bg-muted">
                    <option value="">-- กรุณาเลือก --</option>
                    <?php foreach($all_categories as $c): ?>
                        <?php 
                            $displayName = htmlspecialchars($c['code']." : ".$c['name_th']);
                            $catCode = trim((string)$c['code']);
                            if($catCode === '3.1') $displayName = "3.1+3.2 ผู้รับผิดชอบรายวิชาที่ไม่เป็นผู้สอน (ทฤษฎี/ปฏิบัติ)";
                            if($catCode === '3.2') continue; 
                        ?>
                        <option value="<?= $c['id']; ?>" 
                            data-code="<?= $catCode; ?>"
                            data-weight="<?= $c['weight']; ?>"
                            <?= ($c['id'] == $input['category_id']) ? 'selected' : '' ?>>
                            <?= $displayName; ?> 
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="full" style="grid-column: span 2;">
                <label class="text-primary" style="font-size:16px;">
                    <i class="bi bi-link-45deg"></i> ลิงก์หลักฐาน (Google Drive) <span class="text-danger">*</span>
                </label>
                <input type="url" name="attachment_link" class="w-full" style="font-size:16px; padding:12px;" 
                       placeholder="วางลิงก์ที่นี่ (จำเป็นต้องระบุ)" 
                       value="<?= htmlspecialchars($input['attachment_link']) ?>" required>
                <p class="input-help mt-1" id="evidenceHint">เอกสาร/คำสั่ง/รูปภาพประกอบ</p>
            </div>
        </div>

        <hr class="mb-6 opacity-50">

        <h4 class="text-primary mb-3"><i class="bi bi-list-check"></i> <span id="sectionTitle">2. รายละเอียดภาระงาน</span></h4>
        
        <div id="courseSection" style="display:none;">
            <div class="alert info mb-4">
                <i class="bi bi-info-circle"></i> <strong>วิธีคิดคะแนน:</strong> (จำนวนทฤษฎี x 10) + (จำนวนปฏิบัติ x 20)
            </div>
            <div class="grid grid-2 mb-2 bg-muted p-2 rounded" style="grid-template-columns: 1fr 2fr 100px 100px 40px; gap:10px; font-weight:bold;">
                <div>รหัสวิชา</div><div>ชื่อรายวิชา</div><div class="text-center">ทฤษฎี (10)</div><div class="text-center">ปฏิบัติ (20)</div><div></div>
            </div>
            <div id="itemListContainer" class="mb-3">
                <?php foreach($existing_items as $idx => $v): ?>
                    <div class="item-row grid grid-2 mb-2 p-2 border-bottom" style="grid-template-columns: 1fr 2fr 100px 100px 40px; gap:10px; align-items:center;">
                        <input type="text" name="items[<?= $idx ?>][code]" class="w-full" placeholder="รหัสวิชา" value="<?= htmlspecialchars($v['code']) ?>">
                        <input type="text" name="items[<?= $idx ?>][name]" class="w-full" placeholder="ชื่อรายวิชา" value="<?= htmlspecialchars($v['name']) ?>">
                        <div class="text-center"><input type="number" step="1" min="0" name="items[<?= $idx ?>][theory]" class="text-center w-full score-input border-primary" placeholder="0" value="<?= $v['theory'] > 0 ? $v['theory'] : '' ?>"></div>
                        <div class="text-center"><input type="number" step="1" min="0" name="items[<?= $idx ?>][practical]" class="text-center w-full score-input border-primary" placeholder="0" value="<?= $v['practical'] > 0 ? $v['practical'] : '' ?>"></div>
                        <button type="button" class="btn btn-sm btn-outline btn-danger remove-item-btn" tabindex="-1"><i class="bi bi-trash"></i></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="addItemBtn" class="btn btn-sm btn-outline mb-4"><i class="bi bi-plus-lg"></i> เพิ่มรายวิชา</button>
        </div>

        <div id="projectSection" style="display:none;">
            <div class="alert info mb-4">
                <i class="bi bi-info-circle"></i> <strong id="projectCriteriaLabel">เกณฑ์:</strong> คิดภาระงานต่อ 1 รายการ
            </div>
            <div id="projectListContainer" class="mb-3">
                <?php foreach($existing_projects as $p_name): ?>
                    <div class="project-item stack mb-2">
                        <span class="badge pending row-num" style="width:30px; justify-content:center;">1</span>
                        <input type="text" name="project_names[]" class="project-name-input w-full" 
                               placeholder="ระบุชื่อโครงการ / กิจกรรม" value="<?= htmlspecialchars($p_name) ?>">
                        <button type="button" class="btn btn-sm btn-outline btn-danger remove-project-btn" tabindex="-1"><i class="bi bi-trash"></i></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="stack-between">
                <button type="button" id="addProjectBtn" class="btn btn-sm btn-outline mb-4"><i class="bi bi-plus-lg"></i> เพิ่มรายการ</button>
                <div class="text-right">
                    <span class="muted" id="projectUnitLabel">จำนวน: </span>
                    <span id="projectCountDisplay" class="font-bold">0</span>
                </div>
            </div>
            <input type="hidden" id="projectCountInput" value="0">
        </div>

        <div id="hourlySection" style="display:none;">
            <div class="alert info mb-4">
                <i class="bi bi-clock"></i> <strong id="hourlyCriteriaLabel">เกณฑ์:</strong> คิดภาระงานตามชั่วโมงปฏิบัติจริง
            </div>
            <div class="grid grid-2 mb-6" style="gap:20px;">
                <div class="full" style="grid-column: span 2;">
                    <label id="hourlyTitleLabel">หัวข้อการบรรยาย / งานที่ปฏิบัติ <span class="text-danger">*</span></label>
                    <input type="text" name="title_input" class="w-full" placeholder="ระบุรายละเอียด" value="<?= htmlspecialchars($existing_title) ?>">
                </div>
                <div class="full" id="locationDiv" style="display:none; grid-column: span 2;">
                    <label>สถานที่ / หน่วยงาน <span class="text-danger">*</span></label>
                    <input type="text" name="location_input" class="w-full" placeholder="ระบุสถานที่" value="<?= htmlspecialchars($existing_location) ?>">
                </div>
                <div>
                    <label>จำนวนชั่วโมง (ชม.) <span class="text-danger">*</span></label>
                    <input type="number" step="0.5" min="0" name="hours_input" id="hoursInput" class="text-center font-bold border-primary" 
                           style="font-size:20px;" placeholder="0" value="<?= $existing_hours > 0 ? $existing_hours : '' ?>">
                </div>
                <div class="text-center p-4 bg-muted rounded">
                    <small>คะแนนที่ได้</small>
                    <div class="text-primary font-bold" style="font-size:28px;">
                        <span id="hourlyComputed">0.00</span>
                    </div>
                    <div id="capWarning" class="text-danger text-xs mt-1" style="display:none;">(จำกัดสูงสุด 50 คะแนน)</div>
                </div>
            </div>
        </div>

        <div id="studentAdvisorSection" style="display:none;">
            <div class="alert info mb-4">
                <i class="bi bi-people-fill"></i> <strong>เกณฑ์ 3.22:</strong> 3 คะแนน ต่อ สัปดาห์
            </div>
            <div class="grid grid-2 mb-6">
                <div>
                    <label>จำนวนสัปดาห์ตลอดปีการศึกษา <span class="text-danger">*</span></label>
                    <input type="number" name="week_count" id="weekCount" class="text-center font-bold border-primary" 
                           style="font-size:20px;" placeholder="0" value="<?= $input['week_count'] > 0 ? $input['week_count'] : '' ?>">
                </div>
                <div class="text-center p-4 bg-muted rounded">
                    <small>คะแนนรวม (สัปดาห์ x 3)</small>
                    <div class="text-primary font-bold" style="font-size:28px;">
                        <span id="advisorComputed">0.00</span>
                    </div>
                </div>
            </div>
            <label class="block mb-2 font-bold">รายชื่อนักศึกษาในที่ปรึกษา (เพื่อเป็นหลักฐาน)</label>
            <div id="advisorListContainer"></div> 
            <button type="button" id="addStudentBtn" class="btn btn-sm btn-outline mb-4"><i class="bi bi-plus-lg"></i> เพิ่มรายชื่อนักศึกษา</button>
        </div>

        <hr class="mb-6 opacity-50">

        <div class="mb-6">
            <label class="muted" style="font-size:14px;">แนบไฟล์สำรอง (ถ้ามี)</label>
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
        <span class="close" id="closeCriteriaModal" style="float:right; cursor:pointer;">&times;</span>
        <h3 class="mb-3 text-primary">เกณฑ์ภาระงานด้านที่ 3</h3>
        <div class="table-card border rounded" style="max-height:60vh; overflow-y:auto;">
            <table class="table table-sm">
                <thead class="bg-muted">
                    <tr><th>หมวด</th><th>รายละเอียด</th><th class="text-right">คะแนน</th></tr>
                </thead>
                <tbody>
                    <tr><td>3.1/3.2</td><td>รับผิดชอบวิชา (ทฤษฎี/ปฏิบัติ)</td><td class="text-right">10/20 ต่อวิชา</td></tr>
                    <tr><td>3.3-3.8</td><td>จัดอบรม/ประชุมวิชาการ</td><td class="text-right">10-100 ต่อโครงการ</td></tr>
                    <tr><td>3.9-3.11</td><td>วิทยากร (ใน/นอก/ตปท.)</td><td class="text-right">4/5/15 ต่อชม.</td></tr>
                    <tr><td>3.12</td><td>บริการชุมชน</td><td class="text-right">ตามจริง (Max 50)</td></tr>
                    <tr><td>3.13-16</td><td>Reviewer</td><td class="text-right">10-60 ต่อเล่ม/เรื่อง</td></tr>
                    <tr><td>3.18-20</td><td>งานวารสาร</td><td class="text-right">10-50 ต่อฉบับ</td></tr>
                    <tr><td>3.21</td><td>กรรมการที่ปรึกษาภายนอก</td><td class="text-right">ตามจริง</td></tr>
                    <tr><td>3.22</td><td>ที่ปรึกษา นศ. (บ้าน)</td><td class="text-right">3 ต่อสัปดาห์</td></tr>
                    <tr><td>3.23</td><td>ที่ปรึกษาชมรม/ชั้นปี</td><td class="text-right">20 ต่อปี</td></tr>
                    <tr><td>3.24/25</td><td>สอบวิทยานิพนธ์ (ปธ/กก)</td><td class="text-right">15/10 ต่อครั้ง</td></tr>
                    <tr><td>3.26-28</td><td>อ่านผลงาน (ผศ./รศ./ศ.)</td><td class="text-right">30/50/100 ต่อคน</td></tr>
                </tbody>
            </table>
        </div>
        <div class="text-right mt-3"><button class="btn btn-muted" id="closeCriteriaBtn">ปิดหน้าต่าง</button></div>
    </div>
</div>

<script>
// Modal Logic
const modal = document.getElementById("criteriaModal");
if(document.getElementById("openCriteriaBtn")) {
    document.getElementById("openCriteriaBtn").onclick = () => modal.classList.add("show");
    document.getElementById("closeCriteriaBtn").onclick = () => modal.classList.remove("show");
    document.getElementById("closeCriteriaModal").onclick = () => modal.classList.remove("show");
    modal.onclick = (e) => { if(e.target === modal) modal.classList.remove("show"); };
}

// Elements
const categorySelect = document.getElementById('categorySelect');
const sectionTitle = document.getElementById('sectionTitle');
const grandTotalDisplayTop = document.getElementById('grandTotalDisplayTop');
const evidenceHint = document.getElementById('evidenceHint');

// Sections
const courseSection = document.getElementById('courseSection');
const projectSection = document.getElementById('projectSection');
const hourlySection = document.getElementById('hourlySection');
const studentAdvisorSection = document.getElementById('studentAdvisorSection');
const locationDiv = document.getElementById('locationDiv');

// Labels
const hourlyTitleLabel = document.getElementById('hourlyTitleLabel');
const projectCriteriaLabel = document.getElementById('projectCriteriaLabel');
const hourlyCriteriaLabel = document.getElementById('hourlyCriteriaLabel');
const projectUnitLabel = document.getElementById('projectUnitLabel');
const capWarning = document.getElementById('capWarning');

// Groups
const codesProject = [
    '3.3', '3.4', '3.5', '3.6', '3.7', '3.8', 
    '3.13', '3.14', '3.15', '3.16', '3.18', '3.19', '3.20', 
    '3.23', '3.24', '3.25', '3.26', '3.27', '3.28'
];
const codesHourly = ['3.9', '3.10', '3.11', '3.12', '3.17', '3.21'];
const codesCourse = ['3.1', '3.2'];
const codeAdvisor = '3.22';

let currentWeight = 0;

// Variables A (Course)
const itemListContainer = document.getElementById('itemListContainer');
const addItemBtn = document.getElementById('addItemBtn');
let itemIndex = <?= count($existing_items) ?>;

// Variables B (Project)
const projectListContainer = document.getElementById('projectListContainer');
const addProjectBtn = document.getElementById('addProjectBtn');
const projectCountInput = document.getElementById('projectCountInput');
const projectCountDisplay = document.getElementById('projectCountDisplay');

// Variables C (Hourly)
const hoursInput = document.getElementById('hoursInput');
const hourlyComputed = document.getElementById('hourlyComputed');

// Variables D (Student Advisor)
const weekCount = document.getElementById('weekCount');
const advisorComputed = document.getElementById('advisorComputed');
const studentListArea = document.getElementById('advisorListContainer'); // แก้เป็น advisorListContainer
const addStudentBtn = document.getElementById('addStudentBtn');

// --- MAIN CALCULATION ---
function calculate() {
    const selected = categorySelect.selectedOptions[0];
    const code = selected ? selected.getAttribute('data-code') : '';
    currentWeight = parseFloat(selected ? selected.getAttribute('data-weight') : 0);
    
    let total = 0;

    if (codesCourse.includes(code)) {
        const rows = itemListContainer.querySelectorAll('.item-row');
        rows.forEach(row => {
            const theoryInput = row.querySelector('input[name*="[theory]"]');
            const pracInput = row.querySelector('input[name*="[practical]"]');
            const t = parseFloat(theoryInput.value) || 0;
            const p = parseFloat(pracInput.value) || 0;
            total += (t * 10) + (p * 20);
        });
    } else if (codesProject.includes(code)) {
        const count = parseInt(projectCountInput.value) || 0;
        total = count * currentWeight;
    } else if (codesHourly.includes(code)) {
        const h = parseFloat(hoursInput.value) || 0;
        total = h * currentWeight;
        if (code === '3.12' && total > 50) { total = 50; capWarning.style.display = 'block'; } 
        else { capWarning.style.display = 'none'; }
        hourlyComputed.innerText = total.toFixed(2);
    } else if (code === codeAdvisor) {
        const w = parseFloat(weekCount.value) || 0;
        total = w * 3;
        advisorComputed.innerText = total.toFixed(2);
    }
    
    grandTotalDisplayTop.innerText = total.toFixed(2);
}

// --- UI SWITCHING ---
categorySelect.addEventListener('change', () => {
    const selected = categorySelect.selectedOptions[0];
    const code = selected ? selected.getAttribute('data-code') : '';
    const weight = selected ? selected.getAttribute('data-weight') : 0;

    courseSection.style.display = 'none';
    projectSection.style.display = 'none';
    hourlySection.style.display = 'none';
    studentAdvisorSection.style.display = 'none';
    locationDiv.style.display = 'none';
    
    evidenceHint.innerText = 'เอกสาร/คำสั่ง/รูปภาพประกอบ';
    hourlyTitleLabel.innerHTML = 'หัวข้อการบรรยาย / งานที่ปฏิบัติ <span class="text-danger">*</span>';
    capWarning.style.display = 'none';
    
    let projUnit = "รายการ";
    let projPlaceholder = "ระบุชื่อโครงการ / กิจกรรม";

    if (codesProject.includes(code)) {
        projectSection.style.display = 'block';
        
        if (['3.13', '3.14'].includes(code)) {
            projUnit = "เล่ม"; projPlaceholder = "ชื่อหนังสือ/ตำรา"; sectionTitle.innerText = "2. รายชื่อผลงานที่ Review";
            evidenceHint.innerText = "หนังสือเชิญ (VU Mail เท่านั้น)";
        } else if (['3.15', '3.16'].includes(code)) {
            projUnit = "เรื่อง"; projPlaceholder = "ชื่อบทความ"; sectionTitle.innerText = "2. รายชื่อบทความที่ Review";
            evidenceHint.innerText = "หนังสือเชิญ (VU Mail เท่านั้น)";
        } else if (['3.18', '3.19', '3.20'].includes(code)) {
            projUnit = "ฉบับ"; projPlaceholder = "ระบุชื่อวารสาร"; sectionTitle.innerText = "2. รายชื่อวารสาร";
        } else if (code === '3.23') {
            projUnit = "กลุ่ม"; projPlaceholder = "ชื่อสโมสร / ชมรม / ชั้นปี"; sectionTitle.innerText = "2. รายชื่อกลุ่มกิจกรรม";
        } else if (['3.24', '3.25'].includes(code)) {
            projUnit = "ครั้ง"; projPlaceholder = "ชื่อหัวข้อวิทยานิพนธ์ / มหาวิทยาลัย"; sectionTitle.innerText = "2. รายละเอียดการสอบ";
        } else if (['3.26', '3.27', '3.28'].includes(code)) {
            projUnit = "คน"; projPlaceholder = "ชื่อผู้ขอตำแหน่ง / ชื่อผลงาน"; sectionTitle.innerText = "2. รายชื่อผู้ขอตำแหน่ง";
        } else {
            sectionTitle.innerText = "2. รายชื่อโครงการ / กิจกรรม";
        }

        projectCriteriaLabel.innerText = `เกณฑ์: ${weight} คะแนน ต่อ 1 ${projUnit}`;
        projectUnitLabel.innerText = `จำนวน${projUnit}: `;
        
        const inputs = projectListContainer.querySelectorAll('input[type="text"]');
        inputs.forEach(input => input.placeholder = projPlaceholder);
        updateProjectList();

    } else if (codesHourly.includes(code)) {
        hourlySection.style.display = 'block';
        sectionTitle.innerText = "2. รายละเอียดการปฏิบัติงาน";
        hourlyCriteriaLabel.innerText = `เกณฑ์: ${weight} คะแนน ต่อ 1 ชั่วโมง`;
        
        if (['3.9', '3.10', '3.11'].includes(code)) {
            hourlyTitleLabel.innerHTML = 'ชื่อเรื่อง / กิจกรรม / หัวข้อการบรรยาย <span class="text-danger">*</span>';
            evidenceHint.innerText = 'หนังสือเชิญ / ภาพการจัดกิจกรรม';
        } else if (code === '3.12') {
            hourlyTitleLabel.innerHTML = 'ชื่อโครงการ / กิจกรรมที่ให้บริการ <span class="text-danger">*</span>';
            evidenceHint.innerText = 'เล่มโครงการ / ภาพกิจกรรม';
            hourlyCriteriaLabel.innerText = `เกณฑ์: ตามจริง (สูงสุด 50 คะแนน)`;
        } else if (code === '3.17') {
            hourlyTitleLabel.innerHTML = 'ชื่อโครงการ / หัวข้อการบรรยาย / กิจกรรม <span class="text-danger">*</span>';
        } else if (code === '3.21') {
            hourlyTitleLabel.innerHTML = 'ชื่อผลงาน <span class="text-danger">*</span>';
            locationDiv.style.display = 'block';
            evidenceHint.innerText = 'หนังสือแต่งตั้ง / รายงานการประชุม';
        }
        calculate();

    } else if (code === codeAdvisor) {
        studentAdvisorSection.style.display = 'block';
        sectionTitle.innerText = "2. รายละเอียดการเป็นที่ปรึกษา";
        // ย้ายรายการเดิมมาใส่ถ้ามี
        if (studentListArea.innerHTML === '' && projectListContainer.children.length > 0) {
             while(projectListContainer.children.length > 0) {
                 studentListArea.appendChild(projectListContainer.children[0]);
             }
        }
        const inputs = studentListArea.querySelectorAll('input[type="text"]');
        inputs.forEach(input => input.placeholder = "ชื่อ-สกุล นักศึกษา");
        calculate();

    } else {
        courseSection.style.display = 'block';
        sectionTitle.innerText = "2. รายวิชาที่รับผิดชอบ";
        calculate();
    }
});

// --- HELPER FUNCTIONS ---
// Course List
addItemBtn.addEventListener('click', () => {
    itemIndex++;
    const div = document.createElement('div');
    div.className = 'item-row grid grid-2 mb-2 p-2 border-bottom';
    div.style.gridTemplateColumns = '1fr 2fr 100px 100px 40px';
    div.style.gap = '10px'; div.style.alignItems = 'center';
    div.innerHTML = `
        <input type="text" name="items[${itemIndex}][code]" class="w-full" placeholder="รหัสวิชา">
        <input type="text" name="items[${itemIndex}][name]" class="w-full" placeholder="ชื่อรายวิชา" required>
        <div class="text-center"><input type="number" step="1" min="0" name="items[${itemIndex}][theory]" class="text-center w-full score-input border-primary" placeholder="0"></div>
        <div class="text-center"><input type="number" step="1" min="0" name="items[${itemIndex}][practical]" class="text-center w-full score-input border-primary" placeholder="0"></div>
        <button type="button" class="btn btn-sm btn-outline btn-danger remove-item-btn" tabindex="-1"><i class="bi bi-trash"></i></button>
    `;
    itemListContainer.appendChild(div);
});
itemListContainer.addEventListener('click', (e) => {
    if (e.target.closest('.remove-item-btn')) {
        if (itemListContainer.querySelectorAll('.item-row').length > 1) e.target.closest('.item-row').remove();
        else e.target.closest('.item-row').querySelectorAll('input').forEach(i => i.value = '');
        calculate();
    }
});
itemListContainer.addEventListener('input', (e) => { if (e.target.classList.contains('score-input')) calculate(); });

// Project List Helper
function updateProjectList() {
    const items = projectListContainer.querySelectorAll('.project-item');
    items.forEach((item, index) => item.querySelector('.row-num').innerText = index + 1);
    const count = items.length;
    projectCountInput.value = count;
    projectCountDisplay.innerText = count;
    calculate();
}

function createProjectRow() {
    const div = document.createElement('div');
    div.className = 'project-item stack mb-2';
    div.innerHTML = `
        <span class="badge pending row-num" style="width:30px; justify-content:center;"></span>
        <input type="text" name="project_names[]" class="project-name-input w-full" placeholder="ระบุรายการ">
        <button type="button" class="btn btn-sm btn-outline btn-danger remove-project-btn" tabindex="-1"><i class="bi bi-trash"></i></button>
    `;
    return div;
}

// Add Buttons
addProjectBtn.addEventListener('click', () => {
    projectListContainer.appendChild(createProjectRow());
    updateProjectList();
    categorySelect.dispatchEvent(new Event('change')); // Refresh placeholder
});

addStudentBtn.addEventListener('click', () => {
    studentListArea.appendChild(createProjectRow());
    // Manual Update for students
    const items = studentListArea.querySelectorAll('.project-item');
    items.forEach((item, index) => item.querySelector('.row-num').innerText = index + 1);
    const inputs = studentListArea.querySelectorAll('input[type="text"]');
    inputs.forEach(input => input.placeholder = "ชื่อ-สกุล นักศึกษา");
});

// Remove Logic (Shared)
const handleRemove = (e, container, updateFunc) => {
    if (e.target.closest('.remove-project-btn')) {
        if (container.querySelectorAll('.project-item').length > 1) e.target.closest('.project-item').remove();
        else e.target.closest('.project-item').querySelector('input').value = '';
        if(updateFunc) updateFunc();
    }
};

projectListContainer.addEventListener('click', (e) => handleRemove(e, projectListContainer, updateProjectList));
studentListArea.addEventListener('click', (e) => {
    handleRemove(e, studentListArea, () => {
         const items = studentListArea.querySelectorAll('.project-item');
         items.forEach((item, index) => item.querySelector('.row-num').innerText = index + 1);
    });
});

// Hourly Inputs
hoursInput.addEventListener('input', calculate);
weekCount.addEventListener('input', calculate);

document.addEventListener('DOMContentLoaded', () => {
    categorySelect.dispatchEvent(new Event('change'));
});
</script>