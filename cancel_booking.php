<?php  // คำสั่งยกเลิกการจองอัตโนมัติเมื่อชำระตามเวลา
session_start();
require 'db_connect.php';

if (!isset($_GET['booking_id'])) {
    echo "ไม่พบรหัสการจอง";
    exit();
}

$booking_id = $_GET['booking_id'];

// ยกเลิกรายการจองในฐานข้อมูล
$stmt = $conn->prepare("UPDATE bookings SET payment_status = 'cancelled' WHERE booking_id = ?");
$stmt->bind_param("i", $booking_id);
$stmt->execute();

echo "<h2 style='text-align:center; color:red;'>หมดเวลาชำระเงิน</h2>";
echo "<p style='text-align:center;'>คุณไม่ได้ชำระเงินภายในเวลาที่กำหนด กรุณาทำรายการใหม่</p>";
echo "<div style='text-align:center;'><a href='map.php' class='btn btn-primary'>กลับไปหน้าแผนที่</a></div>";
?>