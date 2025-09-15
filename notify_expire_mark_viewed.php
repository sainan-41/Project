<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0'); error_reporting(E_ERROR|E_PARSE);
require 'db_connect.php';
date_default_timezone_set('Asia/Bangkok');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'Unauthorized'], JSON_UNESCAPED_UNICODE); exit;
}

$warnMin = max(1, (int)($_GET['warn_min'] ?? 10));
$_SESSION['viewed_expire_booking_ids'] = $_SESSION['viewed_expire_booking_ids'] ?? [];

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id > 0 && !in_array($id, $_SESSION['viewed_expire_booking_ids'], true)) {
  $_SESSION['viewed_expire_booking_ids'][] = $id;   // แก้เฉพาะจุด: เก็บว่าอ่านแล้ว
}

echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
