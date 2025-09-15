<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0'); error_reporting(E_ERROR|E_PARSE);
date_default_timezone_set('Asia/Bangkok');

$respond = function(int $code, array $data) {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
};

try {
  require 'db_connect.php';
  if (!isset($conn) || !($conn instanceof mysqli)) {
    $respond(500, ['error'=>'DB connection not available']);
  }
  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

  if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    $respond(403, ['error'=>'Unauthorized']);
  }

  $warnMin = max(1, (int)($_GET['warn_min'] ?? 10));

  // ใช้ session เพื่อไม่นับรายการที่ลบ/อ่านแล้ว (แก้เฉพาะจุด)
  $_SESSION['viewed_payment_ids']         = $_SESSION['viewed_payment_ids']         ?? [];
  $_SESSION['deleted_payment_ids']        = $_SESSION['deleted_payment_ids']        ?? [];
  $_SESSION['deleted_expire_booking_ids'] = $_SESSION['deleted_expire_booking_ids'] ?? [];
  $_SESSION['viewed_expire_booking_ids']  = $_SESSION['viewed_expire_booking_ids']  ?? [];

  $deletedSlips  = array_map('intval', $_SESSION['deleted_payment_ids']);
  $viewedSlips   = array_map('intval', $_SESSION['viewed_payment_ids']);
  $deletedExpire = array_map('intval', $_SESSION['deleted_expire_booking_ids']);
  $viewedExpire  = array_map('intval', $_SESSION['viewed_expire_booking_ids']);

  $tz = new DateTimeZone('Asia/Bangkok');
  $todayStart    = (new DateTime('today', $tz))->format('Y-m-d 00:00:00');
  $tomorrowStart = (new DateTime('tomorrow', $tz))->format('Y-m-d 00:00:00');
  $now  = new DateTime('now', $tz);
  $edge = (clone $now)->modify("+{$warnMin} minutes");
  $nowStr  = $now->format('Y-m-d H:i:s');
  $edgeStr = $edge->format('Y-m-d H:i:s');

  // 1) pending วันนี้ (ข้ามที่ลบ/ที่อ่านแล้ว)
  $st1 = $conn->prepare("SELECT payment_id FROM payments
                         WHERE payment_verified='pending'
                           AND created_at >= ? AND created_at < ?");
  $st1->bind_param('ss', $todayStart, $tomorrowStart);
  $st1->execute(); $rs1 = $st1->get_result();
  $unreadCount = 0;
  while ($r = $rs1->fetch_assoc()) {
    $pid = (int)$r['payment_id'];
    if (in_array($pid, $deletedSlips, true)) continue;
    if (in_array($pid, $viewedSlips,  true)) continue;
    $unreadCount++;
  }
  $st1->close();

  // 2) เช็คอินแล้ว วันนี้ ใกล้หมดเวลาใน warnMin นาที (ครอบคลุมต่อเวลาแล้วเช็คอิน) + ข้ามที่ลบ/อ่านแล้ว
  $endExpr = "STR_TO_DATE(CONCAT(b.booking_date,' ', b.booking_end_time), '%Y-%m-%d %H:%i:%s')";
  $sqlExp = "SELECT b.booking_id
             FROM bookings b
             WHERE (b.checkin_time IS NOT NULL AND b.checkin_time <> '' AND b.checkin_time <> '0000-00-00 00:00:00')
               AND (b.checkout_time IS NULL OR b.checkout_time = '' OR b.checkout_time = '0000-00-00 00:00:00')
               AND b.booking_date = CURDATE()
               AND $endExpr BETWEEN ? AND ?
               AND $endExpr < ?";
  $st2 = $conn->prepare($sqlExp);
  $st2->bind_param('sss', $nowStr, $edgeStr, $tomorrowStart);
  $st2->execute(); $rs2 = $st2->get_result();

  $expSoon = 0;
  while ($row = $rs2->fetch_assoc()) {
    $bid = (int)$row['booking_id'];
    if (in_array($bid, $deletedExpire, true)) continue;
    if (in_array($bid, $viewedExpire, true)) continue;
    $expSoon++;
  }
  $st2->close();

  $respond(200, ['unread_slips'=>$unreadCount, 'about_to_expire'=>$expSoon]);

} catch (Throwable $e) {
  $respond(500, ['error'=>'SERVER_ERROR', 'message'=>$e->getMessage()]);
}
