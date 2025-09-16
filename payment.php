<?php  //หน้าชำระเงิน
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$booking_id = $_GET['booking_id'] ?? '';
if (!$booking_id) {
    echo "<script>alert('ไม่พบเลขที่การจอง'); location.href='map.php';</script>";
    exit();
}

// ดึงข้อมูลการจองพร้อมราคา
$stmt = $conn->prepare("SELECT b.*, d.desk_name, d.price_per_hour FROM bookings b 
                        JOIN desks d ON b.desk_id = d.desk_id 
                        WHERE booking_id = ?");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    echo "<script>alert('ไม่พบบันทึกการจอง'); location.href='map.php';</script>";
    exit();
}

// คำนวณเวลาจอง
$start = new DateTime($booking['booking_start_time']);
$end = new DateTime($booking['booking_end_time']);
$interval = $start->diff($end);
$hours = $interval->h + ($interval->i > 0 ? 1 : 0);
$total_price = $hours * $booking['price_per_hour'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>ชำระเงิน</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <style>
    .countdown { font-size: 20px; font-weight: bold; color: red; margin-bottom: 15px; }
  </style>
</head>
<body class="bg-light">
  <div class="container mt-5">
    <div class="card shadow-sm">
      <div class="card-header bg-primary text-white">
        <h4>ยืนยันการชำระเงิน</h4>
      </div>
      <div class="card-body">
        <p><strong>เลขที่การจอง:</strong> <?= $booking['booking_id'] ?></p>
        <p><strong>ชื่อผู้จอง:</strong> <?= htmlspecialchars($booking['customer_name']) ?></p>
        <p><strong>โต๊ะ:</strong> <?= htmlspecialchars($booking['desk_name']) ?></p>
        <p><strong>วันที่:</strong> <?= $booking['booking_date'] ?> เวลา <?= substr($booking['booking_start_time'], 0, 5) ?> - <?= substr($booking['booking_end_time'], 0, 5) ?></p>
        <p><strong>จำนวนชั่วโมงที่จอง:</strong> <?= $hours ?> ชั่วโมง</p>
        <p><strong>ราคา/ชั่วโมง:</strong> <?= number_format($booking['price_per_hour'], 2) ?> บาท</p>
        <p><strong>รวม:</strong> <span class="text-success fw-bold"><?= number_format($total_price, 2) ?> บาท</span></p>

        <div class="text-center mb-4">
          <img src="myPic/promptpay_qr.png" alt="QR Code" class="img-fluid" style="max-width: 300px;">
        </div>

        <div class="text-center countdown" id="timer">เหลือเวลา 120 วินาที</div>

        <form action="upload_slip.php" method="POST" enctype="multipart/form-data">
          <input type="hidden" name="booking_id" value="<?= $booking_id ?>">
          <input type="hidden" name="amount" value="<?= $total_price ?>">

          <div class="mb-3">
            <label class="form-label">แนบสลิปชำระเงิน:</label>
            <input type="file" name="slip" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
          </div>

          <div class="d-flex justify-content-between">
            <a href="cancel_booking.php?booking_id=<?= $booking_id ?>" class="btn btn-danger">ยกเลิกการจอง</a>
            <button type="submit" class="btn btn-success">ส่งสลิป</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    let timeLeft = 120;
    let timerEl = document.getElementById("timer");

    const countdown = setInterval(() => {
      timeLeft--;
      if (timeLeft > 0) {
        timerEl.textContent = เหลือเวลา ${timeLeft} วินาที;
      } else {
        clearInterval(countdown);
        alert("หมดเวลา กรุณาทำรายการใหม่");
        window.location.href = "cancel_booking.php?booking_id=<?= $booking_id ?>";
      }
    }, 1000);
  </script>
</body>
</html>
