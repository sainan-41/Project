
<?php
session_start();
require 'db_connect.php';

$user_id = $_SESSION['user_id'] ?? 0;


$stmt_user = $conn->prepare("SELECT fullname, profile_pic FROM users WHERE user_id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_result = $stmt_user->get_result();
$user = $user_result->fetch_assoc();


$selected_month = $_GET['month'] ?? date('m');
$selected_year = $_GET['year'] ?? date('Y');
$status_filter = $_GET['status'] ?? 'approved'; // default โชว์เฉพาะรายการอนุมัติ

$sql = "SELECT p.*, b.booking_date, b.booking_start_time, b.booking_end_time, d.desk_name, u.fullname
        FROM payments p
        JOIN bookings b ON p.booking_id = b.booking_id
        JOIN desks d ON b.desk_id = d.desk_id
        JOIN users u ON b.user_id = u.user_id
        WHERE u.user_id = ?
          AND MONTH(p.payment_time) = ?
          AND YEAR(p.payment_time) = ?
          AND p.payment_verified = ?
        ORDER BY p.payment_time DESC";


$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("iiis", $user_id, $selected_month, $selected_year, $status_filter);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>ประวัติการชำระเงิน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        body {
    position: relative;
    min-height: 100vh;
    font-family: 'Prompt', sans-serif;
    overflow-x: hidden;
}

body::before {
    content: "";
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-image: url('myPic/bg-map.png');
    background-size: cover;
    background-position: center;
    filter: blur(8px); /* ความเบลอ */
    z-index: -1; /* ส่งให้ไปอยู่หลังทุกอย่าง */
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

        .nav-pills .nav-link.active { background-color: #0d6efd; }
    </style>
</head>
<body>


<!--เรียกใช้ sidebar-->
<?php include 'sidebar_user.php'; ?>

<div class="container py-4" style="margin-left:230px;">
    <h3 class="mb-3">📄 ประวัติการชำระเงิน</h3>

    <ul class="nav nav-pills mb-3">
        <li class="nav-item"><a class="nav-link <?= $status_filter == 'approved' ? 'active' : '' ?>" href="?status=approved&month=<?= $selected_month ?>&year=<?= $selected_year ?>">✔ อนุมัติแล้ว</a></li>
        <li class="nav-item"><a class="nav-link <?= $status_filter == 'pending' ? 'active' : '' ?>" href="?status=pending&month=<?= $selected_month ?>&year=<?= $selected_year ?>">⏳ รอตรวจสอบ</a></li>
        <li class="nav-item"><a class="nav-link <?= $status_filter == 'rejected' ? 'active' : '' ?>" href="?status=rejected&month=<?= $selected_month ?>&year=<?= $selected_year ?>">❌ ไม่ผ่าน</a></li>
    </ul>

    <form method="get" class="row g-3 mb-3">
        <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
        <div class="col-md-4">
            <label class="form-label">เลือกเดือน</label>
            <select name="month" class="form-select">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= ($m == $selected_month) ? 'selected' : '' ?>>
                        <?= date('F', mktime(0, 0, 0, $m, 10)) ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">เลือกปี พ.ศ.</label>
            <select name="year" class="form-select">
                <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                    <option value="<?= $y ?>" <?= ($y == $selected_year) ? 'selected' : '' ?>>
                        <?= $y + 543 ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">🔍 ดูรายการ</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered align-middle text-center">
            <thead class="table-light">
                <tr>
                    <th>วันที่ชำระ</th>
                    <th>ชื่อผู้จอง</th>
                    <th>โต๊ะ</th>
                    <th>วันที่จอง</th>
                    <th>เวลา</th>
                    <th>จำนวน</th>
                    <th>วิธีชำระ</th>
                    <th>สถานะ</th>
                    <th>สลิป</th>
                    <th>ใบเสร็จ</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($row['payment_time'])) ?></td>
                    <td><?= htmlspecialchars($row['fullname']) ?></td>
                    <td><?= htmlspecialchars($row['desk_name']) ?></td>
                    <td><?= date('d/m/Y', strtotime($row['booking_date'])) ?></td>
                    <td><?= $row['booking_start_time'] . " - " . $row['booking_end_time'] ?></td>
                    <td><?= number_format($row['amount'], 2) ?> บาท</td>
                    <td><?= htmlspecialchars($row['payment_method']) ?></td>
                    <td>
                        <?php
                        if ($row['payment_verified'] == 'approved') echo "<span class='badge bg-success'>✔ ผ่าน</span>";
                        elseif ($row['payment_verified'] == 'pending') echo "<span class='badge bg-warning text-dark'>⏳ รอตรวจสอบ</span>";
                        else echo "<span class='badge bg-danger'>❌ ไม่ผ่าน</span>";
                        ?>
                    </td>
                    <td>
                         <?php if (!empty($row['slip'])): ?>
                            <a href="/coworking/<?= $row['slip'] ?>" target="_blank">📷</a>
                        <?php else: ?>
                           -
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php if ($row['payment_verified'] == 'approved'): ?>
                            <a href="receipt_preview.php?payment_id=<?= $row['payment_id'] ?>" class="btn btn-sm btn-outline-info">🧾</a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
