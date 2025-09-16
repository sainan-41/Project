<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
require 'db_connect.php';
date_default_timezone_set('Asia/Bangkok');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ต้องเป็นแอดมิน
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  http_response_code(403);
  echo json_encode(['ok'=>false, 'error'=>'Unauthorized'], JSON_UNESCAPED_UNICODE); exit;
}

$desk_id       = intval($_REQUEST['desk_id'] ?? 0);
$booking_id    = intval($_REQUEST['booking_id'] ?? 0);
$booking_date  = $_REQUEST['booking_date'] ?? '';
$old_end_time  = $_REQUEST['old_end_time'] ?? '';
$new_end_time  = $_REQUEST['new_end_time'] ?? ''; // optional

if (!$desk_id || !$booking_id || !$booking_date || !$old_end_time) {
  echo json_encode(['ok'=>false, 'error'=>'ข้อมูลไม่ครบ'], JSON_UNESCAPED_UNICODE); exit;
}

// ดึงข้อมูลการจองปัจจุบัน + ราคา/ชม. + ข้อมูลลูกค้าเดิม
$stmt = $conn->prepare("
  SELECT b.booking_id, b.user_id, b.customer_name, b.desk_id, b.booking_date,
         b.booking_start_time, b.booking_end_time,
         b.payment_status, b.payment_verified, b.checkin_status,
         d.price_per_hour
  FROM bookings b
  JOIN desks d ON d.desk_id = b.desk_id
  WHERE b.booking_id = ? AND b.desk_id = ? AND b.booking_date = ?
  LIMIT 1
");
$stmt->bind_param('iis', $booking_id, $desk_id, $booking_date);
$stmt->execute();
$cur = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cur) {
  echo json_encode(['ok'=>false, 'error'=>'ไม่พบรายการจอง'], JSON_UNESCAPED_UNICODE); exit;
}

// เงื่อนไขสิทธิ์
$paymentVerified = $cur['payment_verified'] ?? 'pending';
$paymentStatus   = $cur['payment_status']   ?? 'pending';
$checkinStatus   = $cur['checkin_status']   ?? null;

if ($paymentStatus === 'cancelled' || $paymentVerified === 'rejected') {
  echo json_encode(['ok'=>false, 'error'=>'รายการนี้ถูกยกเลิกหรือสลิปถูกปฏิเสธ'], JSON_UNESCAPED_UNICODE); exit;
}
if ($checkinStatus !== 'checked_in') {
  echo json_encode(['ok'=>false, 'error'=>'ต้องเช็คอินก่อนจึงจะต่อเวลาได้'], JSON_UNESCAPED_UNICODE); exit;
}

$pricePerHour = (float)($cur['price_per_hour'] ?? 0.0);
$oldEnd       = substr(($cur['booking_end_time'] ?: $old_end_time), 0, 5);

// หาเวลาสูงสุดที่ต่อได้ (ก่อนการจองถัดไป)
$stmt = $conn->prepare("
  SELECT booking_start_time
  FROM bookings
  WHERE desk_id = ?
    AND booking_date = ?
    AND booking_id <> ?
    AND (payment_status <> 'cancelled')
    AND (payment_verified <> 'rejected')
    AND booking_start_time > ?
  ORDER BY booking_start_time ASC
  LIMIT 1
");
$stmt->bind_param('isis', $desk_id, $booking_date, $booking_id, $oldEnd);
$stmt->execute();
$next = $stmt->get_result()->fetch_assoc();
$stmt->close();

$maxEnd = $next ? substr($next['booking_start_time'], 0, 5) : '23:59';

// ============== โหมดตรวจสอบ (GET หรือไม่มี finalize) ==============
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' || !isset($_POST['finalize'])) {
  if ($new_end_time) {
    $newEnd = substr($new_end_time, 0, 5);
    if ($newEnd <= $oldEnd) {
      echo json_encode(['ok'=>false, 'error'=>'เวลาใหม่ต้องมากกว่าเวลาเดิม', 'max_end_time'=>$maxEnd], JSON_UNESCAPED_UNICODE); exit;
    }
    if ($newEnd > $maxEnd) {
      echo json_encode(['ok'=>false, 'error'=>"ต่อเวลาเกินช่วงที่ว่าง (สูงสุดถึง $maxEnd)", 'max_end_time'=>$maxEnd], JSON_UNESCAPED_UNICODE); exit;
    }
    // ตรวจทับซ้อน hard-check
    $stmt = $conn->prepare("
      SELECT 1
      FROM bookings
      WHERE desk_id = ?
        AND booking_date = ?
        AND booking_id <> ?
        AND (payment_status <> 'cancelled')
        AND (payment_verified <> 'rejected')
        AND NOT (booking_end_time <= ? OR booking_start_time >= ?)
      LIMIT 1
    ");
    $stmt->bind_param('isiss', $desk_id, $booking_date, $booking_id, $oldEnd, $newEnd);
    $stmt->execute();
    $overlap = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($overlap) {
      echo json_encode(['ok'=>false, 'error'=>'มีการจองทับซ้อน ไม่สามารถต่อเวลา', 'max_end_time'=>$maxEnd], JSON_UNESCAPED_UNICODE); exit;
    }
  }

  echo json_encode([
    'ok' => true,
    'price_per_hour' => $pricePerHour,
    'max_end_time'   => $maxEnd
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

// ============== โหมดยืนยันต่อเวลา (POST finalize=1) ==============
// เงื่อนไขใหม่ตามที่ต้องการ: สร้าง "การจองใหม่" ทันที + ชำระเงินสด approved โดยอัตโนมัติ
$finalize = ($_POST['finalize'] ?? '') === '1';
if ($finalize) {
  $newEnd = substr(($new_end_time ?: ''), 0, 5);
  if (!$newEnd) {
    echo json_encode(['ok'=>false, 'error'=>'กรุณาระบุเวลาสิ้นสุดใหม่'], JSON_UNESCAPED_UNICODE); exit;
  }
  if ($newEnd <= $oldEnd) {
    echo json_encode(['ok'=>false, 'error'=>'เวลาใหม่ต้องมากกว่าเวลาเดิม'], JSON_UNESCAPED_UNICODE); exit;
  }
  if ($newEnd > $maxEnd) {
    echo json_encode(['ok'=>false, 'error'=>"ต่อเวลาเกินช่วงที่ว่าง (สูงสุดถึง $maxEnd)"], JSON_UNESCAPED_UNICODE); exit;
  }
  // ตรวจทับซ้อนอีกครั้ง
  $stmt = $conn->prepare("
    SELECT 1
    FROM bookings
    WHERE desk_id = ?
      AND booking_date = ?
      AND booking_id <> ?
      AND (payment_status <> 'cancelled')
      AND (payment_verified <> 'rejected')
      AND NOT (booking_end_time <= ? OR booking_start_time >= ?)
    LIMIT 1
  ");
  $stmt->bind_param('isiss', $desk_id, $booking_date, $booking_id, $oldEnd, $newEnd);
  $stmt->execute();
  $overlap = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if ($overlap) {
    echo json_encode(['ok'=>false, 'error'=>'มีการจองทับซ้อน ไม่สามารถต่อเวลา'], JSON_UNESCAPED_UNICODE); exit;
  }

  // คำนวณยอดจากช่วงที่ต่อเพิ่ม
  $toMinutes = function(string $hhmm): int {
    [$h,$m] = array_map('intval', explode(':', $hhmm));
    return $h*60 + $m;
  };
  $addMin = max(0, $toMinutes($newEnd) - $toMinutes($oldEnd));
  $hours  = $addMin / 60.0;
  $amount = round($hours * $pricePerHour, 2);

  // 1) สร้าง "การจองใหม่" (start = oldEnd, end = newEnd)
  $new_user_id      = $cur['user_id'] ?? null;
  $new_customer     = $cur['customer_name'] ?? null;

  $stmt = $conn->prepare("
    INSERT INTO bookings
      (user_id, desk_id, customer_name, booking_date, booking_start_time, booking_end_time,
       payment_status, payment_verified, created_at, updated_at, checkin_status)
    VALUES
      (?, ?, ?, ?, ?, ?, 'paid', 'approved', NOW(), NOW(), 'checked_in')
  ");
  $stmt->bind_param('iissss', $new_user_id, $desk_id, $new_customer, $booking_date, $oldEnd, $newEnd);
  $stmt->execute();
  $new_booking_id = $stmt->insert_id;
  $stmt->close();

  // 2) บันทึกการชำระแบบเงินสด (approved) ผูกกับ "การจองใหม่"
  $now = date('Y-m-d H:i:s');
  $method = 'cash';
  $slip   = '';
  $verified_status = 'approved';

  $stmt = $conn->prepare("INSERT INTO payments (booking_id, payment_method, amount, payment_time, slip, payment_verified, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?)");
  $stmt->bind_param('isdssss', $new_booking_id, $method, $amount, $now, $slip, $verified_status, $now);
  $stmt->execute();
  $payment_id = $stmt->insert_id;
  $stmt->close();

  echo json_encode([
    'ok'            => true,
    'new_booking_id'=> (int)$new_booking_id,
    'payment_id'    => (int)$payment_id,
    'amount'        => (float)$amount,
    'start_time'    => $oldEnd,
    'new_end_time'  => $newEnd
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

echo json_encode(['ok'=>false, 'error'=>'Invalid request'], JSON_UNESCAPED_UNICODE);
