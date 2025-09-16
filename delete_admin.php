<?php
// delete_admin.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require 'db_connect.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  http_response_code(403);
  echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
  exit();
}

// รับ JSON
$input = json_decode(file_get_contents('php://input'), true);
$targetId = (int)($input['user_id'] ?? 0);
$csrf     = $input['csrf_token'] ?? '';

if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid CSRF token'], JSON_UNESCAPED_UNICODE);
  exit();
}
if ($targetId === (int)$_SESSION['user_id']) {
  http_response_code(400);
  echo json_encode(['error' => 'ไม่สามารถลบบัญชีของตัวเองได้'], JSON_UNESCAPED_UNICODE);
  exit();
}

// ถ้ามีคอลัมน์ role ให้จำกัดลบเฉพาะผู้ที่เป็น admin เท่านั้น
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
$hasRole = hasCol($conn, 'users', 'role');

if ($hasRole) {
  // ตรวจว่าเป้าหมายเป็น admin จริง
  $st = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
  $st->bind_param("i", $targetId);
  $st->execute();
  $u = $st->get_result()->fetch_assoc();
  $st->close();

  if (!$u || ($u['role'] ?? '') !== 'admin') {
    http_response_code(404);
    echo json_encode(['error' => 'ไม่พบผู้ใช้หรือไม่ใช่แอดมิน'], JSON_UNESCAPED_UNICODE);
    exit();
  }
}

// ลบ
$del = $conn->prepare("DELETE FROM users WHERE user_id = ?");
$del->bind_param("i", $targetId);
$ok = $del->execute();
$del->close();

if (!$ok) {
  http_response_code(500);
  echo json_encode(['error' => 'ลบไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
  exit();
}
echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
