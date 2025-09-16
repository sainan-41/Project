<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT fullname, profile_pic FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// ดึงรายการจองทั้งหมด
$stmt = $conn->prepare("SELECT b.*, d.desk_name FROM bookings b
                        JOIN desks d ON b.desk_id = d.desk_id
                        WHERE b.user_id = ?
                        ORDER BY b.booking_date DESC, b.booking_start_time DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$today = date("Y-m-d");
$bookings = [
  'current' => [],
  'pending' => [],
  'cancelled' => [],
  'completed' => []
];

while ($row = $result->fetch_assoc()) {
    $date = $row['booking_date'];
    $status = $row['payment_status'];
    $verified = $row['payment_verified'];
    $checked_in = $row['checked_in'] ?? 0;

    if ($checked_in == 1 || $date < $today) {
        $bookings['completed'][] = $row;
    } elseif ($status === 'cancelled') {
        $bookings['cancelled'][] = $row;
    } elseif ($status === 'waiting' || $verified === 'pending') {
        $bookings['pending'][] = $row;
    } elseif ($status === 'paid' && $verified === 'approved') {
        $bookings['current'][] = $row;
    } else {
        $bookings['pending'][] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>My Booking</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  
  
  <style>
    body {
    background-color: #f8f9fa;
    margin: 0;
    padding: 0;
    font-family: 'Prompt', sans-serif;
    position: relative;
}

/* พื้นหลังเบลอ */
body::before {
    content: "";
    position: fixed;
    top: 0; left: 0;
    width: 100%;
    height: 100%;
    background: url('myPic/bg-map.png') no-repeat center center fixed;
    background-size: cover;
    filter: blur(8px);   /* ความเบลอ */
    z-index: -1;
}
    
    
    .content { margin-left: 220px; padding: 30px; }
    .card-booking {
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 15px;
      margin-bottom: 10px;
      background: white;
    }
    .card-booking .label {
      font-weight: bold;
    }

    .sidebar {
  width: 220px;
  height: 100vh;
  position: fixed;
  top: 0;
  left: 0;
  background: #1f2937; /* เทาเข้มแบบ modern */
  color: #f9fafb;
  padding: 20px 15px;
  box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
  border-right: 1px solid #374151;
  z-index: 1000;
}

.sidebar .profile img {
  width: 70px;
  height: 70px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid #3b82f6;
}

.sidebar .name {
  margin-top: 10px;
  font-weight: bold;
  font-size: 15px;
  color: #e5e7eb;
}

.sidebar a {
  display: flex;
  align-items: center;
  padding: 10px 12px;
  margin: 8px 0;
  font-size: 14px;
  color: #d1d5db;
  text-decoration: none;
  border-radius: 6px;
  transition: all 0.2s ease-in-out;
}

.sidebar a:hover {
  background-color: #3b82f6;
  color: white;
}

.sidebar a.active {
  background-color: #2563eb;
  color: white;
}

.sidebar i {
  font-size: 18px;
  margin-right: 10px;
}
  </style>
</head>
<body>
    <!--เรียกsidebarของ user เข้ามาใช้-->
  <?php include 'sidebar_user.php'; ?>
  
  <div class="content">
    <h2 class="mb-4">My Booking รายการจอง</h2>

    <!--<input type="date" id="dateFilter" class="form-control w-25 mb-3" placeholder="กรองตามวันที่">
    <input type="text" id="searchInput" class="form-control w-50 mb-3" placeholder="ค้นหาด้วยโต๊ะ / วันที่ / สถานะ">-->

    <ul class="nav nav-tabs" id="myTab" role="tablist">
      <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#current" type="button">📌 ปัจจุบัน</button></li>
      <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pending" type="button">⏳ รอตรวจสอบ</button></li>
      <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cancelled" type="button">❌ ยกเลิก</button></li>
      <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#completed" type="button">✅ เสร็จสิ้น</button></li>
    </ul>

    <div class="tab-content pt-3" id="bookingContent">
      <?php foreach ($bookings as $key => $rows): ?>
        <div class="tab-pane fade <?= $key === 'current' ? 'show active' : '' ?>" id="<?= $key ?>">
          <?php if (count($rows) > 0): ?>
            <?php foreach ($rows as $row): ?>
              <div class="card-booking search-item" data-date="<?= $row['booking_date'] ?>">
                <div><span class="label">รหัส:</span> <?= $row['booking_id'] ?> | <span class="label">โต๊ะ:</span> <?= $row['desk_name'] ?></div>
                <div><span class="label">วันที่:</span> <?= $row['booking_date'] ?> | <span class="label">เวลา:</span> <?= $row['booking_start_time'] ?> - <?= $row['booking_end_time'] ?></div>
                <div><span class="label">สถานะ:</span>
                  <?php if ($row['payment_verified'] === 'rejected'): ?>
                    <span class="text-danger fw-bold">สลิปถูกปฏิเสธ</span>
                    <div class="alert alert-danger p-2 mt-2 mb-0" style="font-size: 13px;">
                      กรุณาติดต่อเจ้าหน้าที่ 📱 <a href="tel:0912345678">091-234-5678</a> | 💬 Line: <a href="https://line.me/ti/p/~moonco" target="_blank">@moonco</a>
                    </div>
                  <?php elseif ($row['payment_status'] === 'paid' && $row['payment_verified'] === 'approved'): ?>
                    ✅ พร้อมเช็คอิน
                  <?php elseif ($row['payment_status'] === 'waiting'): ?>
                    ⏳ รอตรวจสอบ
                  <?php elseif ($row['payment_status'] === 'cancelled'): ?>
                    ❌ ยกเลิก
                  <?php else: ?>
                    ⭕ ยังไม่ชำระ
                  <?php endif; ?>
                </div>
                <div><span class="label">QR:</span>
                  <?php if ($row['payment_status'] === 'paid' && $row['payment_verified'] === 'approved'): ?>
                    <a href="generate_qr.php?booking_id=<?= $row['booking_id'] ?>" target="_blank">ดู QR</a>
                  <?php else: ?>
                    -
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="text-muted">ไม่มีข้อมูลในหมวดนี้</p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const searchInput = document.getElementById("searchInput");
    const dateFilter = document.getElementById("dateFilter");

    function filterItems() {
      const keyword = searchInput.value.toLowerCase();
      const dateVal = dateFilter.value;

      document.querySelectorAll(".search-item").forEach(item => {
        const text = item.textContent.toLowerCase();
        const date = item.getAttribute("data-date");
        const show = (!keyword || text.includes(keyword)) && (!dateVal || date === dateVal);
        item.style.display = show ? "block" : "none";
      });
    }

    searchInput.addEventListener("input", filterItems);
    dateFilter.addEventListener("change", filterItems);
  </script>
</body>
</html>