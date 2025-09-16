<?php
session_start();
require 'db_connect.php';
date_default_timezone_set("Asia/Bangkok");

// ====== ฟังก์ชันเล็ก ๆ กัน redirect ไปเว็บอื่น (ออปชัน ปลอดภัยขึ้น) ======
function is_same_host($url) {
    $parts = @parse_url($url);
    if ($parts === false) return false;
    if (!isset($parts['host'])) return false;
    return strcasecmp($parts['host'], $_SERVER['HTTP_HOST']) === 0;
}
function sanitize_return_url($url, $fallback) {
    if (!$url) return $fallback;
    // อนุญาต path ภายใน เช่น /desk_status1.php?a=1
    if ($url[0] === '/') return $url;
    // อนุญาต http(s) ที่ host เดียวกัน
    if (preg_match('#^https?://#i', $url) && is_same_host($url)) return $url;
    return $fallback;
}

// ====== รับพารามิเตอร์หลัก ======
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
if ($booking_id <= 0) { echo "ไม่พบ booking_id"; exit(); }

// ====== fallback เมื่อไม่มี return_url (ให้เหมือนเดิม) ======
$default_return = (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'desk_status1.php' : 'map.php';

// รับ return_url ถ้ามี (จากปุ่มแอดมิน), ถ้าไม่มีจะใช้ default (ผู้ใช้ทั่วไปเลยยังทำงานเหมือนเดิม)
$return_url = $_GET['return_url'] ?? $_POST['return_url'] ?? '';
// (ออปชัน) ถ้าไม่มีจริง ๆ ลองใช้ HTTP_REFERER ถ้าเป็นโดเมนเดียวกัน
if (!$return_url && !empty($_SERVER['HTTP_REFERER']) && is_same_host($_SERVER['HTTP_REFERER'])) {
    $return_url = $_SERVER['HTTP_REFERER'];
}
$return_url = sanitize_return_url($return_url, $default_return);

// ====== โหลดข้อมูลการจอง ======
$stmt = $conn->prepare("
    SELECT b.*, d.desk_id, d.desk_name, d.status AS desk_status, u.fullname
    FROM bookings b
    JOIN desks d ON b.desk_id = d.desk_id
    JOIN users u ON b.user_id = u.user_id
    WHERE b.booking_id = ?
");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) { echo "ไม่พบข้อมูลการจอง"; exit(); }

$already_checked_out = ($booking['checkout_status'] === 'checked_out');
$now = date("Y-m-d H:i:s");

// ====== เช็คเอาท์ ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already_checked_out) {
    // อัปเดตสถานะการจอง
    $stmt = $conn->prepare("UPDATE bookings SET checkout_status = 'checked_out', checkout_time = ? WHERE booking_id = ?");
    $stmt->bind_param("si", $now, $booking_id);
    $stmt->execute();

    // คืน current_user_id ที่โต๊ะ (ไม่เปลี่ยนสถานะเป็น available ถ้าโต๊ะถูกปิดไว้)
    $desk_id = (int)$booking['desk_id'];
    $stmt2 = $conn->prepare("UPDATE desks SET current_user_id = NULL WHERE desk_id = ?");
    $stmt2->bind_param("i", $desk_id);
    $stmt2->execute();

    // เสร็จแล้วกลับ (ผู้ใช้ที่ไม่ได้ส่ง return_url ก็จะกลับหน้า default เหมือนเดิม)
    $sep = (strpos($return_url, '?') === false) ? '?' : '&';
    header("Location: " . $return_url . $sep . "msg=checkout_success");
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>เช็คเอาท์</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { background-color: #f8f9fa; padding: 30px; }
    .card { max-width: 420px; margin: auto; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
  </style>
</head>
<body>
  <div class="card p-4">
    <h4 class="text-center mb-3">เช็คเอาท์</h4>

    <?php if ($already_checked_out): ?>
      <div class="alert alert-info">📤 คุณได้เช็คเอาท์ไปแล้ว</div>
      <div class="text-center mt-3">
        <a href="<?= htmlspecialchars($return_url) ?>" class="btn btn-outline-secondary w-100">🔙 กลับหน้าก่อนหน้า</a>
      </div>
    <?php else: ?>
      <p><strong>ชื่อผู้จอง:</strong> <?= htmlspecialchars($booking['fullname']) ?></p>
      <p><strong>โต๊ะ:</strong> <?= htmlspecialchars($booking['desk_name']) ?></p>
      <p><strong>วันที่:</strong> <?= htmlspecialchars($booking['booking_date']) ?></p>
      <p><strong>เวลา:</strong> <?= htmlspecialchars(substr($booking['booking_start_time'], 0, 5)) ?> - <?= htmlspecialchars(substr($booking['booking_end_time'], 0, 5)) ?></p>

      <form method="post">
        <input type="hidden" name="return_url" value="<?= htmlspecialchars($return_url) ?>">
        <button type="submit" class="btn btn-danger w-100">📤 ยืนยันเช็คเอาท์</button>
      </form>

      <div class="text-center mt-3">
        <a href="<?= htmlspecialchars($return_url) ?>" class="btn btn-outline-secondary w-100">🔙 ยกเลิก/กลับหน้าก่อนหน้า</a>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
