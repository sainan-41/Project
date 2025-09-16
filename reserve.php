<?php
//หน้าเงื่อนไขการจอง รับข้อมูล ตรวจสอบ และบันทึกลง db
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// รับข้อมูลจากฟอร์ม
$user_id = $_SESSION['user_id'];
$desk_id = $_POST['desk_id'];
$customer_name = $_POST['customer_name'];
$booking_date = $_POST['booking_date'];
$start_time = $_POST['booking_start_time'];
$end_time = $_POST['booking_end_time'];
$phone = $_POST['phone'];
$note = $_POST['note'] ?? '';

// 🔒 ตรวจสอบการจองซ้ำช่วงเวลา
$stmt = $conn->prepare("
    SELECT * FROM bookings 
    WHERE desk_id = ? AND booking_date = ? 
      AND payment_status != 'cancelled'
      AND NOT (booking_end_time <= ? OR booking_start_time >= ?)
");
$stmt->bind_param("isss", $desk_id, $booking_date, $start_time, $end_time);
$stmt->execute();
$result = $stmt->get_result();


if ($result->num_rows > 0) {
    echo "<script>alert('ขออภัย! เวลานี้ถูกจองไปแล้ว กรุณาเลือกเวลาอื่น'); history.back();</script>";
    exit();
}

// ✅ บันทึกการจอง
$stmt = $conn->prepare("INSERT INTO bookings 
    (user_id, desk_id, customer_name, booking_date, booking_start_time, booking_end_time, phone, note, payment_status) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
$stmt->bind_param("iissssss", $user_id, $desk_id, $customer_name, $booking_date, $start_time, $end_time, $phone, $note);
$stmt->execute();
$booking_id = $conn->insert_id;

// ➡ ไปยังหน้า payment.php พร้อม booking_id
header("Location: payment.php?booking_id=" . $booking_id);
exit();
?>