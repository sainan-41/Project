<?php
// today_bookings_api.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  http_response_code(403);
  echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
  exit();
}

date_default_timezone_set('Asia/Bangkok');
$today = date('Y-m-d');

/*
  ดึงรายการ "บุกกิ้งของวันนี้" ที่ชำระเงินถูกอนุมัติแล้ว ครอบคลุมทุกชั้น
  เพิ่มคอลัมน์ d.areas เป็นชื่อ "ชั้น"
*/
$sql = "
  SELECT 
    b.booking_id,
    u.fullname,
    d.desk_name,
    d.areas AS area,
    b.booking_date,
    b.booking_start_time,
    b.booking_end_time,
    COALESCE(SUM(CAST(p.amount AS DECIMAL(18,2))), 0) AS amount
  FROM bookings b
  JOIN users u ON u.user_id = b.user_id
  JOIN desks d ON d.desk_id = b.desk_id
  JOIN payments p ON p.booking_id = b.booking_id
                 AND LOWER(p.payment_verified) = 'approved'
  WHERE b.booking_date = ?
  GROUP BY b.booking_id, u.fullname, d.desk_name, d.areas, b.booking_date, b.booking_start_time, b.booking_end_time
  ORDER BY d.areas ASC, b.booking_start_time ASC, d.desk_name ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$res = $stmt->get_result();

$list = [];
while ($row = $res->fetch_assoc()) {
  $list[] = [
    'booking_id' => (int)$row['booking_id'],
    'fullname' => $row['fullname'],
    'desk_name' => $row['desk_name'],
    'area' => $row['area'],
    'booking_date' => $row['booking_date'],
    'booking_start_time' => $row['booking_start_time'],
    'booking_end_time' => $row['booking_end_time'],
    'amount' => (float)$row['amount']
  ];
}

echo json_encode($list, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
