<?php
require 'db_connect.php';
header('Content-Type: application/json');

$desk_name = $_GET['desk_name'] ?? '';
if (!$desk_name) {
  echo json_encode([]);
  exit;
}

$stmt = $conn->prepare("SELECT desk_id FROM desks WHERE desk_name = ?");
$stmt->bind_param("s", $desk_name);
$stmt->execute();
$result = $stmt->get_result();
$desk = $result->fetch_assoc();
if (!$desk) {
  echo json_encode([]);
  exit;
}
$desk_id = $desk['desk_id'];

$stmt = $conn->prepare("
  SELECT b.booking_date, b.booking_start_time, b.booking_end_time, b.payment_amount, u.fullname
  FROM bookings b
  JOIN users u ON b.user_id = u.user_id
  WHERE b.desk_id = ? AND b.payment_verified = 'approved'
  ORDER BY b.booking_date DESC, b.booking_start_time DESC
");
$stmt->bind_param("i", $desk_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode($data);
