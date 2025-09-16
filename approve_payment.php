<?php  // รีแอคอนุมัติสลิปโอนเงิน
require 'db_connect.php';

if (!isset($_GET['payment_id'])) {
    echo "ไม่พบข้อมูล payment_id";
    exit();
}

$payment_id = $_GET['payment_id'];

// ดึงข้อมูล booking_id จาก payments
$stmt = $conn->prepare("SELECT booking_id FROM payments WHERE payment_id = ?");
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$stmt->bind_result($booking_id);
if (!$stmt->fetch()) {
    echo "ไม่พบรายการชำระเงินนี้";
    exit();
}
$stmt->close();

// อัปเดตสถานะใน payments
$stmt = $conn->prepare("UPDATE payments SET payment_verified = 'approved' WHERE payment_id = ?");
$stmt->bind_param("i", $payment_id);
$stmt->execute();

// อัปเดตสถานะใน bookings
$stmt = $conn->prepare("UPDATE bookings SET payment_verified = 'approved', payment_status = 'paid' WHERE booking_id = ?");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
// ✅ ดึง desk_id จาก bookings
$stmt = $conn->prepare("SELECT desk_id FROM bookings WHERE booking_id = ?");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$stmt->bind_result($desk_id);
if (!$stmt->fetch()) {
    echo "❌ ไม่พบ desk_id ที่เกี่ยวข้องกับ booking นี้";
    exit();
}
$stmt->close();

// ✅ อัปเดตโต๊ะเป็น reserved
$stmt = $conn->prepare("UPDATE desks SET status = 'reserved' WHERE desk_id = ?");
$stmt->bind_param("i", $desk_id);
$stmt->execute();

echo "✅ อนุมัติสำเร็จ";
?>
