<?php
session_start();
require 'db_connect.php';

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = $_POST['fullname'];
    $phone = $_POST['phone'];
    $profile_pic = $_FILES['profile_pic'];

    if ($profile_pic['name']) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir);
        $filename = $user_id . "_" . basename($profile_pic["name"]);
        $target_file = $target_dir . $filename;
        move_uploaded_file($profile_pic["tmp_name"], $target_file);

        $stmt = $conn->prepare("UPDATE users SET fullname=?, phone=?, profile_pic=? WHERE user_id=?");
        $stmt->bind_param("sssi", $fullname, $phone, $filename, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET fullname=?, phone=? WHERE user_id=?");
        $stmt->bind_param("ssi", $fullname, $phone, $user_id);
    }

    $stmt->execute();
    echo "<script>alert('อัปเดตโปรไฟล์แล้ว'); window.location='map.php';</script>";
}

// ดึงข้อมูลผู้ใช้
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>


<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>โปรไฟล์ของฉัน</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">




<style>
        body {
            background-image: url('myPic/bg-hero.png');
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
            background-position: center;
            min-height: 100vh;
            font-family: 'Prompt', sans-serif;
        }
        .container-box {
            max-width: 500px;
            margin: 40px auto;
            background: rgba(255, 255, 255, 0.86);
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .form-label {
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

<body class="bg-light">
  <!--เรียกsidebarของ user เข้ามาใช้-->
  <?php include 'sidebar_user.php'; ?>

  
<div class="container-fluid">
    <div class="container-box">
      <h3 class="mb-4 text-center">โปรไฟล์ของฉัน</h3>
      <form method="POST" enctype="multipart/form-data">
        <div class="mb-3 text-center">
          <img src="<?= $user['profile_pic'] ? 'uploads/' . $user['profile_pic'] : 'uploads/default.png' ?>" alt="รูปโปรไฟล์" class="rounded-circle" width="120" height="120">
        </div>
        <div class="mb-3">
          <label>ชื่อ - นามสกุล</label>
          <input type="text" name="fullname" class="form-control" value="<?= $user['fullname'] ?>" required>
        </div>
        <div class="mb-3">
          <label>เบอร์โทรศัพท์</label>
          <input type="text" name="phone" class="form-control" value="<?= $user['phone'] ?>" required>
        </div>
        <div class="mb-3">
          <label>อัปโหลดรูปโปรไฟล์</label>
          <input type="file" name="profile_pic" class="form-control">
        </div>
        <button type="submit" class="btn btn-primary w-100">บันทึกการเปลี่ยนแปลง</button>
      </form>
    </div>
  </div>
</body>
</html>
