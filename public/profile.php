<?php
// public/profile.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// 1. ดึงข้อมูลล่าสุด
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user']['id']);
$stmt->execute();
$user_latest = $stmt->get_result()->fetch_assoc();
$user = $user_latest; 

// ===== ส่วนบันทึกข้อมูล (POST) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token.");
    }

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role_position = trim($_POST['role_position'] ?? '');
    
    // 2. จัดการอัปโหลดรูปภาพ
    $image_sql_part = ""; 
    $params = [$name, $email, $role_position];
    $types = "sss"; 

    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        
        // ถ้าไม่มีนามสกุล (เกิดจากการแปลง Blob ของ JS) ให้ตั้งเป็น jpg
        if(empty($ext)) $ext = 'jpg'; 

        $newFilename = "profile_" . $user['id'] . "_" . time() . "." . $ext;
        $targetDir = "../uploads/profiles/";
        
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetDir . $newFilename)) {
            $image_sql_part = ", profile_image = ?"; 
            $params[] = $newFilename;
            $types .= "s";
            
            if (!empty($user['profile_image']) && file_exists($targetDir . $user['profile_image'])) {
                @unlink($targetDir . $user['profile_image']);
            }
        }
    }

    // 3. รวม SQL
    $params[] = $user['id']; 
    $types .= "i";

    $sql = "UPDATE users SET name=?, email=?, role_position=? $image_sql_part WHERE id=?";
    $stmtUpdate = $conn->prepare($sql);
    $stmtUpdate->bind_param($types, ...$params);

    if ($stmtUpdate->execute()) {
        // อัปเดต Session
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['email'] = $email;
        $_SESSION['user']['role_position'] = $role_position;
        if (!empty($image_sql_part)) {
            $_SESSION['user']['profile_image'] = $params[count($params)-2]; 
        }
        
        header("Location: profile.php?success=บันทึกข้อมูลเรียบร้อย");
        exit;
    } else {
        $error = "เกิดข้อผิดพลาด: " . $conn->error;
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขข้อมูลส่วนตัว</title>
    <link rel="stylesheet" href="../medui/medui.css">
    <link rel="stylesheet" href="../medui/medui.components.css">
    <link rel="stylesheet" href="../medui/medui.layout.css">
    <link rel="stylesheet" href="../medui/medui.theme.medical.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">

    <style>
        .profile-container { max-width: 600px; margin: 40px auto; }
        .avatar-preview {
            width: 160px; height: 160px; border-radius: 50%; object-fit: cover;
            border: 4px solid var(--primary-100); box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px; transition: all 0.3s;
        }
        .avatar-preview:hover { transform: scale(1.05); }
        
        .upload-btn-wrapper { position: relative; overflow: hidden; display: inline-block; margin-top: 10px; }
        .upload-btn-wrapper input[type=file] {
            font-size: 100px; position: absolute; left: 0; top: 0; opacity: 0; cursor: pointer;
        }

        /* Modal Styles */
        .crop-modal {
            display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%;
            overflow: auto; background-color: rgba(0,0,0,0.8); align-items: center; justify-content: center;
        }
        .crop-content {
            background-color: #fff; margin: auto; padding: 20px; border-radius: 12px;
            width: 90%; max-width: 500px; text-align: center; position: relative;
        }
        .img-container {
            max-height: 400px; margin-bottom: 20px; overflow: hidden;
        }
        .img-container img {
            max-width: 100%; display: block; /* Important for cropper */
        }
    </style>
</head>
<body>

<div class="app">
    <?php include '../inc/nav.php'; ?>

    <main class="main">
        <div class="profile-container">
            <?php include '../inc/alert.php'; ?>
            
            <div class="card p-5 border shadow-sm" style="border-radius:16px;">
                <div class="text-center mb-4">
                    <h2 class="text-primary m-0">ข้อมูลส่วนตัว</h2>
                    <p class="text-muted">จัดการข้อมูลและรูปโปรไฟล์ของคุณ</p>
                </div>

                <form method="POST" enctype="multipart/form-data" id="profileForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <div class="text-center mb-5">
                        <?php 
                            $imgSrc = "../assets/img/default_avatar.png"; 
                            if (!empty($user['profile_image'])) {
                                $imgSrc = "../uploads/profiles/" . $user['profile_image'];
                            }
                        ?>
                        <img src="<?= htmlspecialchars($imgSrc) ?>" id="finalPreview" class="avatar-preview">
                        
                        <div class="upload-btn-wrapper d-block">
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-4">
                                <i class="bi bi-camera"></i> เปลี่ยนรูปโปรไฟล์
                            </button>
                            <input type="file" id="imageInput" name="profile_image" accept="image/*">
                        </div>
                        <small class="text-muted d-block mt-2">รองรับ JPG, PNG (ระบบจะให้ท่านครอปรูปอัตโนมัติ)</small>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-bold">ชื่อ-นามสกุล</label>
                        <input type="text" name="name" class="input w-full" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-bold">อีเมล (Login)</label>
                        <input type="email" name="email" class="input w-full bg-light" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                    </div>

                    <div class="form-group mb-4">
                        <label class="font-bold">ตำแหน่ง / สังกัด</label>
                        <input type="text" name="role_position" class="input w-full" value="<?= htmlspecialchars($user['role_position'] ?? '') ?>" placeholder="เช่น อาจารย์ประจำสาขา...">
                    </div>

                    <div class="form-group mb-4">
                        <label class="font-bold">ระดับสิทธิ์</label>
                        <div class="badge info">
                            <?= ucfirst($user['role']) ?>
                        </div>
                    </div>

                    <hr class="mb-4">

                    <button type="submit" class="btn btn-primary w-full btn-lg shadow-sm">
                        <i class="bi bi-save"></i> บันทึกการเปลี่ยนแปลง
                    </button>
                </form>
            </div>
        </div>
    </main>
</div>

<div id="cropModal" class="crop-modal">
    <div class="crop-content">
        <h4 class="mb-3">ปรับขนาดรูปโปรไฟล์</h4>
        <div class="img-container">
            <img id="imageToCrop" src="">
        </div>
        <div class="stack-between">
            <button type="button" class="btn btn-muted" id="cancelCrop">ยกเลิก</button>
            <button type="button" class="btn btn-primary px-4" id="cropBtn">
                <i class="bi bi-crop"></i> ยืนยัน / ครอปรูป
            </button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<script>
    let cropper;
    const imageInput = document.getElementById('imageInput');
    const cropModal = document.getElementById('cropModal');
    const imageToCrop = document.getElementById('imageToCrop');
    const finalPreview = document.getElementById('finalPreview');
    const cropBtn = document.getElementById('cropBtn');
    const cancelCrop = document.getElementById('cancelCrop');

    // 1. เมื่อเลือกไฟล์
    imageInput.addEventListener('change', function(e) {
        const files = e.target.files;
        if (files && files.length > 0) {
            const file = files[0];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                // เอารูปใส่ใน Modal
                imageToCrop.src = e.target.result;
                
                // เปิด Modal (ใช้ Flex เพื่อจัดกึ่งกลาง)
                cropModal.style.display = 'flex'; 

                // ทำลาย Cropper ตัวเก่า (ถ้ามี)
                if (cropper) { cropper.destroy(); }

                // เริ่ม Cropper ใหม่
                cropper = new Cropper(imageToCrop, {
                    aspectRatio: 1, // บังคับสี่เหลี่ยมจัตุรัส 1:1
                    viewMode: 1,    // ไม่ให้ Crop เกินขอบรูป
                    autoCropArea: 0.8,
                });
            };
            reader.readAsDataURL(file);
        }
    });

    // 2. เมื่อกดปุ่ม "ยืนยัน / ครอปรูป"
    cropBtn.addEventListener('click', function() {
        if (!cropper) return;

        // แปลงส่วนที่ครอปเป็น Canvas -> Blob (ไฟล์ภาพ)
        cropper.getCroppedCanvas({
            width: 400,  // ย่อรูปให้ไม่ใหญ่เกินไป
            height: 400
        }).toBlob(function(blob) {
            
            // 2.1 แสดงตัวอย่างรูปที่ครอปแล้วทันที
            const url = URL.createObjectURL(blob);
            finalPreview.src = url;

            // 2.2 ยัดไฟล์ที่ครอปแล้ว กลับเข้าไปใน Input (เพื่อส่งไป PHP)
            // เทคนิคนี้ทำให้ PHP ไม่ต้องแก้โค้ดเลย เพราะมันนึกว่าเป็นไฟล์เดิม
            const file = new File([blob], "cropped_profile.jpg", { type: "image/jpeg" });
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            imageInput.files = dataTransfer.files;

            // ปิด Modal
            cropModal.style.display = 'none';
        }, 'image/jpeg');
    });

    // 3. ยกเลิก
    cancelCrop.addEventListener('click', function() {
        cropModal.style.display = 'none';
        imageInput.value = ''; // เคลียร์ไฟล์ที่เลือก
        if (cropper) { cropper.destroy(); }
    });
</script>

</body>
</html>