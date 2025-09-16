<?php    //หน้าล็อคอินเข้าระบบ
include 'db_connect.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // ตรวจ role ก่อน redirect
            if ($user['role'] === 'admin') {
                header("Location: dashboard_admin.php");
            } else {
                header("Location: map.php");
            }
            exit();
        } else {
            echo "<script>alert('รหัสผ่านไม่ถูกต้อง'); window.location='login.php';</script>";
        }
    } else {
        echo "<script>alert('ไม่พบบัญชีผู้ใช้'); window.location='login.php';</script>";
    }
}
?>


<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>เข้าสู่ระบบ</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body {
    background: url('myPic/bg-hero.png') no-repeat center center fixed;
    background-size: cover;
  }

  .login-box {
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
    <div class="row justify-content-center">
      <div class="col-12 col-md-4"></div>
      <div class="col-12 col-md-4 login-box">
            <div class="text-center mb-3">
              <img src="myPic/moon.png" alt="โลโก้" width="200">
            </div>
        <h2 class="mb-4 text-center">เข้าสู่ระบบ</h2>
        <form method="POST">
          <div class="mb-3">
            <label>ชื่อผู้ใช้</label>
            <input type="text" name="username" class="form-control" required>
          </div>
          <div class="mb-3">
            <label>รหัสผ่าน</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-primary w-100">เข้าสู่ระบบ</button>
          <p class="mt-3 text-center">ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิก</a></p>
        </form>
      </div>
      <div class="col-12 col-md-4"></div>
    </div>
  </div>
</body>
</html>
