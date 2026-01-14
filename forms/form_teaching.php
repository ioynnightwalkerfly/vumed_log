<?php
// forms/form_teaching.php
// ===== ฟอร์มด้านที่ 1: ภาระงานสอน 

// รับค่าจากหน้าหลัก (Parent Script) หรือค่า Default
$academic_year = $academic_year ?? (date("Y") + 543);
$term_id = $term_no ?? ($term_id ?? 1); 
$is_edit = $is_edit ?? false;

// --- Config ข้อมูลตัวเลือกใหม่ 
// 1.6 Modular Roles
$modular_roles = [
    'chairman' => ['name' => 'ประธาน', 'hours' => 15],
    'vice_secretary' => ['name' => 'รองประธานและเลขานุการ', 'hours' => 15],
    'member' => ['name' => 'กรรมการ', 'hours' => 10]
];
// 1.7 CLC Roles (ประธาน 2, รอง 1, กรรมการ 2)
$clc_roles = [
    'chairman' => ['name' => 'ประธาน', 'hours' => 2],
    'vice_secretary' => ['name' => 'เลขานุการ', 'hours' => 2],
    'member' => ['name' => 'กรรมการ', 'hours' => 1]
];
// 1.8 Exam Committee Types
$exam_types = [
    'MCQ' => 'MCQ',
    'Short Essay' => 'Short Essay',
    'Lab' => 'ปฏิบัติการ'
];

// 1. เตรียมตัวแปร Input (เหมือนเดิม)
$errors = [];
$input = [
    'category_id'     => $_POST['category_id'] ?? ($is_edit ? $item['category_id'] : null),
    'course_code'     => $_POST['course_code'] ?? ($is_edit ? ($item['course_code'] ?? '') : ''),
    'title'           => $_POST['title'] ?? ($is_edit ? $item['title'] : ''),
    'attachment_link' => $_POST['attachment_link'] ?? ($is_edit ? ($item['attachment_link'] ?? '') : ''),
    
    // ตัวแปรคำนวณ
    'hours_lec'     => $_POST['hours_lec'] ?? ($is_edit ? ($item['hours_lec'] ?? 0) : 0),
    'hours_lab'     => $_POST['hours_lab'] ?? ($is_edit ? ($item['hours_lab'] ?? 0) : 0),
    'week_count'    => $_POST['week_count'] ?? ($is_edit ? ($item['week_count'] ?? '') : ''),
    'project_count' => $_POST['project_count'] ?? ($is_edit ? ($item['project_count'] ?? 0) : 0),
    'description'   => $_POST['description'] ?? ($is_edit ? $item['description'] : ''),
];

// โหลดหมวดหมู่
$stmt = $conn->prepare("
    SELECT id, code, name_th, weight 
    FROM workload_categories 
    WHERE main_area = 1 AND is_active = 1 AND target_group = 'teacher'
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
    $course_code     = trim($input['course_code']);
    $title           = trim($input['title']);
    $attachment_link = trim($input['attachment_link']);
    
    $hours_lec    = floatval($input['hours_lec']);
    $hours_lab    = floatval($input['hours_lab']);
    $week_count   = floatval($input['week_count']);
    
    // 1.3 Logic (Project Lists) - โค้ดเดิมรักษาไว้
    $project_titles = $_POST['project_titles'] ?? [];
    $project_students = $_POST['project_students'] ?? [];
    $projects_data = [];
    for ($i = 0; $i < count($project_titles); $i++) {
        $p_name = trim($project_titles[$i] ?? '');
        $s_name = trim($project_students[$i] ?? '');
        if (!empty($p_name)) {
            $projects_data[] = ['title' => $p_name, 'student' => $s_name];
        }
    }
    $project_count = count($projects_data);
    
    // Description Generation
    $description_text = $input['description'];
    if (!empty($projects_data)) {
        $description_text = "รายชื่อโครงงาน ($project_count เรื่อง):";
        foreach ($projects_data as $index => $proj) {
            $num = $index + 1;
            $description_text .= "\n{$num}. เรื่อง: {$proj['title']} (นศ: {$proj['student']})";
        }
    }

    $status = 'pending';

    // หา Code ของ Category ที่เลือก
    $selectedCatCode = '';
    foreach($all_categories as $cat) {
        if($cat['id'] == $category_id) {
            $selectedCatCode = trim($cat['code']);
            break;
        }
    }

    // Validation
    if (empty($category_id)) $errors[] = "กรุณาเลือกประเภทภาระงาน";
    
    if ($selectedCatCode == '1.3') {
        if (empty($title)) $title = "อาจารย์ที่ปรึกษาโครงงาน ($project_count เรื่อง)";
    } else {
        // 1.7 และ 1.8 ให้ระบุชื่อเรื่อง/วิชาในช่อง Title ปกติ
        if (empty($title)) $errors[] = "กรุณากรอกชื่อวิชา / เรื่อง";
    }
    
    if (empty($attachment_link)) $errors[] = "กรุณาระบุลิงก์เอกสารอ้างอิง";

    // --- Logic คำนวณคะแนน  ---
    $computed = 0;
    $actual_total = 0;

    if ($selectedCatCode == '1.3') {
        // 1.3 ที่ปรึกษาโครงงาน 
        $weight = 1.5; 
        $computed = $project_count * $week_count * $weight;
        $actual_total = 0; 
        if ($project_count <= 0) $errors[] = "กรุณากรอกรายชื่อโครงงานอย่างน้อย 1 เรื่อง";

    } else if ($selectedCatCode == '1.6') {
        //  1.6 Modular System
        $role_key = $_POST['modular_role'] ?? '';
        $credits = floatval($_POST['credits_input'] ?? 0);
        $role_hours = $modular_roles[$role_key]['hours'] ?? 0;
        
        $computed = $role_hours * $credits;
        $actual_total = $credits;
        
        $role_name = $modular_roles[$role_key]['name'] ?? '-';
        $title = $title . " (Modular: $role_name)";
        $description_text = "หน่วยกิต: $credits, บทบาท: $role_name\n" . $description_text;

    } else if ($selectedCatCode == '1.7') {
        //  1.7 CLC
        $role_key = $_POST['clc_role'] ?? '';
        $clc_name = trim($_POST['clc_name'] ?? '');
        $role_hours = $clc_roles[$role_key]['hours'] ?? 0;
        
        $computed = $role_hours;
        $actual_total = $role_hours;
        
        $role_name = $clc_roles[$role_key]['name'] ?? '-';
        $title = "CLC: $clc_name - รายวิชา: $title ($role_name)";
        $description_text = "ชื่อ CLC: $clc_name, ตำแหน่ง: $role_name\n" . $description_text;

    } else if ($selectedCatCode == '1.8') {
        //  1.8 คุมสอบ
        $exam_type = $_POST['exam_type'] ?? '';
        $hours_comm = floatval($_POST['hours_exam_comm'] ?? 0);
        
        $computed = $hours_comm; // ชั่วโมงจริง
        $actual_total = $hours_comm;
        
        $title = "คุมสอบ ($exam_type): " . $title;
        $description_text = "รูปแบบ: $exam_type, ชั่วโมงจริง: $hours_comm\n" . $description_text;

    } else if (in_array($selectedCatCode, ['1.4', '1.5'])) {
        // 1.4-1.5 (สอบ/นิเทศ - เดิม)
        $weight = 1.5; 
        $computed = $hours_lec * $weight;
        $actual_total = $hours_lec;
        if ($hours_lec <= 0) $errors[] = "กรุณากรอกจำนวนชั่วโมง";

    } else {
        // 1.1, 1.2 และ 
        $weight_lec = 3.0;
        $weight_lab = 1.5;
        $computed = ($hours_lec * $weight_lec) + ($hours_lab * $weight_lab);
        $actual_total = $hours_lec + $hours_lab; 
        
        if ($selectedCatCode == '1.9') {
            $title = $title . " (สอบซ่อม/ไม่ผ่านเกณฑ์)";
        }
    }

    // บันทึกข้อมูล 
    if (empty($errors)) {
        if ($is_edit) {
            // UPDATE
            $sql_update = "UPDATE workload_items SET category_id=?, course_code=?, title=?, hours_lec=?, hours_lab=?, actual_hours=?, computed_hours=?, description=?, project_count=?, week_count=?, attachment_link=?, updated_at=NOW()";
            
            if ($user['role'] === 'admin' || $user['role'] === 'manager') {
                $sql_update .= " WHERE id=?";
                $stmt = $conn->prepare($sql_update);
                $stmt->bind_param("issddddsiisi", $category_id, $course_code, $title, $hours_lec, $hours_lab, $actual_total, $computed, $description_text, $project_count, $week_count, $attachment_link, $item['id']);
            } else {
                $sql_update .= ", status='pending' WHERE id=? AND user_id=?";
                $stmt = $conn->prepare($sql_update);
                $stmt->bind_param("issddddsiisii", $category_id, $course_code, $title, $hours_lec, $hours_lab, $actual_total, $computed, $description_text, $project_count, $week_count, $attachment_link, $item['id'], $user['id']);
            }
            $success_msg = "แก้ไขข้อมูลสำเร็จ";
            
        } else {
            // INSERT
            $stmt = $conn->prepare("
                INSERT INTO workload_items
                (user_id, academic_year, term_id, category_id, course_code, title, 
                 hours_lec, hours_lab, actual_hours, computed_hours, 
                 description, status, project_count, week_count, attachment_link)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param(
                "isiissddddssiis",
                $user['id'], $academic_year, $term_id, $category_id, $course_code, $title, 
                $hours_lec, $hours_lab, $actual_total, $computed,
                $description_text, $status,
                $project_count, $week_count, $attachment_link
            );
            $success_msg = "เพิ่มภาระงานสำเร็จ";
        }

        if ($stmt->execute()) {
            // Log
            $target_id = $is_edit ? $item['id'] : $stmt->insert_id;
            $log_action  = $is_edit ? 'update' : 'create';
            $log_comment = $is_edit ? "แก้ไขงานสอน ($course_code)" : "เพิ่มงานสอน ($course_code)";

            $stmt_log = $conn->prepare("INSERT INTO workload_logs (work_log_id, user_id, action, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt_log->bind_param("iiss", $target_id, $user['id'], $log_action, $log_comment);
            $stmt_log->execute();
            
            echo "<script>window.location.href = 'workloads.php?success=" . urlencode($success_msg) . "';</script>";
            exit;
        } else {
            $errors[] = "Database Error: " . htmlspecialchars($stmt->error);
        }
    }
}

// เตรียมข้อมูลเก่า 1.3 
$existing_projects_data = [];
if ($is_edit && !empty($item['description']) && strpos($item['description'], "รายชื่อโครงงาน") !== false) {
    $lines = explode("\n", $item['description']);
    foreach ($lines as $line) {
        if (preg_match('/เรื่อง:\s*(.+?)\s*\(นศ:\s*(.+?)\)/', $line, $matches)) {
            $existing_projects_data[] = ['title' => $matches[1], 'student' => $matches[2]];
        }
    }
}
if (empty($existing_projects_data)) $existing_projects_data[] = ['title' => '', 'student' => ''];
?>

<style>
    /* บังคับเต็มจอ เหมือนเดิม */
    .app, .main, .container, main {
        max-width: 100vw !important; width: 100% !important; margin: 0 !important;
        padding-left: 10px !important; padding-right: 10px !important;
    }
    .card {
        max-width: 100% !important; width: 100% !important; margin: 15px 0 !important;
        padding: 30px !important; border-radius: 12px;
    }
    body { font-size: 16px !important; }
    input, select, textarea { font-size: 1.2rem !important; padding: 14px 18px !important; }
    label { font-size: 1.25rem !important; font-weight: bold !important; margin-bottom: 12px !important; }
    .btn { font-size: 1.2rem !important; padding: 12px 30px !important; }
    
    /* สีพื้นหลังสำหรับฟิลด์ใหม่ */
    .bg-modular { background-color: #f0f9ff; border-left: 5px solid #0ea5e9; }
    .bg-clc { background-color: #f0fdf4; border-left: 5px solid #22c55e; }
    .bg-examcomm { background-color: #fff7ed; border-left: 5px solid #f97316; }
</style>

<div class="card">
    
    <div class="stack-between mb-4 border-bottom pb-4">
        <div>
            <h2 class="mb-0">
                <?= $is_edit ? "แก้ไขข้อมูล (ด้านที่ 1 : ภาระงานสอน)" : "บันทึกภาระงานสอน" ?>
            </h2>
            <div class="stack mt-2">
                <p class="muted mb-0" style="font-size:1.1rem;">กรอกข้อมูลรายวิชาและคำนวณภาระงาน</p>
                <button type="button" id="openCriteriaBtn" class="btn btn-outline btn-sm">
                    <i class="bi bi-info-circle"></i> ดูเกณฑ์การคิดคะแนน
                </button>
            </div>
        </div>
        <div class="text-right">
            <small class="muted" style="font-size:1rem;">ภาระงานสุทธิ</small>
            <div class="text-primary font-bold" style="font-size:32px;">
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

        <h4 class="text-primary mb-3"><i class="bi bi-journal-bookmark"></i> 1. ข้อมูลภาระงาน</h4>
        
        <div class="grid grid-2 mb-6">
            <div class="full" style="grid-column: span 2;">
                <label>ประเภทภาระงาน <span class="text-danger">*</span></label>
                <select name="category_id" id="categorySelect" required class="bg-muted">
                    <option value="">-- เลือกประเภท --</option>
                    <?php foreach($all_categories as $c): ?>
                        <?php 
                            $displayName = $c['code'] . " " . $c['name_th'];
                            if($c['code'] == '1.1') $displayName = "1.1+1.2 การสอนระดับปริญญาตรี(บรรยาย/ปฏิบัติ)";
                            if($c['code'] == '1.2') continue; 
                        ?>
                        <option value="<?= $c['id']; ?>" data-code="<?= htmlspecialchars(trim($c['code'])); ?>" <?= ($c['id'] == $input['category_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($displayName); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="courseInfoFields" class="grid grid-2" style="grid-column: span 2; gap:30px; display:inherit;">
                <div id="courseCodeDiv">
                    <label>รหัสวิชา</label>
                    <input type="text" name="course_code" value="<?= htmlspecialchars($input['course_code']) ?>" placeholder="เช่น 101-101">
                </div>
                <div id="titleDiv">
                    <label id="titleLabel">ชื่อรายวิชา <span class="text-danger">*</span></label>
                    <input type="text" name="title" value="<?= htmlspecialchars($input['title']) ?>" placeholder="ระบุข้อมูล">
                </div>
            </div>

            <div id="modularFields" class="full p-4 rounded bg-modular" style="grid-column: span 2; display:none;">
                <div class="alert info mb-4">
                    <i class="bi bi-info-circle"></i> <strong>เกณฑ์ 1.6:</strong> คะแนนบทบาท + (15 คะแนน x จำนวนหน่วยกิต)
                </div>
                <div class="grid grid-2 gap-4">
                    <div>
                        <label>บทบาทในโมดูล <span class="text-danger">*</span></label>
                        <select name="modular_role" id="modularRole" class="input w-full bg-white">
                            <option value="">-- เลือกบทบาท --</option>
                            <?php foreach($modular_roles as $key => $role): ?>
                                <option value="<?= $key ?>" data-hours="<?= $role['hours'] ?>"><?= $role['name'] ?> (<?= $role['hours'] ?> ชม.)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>จำนวนหน่วยกิต <span class="text-danger">*</span></label>
                        <input type="number" name="credits_input" id="creditsInput" class="input w-full text-center font-bold" step="1" min="1" placeholder="ระบุหน่วยกิต">
                    </div>
                </div>
            </div>

            <div id="clcFields" class="full p-4 rounded bg-clc" style="grid-column: span 2; display:none;">
                <div class="grid grid-2 gap-4">
                    <div>
                        <label>ชื่อ CLC <span class="text-danger">*</span></label>
                        <input type="text" name="clc_name" id="clcName" class="input w-full bg-white" placeholder="เช่น ชุมชนสัมพันธ์">
                    </div>
                    <div>
                        <label>ตำแหน่ง/บทบาท <span class="text-danger">*</span></label>
                        <select name="clc_role" id="clcRole" class="input w-full bg-white">
                            <option value="">-- เลือก --</option>
                            <?php foreach($clc_roles as $key => $role): ?>
                                <option value="<?= $key ?>" data-hours="<?= $role['hours'] ?>"><?= $role['name'] ?> (<?= $role['hours'] ?> ชม.)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div id="examCommFields" class="full p-4 rounded bg-examcomm" style="grid-column: span 2; display:none;">
                <div class="grid grid-2 gap-4">
                    <div>
                        <label>รูปแบบการคุมสอบ <span class="text-danger">*</span></label>
                        <select name="exam_type" id="examType" class="input w-full bg-white">
                            <option value="">-- เลือก --</option>
                            <?php foreach($exam_types as $val => $label): ?>
                                <option value="<?= $val ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>ชั่วโมงปฏิบัติจริง <span class="text-danger">*</span></label>
                        <input type="number" name="hours_exam_comm" id="hoursExamComm" class="input w-full text-center font-bold" step="0.5" placeholder="0.0">
                    </div>
                </div>
            </div>

            <div class="full" style="grid-column: span 2;">
                <label class="text-primary">
                    <i class="bi bi-link-45deg"></i> ลิงก์หลักฐาน / ตารางสอน (Google Drive/OneDrive) <span class="text-danger">*</span>
                </label>
                <input type="url" name="attachment_link" class="w-full" placeholder="วางลิงก์ที่นี่ (จำเป็นต้องระบุ)" value="<?= htmlspecialchars($input['attachment_link']) ?>" required>
            </div>
        </div>

        <hr class="mb-6 opacity-50">

        <h4 class="text-primary mb-3"><i class="bi bi-calculator"></i> 2. คำนวณภาระงาน</h4>
        
        <div id="teachingTable" class="mb-6 p-4 rounded bg-surface border shadow-sm">
            <div class="alert info mb-3" id="reExamInfo" style="display:none; background:#fff3cd; color:#856404; border-color:#ffeeba;">
                <i class="bi bi-exclamation-circle"></i> <strong>1.9 สอบซ่อม:</strong> คิดคะแนนเหมือนการสอนปกติ (ทฤษฎี x3, ปฏิบัติ x1.5)
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:25%">ประเภทการสอน</th>
                        <th style="width:25%" class="text-center">ชั่วโมงปฏิบัติจริง (ชม.)</th>
                        <th style="width:20%" class="text-center">ตัวคูณ (Weight)</th>
                        <th style="width:30%" class="text-right">ภาระงานที่ได้ (คะแนน)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="align-middle"><strong class="text-primary">บรรยาย/ทฤษฎี</strong></td>
                        <td><input type="number" step="0.5" min="0" name="hours_lec" id="hoursLec" class="text-center font-bold" value="<?= htmlspecialchars($input['hours_lec']) ?>"></td>
                        <td class="text-center align-middle"><span class="badge pending" style="font-size:1rem;">x 3.0</span></td>
                        <td class="text-right align-middle"><span id="sumLec" style="font-size:1.4rem;">0.00</span></td>
                    </tr>
                    <tr>
                        <td class="align-middle"><strong class="text-info">ปฏิบัติ</strong></td>
                        <td><input type="number" step="0.5" min="0" name="hours_lab" id="hoursLab" class="text-center font-bold" value="<?= htmlspecialchars($input['hours_lab']) ?>"></td>
                        <td class="text-center align-middle"><span class="badge pending" style="font-size:1rem;">x 1.5</span></td>
                        <td class="text-right align-middle"><span id="sumLab" style="font-size:1.4rem;">0.00</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div id="advisorFields" class="mb-6" style="display:none;">
            <div class="alert info mb-4" style="font-size:1.1rem;">
                <i class="bi bi-info-circle"></i> <strong>เกณฑ์ 1.3:</strong> 1.5 คะแนน ต่อ 1 เรื่อง ต่อสัปดาห์
            </div>
            <div class="grid grid-2 mb-4" style="align-items:end;">
                <div>
                    <label>จำนวนสัปดาห์ตลอดเทอม <span class="text-danger">*</span></label>
                    <input type="number" id="weekCount" name="week_count" class="text-center font-bold bg-white border-primary" 
                           placeholder="เช่น 15" value="<?= htmlspecialchars($input['week_count']) ?>">
                </div>
                <div class="text-right pb-2">
                    <button type="button" id="addProjectBtn" class="btn btn-outline "><i class="bi "></i> เพิ่มโครงงาน</button>
                </div>
            </div>
            <div class="table-card border rounded mb-3">
                <table class="table">
                    <thead class="bg-muted">
                        <tr><th width="5%">#</th><th width="40%">ชื่อโครงงาน</th><th width="35%">รายชื่อ นศ.</th><th width="20%" class="text-right">คะแนน</th><th width="5%"></th></tr>
                    </thead>
                    <tbody id="projectListBody">
                        <?php foreach($existing_projects_data as $index => $proj): ?>
                        <tr class="project-row">
                            <td class="align-middle text-center"><span class="badge pending row-num"><?= $index + 1 ?></span></td>
                            <td><input type="text" name="project_titles[]" class="w-full" value="<?= htmlspecialchars($proj['title']) ?>" placeholder="ชื่อโครงงาน"></td>
                            <td><input type="text" name="project_students[]" class="w-full" value="<?= htmlspecialchars($proj['student']) ?>" placeholder="ชื่อ นศ."></td>
                            <td class="align-middle text-right"><span class="row-score font-bold text-primary">0.00</span></td>
                            <td class="align-middle text-center"><button type="button" class="btn btn-sm btn-danger remove-project-btn" tabindex="-1"><i class="bi bi-trash"></i></button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <input type="hidden" id="projectCount" name="project_count" value="<?= count($existing_projects_data) ?>">
        </div>

        <div id="examFields" class="mb-6 p-4 rounded bg-surface border shadow-sm" style="display:none;">
            <div class="alert info mb-4" style="font-size:1.1rem;">
                <i class="bi bi-info-circle"></i> <strong id="examLabelInfo">เกณฑ์:</strong> 1.5 คะแนน ต่อ 1 ชั่วโมงปฏิบัติการจริง
            </div>
            <div class="grid grid-2">
                <div>
                    <label id="examHoursLabel">จำนวนชั่วโมง (ชม.) <span class="text-danger">*</span></label>
                    <input type="number" step="0.5" min="0" id="hoursExam" class="text-center font-bold border-primary" 
                           value="<?= htmlspecialchars($input['hours_lec']) ?>">
                </div>
                <div class="text-center p-4 bg-muted rounded">
                    <small style="font-size:1rem;">ภาระงานที่คำนวณได้</small>
                    <div class="text-primary font-bold" style="font-size:36px;">
                        <span id="examComputed">0.00</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="stack-between p-4 rounded bg-muted">
            <a href="workloads.php" class="btn btn-muted text-dark"><i class="bi bi-arrow-left"></i> ย้อนกลับ</a>
            <button type="submit" class="btn btn-primary btn-lg px-6"><i class="bi bi-save"></i> บันทึกข้อมูล</button>
        </div>
    </form>
</div>

<div class="modal" id="criteriaModal">
    <div class="modal-content" style="max-width: 800px;">
        <span class="close" id="closeCriteriaModal" style="float:right; cursor:pointer; font-size:1.5rem;">&times;</span>
        <h3 class="mb-3 text-primary">เกณฑ์ภาระงานด้านการสอน</h3>
        <table class="table table-sm border">
            <tbody>
                <tr><td>1.1 บรรยาย</td><td>3.0 ต่อ ชม.ปฏิบัติ</td></tr>
                <tr><td>1.2 ปฏิบัติ</td><td>1.5 ต่อ ชม.ปฏิบัติ</td></tr>
                <tr><td>1.3 ที่ปรึกษา</td><td>1.5 ต่อ เรื่อง/สัปดาห์</td></tr>
                <tr><td>1.4 สอบ (ภายใน)</td><td>1.5 ต่อ ชม.ปฏิบัติ</td></tr>
                <tr><td>1.5 อาจารย์นิเทศ</td><td>1.5 ต่อ ชม.ปฏิบัติ</td></tr>
                <tr><td>1.6 Modular</td><td>บทบาท + (15 x หน่วยกิต)</td></tr>
                <tr><td>1.7 CLC</td><td>ประธาน(2), รอง/เลขา(1), กก.(2)</td></tr>
                <tr><td>1.8 คุมสอบ</td><td>คิดตามชั่วโมงจริง</td></tr>
                <tr><td>1.9 สอบซ่อม</td><td>คิดเหมือน 1.1 / 1.2</td></tr>
            </tbody>
        </table>
        <div class="text-right mt-3"><button class="btn btn-muted" id="closeCriteriaBtn">ปิด</button></div>
    </div>
</div>

<script>
// Modal Logic (เหมือนเดิม)
const modal = document.getElementById("criteriaModal");
if (document.getElementById("openCriteriaBtn")) {
    document.getElementById("openCriteriaBtn").onclick = () => modal.classList.add("show");
    const closeModal = () => modal.classList.remove("show");
    document.getElementById("closeCriteriaBtn").onclick = closeModal;
    document.getElementById("closeCriteriaModal").onclick = closeModal;
    modal.onclick = (e) => { if(e.target === modal) closeModal(); };
}

// Elements Reference
const categorySelect = document.getElementById('categorySelect');
const teachingTable = document.getElementById('teachingTable');
const advisorFields = document.getElementById('advisorFields');
const examFields = document.getElementById('examFields');
const courseInfoFields = document.getElementById('courseInfoFields');
const courseCodeDiv = document.getElementById('courseCodeDiv');
const titleLabel = document.getElementById('titleLabel');
const examLabelInfo = document.getElementById('examLabelInfo');
const examHoursLabel = document.getElementById('examHoursLabel');

//  New Elements References
const modularFields = document.getElementById('modularFields');
const clcFields = document.getElementById('clcFields');
const examCommFields = document.getElementById('examCommFields');
const reExamInfo = document.getElementById('reExamInfo');

// Inputs
const hoursLec = document.getElementById('hoursLec');
const hoursLab = document.getElementById('hoursLab');
const hoursExam = document.getElementById('hoursExam');
const weekCount = document.getElementById('weekCount');
const projectCountInput = document.getElementById('projectCount');
const projectListBody = document.getElementById('projectListBody');
const addProjectBtn = document.getElementById('addProjectBtn');

//  New Inputs for Calc
const modularRole = document.getElementById('modularRole');
const creditsInput = document.getElementById('creditsInput');
const clcRole = document.getElementById('clcRole');
const hoursExamComm = document.getElementById('hoursExamComm');

const sumLec = document.getElementById('sumLec');
const sumLab = document.getElementById('sumLab');
const examComputed = document.getElementById('examComputed');
const grandTotalDisplay = document.getElementById('grandTotalDisplayTop');

const WEIGHT_LEC = 3.0;
const WEIGHT_LAB = 1.5;
const WEIGHT_PROJECT = 1.5;
const WEIGHT_EXAM = 1.5;

function updateRowScores() {
    const weeks = parseFloat(weekCount.value) || 0;
    const score = weeks * WEIGHT_PROJECT;
    const rows = projectListBody.querySelectorAll('.project-row');
    rows.forEach((row, i) => {
        row.querySelector('.row-num').innerText = i + 1;
        row.querySelector('.row-score').innerText = score.toFixed(2);
    });
    projectCountInput.value = rows.length;
    calculate();
}

addProjectBtn.addEventListener('click', () => {
    const tr = document.createElement('tr');
    tr.className = 'project-row';
    tr.innerHTML = `<td class="align-middle text-center"><span class="badge pending row-num"></span></td>
        <td><input type="text" name="project_titles[]" class="w-full" placeholder="ชื่อโครงงาน"></td>
        <td><input type="text" name="project_students[]" class="w-full" placeholder="ชื่อ นศ."></td>
        <td class="align-middle text-right"><span class="row-score font-bold text-primary">0.00</span></td>
        <td class="align-middle text-center"><button type="button" class="btn btn-sm btn-danger remove-project-btn" tabindex="-1"><i class="bi bi-trash"></i></button></td>`;
    projectListBody.appendChild(tr);
    updateRowScores();
});
projectListBody.addEventListener('click', e => {
    if(e.target.closest('.remove-project-btn')) {
        if(projectListBody.querySelectorAll('.project-row').length > 1) {
            e.target.closest('.project-row').remove();
            updateRowScores();
        } else { e.target.closest('.project-row').querySelectorAll('input').forEach(i=>i.value=''); }
    }
});

hoursExam.addEventListener('input', () => {
    hoursLec.value = hoursExam.value;
    calculate();
});

function calculate() {
    const selected = categorySelect.selectedOptions[0];
    const code = selected ? selected.dataset.code.trim() : '';
    let grandTotal = 0;

    if (code === '1.3') {
        const count = parseFloat(projectCountInput.value) || 0;
        const weeks = parseFloat(weekCount.value) || 0;
        grandTotal = count * weeks * WEIGHT_PROJECT;
        
    } else if (code === '1.6') { // Modular
        const r = parseFloat(modularRole.options[modularRole.selectedIndex]?.dataset.hours || 0);
        const c = parseFloat(creditsInput.value || 0);
        grandTotal = r * c;  
        
    } else if (code === '1.7') { // CLC
        grandTotal = parseFloat(clcRole.options[clcRole.selectedIndex]?.dataset.hours || 0);
        
    } else if (code === '1.8') { // Exam Comm
        grandTotal = parseFloat(hoursExamComm.value || 0);
        
    } else if (code === '1.4' || code === '1.5') {
        const h = parseFloat(hoursExam.value) || 0;
        grandTotal = h * WEIGHT_EXAM;
        examComputed.innerText = grandTotal.toFixed(2);
        
    } else if (['1.9'].includes(code)) { // 1.9 สอบซ่อม
        const hLec = parseFloat(hoursLec.value) || 0;
        const hLab = parseFloat(hoursLab.value) || 0;
        grandTotal = (hLec * WEIGHT_LEC) + (hLab * WEIGHT_LAB);
        if(sumLec) sumLec.innerText = (hLec * WEIGHT_LEC).toFixed(2);
        if(sumLab) sumLab.innerText = (hLab * WEIGHT_LAB).toFixed(2);
        
    } else { // 1.1, 1.2
        const hLec = parseFloat(hoursLec.value) || 0;
        const hLab = parseFloat(hoursLab.value) || 0;
        grandTotal = (hLec * WEIGHT_LEC) + (hLab * WEIGHT_LAB);
        if(sumLec) sumLec.innerText = (hLec * WEIGHT_LEC).toFixed(2);
        if(sumLab) sumLab.innerText = (hLab * WEIGHT_LAB).toFixed(2);
    }
    
    if(grandTotalDisplay) grandTotalDisplay.innerText = grandTotal.toFixed(2);
}

categorySelect.addEventListener('change', () => {
    const selected = categorySelect.selectedOptions[0];
    const code = selected ? selected.dataset.code.trim() : '';
    
    // Reset All Visibility
    teachingTable.style.display = 'none';
    advisorFields.style.display = 'none';
    examFields.style.display = 'none';
    modularFields.style.display = 'none';
    clcFields.style.display = 'none';
    examCommFields.style.display = 'none';
    reExamInfo.style.display = 'none';
    
    // Default Inputs
    courseInfoFields.style.display = 'grid'; 
    courseCodeDiv.style.visibility = 'visible'; 
    titleLabel.innerHTML = 'ชื่อรายวิชา <span class="text-danger">*</span>';

    if (code === '1.3') {
        advisorFields.style.display = 'block';
        courseInfoFields.style.display = 'none'; 
        updateRowScores();
    } 
    else if (code === '1.6') { // Modular
        modularFields.style.display = 'block';
        courseCodeDiv.style.visibility = 'visible';
        titleLabel.innerHTML = 'ชื่อรายวิชา / Module <span class="text-danger">*</span>';
    }
    else if (code === '1.7') { // CLC
        clcFields.style.display = 'block';
        courseCodeDiv.style.visibility = 'hidden'; 
        titleLabel.innerHTML = 'ชื่อรายวิชา (ถ้ามี)';
    }
    else if (code === '1.8') { // Exam Comm
        examCommFields.style.display = 'block';
        courseCodeDiv.style.visibility = 'hidden'; 
        titleLabel.innerHTML = 'ชื่อรายวิชาที่คุมสอบ <span class="text-danger">*</span>';
    }
    else if (code === '1.9') { // Re-Exam (ใช้ตารางเดียวกับ 1.1)
        teachingTable.style.display = 'block';
        reExamInfo.style.display = 'block';
        titleLabel.innerHTML = 'ชื่อรายวิชา (สอบซ่อม) <span class="text-danger">*</span>';
    }
    else if (code === '1.4') {
        examFields.style.display = 'block';
        courseCodeDiv.style.visibility = 'hidden'; 
        titleLabel.innerHTML = 'ชื่อเรื่อง <span class="text-danger">*</span>';
        examLabelInfo.innerText = "เกณฑ์ 1.4:";
        examHoursLabel.innerHTML = 'จำนวนชั่วโมงสอบ (ชม.) <span class="text-danger">*</span>';
        hoursExam.value = hoursLec.value;
    } 
    else if (code === '1.5') {
        examFields.style.display = 'block';
        courseCodeDiv.style.visibility = 'hidden'; 
        titleLabel.innerHTML = 'ชื่อสถานที่ / หน่วยงาน <span class="text-danger">*</span>'; 
        examLabelInfo.innerText = "เกณฑ์ 1.5:";
        examHoursLabel.innerHTML = 'จำนวนชั่วโมงนิเทศ (ชม.) <span class="text-danger">*</span>';
        hoursExam.value = hoursLec.value;
    } 
    else {
        teachingTable.style.display = 'block';
        hoursLab.disabled = false; hoursLab.closest('tr').style.opacity = '1';
    }
    calculate();
});

// Bind Events for new elements
[hoursLec, hoursLab, weekCount, hoursExam, modularRole, creditsInput, clcRole, hoursExamComm].forEach(el => {
    if(el) el.addEventListener('input', calculate);
});

document.addEventListener('DOMContentLoaded', () => {
    updateRowScores();
    categorySelect.dispatchEvent(new Event('change'));
    calculate();
});
</script>