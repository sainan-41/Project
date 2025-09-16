<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// กันกรณีบางหน้าลืมเตรียม $admin
if (!isset($admin) && isset($_SESSION['user_id'])) {
  require_once 'db_connect.php';
  $aid = (int)$_SESSION['user_id'];
  $st = $conn->prepare("SELECT fullname, profile_pic FROM users WHERE user_id = ?");
  $st->bind_param("i", $aid);
  $st->execute();
  $admin = $st->get_result()->fetch_assoc() ?: ['fullname' => 'Admin', 'profile_pic' => ''];
  $st->close();
}

// ปรับพาธรูป + กันเคส uploads/uploads + กันแคช
$rawPic = trim($admin['profile_pic'] ?? '');
$pic = 'uploads/default.png';

if ($rawPic !== '') {
  if (preg_match('~^https?://~i', $rawPic)) {
    $pic = $rawPic;
  } else {
    $p = ltrim($rawPic, './');
    if (strpos($p, 'uploads/') !== 0) $p = 'uploads/' . $p;
    $p = preg_replace('~^uploads/uploads/~', 'uploads/', $p);
    if (@file_exists($p)) $pic = $p;
  }
}
$qs = (!preg_match('~^https?://~i', $pic) && @file_exists($pic)) ? ('?v=' . filemtime($pic)) : '';
?>
<aside class="main-sidebar">
  <div class="sidebar-wrapper">
    <div class="sidebar-header text-center p-3">
      <img src="<?= htmlspecialchars($pic . $qs) ?>" alt="Admin" class="avatar" style="width:110px;height:110px;border-radius:50%;object-fit:cover;border:2px solid #3b82f6;">
      <div class="name mt-2 fw-semibold"><?= htmlspecialchars($admin['fullname'] ?? 'Admin') ?></div>
    </div>

    <nav class="px-2">
      <a href="admin_profile.php" class="nav-link"><i class="bi bi-person-circle text-pink"></i><span>&nbsp;โปรไฟล์แอดมิน</span></a>
      <hr class="sidebar-divider">
      <div class="nav-link disabled-link"><i class="bi bi-speedometer"></i><span>&nbsp;Report / Dashboard</span></div>
      <a href="desk_status.php" class="nav-link"><i class="bi bi-speedometer2 text-info"></i><span>&nbsp;ภาพรวม / สถานะ</span></a>
      <a href="dashboard_admin.php" class="nav-link"><i class="bi bi-file-earmark-check text-lime"></i><span>&nbsp;ตรวจสอบการชำระเงิน</span></a>
      <a href="desk_booking.php" class="nav-link"><i class="bi bi-calendar-check text-pastel-lavender"></i><span>&nbsp;แผนผังข้อมูลโต๊ะ</span></a>
      <a href="manage_user.php" class="nav-link"><i class="bi bi-person text-pastel-pink"></i><span>&nbsp;ข้อมูลผู้ใช้งาน</span></a>
      <a href="dashboard_daily.php" class="nav-link"><i class="bi bi-graph-up-arrow text-warning"></i><span>&nbsp;สรุปยอดขาย</span></a>
      <a href="report_summary.php" class="nav-link"><i class="bi bi-file-earmark-bar-graph text-pastel-purple"></i><span>&nbsp;รายงานสรุป</span></a>
      <a href="admin_manage.php" class="nav-link"><i class="bi bi-person-gear text-teal"></i><span>&nbsp;จัดการแอดมิน</span></a>
      <hr class="sidebar-divider">
      <a href="logout.php" class="nav-link"><i class="bi bi-power text-danger"></i><span class="text-danger">&nbsp;ออกจากระบบ</span></a>
    </nav>
  </div>
</aside>
 