<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0'); error_reporting(E_ERROR|E_PARSE);
date_default_timezone_set('Asia/Bangkok');

echo json_encode([
  'time_ms' => (int) round(microtime(true)*1000),
  'tz'      => date_default_timezone_get()
], JSON_UNESCAPED_UNICODE);
