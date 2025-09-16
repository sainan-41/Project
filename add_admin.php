<?php
// add_admin.php
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
$hasRole       = hasCol($conn, 'users', 'role');
$hasProfilePic = hasCol($conn, 'users', 'profile_pic');
$hasCreatedAt  = hasCol($conn, 'users', 'created_at');

$fullname = trim($_POST['fullname'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($fullname === '' || $email === '' || $password === '') {
  http_response_code(400);
  echo json_encode(['error' => 'ข้อมูลไม่ครบถ้วน'], JSON_UNESCAPED_UNICODE);
  exit();
}

// ตรวจอีเมลซ้ำ
$chk = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
$chk->bind_param("s", $email);
$chk->execute();
$dup = $chk->get_result()->fetch_assoc();
$chk->close();
if ($dup) {
  http_response_code(400);
  echo json_encode(['error' => 'อีเมลนี้ถูกใช้งานแล้ว'], JSON_UNESCAPED_UNICODE);
  exit();
}

$username = null;
if ($hasUsername) {
  $username = trim($_POST['username'] ?? '');
  if ($username !== '') {
    $cu = $conn->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
    $cu->bind_param("s", $username);
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

$phone = ($hasPhone) ? trim($_POST['phone'] ?? '') : null;

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

$hashed = password_hash($password, PASSWORD_DEFAULT);

// สร้าง INSERT dynamic
$cols = ['fullname','email','password'];
$qs   = ['?','?','?'];
$tys  = 'sss';
$val  = [$fullname, $email, $hashed];

if ($hasUsername)   { $cols[]='username';   $qs[]='?'; $tys.='s'; $val[]=$username; }
if ($hasPhone)      { $cols[]='phone';      $qs[]='?'; $tys.='s'; $val[]=$phone; }
if ($hasRole)       { $cols[]='role';       $qs[]='?'; $tys.='s'; $val[]='admin'; }
if ($hasProfilePic) { $cols[]='profile_pic';$qs[]='?'; $tys.='s'; $val[]=$profile_pic; }
if ($hasCreatedAt)  { $cols[]='created_at'; $qs[]='NOW()'; /* not bind */ }

$sql = "INSERT INTO users (".implode(',', $cols).") VALUES (".implode(',', $qs).")";
$stmt = $conn->prepare($sql);

// bind (ถ้ามี NOW() จะไม่อยู่ใน bind)
if ($hasCreatedAt) {
  $stmt->bind_param($tys, ...$val);
} else {
  $stmt->bind_param($tys, ...$val);
}

$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
  http_response_code(500);
  echo json_encode(['error' => 'เพิ่มแอดมินไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
  exit();
}
echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
