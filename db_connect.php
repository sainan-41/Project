<?php
$servername = "localhost";
$username = "root";
$password = ""; // ใช้ XAMPP ทั่วไป ไม่ต้องใส่รหัสผ่าน
$dbname = "coworking_db"; // ชื่อฐานข้อมูล
$port = 3307;

$conn = new mysqli($servername, $username, $password, $dbname, $port);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
date_default_timezone_set('Asia/Bangkok');
// รองรับภาษาไทย
$conn->set_charset("utf8");
?>
