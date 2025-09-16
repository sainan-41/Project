<?php
include 'db_connect.php';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = $_POST['fullname'] ?? '';
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (fullname, username, email, phone, password) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $fullname, $username, $email, $phone, $hashed_password);

    if ($stmt->execute()) {
        echo "<script>alert('สมัครสมาชิกสำเร็จ!'); window.location='login.php';</script>";
    } else {
        echo "<script>alert('เกิดข้อผิดพลาดในการสมัครสมาชิก');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title> MoonCo-working Spaceยินดีต้อนรับ</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body, html {
      height: 100%;
      margin: 0;
      font-family: 'Prompt', sans-serif;
    }
    .hero {
      background: url('myPic/bg-hero.png') no-repeat center center/cover;
      height: 100vh;
      display: flex;
      align-items: center;
      color: white;
      position: relative;
    }
    .overlay {
      background-color: rgba(0,0,0,0.0);
      position: absolute;
      top: 0; left: 0;
      width: 100%; height: 100%;
    }
    .content {
      z-index: 2;
      display: flex;
      justify-content: space-between;
      align-items: center;
      width: 100%;
      padding: 0 10%;
      flex-wrap: wrap;
    }
    .intro {
      max-width: 50%;
    }
    .intro h1 {
      font-size: 3rem;
      font-weight: bold;
    }
    .intro p {
      font-size: 1.2rem;
    }
    .register-box {
      background: rgba(255, 255, 255, 0.53);
      padding: 30px;
      border-radius: 16px;
      color: black;
      width: 400px;
      box-shadow: 0 0 20px rgba(0,0,0,0.2);
      backdrop-filter: blur(3px);
    }
    .register-box h2 {
      font-weight: bold;
      margin-bottom: 20px;
    }
    .login-link {
      text-align: center;
      margin-top: 15px;
      font-size: 0.95rem;
    }
    .login-link a {
      color: #007bff;
      text-decoration: none;
    }
    .login-link a:hover {
      text-decoration: underline;
    }

  </style>
</head>
<body>

<div class="hero">
  <div class="overlay"></div>
  <div class="content">
    <div class="intro text-light">
         <!-- โลโก้ใหญ่ -->
  <img src="myPic/logo.png" alt="โลโก้ Moon Co-working" class="img-fluid mb-4" style="max-width: 280px;">
      <h1>เริ่มต้นประสบการณ์ใหม่กับ Co-Working Space</h1>
      <p>สำรองที่นั่งผ่านแผนที่เสมือนจริง เลือกโต๊ะได้ตามใจ เช็คอินง่ายผ่าน QR Code พร้อมระบบจัดการที่ทันสมัย</p>
      <!-- สไลด์ภาพ -->
    <div id="heroCarousel" class="carousel slide mt-4" data-bs-ride="carousel">
    <div class="carousel-inner rounded shadow">
      <div class="carousel-item active">
        <img src="myPic/slider1.png" class="d-block w-100" alt="รูป 1">
      </div>
      <div class="carousel-item">
        <img src="myPic/slider2.png" class="d-block w-100" alt="รูป 2">
      </div>
      <div class="carousel-item">
        <img src="myPic/slider3.png" class="d-block w-100" alt="รูป 3">
      </div>
      <div class="carousel-item">
        <img src="myPic/slider4.png" class="d-block w-100" alt="รูป 4">
      </div>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
      <span class="carousel-control-prev-icon bg-dark rounded-circle" aria-hidden="true"></span>
      <span class="visually-hidden">ก่อนหน้า</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
      <span class="carousel-control-next-icon bg-dark rounded-circle" aria-hidden="true"></span>
      <span class="visually-hidden">ถัดไป</span>
    </button>
    </div>
    </div>

    <div class="register-box">
      <h2 class="text-center">สมัครสมาชิก</h2>
      <form method="POST">
          <div class="mb-3">
            <label>ชื่อ - นามสกุล</label>
            <input type="text" name="fullname" class="form-control" required>
          </div>
          <div class="mb-3">
            <label>ชื่อผู้ใช้</label>
            <input type="text" name="username" class="form-control" required>
          </div>
          <div class="mb-3">
            <label>อีเมล</label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <div class="mb-3">
            <label>เบอร์โทรศัพท์</label>
            <input type="text" name="phone" class="form-control" required>
          </div>
          <div class="mb-3">
            <label>รหัสผ่าน</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-primary w-100">สมัครสมาชิก</button>
          <p class="mt-3 text-center">มีบัญชีแล้ว? <a href="login.php">เข้าสู่ระบบ</a></p>
        </form>
      
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
