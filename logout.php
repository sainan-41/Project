<?php  //หน้าล็อคเอาท์
session_start();
session_destroy();
header("Location: login.php");
