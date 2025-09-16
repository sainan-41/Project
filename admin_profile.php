<?php
session_start();
require 'db_connect.php';

// ตรวจสอบสิทธิ์เข้าใช้งาน
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$admin_id = $_SESSION['user_id'];

// ดึงข้อมูลแอดมิน
$stmt = $conn->prepare("SELECT username, fullname, email, phone, profile_pic, role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>โปรไฟล์แอดมิน</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Bootstrap & Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <!-- App CSS: navbar ก่อน, แล้วค่อย layout+sidebar -->
  <link href="/coworking/style.css" rel="stylesheet">    <!-- แถบนำทาง -->
  <link href="/coworking/style1.css" rel="stylesheet">   <!-- Sidebar + Layout -->
<style>
body {
    background: #f4f6f9;
}
 :root { --sidebar-w:240px; --topbar-h:60px; }
  html,body{height:100%}
  body{margin:0;background:#f6f7fb;overflow-x:hidden;padding-top:var(--topbar-h); /* กันท็อปบาร์ทับเนื้อหา */}
  .wrapper{position:relative;min-height:100vh}

  /* ========== Sidebar / Content / Topbar ========== */
  .main-sidebar{position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;overflow:hidden;scrollbar-width:none;z-index:1040}
  .main-sidebar::-webkit-scrollbar{display:none}
  .main-content{margin-left:var(--sidebar-w);min-height:100vh;padding:0 0 16px}
  .app-topbar{position:fixed!important;top:0;right:0;left:var(--sidebar-w);height:var(--topbar-h);
    z-index:1050;background:#fff;border-bottom:1px solid #e9ecef;
    padding-left:0!important;padding-right:0!important;margin:0!important;border-radius:0!important;box-shadow:none!important}
  .app-topbar .container-fluid{height:100%}
/* ====== การ์ดโปรไฟล์ (คงดีไซน์เดิม) ====== */
.profile-card {
    max-width: 850px;
    margin: 50px auto;
    background: #fff;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}
.profile-header {
    background: linear-gradient(120deg, #515151ff, #515151ff);
    color: white;
    padding: 40px 20px;
    text-align: center;
}
.profile-header img {
    width: 170px;
    height: 170px;
    object-fit: cover;
    border: 3px solid #2155ffff;
    border-radius: 50%;
    margin-bottom: 15px;
    background: #fff;
}
.profile-body {
    padding: 30px;
}
.info-item {
    padding: 15px;
    border-bottom: 1px solid #eee;
}
.info-item:last-child {
    border-bottom: none;
}
.info-item i {
    font-size: 1.2rem;
    color: #0066ff;
    margin-right: 10px;
}
.btn-edit {
    background: #0066ff;
    color: white;
    border-radius: 30px;
    padding: 10px 25px;
    transition: 0.3s;
}
.btn-edit:hover {
    background: #004ecc;
    color: white;
}
</style>
</head>

<!-- ===== ใส่บล็อคที่คุณให้มา (หุ้มคอนเทนต์เดิมไว้ข้างใน) ===== -->
<body class="page-admin-profile">
<div class="wrapper">
  <!-- Sidebar -->
  <?php include 'sidebar_admin.php'; ?>

  <!-- Main -->
  <div class="main-content">

    <!-- Navbar (no-print) -->
    <div class="no-print">
      <?php
        // ลิงก์ "หน้าหลัก" ที่ไอคอนบ้าน
        if (!defined('NAV_HOME_HREF')) define('NAV_HOME_HREF', 'desk_status.php');

        // กันโหลด Bootstrap/Icons ซ้ำในไฟล์ navbar
        if (!defined('APP_BOOTSTRAP_CSS'))      define('APP_BOOTSTRAP_CSS', true);
        if (!defined('BOOTSTRAP_ICONS_LOADED')) define('BOOTSTRAP_ICONS_LOADED', true);
        if (!defined('BOOTSTRAP_JS_LOADED'))    define('BOOTSTRAP_JS_LOADED', true);

        // ส่ง $admin ที่ดึงมาด้านบนให้ navbar_admin1.php ใช้ชื่อ/รูปได้ทันที
        include 'navbar_admin1.php';
      ?>
    </div>

    <!-- ===== เนื้อหาโปรไฟล์เดิม (ย้ายเข้ามาอยู่ใน main-content) ===== -->
    <div class="profile-card">
        <!-- ส่วนหัวโปรไฟล์ -->
        <div class="profile-header">
            <img src="<?= !empty($admin['profile_pic']) ? htmlspecialchars($admin['profile_pic']) : 'default.png' ?>" alt="Profile Picture">
            <h3><?= htmlspecialchars($admin['fullname']) ?></h3>
            <p class="mb-0"><i class="bi bi-shield-lock"></i> <?= ucfirst($admin['role']) ?></p>
        </div>

        <!-- เนื้อหาโปรไฟล์ -->
        <div class="profile-body">
            <div class="info-item">
                <i class="bi bi-person"></i> <strong>ชื่อผู้ใช้:</strong> <?= htmlspecialchars($admin['username']) ?>
            </div>
            <div class="info-item">
                <i class="bi bi-envelope"></i> <strong>อีเมล:</strong> <?= htmlspecialchars($admin['email']) ?>
            </div>
            <div class="info-item">
                <i class="bi bi-telephone"></i> <strong>เบอร์โทร:</strong> <?= htmlspecialchars($admin['phone']) ?>
            </div>
            <div class="text-center mt-4">
                <a href="edit_admin_profile.php" class="btn btn-edit">
                    <i class="bi bi-pencil-square"></i> แก้ไขโปรไฟล์
                </a>
            </div>
        </div>
    </div>

  </div><!-- /main-content -->
</div><!-- /wrapper -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
