<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit();
}

date_default_timezone_set('Asia/Bangkok');

$now     = new DateTime();
$today   = $now->format('Y-m-d');
$nowStr  = $now->format('Y-m-d H:i:s');

/**
 * แนวคิด:
 * - available_desks: โต๊ะที่ไม่มีบุกกิ้ง (อนุมัติแล้ว) วันนี้ที่ยัง active อยู่
 * - in_use_now: เช็คอินแล้ว/ยังไม่เช็คเอาท์/ยังไม่หมดเวลา (อนุมัติแล้ว) วันนี้
 * - total_users_today: จำนวนการเช็กอินทั้งหมดของวันนี้ (และชำระ/อนุมัติแล้ว)  ← ตามที่ผู้ใช้กำหนด
 * - total_revenue: ยอดอนุมัติวันนี้ (ไฮบริด) = DATE(payment_time)=วันนี้ OR booking_date=วันนี้
 */

/* 1) ที่นั่งว่างตอนนี้ */
$sql_available = "
  SELECT COUNT(*) AS c
  FROM desks d
  WHERE NOT EXISTS (
    SELECT 1
    FROM bookings b
    JOIN payments p ON p.booking_id = b.booking_id AND LOWER(p.payment_verified) = 'approved'
    WHERE b.desk_id = d.desk_id
      AND b.booking_date = ?
      AND (
           (b.checkin_status = 'checked_in'
             AND (b.checkout_status IS NULL OR b.checkout_status <> 'checked_out')
             AND CONCAT(b.booking_date,' ',b.booking_end_time) >= ?)
        OR ((b.checkin_status IS NULL OR b.checkin_status <> 'checked_in')
             AND CONCAT(b.booking_date,' ',b.booking_end_time) >= ?)
      )
  )
";
$stmt1 = $conn->prepare($sql_available);
$stmt1->bind_param("sss", $today, $nowStr, $nowStr);
$stmt1->execute();
$available = (int)$stmt1->get_result()->fetch_row()[0];

/* 2) กำลังใช้งานตอนนี้ */
$sql_in_use = "
  SELECT COUNT(DISTINCT b.desk_id) AS c
  FROM bookings b
  JOIN payments p ON p.booking_id = b.booking_id AND LOWER(p.payment_verified) = 'approved'
  WHERE b.booking_date = ?
    AND b.checkin_status = 'checked_in'
    AND (b.checkout_status IS NULL OR b.checkout_status <> 'checked_out')
    AND CONCAT(b.booking_date,' ',b.booking_end_time) >= ?
";
$stmt2 = $conn->prepare($sql_in_use);
$stmt2->bind_param("ss", $today, $nowStr);
$stmt2->execute();
$in_use = (int)$stmt2->get_result()->fetch_row()[0];

/* 3) ผู้ใช้งานวันนี้ = จำนวนการเช็กอิน "ทั้งหมด" ของวันนี้ (และชำระ/อนุมัติแล้ว) */
$sql_users_today = "
  SELECT COUNT(*)
  FROM bookings b
  JOIN payments p 
    ON p.booking_id = b.booking_id
   AND LOWER(p.payment_verified) = 'approved'
  WHERE b.booking_date = ?
    AND b.checkin_status = 'checked_in'
";
$stmt3 = $conn->prepare($sql_users_today);
$stmt3->bind_param("s", $today);
$stmt3->execute();
$users = (int)$stmt3->get_result()->fetch_row()[0];

/* 4) รายรับวันนี้ (ไฮบริด) */
$sql_revenue = "
  SELECT COALESCE(SUM(CAST(p.amount AS DECIMAL(18,2))), 0)
  FROM payments p
  JOIN bookings b ON b.booking_id = p.booking_id
  WHERE LOWER(p.payment_verified) = 'approved'
    AND (
          DATE(p.payment_time) = ?
          OR b.booking_date = ?
        )
";
$stmt4 = $conn->prepare($sql_revenue);
$stmt4->bind_param("ss", $today, $today);
$stmt4->execute();
$revenue = (float)$stmt4->get_result()->fetch_row()[0];

echo json_encode([
  'available_desks'   => $available,
  'in_use_now'        => $in_use,
  'total_users_today' => $users,  // ← กล่องสีน้ำเงินใช้อันนี้
  'total_revenue'     => number_format($revenue, 2, '.', '')
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
