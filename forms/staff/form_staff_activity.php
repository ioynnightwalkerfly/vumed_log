<?php
// forms/staff/form_staff_activity.php
// ===== ด้านที่ 5: งานมีส่วนร่วมกับกิจกรรมของมหาวิทยาลัย (สายสนับสนุน) - แบบบันทึกหลายรายการ =====

$errors = [];

// 1. ดึงหมวดหมู่ (Area 5 Staff)
$stmt = $conn->prepare("
    SELECT id, code, name_th 
    FROM workload_categories 
    WHERE main_area = 5 
      AND is_active = 1 
      AND target_group = 'staff' 
    ORDER BY code ASC
");
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ตรวจสอบว่ามีหมวดหมู่เดียวหรือไม่
$single_category = (count($categories) === 1) ? $categories[0] : null;

// ===== ส่วนบันทึกข้อมูล (POST) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token.");
    }

    $items = $_POST['items'] ?? [];
    $category_id_global = $_POST['category_id'] ?? ($single_category['id'] ?? null);
    $term_id = $term_id ?? 1;
    $success_count = 0;

    if (empty($category_id_global)) {
        $errors[] = "กรุณาเลือกประเภทกิจกรรม";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO workload_items 
            (user_id, academic_year, term_id, category_id, title, 
             actual_hours, computed_hours, description, status, attachment_link) 
            VALUES (?, YEAR(CURDATE()), ?, ?, ?, ?, ?, ?, 'pending', ?)
        ");

        foreach ($items as $row) {
            $title = trim($row['title']);
            $hours = floatval($row['hours']);
            $link  = trim($row['link']);
            $desc  = trim($row['desc']);

            if (!empty($title) && $hours > 0 && !empty($link)) {
                // สูตร: 1 ชั่วโมง = 1 คะแนน
                $computed = $hours * 1; 

                // Type: iiisddss (8 ตัวแปร)
                $stmt->bind_param(
                    "iiisddss", 
                    $user['id'], 
                    $term_id, 
                    $category_id_global, 
                    $title, 
                    $hours, 
                    $computed, 
                    $desc, 
                    $link
                );
                
                if ($stmt->execute()) {
                    $success_count++;
                }
            }
        }
        
        if ($success_count > 0) {
            echo "<script>window.location.href = 'staff_workloads.php?success=" . urlencode("บันทึกกิจกรรมสำเร็จ $success_count รายการ") . "';</script>";
            exit;
        } else {
            $errors[] = "กรุณากรอกข้อมูลให้ครบถ้วนอย่างน้อย 1 รายการ";
        }
    }
}
?>

<div class="card p-6">
    <div class="stack-between mb-4 border-bottom pb-4">
        <div>
            <h2 class="mb-0 text-primary">
                <i class="bi bi-people-fill"></i> บันทึกกิจกรรมมหาวิทยาลัย
            </h2>
            <p class="muted mt-2">เข้าร่วมโครงการ/กิจกรรมที่จัดโดยมหาวิทยาลัย (1 ชม. = 1 คะแนน)</p>
        </div>
        <div class="text-right">
             <small class="muted">รวมคะแนนครั้งนี้</small>
             <div class="text-primary font-bold" style="font-size:2rem;" id="grandTotal">0.00</div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert error mb-4">
            <strong>พบข้อผิดพลาด:</strong>
            <ul><?php foreach($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">

        <div class="mb-4">
            <?php if ($single_category): ?>
                <div class="alert info">
                    <i class="bi bi-check-circle-fill"></i> <strong>ประเภทกิจกรรม:</strong> <?= htmlspecialchars($single_category['name_th']) ?>
                </div>
                <input type="hidden" name="category_id" value="<?= $single_category['id'] ?>">
            <?php else: ?>
                <label>เลือกประเภทกิจกรรม <span class="text-danger">*</span></label>
                <select name="category_id" class="w-full bg-muted" required>
                    <option value="">-- กรุณาเลือก --</option>
                    <?php foreach($categories as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['code']." : ".$c['name_th']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="bg-light">
                    <tr>
                        <th width="5%" class="text-center">#</th>
                        <th width="35%">ชื่อโครงการ / กิจกรรม <span class="text-danger">*</span></th>
                        <th width="20%">สถานที่ / รายละเอียด</th>
                        <th width="15%" class="text-center">ชม.เข้าร่วม <span class="text-danger">*</span></th>
                        <th width="20%">หลักฐาน (Drive) <span class="text-danger">*</span></th>
                        <th width="5%"></th>
                    </tr>
                </thead>
                <tbody id="itemTableBody">
                    </tbody>
            </table>
        </div>

        <button type="button" class="btn btn-outline-primary mt-3 mb-4 w-full" id="addRowBtn" style="border-style:dashed;">
            <i class="bi bi-plus-lg"></i> เพิ่มรายการ
        </button>

        <div class="stack-between p-4 bg-muted rounded">
            <a href="staff_workloads.php" class="btn btn-muted text-dark"><i class="bi bi-arrow-left"></i> ย้อนกลับ</a>
            <button type="submit" class="btn btn-primary btn-lg px-6"><i class="bi bi-save"></i> บันทึกข้อมูลทั้งหมด</button>
        </div>
    </form>
</div>

<template id="rowTemplate">
    <tr class="item-row">
        <td class="text-center align-middle"><span class="row-num badge bg-secondary">1</span></td>
        <td>
            <input type="text" name="items[{i}][title]" class="input w-full" placeholder="เช่น งานกีฬาบุคลากร..." required>
        </td>
        <td>
            <input type="text" name="items[{i}][desc]" class="input w-full" placeholder="ระบุสถานที่ (ถ้ามี)">
        </td>
        <td>
            <input type="number" step="0.5" min="0.5" name="items[{i}][hours]" class="input w-full text-center font-bold hours-input" placeholder="0.0" required>
        </td>
        <td>
            <input type="url" name="items[{i}][link]" class="input w-full" placeholder="Link Drive..." required>
        </td>
        <td class="text-center align-middle">
            <button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-trash"></i></button>
        </td>
    </tr>
</template>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('itemTableBody');
    const template = document.getElementById('rowTemplate').innerHTML;
    const grandTotalEl = document.getElementById('grandTotal');
    let rowCount = 0;

    function addRow() {
        rowCount++;
        const tr = document.createElement('tr');
        tr.className = 'item-row';
        tr.innerHTML = template.replace(/{i}/g, rowCount);
        tableBody.appendChild(tr);
        updateSequence();
    }

    function updateSequence() {
        const rows = tableBody.querySelectorAll('tr');
        let total = 0;
        rows.forEach((row, index) => {
            row.querySelector('.row-num').innerText = index + 1;
            const h = parseFloat(row.querySelector('.hours-input').value) || 0;
            total += h;
        });
        grandTotalEl.innerText = total.toFixed(2);
    }

    document.getElementById('addRowBtn').addEventListener('click', addRow);

    tableBody.addEventListener('click', (e) => {
        if (e.target.closest('.remove-row')) {
            const row = e.target.closest('tr');
            if (tableBody.children.length > 1) {
                row.remove();
                updateSequence();
            } else {
                row.querySelectorAll('input').forEach(input => input.value = '');
                updateSequence();
            }
        }
    });

    tableBody.addEventListener('input', (e) => {
        if (e.target.classList.contains('hours-input')) {
            updateSequence();
        }
    });

    // เริ่มต้น 1 แถว
    addRow();
});
</script>