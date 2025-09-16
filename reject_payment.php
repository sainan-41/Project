<?php
require 'db_connect.php';

if (!isset($_GET['payment_id'])) {
    echo "ไม่พบข้อมูล payment_id";
    exit();
}

$payment_id = $_GET['payment_id'];

// ดึง booking_id เพื่ออัปเดต bookings
$stmt = $conn->prepare("SELECT booking_id FROM payments WHERE payment_id = ?");
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$stmt->bind_result($booking_id);
if (!$stmt->fetch()) {
    echo "ไม่พบรายการชำระเงินนี้";
    exit();
}
$stmt->close();

// ✅ ไม่ลบไฟล์ / ไม่ลบข้อมูล แต่แค่เปลี่ยนสถานะ
// อัปเดต payments
$stmt = $conn->prepare("UPDATE payments SET payment_verified = 'rejected' WHERE payment_id = ?");
$stmt->bind_param("i", $payment_id);
$stmt->execute();

// อัปเดต bookings
$stmt = $conn->prepare("UPDATE bookings SET payment_verified = 'rejected', payment_status = 'waiting' WHERE booking_id = ?");
$stmt->bind_param("i", $booking_id);
$stmt->execute();

echo "❌ ปฏิเสธสำเร็จ - รายการถูกเก็บไว้เพื่อตรวจสอบภายหลัง";
?>