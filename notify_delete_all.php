<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0'); error_reporting(E_ERROR|E_PARSE);
require 'db_connect.php';
date_default_timezone_set('Asia/Bangkok');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'Unauthorized'], JSON_UNESCAPED_UNICODE); exit;
}

$type    = $_GET['type'] ?? '';
$warnMin = max(1, (int)($_GET['warn_min'] ?? 10));

$tz = new DateTimeZone('Asia/Bangkok');
$todayStart    = (new DateTime('today', $tz))->format('Y-m-d 00:00:00');
$tomorrowStart = (new DateTime('tomorrow', $tz))->format('Y-m-d 00:00:00');
$now  = new DateTime('now', $tz);
$edge = (clone $now)->modify("+{$warnMin} minutes");
$nowStr  = $now->format('Y-m-d H:i:s');
$edgeStr = $edge->format('Y-m-d H:i:s');

if ($type === 'slip') {
  $_SESSION['deleted_payment_ids'] = $_SESSION['deleted_payment_ids'] ?? [];

  $stmt = $conn->prepare("SELECT payment_id
                          FROM payments
                          WHERE payment_verified='pending'
                            AND created_at >= ? AND created_at < ?");
  $stmt->bind_param('ss', $todayStart, $tomorrowStart);
  $stmt->execute();
  $rs = $stmt->get_result();

  $added = 0;
  while ($r = $rs->fetch_assoc()) {
    $pid = (int)$r['payment_id'];
    if (!in_array($pid, $_SESSION['deleted_payment_ids'], true)) {
      $_SESSION['deleted_payment_ids'][] = $pid;   // แก้เฉพาะจุด: mark deleted
      $added++;
    }
  }
  $stmt->close();

  echo json_encode(['ok'=>true,'type'=>'slip','deleted_count'=>$added], JSON_UNESCAPED_UNICODE); exit;
}

if ($type === 'expire') {
  $_SESSION['deleted_expire_booking_ids'] = $_SESSION['deleted_expire_booking_ids'] ?? [];

  $stmt = $conn->prepare("SELECT b.booking_id
                          FROM bookings b
                          WHERE (b.checkin_time IS NOT NULL AND b.checkin_time <> '' AND b.checkin_time <> '0000-00-00 00:00:00')
                            AND (b.checkout_time IS NULL OR b.checkout_time = '' OR b.checkout_time = '0000-00-00 00:00:00')
                            AND b.booking_date = CURDATE()
                            AND CONCAT(b.booking_date,' ', b.booking_end_time) BETWEEN ? AND ?
                            AND CONCAT(b.booking_date,' ', b.booking_end_time) < ?");
  $stmt->bind_param('sss', $nowStr, $edgeStr, $tomorrowStart);
  $stmt->execute();
  $rs = $stmt->get_result();

  $added = 0;
  while ($r = $rs->fetch_assoc()) {
    $bid = (int)$r['booking_id'];
    if (!in_array($bid, $_SESSION['deleted_expire_booking_ids'], true)) {
      $_SESSION['deleted_expire_booking_ids'][] = $bid;  // แก้เฉพาะจุด: mark deleted
      $added++;
    }
  }
  $stmt->close();

  echo json_encode(['ok'=>true,'type'=>'expire','deleted_count'=>$added], JSON_UNESCAPED_UNICODE); exit;
}

echo json_encode(['ok'=>false,'error'=>'invalid type'], JSON_UNESCAPED_UNICODE);
