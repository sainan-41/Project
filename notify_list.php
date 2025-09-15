<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0'); error_reporting(E_ERROR|E_PARSE);
date_default_timezone_set('Asia/Bangkok');

try {
  require 'db_connect.php';
  if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['error'=>'DB connection not available'], JSON_UNESCAPED_UNICODE); exit;
  }
  if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error'=>'Unauthorized'], JSON_UNESCAPED_UNICODE); exit;
  }
  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

  $warnMin = max(1, (int)($_GET['warn_min'] ?? 10));

  // ใช้ session เพื่อส่งสถานะ viewed และข้าม deleted (แก้เฉพาะจุด)
  $_SESSION['viewed_payment_ids']         = $_SESSION['viewed_payment_ids']         ?? [];
  $_SESSION['deleted_payment_ids']        = $_SESSION['deleted_payment_ids']        ?? [];
  $_SESSION['viewed_expire_booking_ids']  = $_SESSION['viewed_expire_booking_ids']  ?? [];
  $_SESSION['deleted_expire_booking_ids'] = $_SESSION['deleted_expire_booking_ids'] ?? [];

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

  $results = [];

  // 1) Payments วันนี้ (pending) — ไม่มี p.user_id
  $sqlPay = "SELECT p.payment_id, p.booking_id, p.amount, p.created_at
             FROM payments p
             WHERE p.payment_verified='pending'
               AND p.created_at >= ?
               AND p.created_at <  ?
             ORDER BY p.created_at DESC
             LIMIT 200";
  $st1 = $conn->prepare($sqlPay);
  $st1->bind_param('ss', $todayStart, $tomorrowStart);
  $st1->execute();
  for ($rs1 = $st1->get_result(); $r = $rs1->fetch_assoc(); ) {
    $pid = (int)$r['payment_id'];
    if (in_array($pid, $deletedSlips, true)) continue; // ข้ามที่ลบ
    $results[] = [
      'notif_type' => 'payment_today',
      'ref_id'     => $pid,
      'booking_id' => isset($r['booking_id']) ? (int)$r['booking_id'] : null,
      'desk_id'    => null,
      'ref_time'   => $r['created_at'],
      'message'    => 'ชำระเงินรอตรวจสอบ ' . number_format((float)$r['amount'], 2) . ' บาท',
      'viewed'     => in_array($pid, $viewedSlips, true) ? 1 : 0, // ส่งสถานะ viewed
    ];
  }
  $st1->close();

  // 2) เช็คอินแล้ว วันนี้ ใกล้หมดเวลาในอีก warnMin นาที (ครอบคลุมต่อเวลาแล้วเช็คอิน)
  $endExpr = "STR_TO_DATE(CONCAT(b.booking_date,' ', b.booking_end_time), '%Y-%m-%d %H:%i:%s')";
  $sqlBk = "SELECT b.booking_id, b.desk_id, $endExpr AS end_dt
            FROM bookings b
            WHERE (b.checkin_time IS NOT NULL AND b.checkin_time <> '' AND b.checkin_time <> '0000-00-00 00:00:00')
              AND (b.checkout_time IS NULL OR b.checkout_time = '' OR b.checkout_time = '0000-00-00 00:00:00')
              AND b.booking_date = CURDATE()
              AND $endExpr BETWEEN ? AND ?
              AND $endExpr <  ?
            ORDER BY end_dt ASC
            LIMIT 200";
  $st2 = $conn->prepare($sqlBk);
  $st2->bind_param('sss', $nowStr, $edgeStr, $tomorrowStart);
  $st2->execute();
  for ($rs2 = $st2->get_result(); $r = $rs2->fetch_assoc(); ) {
    $bid = (int)$r['booking_id'];
    if (in_array($bid, $deletedExpire, true)) continue; // ข้ามที่ลบ
    $results[] = [
      'notif_type' => 'booking_expiring_10m',
      'ref_id'     => $bid,
      'booking_id' => $bid,
      'desk_id'    => $r['desk_id'],
      'ref_time'   => $r['end_dt'],
      'message'    => 'ใกล้หมดเวลาใน '.$warnMin.' นาที',
      'viewed'     => in_array($bid, $viewedExpire, true) ? 1 : 0,  // ส่งสถานะ viewed
    ];
  }
  $st2->close();

  usort($results, fn($a,$b)=>strcmp($b['ref_time'] ?? '', $a['ref_time'] ?? ''));
  if (count($results) > 100) $results = array_slice($results, 0, 100);

  echo json_encode($results, JSON_UNESCAPED_UNICODE); exit;

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error'=>'SERVER_ERROR', 'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
