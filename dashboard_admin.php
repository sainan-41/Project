<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];

// ข้อมูลแอดมิน
$stmt = $conn->prepare("SELECT fullname, profile_pic FROM users WHERE user_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ดึงรายการชำระเงินที่รอตรวจสอบ
$stmt = $conn->prepare("
    SELECT p.*, u.fullname, d.desk_name, b.booking_date, b.booking_start_time, b.booking_end_time
    FROM payments p
    JOIN bookings b ON p.booking_id = b.booking_id
    JOIN users u ON b.user_id = u.user_id
    JOIN desks d ON b.desk_id = d.desk_id
    WHERE p.payment_verified = 'pending'
    ORDER BY p.created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>แดชบอร์ดแอดมิน</title>

  <!-- CSS หลักของหน้า -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

  <!-- 1) Layout & Sidebar ก่อน -->
  <link href="style1.css" rel="stylesheet">

  <!-- 2) Navbar ทีหลัง (อย่าแตะ .main-sidebar/.main-content ในไฟล์นี้) -->
  <link href="style.css" rel="stylesheet">

  <style>
    :root { --sidebar-w:240px; --topbar-h:60px; }

    html,body{height:100%}
    body{
      margin:0;
      background:#f6f7fb;
      overflow-x:hidden;
      padding-top:var(--topbar-h); /* กันท็อปบาร์ทับเนื้อหา */
    }
    .wrapper{position:relative;min-height:100vh}

    /* ========== Sidebar / Content / Topbar ========== */
    .main-sidebar{position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;overflow:hidden;scrollbar-width:none;z-index:1040}
    .main-sidebar::-webkit-scrollbar{display:none}
    .main-content{margin-left:var(--sidebar-w);min-height:100vh;padding:0 0 16px}
    .app-topbar{position:fixed!important;top:0;right:0;left:var(--sidebar-w);height:var(--topbar-h);
      z-index:1050;background:#fff;border-bottom:1px solid #e9ecef;
      padding-left:0!important;padding-right:0!important;margin:0!important;border-radius:0!important;box-shadow:none!important}
    .app-topbar .container-fluid{height:100%}

    /* กล่องรายการชำระเงิน — สไตล์เฉพาะหน้านี้ */
    .payment-box {
      background:#fff; border:1px solid #ddd; padding:15px;
      border-radius:8px; margin-bottom:15px;
    }

    /* ===== ชิดท็อปบาร์ขึ้น (ปรับเลขได้) ===== */
    :root { --content-top-gap: 30px; }                 /* ระยะห่างจากท็อปบาร์ */
    .main-content{ padding-top: 0 !important; }       /* กันสไตล์เดิมเผื่อมี padding-top */
    .content{
      margin-top: 0 !important;
      padding-top: var(--content-top-gap);            /* เนื้อหาชิดขึ้น */
      max-width: 1150px; margin-left:auto; margin-right:auto; /* ถ้าต้องการกึ่งกลาง */
    }
    .content > h3{
      margin-top: 0 !important;
      margin-bottom: .5rem;
    }
    .content > hr{
      margin-top: .25rem;
      margin-bottom: 1rem;
    }
  </style>

</head>
<body>

<div class="wrapper"><!-- โครงหลัก -->

  <!-- Sidebar -->
  <?php include 'sidebar_admin.php'; ?>

  <div class="main-content"><!-- เนื้อหาหลัก -->

    <!-- แถบนำทางบน: เปลี่ยนไปใช้ navbar_admin1.php -->
    <?php
        if (!defined('NAV_API_BASE')) define('NAV_API_BASE', '/coworking/');
        // ลิงก์ "หน้าหลัก" ที่ไอคอนบ้าน
        if (!defined('NAV_HOME_HREF')) define('NAV_HOME_HREF', 'desk_status.php');

        // กันโหลด Bootstrap/Icons ซ้ำในไฟล์ navbar
        if (!defined('APP_BOOTSTRAP_CSS'))      define('APP_BOOTSTRAP_CSS', true);
        if (!defined('BOOTSTRAP_ICONS_LOADED')) define('BOOTSTRAP_ICONS_LOADED', true);
        if (!defined('BOOTSTRAP_JS_LOADED'))    define('BOOTSTRAP_JS_LOADED', true);

        include 'navbar_admin1.php';
    ?>
    <!-- *** ลบ </div> เกินออก (เดิมมีปิด div ตรงนี้ ทำให้โครงสร้างผิดชั้นและเว้นช่องมาก) *** -->

    <div class="content">
      <h3 class="mt-1"><i class="bi bi-speedometer2 text-info me-2"></i>ตรวจสอบการชำระเงิน</h3>
      <hr>

      <?php while ($row = $result->fetch_assoc()): ?>
        <div class="payment-box" id="box-<?= (int)$row['payment_id'] ?>">
          <p><strong>ชื่อผู้ใช้:</strong> <?= htmlspecialchars($row['fullname']) ?></p>
          <p><strong>เลขที่การจอง:</strong> <?= (int)$row['booking_id'] ?> | <strong>โต๊ะ:</strong> <?= htmlspecialchars($row['desk_name']) ?></p>
          <p><strong>วันเวลา:</strong>
            <?= htmlspecialchars($row['booking_date']) ?>
            เวลา <?= substr($row['booking_start_time'], 0, 5) ?> - <?= substr($row['booking_end_time'], 0, 5) ?>
          </p>
          <p><strong>ยอดเงิน:</strong> <?= number_format((float)$row['amount'], 2) ?> บาท</p>
          <p><strong>สลิป:</strong><br><img src="<?= htmlspecialchars($row['slip']) ?>" alt="slip" style="max-width:200px;"></p>

          <div class="text-end">
            <button class="btn btn-danger me-2" onclick="verifyPayment('reject', <?= (int)$row['payment_id'] ?>)">ปฏิเสธ</button>
            <button class="btn btn-success" onclick="verifyPayment('approve', <?= (int)$row['payment_id'] ?>)">อนุมัติ</button>
          </div>
        </div>
      <?php endwhile; ?>
    </div>

  </div><!-- /main-content -->
</div><!-- /wrapper -->

<!-- (สำคัญ) โหลด Bootstrap JS ที่หน้านี้ เพื่อให้ dropdown/toast ใช้งานได้แน่นอน -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
  // อนุมัติ/ปฏิเสธการชำระเงิน
  function verifyPayment(action, id) {
    const url = action === 'approve' ? 'approve_payment.php' : 'reject_payment.php';
    if (confirm(action === 'approve' ? 'ยืนยันการอนุมัติ?' : 'ยืนยันการปฏิเสธ?')) {
      fetch(`${url}?payment_id=${id}`)
        .then(res => res.text())
        .then(response => {
          alert(response);
          const box = document.getElementById('box-' + id);
          if (box) box.remove();
        })
        .catch(() => alert('ไม่สามารถดำเนินการได้'));
    }
  }

  // ค้นหา (เผื่อมีช่อง #searchBox ใน navbar_admin1.php)
  (function(){
    const sb = document.getElementById('searchBox');
    if (!sb) return;
    sb.addEventListener('input', function () {
      const filter = this.value.toLowerCase();
      document.querySelectorAll('.payment-box').forEach(box => {
        const text = box.innerText.toLowerCase();
        box.style.display = text.includes(filter) ? 'block' : 'none';
      });
    });
  })();
</script>
</body>
</html>
