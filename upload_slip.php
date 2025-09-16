
<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_POST['booking_id']) || !isset($_FILES['slip'])) {
    echo "ข้อมูลไม่ครบถ้วน"; exit();
}

$booking_id = $_POST['booking_id'];
$user_id = $_SESSION['user_id'];
$target_dir = "payment_slips/";
$filename = basename($_FILES["slip"]["name"]);
$unique_name = time() . "_" . $filename;
$target_file = $target_dir . $unique_name;
$imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

$allowed_types = ['jpg', 'jpeg', 'png', 'pdf'];
if (!in_array($imageFileType, $allowed_types)) {
    echo "อนุญาตเฉพาะไฟล์ JPG, JPEG, PNG, หรือ PDF เท่านั้น"; exit();
}

if (!move_uploaded_file($_FILES["slip"]["tmp_name"], $target_file)) {
    echo "เกิดข้อผิดพลาดในการอัปโหลดไฟล์"; exit();
}

// ดึงข้อมูลและคำนวณราคา
$stmt2 = $conn->prepare("SELECT b.*, d.price_per_hour, d.desk_name FROM bookings b 
                        JOIN desks d ON b.desk_id = d.desk_id 
                        WHERE b.booking_id = ?");
$stmt2->bind_param("i", $booking_id);
$stmt2->execute();
$booking = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

if (!$booking) {
    echo "ไม่พบข้อมูลการจอง"; exit();
}

$start = strtotime($booking['booking_start_time']);
$end = strtotime($booking['booking_end_time']);
$duration = round(($end - $start) / 3600, 1);
$amount = $booking['price_per_hour'] * $duration;

// บันทึกลง payments
$now = date("Y-m-d H:i:s");
$method = "QR";
$stmt3 = $conn->prepare("INSERT INTO payments (booking_id, payment_method, amount, payment_time, slip, payment_verified, created_at)
                         VALUES (?, ?, ?, ?, ?, 'pending', ?)");
if (!$stmt3) {
    echo "เกิดข้อผิดพลาดในการเตรียมคำสั่ง: " . $conn->error;
    exit();
}
$stmt3->bind_param("isdsss", $booking_id, $method, $amount, $now, $target_file, $now);
if (!$stmt3->execute()) {
    echo "เกิดข้อผิดพลาดในการบันทึกการชำระเงิน: " . $stmt3->error;
    exit();
}
$payment_id = $stmt3->insert_id;

// อัปเดต bookings
$stmt4 = $conn->prepare("UPDATE bookings SET payment_status = 'waiting', slip = ?, payment_verified = 'pending' WHERE booking_id = ?");
$stmt4->bind_param("si", $target_file, $booking_id);
$stmt4->execute();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>อัปโหลดสลิปสำเร็จ</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <style>
    .receipt-line {
      white-space: pre-wrap;
      font-family: 'Courier New', monospace;
      font-size: 14px;
    }
  </style>
</head>
<body class="bg-light">
  <div class="container py-5">
    <div class="alert alert-success text-center w-100 mx-auto" style="max-width: 400px;">
      <h5 class="mb-1">✅ อัปโหลดสลิปเรียบร้อยแล้ว</h5>
      <p class="mb-0">กรุณารอเจ้าหน้าที่ตรวจสอบ</p>
    </div>
    <div class="card shadow-sm p-4 mx-auto" style="width: 100%; max-width: 360px;">
      <div class="card-body text-start">
        <div class="text-center mb-3">
          <img src="myPic/moon.png" alt="โลโก้" style="max-width: 70px;">
          <h5 class="mt-2 mb-0">ใบเสร็จแบบย่อ</h5>
        </div>
        <div class="receipt-line">เลขที่การชำระเงิน: <?= $payment_id ?></div>
        <div class="receipt-line">เวลา: <?= $now ?></div>
        <div class="receipt-line">เลขที่การจอง: <?= $booking['booking_id'] ?></div>
        <div class="receipt-line">ชื่อผู้จอง: <?= htmlspecialchars($booking['customer_name']) ?></div>
        <div class="receipt-line">โต๊ะ: <?= htmlspecialchars($booking['desk_name']) ?></div>
        <div class="receipt-line">วันที่: <?= $booking['booking_date'] ?></div>
        <div class="receipt-line">เวลา booking: <?= substr($booking['booking_start_time'], 0, 5) ?> - <?= substr($booking['booking_end_time'], 0, 5) ?></div>
        <div class="receipt-line">รวม: <?= number_format($amount, 2) ?> บาท</div>
        <div class="receipt-line mb-4">วิธีชำระเงิน: โอนผ่าน QR</div>
        <div class="text-center">
          <a href="booking_history.php" class="btn btn-primary w-100">ตรวจสอบสถานะ</a>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
