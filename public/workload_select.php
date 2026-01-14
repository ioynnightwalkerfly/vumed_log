<?php
// public/workload_select.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php'; 

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}
$user = $_SESSION['user'];

// --- 1. สร้างตัวเลือกปีการศึกษา (ย้อนหลัง 1 ปี - ล่วงหน้า 2 ปี) ---
$currentYear = date("Y") + 543; // ปี พ.ศ. ปัจจุบัน
$years = [];
for ($y = $currentYear - 1; $y <= $currentYear + 2; $y++) {
    $years[] = $y;
}

// --- 2. รายการด้านภาระงาน ---
$areas = [
    1 => 'ด้านการสอน',
    2 => 'ด้านวิจัยและงานวิชาการ',
    3 => 'ด้านบริการวิชาการ',
    4 => 'ด้านทำนุบำรุงศิลปวัฒนธรรม',
    5 => 'ด้านบริหาร',
    6 => 'ภาระงานอื่น ๆ'
];

// --- 3. เมื่อกด "ถัดไป" ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $year = $_POST['academic_year'];
    $term = $_POST['term'];
    $area = $_POST['main_area'];

    if (!$year || !$term || !$area) {
        $error = "กรุณาเลือกข้อมูลให้ครบถ้วน";
    } else {
        // ส่งค่า academic_year และ term ไปแยกกัน
        // เช่น workload_add.php?year=2568&term=1&area=1
        header("Location: workload_add.php?year=$year&term=$term&area=$area");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>เลือกภาระงาน | MedUI System</title>
  <link rel="stylesheet" href="../medui/medui.css">
  <link rel="stylesheet" href="../medui/medui.components.css">
  <link rel="stylesheet" href="../medui/medui.layout.css">
  <link rel="stylesheet" href="../medui/medui.theme.medical.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>

<div class="app">
  <?php include '../inc/nav.php'; ?>

  <main class="main" style="display:flex; justify-content:center; align-items:center; min-height:80vh;">

    <div class="card p-5 border shadow-sm" style="max-width: 500px; width:100%; border-radius:16px;">
      <div class="text-center mb-4">
          <h2 class="text-primary m-0"><i class="bi bi-plus-circle-fill"></i> เพิ่มภาระงานใหม่</h2>
          <p class="text-muted">เลือกปีการศึกษาและหมวดงาน</p>
      </div>

      <div style="background: #f0f9ff; border: 1px dashed #bae6fd; border-radius: 8px; padding: 15px; margin-bottom: 25px; text-align: center;">
          <a href="https://drive.google.com/file/d/17-RNJ-YUghSLGJ-U18ZLMAukwpO-26nw/view?usp=sharing" target="_blank" class="text-primary" style="text-decoration: none; font-weight: 600;">
              <i class="bi bi-file-earmark-pdf-fill text-danger"></i> อ่านเกณฑ์การประเมิน (PDF)
          </a>
          
      </div>
      <div style="background: #3cabf5ff; border: 1px dashed #bae6fd; border-radius: 8px; padding: 15px; margin-bottom: 25px; text-align: center;">
          <a href="Teacher_Workload_Infographic.html" target="_blank" class="text-primary" style="text-decoration: none; font-weight: 600;">
              <i class="bi bi-file-earmark-pdf-fill text-danger"></i> Infographic (AI)
          </a>
          
      </div>

        <div style="background: #64e664ff; border: 1px dashed #bae6fd; border-radius: 8px; padding: 15px; margin-bottom: 25px; text-align: center;">
          <a href="Teacher_Workload_Planner.html" target="_blank" class="text-primary" style="text-decoration: none; font-weight: 600;">
              <i class="bi bi-file-earmark-pdf-fill text-danger"></i> Planer (AI)
          </a>
          
      </div>


      <?php if (!empty($error)): ?>
        <div class="alert error mb-4 text-center"><?php echo $error; ?></div>
      <?php endif; ?>

      <form method="POST">
        
        <div class="grid grid-2 gap-3 mb-3">
            <div class="form-group">
                <label class="font-bold">ปีการศึกษา</label>
                <select name="academic_year" class="input w-full" required>
                    <option value="">- เลือกปี -</option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= $y ?>" <?= $y == $currentYear ? 'selected' : '' ?>>
                            <?= $y ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="font-bold">ภาคเรียน</label>
                <select name="term" class="input w-full" required>
                    <option value="3">ตลอดปีการศึกษา</option>
                </select>
            </div>
        </div>

        <div class="form-group mb-5">
          <label class="font-bold">ด้านภาระงาน</label>
          <select name="main_area" class="input w-full p-3 font-bold text-dark" required>
            <option value="">-- กรุณาเลือกด้านภาระงาน --</option>
            <?php foreach ($areas as $key=>$val): ?>
              <option value="<?php echo $key; ?>">
                  <?php echo $key . ". " . $val; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="stack-between">
          <a href="workloads.php" class="btn btn-light px-4">ยกเลิก</a>
          <button type="submit" class="btn btn-primary px-5 shadow-sm">ถัดไป <i class="bi bi-arrow-right"></i></button>
        </div>
      </form>
    </div>

  </main>
</div>

</body>
</html>