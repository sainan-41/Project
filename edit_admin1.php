<?php
// edit_admin.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require 'db_connect.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  http_response_code(403);
  echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
  exit();
}
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid CSRF token'], JSON_UNESCAPED_UNICODE);
  exit();
}

function hasCol(mysqli $conn, string $table, string $col): bool {
  $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
  $st = $conn->prepare($sql);
  $st->bind_param("ss", $table, $col);
  $st->execute();
  $ok = (bool)$st->get_result()->fetch_row();
  $st->close();
  return $ok;
}
$hasUsername   = hasCol($conn, 'users', 'username');
$hasPhone      = hasCol($conn, 'users', 'phone');
$hasProfilePic = hasCol($conn, 'users', 'profile_pic');

$user_id = (int)($_POST['user_id'] ?? 0);
$fullname = trim($_POST['fullname'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!$user_id || $fullname === '' || $email === '') {
  http_response_code(400);
  echo json_encode(['error' => 'ข้อมูลไม่ครบถ้วน'], JSON_UNESCAPED_UNICODE);
  exit();
}

// อีเมลซ้ำ
$chk = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id <> ? LIMIT 1");
$chk->bind_param("si", $email, $user_id);
$chk->execute();
$dup = $chk->get_result()->fetch_assoc();
$chk->close();
if ($dup) {
  http_response_code(400);
  echo json_encode(['error' => 'อีเมลนี้ถูกใช้งานแล้ว'], JSON_UNESCAPED_UNICODE);
  exit();
}

// username (ถ้ามี)
$username = null;
if ($hasUsername) {
  $username = trim($_POST['username'] ?? '');
  if ($username !== '') {
    $cu = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id <> ? LIMIT 1");
    $cu->bind_param("si", $username, $user_id);
    $cu->execute();
    $dupU = $cu->get_result()->fetch_assoc();
    $cu->close();
    if ($dupU) {
      http_response_code(400);
      echo json_encode(['error' => 'ชื่อผู้ใช้ถูกใช้งานแล้ว'], JSON_UNESCAPED_UNICODE);
      exit();
    }
  }
}

// อัปโหลดรูป (ถ้ามี)
$profile_pic = null;
if ($hasProfilePic && !empty($_FILES['profile_pic']['name'])) {
  $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
  $allowed = ['jpg','jpeg','png','webp','gif'];
  if (!in_array($ext, $allowed)) {
    http_response_code(400);
    echo json_encode(['error' => 'ไฟล์รูปไม่รองรับ'], JSON_UNESCAPED_UNICODE);
    exit();
  }
  if (!is_dir('uploads')) { @mkdir('uploads', 0755, true); }
  $filename = 'uploads/admin_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
  if (!move_uploaded_file($_FILES['profile_pic']['tmp_name'], $filename)) {
    http_response_code(500);
    echo json_encode(['error' => 'อัปโหลดรูปไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
    exit();
  }
  $profile_pic = $filename;
}

// สร้าง UPDATE
$sets  = ['fullname = ?', 'email = ?'];
$types = 'ss';
$vals  = [$fullname, $email];

if ($hasUsername) { $sets[] = 'username = ?'; $types .= 's'; $vals[] = $username; }
if ($hasPhone)    { $phone = trim($_POST['phone'] ?? ''); $sets[] = 'phone = ?'; $types .= 's'; $vals[] = $phone; }
if ($password !== '') {
  $hashed = password_hash($password, PASSWORD_DEFAULT);
  $sets[] = 'password = ?'; $types .= 's'; $vals[] = $hashed;
}
if ($hasProfilePic && $profile_pic) {
  $sets[] = 'profile_pic = ?'; $types .= 's'; $vals[] = $profile_pic;
}

$types .= 'i';
$vals[] = $user_id;

$sql = "UPDATE users SET ".implode(', ', $sets)." WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$vals);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
  http_response_code(500);
  echo json_encode(['error' => 'แก้ไขไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
  exit();
}

// อัปเดต session ถ้าคนที่แก้คือแอดมินคนเดิม (ให้ sidebar เปลี่ยนทันที ถ้าโค้ดคุณใช้ session ตรงๆ ที่อื่น)
if ($hasProfilePic && $profile_pic && isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $user_id) {
  $_SESSION['profile_pic'] = $profile_pic;
}

echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
