<!-- register ลงทะเบียน.php -->
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
  <title>สมัครสมาชิก</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body {
    background: url('myPic/bg-hero.png') no-repeat center center fixed;
    background-size: cover;
  }

  .register-box {
    background: rgba(255, 255, 255, 0.53);
    padding: 30px;
    border-radius: 20px;
    box-shadow: 0 0 20px rgba(0,0,0,0.2);
    backdrop-filter: blur(3px);
  }
</style>


</head>
<body class="bg-light">
    <div class="container mt-5">
    
  <div class="container mt-5">
    <div class="row justify-content-center">
      <div class="col-12 col-md-4"></div>
      <div class="col-12 col-md-4 register-box">
          <div class="text-center mb-3">
            <img src="myPic/moon.png" alt="โลโก้" width="150">
          </div>
        <h2 class="mb-4 text-center">สมัครสมาชิก</h2>
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
      <div class="col-12 col-md-4"></div>
    </div>
  </div>
</body>
</html>
