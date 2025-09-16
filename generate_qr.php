<?php
require 'vendor/autoload.php';
require 'db_connect.php';

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

if (!isset($_GET['booking_id'])) {
    echo "ไม่พบ booking_id";
    exit();
}

$booking_id = intval($_GET['booking_id']);
$stmt = $conn->prepare("SELECT b.*, d.desk_name, u.fullname FROM bookings b
                        JOIN desks d ON b.desk_id = d.desk_id
                        JOIN users u ON b.user_id = u.user_id
                        WHERE b.booking_id = ?");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "ไม่พบข้อมูลการจอง";
    exit();
}

$data = $result->fetch_assoc();

// เช็คเวลาปัจจุบันกับเวลาเริ่ม
date_default_timezone_set("Asia/Bangkok");
$now = strtotime(date("Y-m-d H:i"));
$start = strtotime($data['booking_start_time']) - 300; // ก่อนเวลา 5 นาที
$end = strtotime($data['booking_end_time']);
$allow_checkin = ($now >= $start && $now <= $end);

// สร้าง QR
$renderer = new ImageRenderer(
    new RendererStyle(200),
    new SvgImageBackEnd()
);
$writer = new Writer($renderer);
$qrContent = "http://localhost/coworking/checkin_confirm.php?booking_id={$booking_id}";
$qrSvg = $writer->writeString($qrContent);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>QR Check-in</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background-color: #f8f9fa; padding: 20px; }
    .qr-container {
      background: white;
      padding: 20px;
      border-radius: 15px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
      max-width: 400px;
      margin: auto;
    }
    .qr-box {
      text-align: center;
      margin-bottom: 15px;
    }
    svg {
      width: 200px;
      height: 200px;
    }
    .info {
      font-size: 16px;
    }
  </style>
</head>
<body>
  <div class="qr-container">
    <h4 class="text-center mb-3">QR สำหรับเช็คอิน</h4>
    <div class="qr-box">
      <?= $qrSvg ?>
    </div>
    <div class="info">
      <p><strong>ชื่อผู้จอง:</strong> <?= htmlspecialchars($data['fullname']) ?></p>
      <p><strong>วันที่:</strong> <?= $data['booking_date'] ?></p>
      <p><strong>เวลา:</strong> <?= substr($data['booking_start_time'], 0, 5) ?> - <?= substr($data['booking_end_time'], 0, 5) ?></p>
      <p><strong>โต๊ะ:</strong> <?= $data['desk_name'] ?></p>
      <?php if ($allow_checkin): ?>
        <div class="alert alert-success">สามารถเช็คอินได้ในขณะนี้</div>
      <?php else: ?>
        <div class="alert alert-warning">ยังไม่ถึงเวลาหรือเลยเวลาเช็คอิน</div>
      <?php endif; ?>
        <div class="text-center mt-3">
          <a href="booking_history.php" class="btn btn-outline-secondary">🔙 กลับหน้าประวัติการจอง</a>
        </div>
    </div>
  </div>
</body>
</html>
