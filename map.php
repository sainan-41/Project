<?php  // หน้าหลัก แสดงแผนที่ (เช็กสถานะทับซ้อน + เคารพปิดโต๊ะ DB/Session)
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require 'db_connect.php';

date_default_timezone_set('Asia/Bangkok');

$area       = $_GET['area']       ?? 'ชั้น 1';
$date       = $_GET['date']       ?? date('Y-m-d');
$start_time = $_GET['start_time'] ?? '08:00';
$end_time   = $_GET['end_time']   ?? '09:00';

// ป้องกันช่วงเวลาผิด (start >= end)
if (strtotime($start_time) >= strtotime($end_time)) {
    $end_time = date('H:i', strtotime($start_time) + 30*60);
}

// --- โต๊ะที่ถูกปิดจาก session (เสริมจาก DB) ---
$closed_from_session = $_SESSION['closed_desks'] ?? [];
if (!is_array($closed_from_session)) $closed_from_session = [];

// ดึงโต๊ะทั้งหมดในพื้นที่ที่เลือก
$stmt = $conn->prepare("SELECT desk_id, desk_name, pos_top, pos_left, areas, status FROM desks WHERE areas = ?");
$stmt->bind_param("s", $area);
$stmt->execute();
$result = $stmt->get_result();
$desks = [];

/**
 * ตรวจทับซ้อนเวลาเฉพาะบุกกิ้งที่ "อนุมัติแล้ว"
 * นิยามทับซ้อน: NOT (existing_end <= new_start OR existing_start >= new_end)
 */
$check_stmt = $conn->prepare("
  SELECT COUNT(*) AS cnt
  FROM bookings b
  JOIN payments p
    ON p.booking_id = b.booking_id
   AND LOWER(p.payment_verified) = 'approved'
  WHERE b.desk_id = ?
    AND b.booking_date = ?
    AND (b.checkout_time IS NULL OR b.checkout_time = '0000-00-00 00:00:00')
    AND NOT (
      b.booking_end_time   <= ?   -- new_start
      OR b.booking_start_time >= ? -- new_end
    )
");

while ($row = $result->fetch_assoc()) {
    $deskId = (int)$row['desk_id'];

    // เช็กทับซ้อน
    $check_stmt->bind_param("isss", $deskId, $date, $start_time, $end_time);
    $check_stmt->execute();
    $conflict = (int)$check_stmt->get_result()->fetch_assoc()['cnt'];

    // เคารพปิดโต๊ะจาก DB หรือ Session ก่อนเสมอ
    $isClosedDb      = strtolower((string)$row['status']) === 'unavailable';
    $isClosedSession = in_array($deskId, $closed_from_session, true);

    if ($isClosedDb || $isClosedSession) {
        $row['status'] = 'unavailable';
    } else {
        // ถ้าไม่ปิดโต๊ะ: มีทับซ้อน => reserved, ไม่ทับซ้อน => available
        $row['status'] = ($conflict > 0) ? 'reserved' : 'available';
    }

    $desks[] = $row;
}

// ข้อมูลผู้ใช้โชว์บนหน้า
$stmt = $conn->prepare("SELECT fullname, profile_pic FROM users WHERE user_id=?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$area_image_map = [
  'ชั้น 1' => 'floor1.png',
  'ชั้น 2' => 'floor2.png',
  'ชั้น 3' => 'floor3.png',
];
$image_file = $area_image_map[$area] ?? 'default_floor.png';

// ขนาดภาพต้นฉบับของแต่ละชั้น (ใช้คำนวณตำแหน่ง %)
$image_sizes = [
  'ชั้น 1' => ['width' => 601, 'height' => 491],
  'ชั้น 2' => ['width' => 605, 'height' => 491],
  'ชั้น 3' => ['width' => 601, 'height' => 520],
];
$img_width  = $image_sizes[$area]['width'];
$img_height = $image_sizes[$area]['height'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>เลือกที่นั่งจากแผนผัง</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="topbar.css">
  <link rel="stylesheet" href="sidebar.css">
  <style>
    body {
      background-color: #f8f9fa;
      margin: 0; padding: 0;
      font-family: 'Prompt', sans-serif;
      position: relative;
    }
    /* พื้นหลังเบลอ */
    body::before {
      content: "";
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: url('myPic/bg-map.png') no-repeat center center fixed;
      background-size: cover;
      filter: blur(8px);
      z-index: -1;
    }

    .content { margin-left: 220px; padding: 60px; }

    /* กล่องแผนที่ */
    .map-wrapper {
      position: relative;
      width: 1000px;
      background-color: rgba(255, 255, 255, 0.52);
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }

    .map-wrapper img {
      width: 100%;
      height: auto;
      display: block;
      border-radius: 20px;
    }

    .desk {
      position: absolute; width: 30px; height: 30px; border-radius: 40%;
      text-align: center; line-height: 30px; color: white; font-weight: bold;
      font-size: 14px; cursor: pointer; transition: transform 0.2s;
    }
    .desk:hover { transform: scale(1.1); }
    .available   { background-color: green; }
    .reserved    { background-color: lightcoral; }
    .unavailable { background-color: gray; pointer-events: none; opacity: 0.8; } /* กดไม่ได้ */

    .menu-icon { width: 3px; height: 3px; margin-right: 3px; object-fit: contain; }
  </style>
</head>
<body>
<?php include 'topbar.php'; ?>
<?php include 'sidebar_user.php'; ?>

<div class="hero">
  <div class="content">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <form method="GET" class="text-center flex-grow-1">
        <input type="hidden" name="area" value="<?= htmlspecialchars($area) ?>">
        <div class="d-flex justify-content-center align-items-center gap-2">
          <input type="date" name="date" class="form-control w-auto" value="<?= htmlspecialchars($date) ?>">
          <select name="start_time" class="form-select w-auto">
            <?php
            for ($h = 8; $h <= 22; $h++) {
              foreach (["00", "30"] as $m) {
                $val = str_pad($h, 2, '0', STR_PAD_LEFT) . ":$m";
                $selected = ($val === $start_time) ? 'selected' : '';
                echo "<option value=\"$val\" $selected>$val น.</option>";
              }
            }
            ?>
          </select>
          ถึง
          <select name="end_time" class="form-select w-auto">
            <?php
            for ($h = 8; $h <= 22; $h++) {
              foreach (["00", "30"] as $m) {
                $val = str_pad($h, 2, '0', STR_PAD_LEFT) . ":$m";
                $selected = ($val === $end_time) ? 'selected' : '';
                echo "<option value=\"$val\" $selected>$val น.</option>";
              }
            }
            ?>
          </select>
          <button type="submit" class="btn btn-outline-primary">ดูสถานะ</button>
        </div>
      </form>
    </div>

    <h4>ผังที่นั่ง <?= htmlspecialchars($area) ?></h4>
    <div class="d-flex">
      <div class="map-wrapper me-4 mb-3">
        <img src="floorplans/<?= htmlspecialchars($image_file) ?>" alt="แผนผัง <?= htmlspecialchars($area) ?>">

        <?php foreach ($desks as $desk):
          $top_percent  = ($img_height > 0) ? ($desk['pos_top']  / $img_height) * 100 : 0;
          $left_percent = ($img_width  > 0) ? ($desk['pos_left'] / $img_width)  * 100 : 0;

          $cls = $desk['status']; // available / reserved / unavailable
          $title = "โต๊ะ: {$desk['desk_name']} ({$cls})";
          // ถ้า unavailable → ไม่ใส่ data-bs-toggle เพื่อไม่ให้เปิดโมดัล
          $canClick = ($cls !== 'unavailable');
        ?>
          <div class="desk <?= $cls ?>"
               style="top: <?= $top_percent ?>%; left: <?= $left_percent ?>%;"
               title="<?= htmlspecialchars($title) ?>"
               <?= $canClick ? 'data-bs-toggle="modal" data-bs-target="#deskModal"' : '' ?>
               data-desk-id="<?= (int)$desk['desk_id'] ?>">
            <?= htmlspecialchars($desk['desk_name']) ?>
          </div>
        <?php endforeach; ?>

      </div>

      <!-- ปุ่มเลือกชั้น -->
      <div class="d-flex flex-column gap-2 me-4">
        <a href="map.php?area=ชั้น 3&date=<?= urlencode($date) ?>&start_time=<?= urlencode($start_time) ?>&end_time=<?= urlencode($end_time) ?>" class="btn <?= $area=='ชั้น 3' ? 'btn-primary' : 'btn-outline-secondary' ?>">ชั้น 3</a>
        <a href="map.php?area=ชั้น 2&date=<?= urlencode($date) ?>&start_time=<?= urlencode($start_time) ?>&end_time=<?= urlencode($end_time) ?>" class="btn <?= $area=='ชั้น 2' ? 'btn-primary' : 'btn-outline-secondary' ?>">ชั้น 2</a>
        <a href="map.php?area=ชั้น 1&date=<?= urlencode($date) ?>&start_time=<?= urlencode($start_time) ?>&end_time=<?= urlencode($end_time) ?>" class="btn <?= $area=='ชั้น 1' ? 'btn-primary' : 'btn-outline-secondary' ?>">ชั้น 1</a>
        <hr>
        <!-- หมายเหตุแจ้งสีสถานะ -->
        <div><strong>หมายเหตุ:</strong></div>
        <div><span style="display:inline-block;width:20px;height:20px;background-color:green;margin-right:5px;border-radius:50%;"></span>ว่าง</div>
        <div><span style="display:inline-block;width:20px;height:20px;background-color:lightcoral;margin-right:5px;border-radius:50%;"></span>จองแล้ว</div>
        <div><span style="display:inline-block;width:20px;height:20px;background-color:gray;margin-right:5px;border-radius:50%;"></span>ไม่สามารถใช้งานได้</div>
      </div>
    </div>
  </div>
</div>

<!-- Modal จอง -->
<div class="modal fade" id="deskModal" tabindex="-1" aria-labelledby="deskModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" id="deskModalContent"></div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const deskModal = document.getElementById('deskModal');
  deskModal.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    if (!button) return; // กัน edge case

    const deskId = button.getAttribute('data-desk-id');
    fetch(
      'desk_detail.php'
      + '?desk_id=' + encodeURIComponent(deskId)
      + '&date=' + encodeURIComponent('<?= $date ?>')
      + '&start_time=' + encodeURIComponent('<?= $start_time ?>')
      + '&end_time=' + encodeURIComponent('<?= $end_time ?>')
      + '&fullname=' + encodeURIComponent('<?= $user['fullname'] ?? '' ?>')
    )
    .then(response => response.text())
    .then(html => {
      document.getElementById('deskModalContent').innerHTML = html;
    })
    .catch(err => {
      document.getElementById('deskModalContent').innerHTML = '<div class="p-4 text-danger">เกิดข้อผิดพลาดในการโหลดข้อมูลโต๊ะ</div>';
      console.error(err);
    });
  });
</script>
</body>
</html>
