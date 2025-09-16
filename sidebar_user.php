<!-- sidebar_user.php -->
<div class="sidebar">
  <div class="profile text-center">
    <img src="<?= $user['profile_pic'] ? 'uploads/' . $user['profile_pic'] : 'uploads/default.png' ?>" alt="รูปโปรไฟล์">
    <div class="name"><?= htmlspecialchars($user['fullname']) ?></div>
  </div>
  <a href="profile.php"><i class="bi bi-person-circle me-2"></i> โปรไฟล์</a>
<a href="map.php"><i class="bi bi-map me-2"></i> เลือกโต๊ะที่นั่ง</a>
<a href="booking_history.php"><i class="bi bi-journal-text me-2"></i> My Booking</a>
<a href="payment_history.php"><i class="bi bi-wallet2 me-2"></i> การชำระเงิน</a>
<a href="password.php"><i class="bi bi-shield-lock me-2"></i> เปลี่ยนรหัสผ่าน</a>
<a href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ</a>

</div>
