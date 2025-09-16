<?php
session_start();
require 'db_connect.php';

// --- DB setup ---
$conn->set_charset('utf8mb4');
// ให้ NOW() เป็นเวลาไทย (สำคัญมากสำหรับการคำนวณและบันทึกเวลา)
$conn->query("SET time_zone = '+07:00'");

if (!isset($_GET['booking_id'])) {
    echo "ไม่พบข้อมูลการจอง";
    exit();
}

$booking_id = intval($_GET['booking_id']);

// ดึงข้อมูลการจอง + โต๊ะ
$stmt = $conn->prepare(
    "SELECT b.*, d.desk_id, d.desk_name
     FROM bookings b
     JOIN desks d ON b.desk_id = d.desk_id
     WHERE b.booking_id = ?"
);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    echo "ไม่พบข้อมูลการจอง";
    exit();
}

// --- คำนวณเวลาแบบถูกต้อง (ใช้ booking_date + time, รองรับข้ามวัน) ---
$tz = new DateTimeZone('Asia/Bangkok');

// now ที่ไทย
$now = new DateTime('now', $tz);

// start_dt = booking_date + booking_start_time
$startDt = DateTime::createFromFormat('Y-m-d H:i:s', $booking['booking_date'] . ' ' . $booking['booking_start_time'], $tz);
if (!$startDt) {
    // fallback เผื่อรูปแบบเวลาไม่ตรง
    $startDt = new DateTime($booking['booking_date'] . ' ' . $booking['booking_start_time'], $tz);
}

// end_dt = booking_date + booking_end_time (ถ้า end < start ให้ข้ามไปวันถัดไป)
$endDateStr = $booking['booking_date'];
if (strtotime($booking['booking_end_time']) < strtotime($booking['booking_start_time'])) {
    // ข้ามวัน
    $endDateStr = date('Y-m-d', strtotime($booking['booking_date'] . ' +1 day'));
}
$endDt = DateTime::createFromFormat('Y-m-d H:i:s', $endDateStr . ' ' . $booking['booking_end_time'], $tz);
if (!$endDt) {
    $endDt = new DateTime($endDateStr . ' ' . $booking['booking_end_time'], $tz);
}

// อนุญาตเช็คอินล่วงหน้า 5 นาที
$earlyStart = (clone $startDt)->modify('-5 minutes');

// สถานะเดิม
$already_checked_in = strtolower((string)$booking['checkin_status']) === 'checked_in';
$already_checked_out = in_array(strtolower((string)$booking['checkout_status'] ?? ''), ['checked_out','completed','done','เช็คเอาท์','เสร็จสิ้น'], true);

// ตรวจเงื่อนไขเวลา
if ($already_checked_in) {
    $message = "คุณได้เช็คอินไปแล้วสำหรับการจองนี้";
    $type = "info";
} elseif ($already_checked_out) {
    $message = "รายการนี้เช็คเอาท์แล้ว ไม่สามารถเช็คอินได้";
    $type = "danger";
} elseif ($now < $earlyStart) {
    $message = "ยังไม่ถึงเวลาเช็คอิน กรุณาลองใหม่ใกล้เวลาเริ่มต้น";
    $type = "warning";
} elseif ($now > $endDt) {
    $message = "เลยเวลาการจองไปแล้ว ไม่สามารถเช็คอินได้";
    $type = "danger";
} else {
    // --- เช็คอินสำเร็จ: อัปเดตสถานะ + ตั้ง checkin_time ครั้งแรกเท่านั้น ---
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            "UPDATE bookings
             SET checkin_status = 'checked_in',
                 checkin_time   = IF(checkin_time IS NULL, NOW(), checkin_time),
                 updated_at     = NOW()
             WHERE booking_id = ?"
        );
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $stmt->close();

        $stmt2 = $conn->prepare("UPDATE desks SET status = 'occupied' WHERE desk_id = ?");
        $stmt2->bind_param("i", $booking['desk_id']);
        $stmt2->execute();
        $stmt2->close();

        $conn->commit();
        $message = "เช็คอินสำเร็จ! ขอให้มีวันที่ดีในพื้นที่ของเรา 😊";
        $type = "success";

        // รีเฟรชข้อมูลล่าสุดหลังอัปเดต (เพื่อแสดงเวลา checkin_time ที่เพิ่งตั้ง)
        $stmt = $conn->prepare(
            "SELECT b.*, d.desk_id, d.desk_name FROM bookings b
             JOIN desks d ON b.desk_id = d.desk_id
             WHERE b.booking_id = ?"
        );
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } catch (Throwable $e) {
        $conn->rollback();
        $message = "เช็คอินไม่สำเร็จ: " . $e->getMessage();
        $type = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>ยืนยันการเช็คอิน</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { background:#f8f9fa; padding-top:50px; text-align:center; }
    .card { max-width:480px; margin:auto; border-radius:16px; box-shadow:0 4px 12px rgba(0,0,0,.1); }
    .icon { font-size:64px; margin-bottom:20px; }
    .kv { display:flex; justify-content:space-between; margin:.25rem 0; }
    .kv .l { color:#6b7280; }
  </style>
</head>
<body>

<div class="card p-4">
  <div class="icon">
    <?php if ($type === 'success') echo '✅'; ?>
    <?php if ($type === 'warning') echo '⏳'; ?>
    <?php if ($type === 'danger') echo '❌'; ?>
    <?php if ($type === 'info') echo 'ℹ️'; ?>
  </div>
  <h4 class="text-<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($message) ?></h4>

  <div class="text-start mt-3">
    <div class="kv"><span class="l">เลขที่การจอง</span><span><strong><?= (int)$booking['booking_id'] ?></strong></span></div>
    <?php if (isset($booking['customer_name'])): ?>
      <div class="kv"><span class="l">ชื่อผู้จอง</span><span><strong><?= htmlspecialchars($booking['customer_name']) ?></strong></span></div>
    <?php endif; ?>
    <div class="kv"><span class="l">โต๊ะ</span><span><strong><?= htmlspecialchars($booking['desk_name']) ?></strong></span></div>
    <div class="kv"><span class="l">เวลาเริ่ม–สิ้นสุด</span><span>
      <?= htmlspecialchars(date('H:i', strtotime($booking['booking_start_time']))) ?> –
      <?= htmlspecialchars(date('H:i', strtotime($booking['booking_end_time']))) ?>
    </span></div>
    <div class="kv"><span class="l">เวลาเช็คอิน</span><span>
      <?php if (!empty($booking['checkin_time'])): ?>
        <strong><?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($booking['checkin_time']))) ?></strong>
      <?php else: ?>
        -
      <?php endif; ?>
    </span></div>
  </div>

  <div class="text-center mt-3">
    <a href="booking_history.php" class="btn btn-outline-secondary">🔙 กลับหน้าประวัติการจอง</a>
  </div>
</div>

</body>
</html>
