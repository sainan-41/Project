

<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ดึงข้อมูล user ล่วงหน้าไว้ใช้ใน sidebar
$user = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
}

// ประกาศค่าผลลัพธ์ล่วงหน้า
$success = '';
$error = '';

// เมื่อมีการส่งฟอร์ม POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($new_password) < 6) {
        $error = 'รหัสผ่านใหม่ควรมีอย่างน้อย 6 ตัวอักษร';
    } elseif ($new_password !== $confirm_password) {
        $error = 'รหัสผ่านใหม่และการยืนยันไม่ตรงกัน';
    } elseif (!password_verify($old_password, $user['password'])) {
        $error = 'รหัสผ่านเดิมไม่ถูกต้อง';
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $update_stmt->bind_param("si", $hashed, $_SESSION['user_id']);
        if ($update_stmt->execute()) {
            $success = '🎉 เปลี่ยนรหัสผ่านสำเร็จแล้ว';
        } else {
            $error = 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เปลี่ยนรหัสผ่าน</title>
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
<body>

 <?php include 'sidebar_user.php'; ?>
<div class="d-flex">
   

    <div class="container-fluid">
        <div class="container-box">
            <h4 class="mb-4 text-center">🔐 เปลี่ยนรหัสผ่าน</h4>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php elseif ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label for="old_password" class="form-label">รหัสผ่านเดิม</label>
                    <input type="password" name="old_password" id="old_password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="new_password" class="form-label">รหัสผ่านใหม่</label>
                    <input type="password" name="new_password" id="new_password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">ยืนยันรหัสผ่านใหม่</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">ยืนยันการเปลี่ยนรหัสผ่าน</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
