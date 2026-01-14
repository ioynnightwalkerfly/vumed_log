<?php
// forms/staff/form_staff_admin.php
// ===== ด้านที่ 6: ภาระงานบริหาร (สายสนับสนุน) - แบบใหม่ =====

$is_edit = $is_edit ?? false;
$errors = [];

$input = [
    'category_id' => $_POST['category_id'] ?? ($is_edit ? $item['category_id'] : null),
    'title'       => $_POST['title'] ?? ($is_edit ? $item['title'] : ''),
    'actual_hours'=> $_POST['actual_hours'] ?? ($is_edit ? $item['actual_hours'] : 0), // เก็บจำนวนสัปดาห์
    'description' => $_POST['description'] ?? ($is_edit ? $item['description'] : ''),
    'attachment_link' => $_POST['attachment_link'] ?? ($is_edit ? ($item['attachment_link'] ?? '') : ''),
    'weight'      => $is_edit ? ($item['computed_hours'] / ($item['actual_hours'] ?: 1)) : 0, // คำนวณ weight กลับมาแสดง
];

// ดึงหมวดหมู่ (Area 6 Staff)
$stmt = $conn->prepare("SELECT id, code, name_th, weight FROM workload_categories WHERE main_area = 6 AND is_active = 1 AND target_group = 'staff' ORDER BY code ASC");
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Post Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) die("Invalid CSRF token.");

    $category_id = $input['category_id'];
    $title = $input['title'];
    $quantity = floatval($input['actual_hours']); // จำนวนสัปดาห์
    $description = $input['description'];
    $attachment_link = $input['attachment_link'];
    $status = 'pending';

    if (empty($category_id)) $errors[] = "กรุณาเลือกตำแหน่ง";
    if (empty($title)) $errors[] = "กรุณากรอกหน่วยงาน";
    if ($quantity <= 0) $errors[] = "กรุณากรอกจำนวนสัปดาห์";
    if (empty($attachment_link)) $errors[] = "กรุณาแนบลิงก์คำสั่ง";

    // ดึง Weight จริงจาก DB (20 หรือ 10)
    $realWeight = 0;
    foreach($categories as $c) {
        if ($c['id'] == $category_id) {
            $realWeight = floatval($c['weight']);
            break;
        }
    }

    // คำนวณ (สัปดาห์ x คะแนนต่อสัปดาห์)
    $computed = $quantity * $realWeight;

    if (empty($errors)) {
        if ($is_edit) {
            $stmt = $conn->prepare("UPDATE workload_items SET category_id=?, title=?, actual_hours=?, computed_hours=?, description=?, attachment_link=?, updated_at=NOW() WHERE id=? AND user_id=?");
            $stmt->bind_param("isddssii", $category_id, $title, $quantity, $computed, $description, $attachment_link, $item['id'], $user['id']);
        } else {
            $term_id = $term_id ?? 1;
            $stmt = $conn->prepare("INSERT INTO workload_items (user_id, academic_year, term_id, category_id, title, actual_hours, computed_hours, description, status, attachment_link) VALUES (?, YEAR(CURDATE()), ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiisddsss", $user['id'], $term_id, $category_id, $title, $quantity, $computed, $description, $status, $attachment_link);
        }
        if ($stmt->execute()) {
            echo "<script>window.location.href = 'staff_workloads.php?success=" . urlencode("บันทึกงานบริหารสำเร็จ") . "';</script>";
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
                <i class="bi bi-person-badge-fill"></i> <?= $is_edit ? "แก้ไข" : "บันทึก" ?> (ภาระงานบริหาร)
            </h2>
            <p class="muted mt-2" style="font-size:1.1rem;">สำหรับผู้อำนวยการสำนัก หรือหัวหน้างาน (ตามคำสั่งแต่งตั้ง)</p>
        </div>
        <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('criteriaModal').classList.add('show')">
            <i class="bi bi-info-circle"></i> ดูเกณฑ์
        </button>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert error mb-4"><ul><?php foreach($errors as $e) echo "<li>$e</li>"; ?></ul></div>
    <?php endif; ?>

    <form method="POST" class="grid grid-2" style="gap:30px;">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">

        <div class="full" style="grid-column: span 2;">
            <label>ตำแหน่งบริหาร <span class="text-danger">*</span></label>
            <select name="category_id" id="categorySelect" required class="bg-muted">
                <option value="">-- เลือกตำแหน่ง --</option>
                <?php foreach($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" 
                            data-weight="<?= $c['weight'] ?>"
                            <?= ($c['id']==$input['category_id'])?'selected':'' ?>>
                        <?= htmlspecialchars($c['name_th']) ?> (<?= $c['weight'] ?> ชม./สัปดาห์)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="full" style="grid-column: span 2;">
            <label>หน่วยงานที่สังกัด / บริหาร <span class="text-danger">*</span></label>
            <input type="text" name="title" value="<?= htmlspecialchars($input['title']) ?>" required 
                   placeholder="เช่น สำนักวิทยบริการ, กองแผนงาน">
        </div>

        <div class="full p-4 rounded bg-surface border shadow-sm mt-2">
            <div class="grid grid-2" style="align-items:center;">
                <div>
                    <label>จำนวนสัปดาห์ที่ดำรงตำแหน่ง <span class="text-danger">*</span></label>
                    <input type="number" step="1" min="1" name="actual_hours" id="quantityInput" 
                           class="text-center font-bold text-primary" 
                           style="font-size:1.5rem !important;"
                           value="<?= htmlspecialchars($input['actual_hours']) ?>" required>
                    <small class="muted">ปกติ 1 ปีการศึกษา = 52 สัปดาห์</small>
                </div>
                <div class="text-center">
                    <small class="muted" style="font-size:1.1rem;">คะแนนที่ได้ (Auto)</small>
                    <div class="text-primary font-bold" style="font-size:3rem;">
                        <span id="computedDisplay"><?= number_format($is_edit ? $item['computed_hours'] : 0, 2) ?></span>
                    </div>
                    <small class="text-muted" id="formulaText">(สัปดาห์ x คะแนน)</small>
                </div>
            </div>
            <input type="hidden" id="weightHidden" value="<?= $input['weight'] ?>">
        </div>

        <div class="full">
            <label>เลขที่คำสั่งแต่งตั้ง / รายละเอียดเพิ่มเติม</label>
            <textarea name="description" rows="2" placeholder="ระบุเลขที่คำสั่งแต่งตั้ง..."><?= htmlspecialchars($input['description']) ?></textarea>
        </div>

        <div class="full" style="grid-column: span 2;">
            <label class="text-primary" style="font-size:1.3rem !important;">
                <i class="bi bi-link-45deg"></i> ลิงก์คำสั่ง (Google Drive) <span class="text-danger">*</span>
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
    <div class="modal-content" style="max-width:700px;">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding-bottom:10px; margin-bottom:15px;">
            <h3 class="m-0 text-primary">เกณฑ์: ภาระงานบริหาร</h3>
            <span class="close" onclick="document.getElementById('criteriaModal').classList.remove('show')" style="cursor:pointer; font-size:1.5rem;">&times;</span>
        </div>
        
        <div style="line-height:1.6;">
            <p>คิดภาระงานตาม <strong>ตำแหน่ง</strong> x <strong>ระยะเวลา (สัปดาห์)</strong></p>
            
            <ul class="text-muted ml-4 mb-4">
                <li><strong>ผอ.สำนัก / หัวหน้าศูนย์:</strong> 20 ชั่วโมง/สัปดาห์</li>
                <li><strong>หัวหน้างาน:</strong> 10 ชั่วโมง/สัปดาห์</li>
            </ul>

            <div class="alert info p-3 bg-light rounded border">
                <strong>ตัวอย่าง:</strong> หัวหน้างานพัสดุ ปฏิบัติงานตลอดปี (52 สัปดาห์)<br>
                👉 สูตร: 52 สัปดาห์ x 10 คะแนน = <strong>520</strong> คะแนน
            </div>
        </div>
        
        <div class="mt-4 text-right">
            <button class="btn btn-primary" onclick="document.getElementById('criteriaModal').classList.remove('show')">ปิด</button>
        </div>
    </div>
</div>

<script>
const catSelect = document.getElementById('categorySelect');
const quantityInput = document.getElementById('quantityInput');
const computedDisplay = document.getElementById('computedDisplay');
const weightHidden = document.getElementById('weightHidden');
const formulaText = document.getElementById('formulaText');

function calculate() {
    let w = parseFloat(weightHidden.value) || 0;
    let q = parseFloat(quantityInput.value) || 0;
    computedDisplay.innerText = (q * w).toFixed(2);
    formulaText.innerText = `(${q} สัปดาห์ x ${w} คะแนน)`;
}

catSelect.addEventListener('change', function() {
    const option = this.options[this.selectedIndex];
    const w = option.getAttribute('data-weight') || 0;
    weightHidden.value = w;
    calculate();
});

quantityInput.addEventListener('input', calculate);

document.addEventListener('DOMContentLoaded', () => {
    if (catSelect.value) {
        // ดึงค่า weight เริ่มต้นถ้ามีการเลือกไว้แล้ว (กรณี Edit)
        const selectedOption = catSelect.options[catSelect.selectedIndex];
        if (selectedOption) {
             weightHidden.value = selectedOption.getAttribute('data-weight');
        }
        calculate();
    }
});
</script>