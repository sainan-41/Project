<?php
// delete_user.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
require 'db_connect.php';
@date_default_timezone_set('Asia/Bangkok');

// ต้องเป็นแอดมิน และใช้เฉพาะ POST
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'Unauthorized'], JSON_UNESCAPED_UNICODE); exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'Method Not Allowed'], JSON_UNESCAPED_UNICODE); exit;
}

$userId = (int)($_POST['id'] ?? 0);
if ($userId <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'รหัสผู้ใช้ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE); exit;
}

// เพื่อความชัดเจนเรื่องวันที่ (แม้ booking_date เป็น DATE อยู่แล้ว)
@$conn->query("SET time_zone = '+07:00'");

// 1) ยืนยันว่าเป็น role=user (กันลบแอดมิน)
$stmt = $conn->prepare("SELECT role FROM users WHERE user_id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$roleRes = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$roleRes) {
  echo json_encode(['ok'=>false,'error'=>'ไม่พบผู้ใช้ในระบบ'], JSON_UNESCAPED_UNICODE); exit;
}
if (($roleRes['role'] ?? '') !== 'user') {
  echo json_encode(['ok'=>false,'error'=>'ไม่สามารถลบผู้ใช้ประเภทนี้ได้'], JSON_UNESCAPED_UNICODE); exit;
}

/*
   2) เช็คฐานข้อมูลตามสคีมาล่าสุด:
      ตาราง bookings มี booking_date, payment_status, payment_verified, checkout_status, checkout_time
      นิยาม "มีการจองและการชำระเงินของวันนี้และยังไม่เช็คเอาท์":
        - booking_date = CURDATE()                       // จองวันนี้
        - payment_status='paid' AND payment_verified='approved' // ชำระและอนุมัติแล้ว
        - (checkout_time IS NULL OR checkout_status!='checked_out') // ยังไม่เช็คเอาท์
*/
$sql = "
  SELECT COUNT(*) AS cnt
  FROM bookings b
  WHERE b.user_id = ?
    AND b.booking_date = CURDATE()
    AND b.payment_status = 'paid'
    AND b.payment_verified = 'approved'
    AND (
      b.checkout_time IS NULL
      OR b.checkout_status IS NULL
      OR b.checkout_status <> 'checked_out'
    )
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$chk = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ((int)($chk['cnt'] ?? 0) > 0) {
  echo json_encode([
    'ok'=>false,
    'error'=>'ไม่สามารถลบข้อมูลได้: ผู้ใช้นี้มีการจอง “วันนี้” ที่ชำระเงินและอนุมัติแล้ว'
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

// ===== ผ่านเงื่อนไข สามารถลบได้ =====
$conn->begin_transaction();
try {
  // หมายเหตุ: ถ้ามี Foreign Key อ้างถึง users.user_id และตั้ง ON DELETE RESTRICT อาจลบไม่ได้
  // ที่นี่ลบเฉพาะในตาราง users และกำหนด role='user' เพื่อความปลอดภัย
  $del = $conn->prepare("DELETE FROM users WHERE user_id=? AND role='user' LIMIT 1");
  $del->bind_param("i", $userId);
  $del->execute();
  $affected = $del->affected_rows;
  $del->close();

  if ($affected < 1) {
    $conn->rollback();
    echo json_encode(['ok'=>false,'error'=>'ลบไม่สำเร็จ หรือผู้ใช้ถูกลบไปแล้ว'], JSON_UNESCAPED_UNICODE); exit;
  }

  $conn->commit();
  echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;

} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'เกิดข้อผิดพลาดระหว่างลบผู้ใช้'], JSON_UNESCAPED_UNICODE); exit;
}
