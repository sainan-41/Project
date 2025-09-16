<?php
/** 
 * edit_admin_profile.php — หน้าแก้ไขโปรไฟล์แอดมิน (โปรเฟสชันนัล)
 * Stack: PHP 8+, MySQLi (prepared), Bootstrap 5, Bootstrap Icons
 * คุณสมบัติหลัก:
 *  - อัปเดตชื่อ-อีเมล-เบอร์โทร
 *  - เปลี่ยนรหัสผ่าน (ไม่บังคับ)
 *  - อัปโหลดรูปโปรไฟล์พร้อมพรีวิวทันที (JPG/PNG/WEBP ≤ 2MB)
 *  - ลบรูปโปรไฟล์ (ตั้งค่าเป็น NULL)
 *  - ปลอดภัย: CSRF token, ตรวจ mimetype, จำกัดขนาดไฟล์, ใช้ prepared statements
 *  - สอดคล้องสคีมา: ตาราง users(field: username, fullname, email, phone, profile_pic, password, role)
 *
 * ความต้องการ:
 *  - มีไฟล์ db_connect.php คืนค่า $conn (mysqli)
 *  - โฟลเดอร์ /uploads/avatars/ (เว็บเขียนได้) ถ้าไม่มีสคริปต์จะสร้างให้
 */

session_start();
require_once 'db_connect.php';

// ===== ตรวจสอบสิทธิ์ =====
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  header('Location: login.php');
  exit();
}
$admin_id = (int)$_SESSION['user_id'];

// ===== CSRF Token =====
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ===== ดึงข้อมูลปัจจุบัน =====
$stmt = $conn->prepare("SELECT username, fullname, email, phone, profile_pic FROM users WHERE user_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$current = $stmt->get_result()->fetch_assoc();
$stmt->close();

$errors = [];
$success = "";

// ===== สร้างโฟลเดอร์อัปโหลดถ้ายังไม่มี =====
$uploadDir = __DIR__ . '/uploads/avatars/';
$uploadWeb = 'uploads/avatars/'; // path ที่เก็บใน DB/แสดงผล
if (!is_dir($uploadDir)) {
  @mkdir($uploadDir, 0775, true);
}

// ===== Handle POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // ตรวจ CSRF
  if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $errors[] = "คำขอไม่ถูกต้อง (CSRF)";
  } else {
    // รับค่า
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $remove_pic = isset($_POST['remove_pic']) ? (int)$_POST['remove_pic'] : 0;

    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // ตรวจความถูกต้องเบื้องต้น
    if ($fullname === '') $errors[] = "กรุณากรอกชื่อ-นามสกุล";
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "อีเมลไม่ถูกต้อง";
    if ($phone !== '' && !preg_match('/^[0-9+\-\s]{6,20}$/', $phone)) $errors[] = "เบอร์โทรไม่ถูกต้อง";

    // ตรวจรหัสผ่านถ้ามีการเปลี่ยน
    $password_hash = null;
    if ($new_password !== '' || $confirm_password !== '') {
      if (strlen($new_password) < 8) $errors[] = "รหัสผ่านใหม่อย่างน้อย 8 ตัวอักษร";
      if ($new_password !== $confirm_password) $errors[] = "ยืนยันรหัสผ่านไม่ตรงกัน";
      if (!$errors) {
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
      }
    }

    // จัดการรูปโปรไฟล์
    $finalProfilePath = $current['profile_pic']; // ค่าเริ่มจากเดิม
    $oldPath = $current['profile_pic'];

    // ลบรูป
    if ($remove_pic === 1) {
      $finalProfilePath = null;
      // ลบไฟล์เก่า (ถ้าเป็นไฟล์ของเราและมีอยู่)
      if (!empty($oldPath)) {
        $absOld = __DIR__ . '/' . ltrim($oldPath, '/');
        if (is_file($absOld)) @unlink($absOld);
      }
    }

    // อัปโหลดไฟล์ใหม่ (ถ้าไม่ติ๊ก "ลบรูป")
    if ($remove_pic !== 1 && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
      $file = $_FILES['profile_pic'];

      if ($file['error'] === UPLOAD_ERR_OK) {
        // ขนาดไม่เกิน 2MB
        if ($file['size'] > 2 * 1024 * 1024) {
          $errors[] = "ไฟล์ใหญ่เกิน 2MB";
        } else {
          // ตรวจ MIME จริง
          $finfo = new finfo(FILEINFO_MIME_TYPE);
          $mime = $finfo->file($file['tmp_name']);
          $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
          ];
          if (!isset($allowed[$mime])) {
            $errors[] = "อนุญาตเฉพาะ JPG, PNG, WEBP เท่านั้น";
          } else {
            $ext = $allowed[$mime];
            $safeName = sprintf('u%05d_%s_%04d.%s',
              $admin_id,
              date('YmdHis'),
              random_int(1000, 9999),
              $ext
            );
            $destPath = $uploadDir . $safeName;
            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
              $errors[] = "อัปโหลดไฟล์ล้มเหลว";
            } else {
              // ลบรูปเก่าถ้ามี
              if (!empty($oldPath)) {
                $absOld = __DIR__ . '/' . ltrim($oldPath, '/');
                if (is_file($absOld)) @unlink($absOld);
              }
              $finalProfilePath = $uploadWeb . $safeName;
            }
          }
        }
      } else {
        $errors[] = "อัปโหลดไฟล์ผิดพลาด (code: {$file['error']})";
      }
    }

    // ถ้าไม่มี error — อัปเดตฐานข้อมูล
    if (!$errors) {
      // อัปเดตข้อมูลทั่วไป
      if ($password_hash === null) {
        $stmt = $conn->prepare("UPDATE users SET fullname=?, email=?, phone=?, profile_pic=? WHERE user_id=?");
        $stmt->bind_param("ssssi", $fullname, $email, $phone, $finalProfilePath, $admin_id);
      } else {
        $stmt = $conn->prepare("UPDATE users SET fullname=?, email=?, phone=?, profile_pic=?, password=? WHERE user_id=?");
        $stmt->bind_param("sssssi", $fullname, $email, $phone, $finalProfilePath, $password_hash, $admin_id);
      }
      if ($stmt->execute()) {
        $success = "บันทึกโปรไฟล์เรียบร้อยแล้ว";
        $stmt->close();

        // รีเฟรชข้อมูลปัจจุบัน
        $stmt2 = $conn->prepare("SELECT username, fullname, email, phone, profile_pic FROM users WHERE user_id = ?");
        $stmt2->bind_param("i", $admin_id);
        $stmt2->execute();
        $current = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
      } else {
        $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
        $stmt->close();
      }
      // หมุน CSRF token ใหม่หลังบันทึก
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
      $csrf_token = $_SESSION['csrf_token'];
    }
  }
}

// ===== Helper สำหรับรูป =====
function avatarUrl($path) {
  $fallback = 'https://cdn.jsdelivr.net/gh/itsrealfaris/placeholder/512x512-user.png';
  if (empty($path)) return $fallback;
  return htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>แก้ไขโปรไฟล์แอดมิน</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
  body{background:#f5f7fb}
  .page-wrap{max-width:980px;margin:32px auto;padding:0 16px}
  .glass{
    background:#fff;border-radius:20px;overflow:hidden;
    box-shadow:0 10px 30px rgba(0,0,0,.08)
  }
  .hero{
    background:linear-gradient(135deg,#515151ff,#515151ff);
    color:#fff;padding:28px 24px;position:relative
  }
  .hero .title{font-weight:700;font-size:1.4rem}
  .card-body{padding:28px}
  .avatar-wrap{position:relative;display:inline-block}
  .avatar{
    width:140px;height:140px;border-radius:50%;object-fit:cover;
    border:3px solid #2155ffff;box-shadow:0 6px 20px rgba(0,0,0,.15)
  }
  .btn-change{
    position:absolute;right:6px;bottom:6px;border-radius:999px;
  }
  .form-label{font-weight:600}
  .muted{color:#fff}
  .pill{
    border-radius:999px;padding:.35rem .9rem;font-weight:600
  }
  .divider{height:1px;background:#eef0f4;margin:18px 0}
  .help{font-size:.875rem;color:#6b7280}
  .required:after{content:" *";color:#ef4444}
</style>
</head>
<body>
<div class="page-wrap">
  <div class="glass">
    <div class="hero d-flex align-items-center justify-content-between">
      <div>
        <div class="title">แก้ไขโปรไฟล์แอดมิน</div>
        <div class="muted">อัปเดตข้อมูลส่วนตัว รูปโปรไฟล์ และรหัสผ่าน</div>
      </div>
      <span class="badge bg-light text-dark pill"><i class="bi bi-shield-lock me-1"></i>Administrator</span>
    </div>

    <div class="card-body">
      <?php if ($success): ?>
        <div class="alert alert-success d-flex align-items-center" role="alert">
          <i class="bi bi-check-circle-fill me-2"></i>
          <div><?= htmlspecialchars($success) ?></div>
        </div>
      <?php endif; ?>

      <?php if ($errors): ?>
        <div class="alert alert-danger" role="alert">
          <div class="fw-semibold mb-1">ไม่สามารถบันทึกได้:</div>
          <ul class="mb-0">
            <?php foreach($errors as $e): ?>
              <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <!-- รูปโปรไฟล์ -->
        <div class="mb-4">
          <label class="form-label d-block">รูปโปรไฟล์</label>
          <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="avatar-wrap">
              <img id="avatarPreview" class="avatar" src="<?= avatarUrl($current['profile_pic']) ?>" alt="Avatar">
              <label class="btn btn-dark btn-sm btn-change" for="profile_pic" title="เปลี่ยนรูป">
                <i class="bi bi-camera"></i>
              </label>
            </div>
            <div>
              <input class="form-control" type="file" id="profile_pic" name="profile_pic" accept=".jpg,.jpeg,.png,.webp">
              <div class="form-text">รองรับ JPG/PNG/WEBP ขนาดไม่เกิน 2MB</div>
              <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" value="1" id="remove_pic" name="remove_pic">
                <label class="form-check-label" for="remove_pic">ลบรูปปัจจุบัน</label>
              </div>
            </div>
          </div>
        </div>

        <div class="divider"></div>

        <!-- ข้อมูลทั่วไป -->
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label required">ชื่อ-นามสกุล</label>
            <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($current['fullname']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">ชื่อผู้ใช้</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($current['username']) ?>" disabled>
            <div class="help">ชื่อผู้ใช้ไม่สามารถแก้ไขได้</div>
          </div>
          <div class="col-md-6">
            <label class="form-label required">อีเมล</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($current['email']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">เบอร์โทร</label>
            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($current['phone']) ?>">
          </div>
        </div>

        <div class="divider"></div>

        <!-- เปลี่ยนรหัสผ่าน (ไม่บังคับ) -->
        <div class="mb-3">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <label class="form-label mb-0">เปลี่ยนรหัสผ่าน (ไม่บังคับ)</label>
            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#pwBox">
              <i class="bi bi-key me-1"></i> แสดง/ซ่อน
            </button>
          </div>
          <div id="pwBox" class="collapse">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">รหัสผ่านใหม่</label>
                <input type="password" name="new_password" class="form-control" placeholder="อย่างน้อย 8 ตัวอักษร">
              </div>
              <div class="col-md-6">
                <label class="form-label">ยืนยันรหัสผ่านใหม่</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="พิมพ์ซ้ำอีกครั้ง">
              </div>
            </div>
            <div class="help mt-2">หากไม่ต้องการเปลี่ยน ปล่อยว่างได้เลย</div>
          </div>
        </div>

        <div class="d-flex gap-2 mt-4">
          <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-save me-1"></i> บันทึกการเปลี่ยนแปลง
          </button>
          <a href="admin_profile.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> กลับหน้าโปรไฟล์
          </a>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// พรีวิวภาพ + ตรวจขนาดฝั่ง client
const input = document.getElementById('profile_pic');
const preview = document.getElementById('avatarPreview');
const removeCb = document.getElementById('remove_pic');

input?.addEventListener('change', () => {
  const f = input.files?.[0];
  if (!f) return;
  if (f.size > 2 * 1024 * 1024) {
    alert('ไฟล์ใหญ่เกิน 2MB');
    input.value = '';
    return;
  }
  const url = URL.createObjectURL(f);
  preview.src = url;
  removeCb.checked = false; // ถ้าอัปโหลดใหม่ ยกเลิกลบ
});

// ถ้าติ๊ก "ลบรูป" ให้ล้างไฟล์อินพุตและพรีวิวเป็น placeholder
removeCb?.addEventListener('change', () => {
  if (removeCb.checked) {
    input.value = '';
    preview.src = 'https://cdn.jsdelivr.net/gh/itsrealfaris/placeholder/512x512-user.png';
  } else {
    // คงรูปเดิมไว้หากยกเลิกติ๊ก (รีโหลดหน้าเพื่อดึงรูปเดิมหากจำเป็น)
  }
});
</script>
</body>
</html>
