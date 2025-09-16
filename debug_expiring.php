<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
require 'db_connect.php';
date_default_timezone_set('Asia/Bangkok');

/* ให้ MySQL ใช้เวลาไทย */
@$conn->query("SET time_zone = '+07:00'");

$warnMin = isset($_GET['warn_min']) ? max(1,(int)$_GET['warn_min']) : 10;

/* ถ้าฐานข้อมูลเก็บ DATETIME เป็น UTC ให้สลับ true */
const DB_TIME_IS_UTC = false;  // <— ลอง false ก่อน ถ้ายังผิดวัน ค่อยเปลี่ยนเป็น true

$endExpr = DB_TIME_IS_UTC
  ? "CONVERT_TZ(b.booking_end_time,'+00:00','+07:00')"
  : "b.booking_end_time";

$sql = "
  SELECT b.booking_id, b.desk_id, d.desk_name,
         b.booking_end_time AS raw_end,
         $endExpr            AS end_bkk,
         DATE($endExpr)      AS end_bkk_date,
         NOW()               AS now_bkk,
         TIMESTAMPDIFF(MINUTE, NOW(), $endExpr) AS mins_left
  FROM bookings b
  JOIN desks d ON b.desk_id = d.desk_id
  WHERE b.checkin_time IS NOT NULL
    AND (b.checkout_time IS NULL OR b.checkout_time = '')
    AND b.booking_end_time IS NOT NULL
    AND b.booking_end_time <> '0000-00-00 00:00:00'
  ORDER BY $endExpr ASC
  LIMIT 50
";
$res = $conn->query($sql);
$out = [];
while($r = $res->fetch_assoc()){
  $out[] = [
    'booking_id'   => (int)$r['booking_id'],
    'desk'         => $r['desk_name'],
    'raw_end'      => $r['raw_end'],
    'end_bkk'      => $r['end_bkk'],
    'end_bkk_date' => $r['end_bkk_date'],
    'now_bkk'      => $r['now_bkk'],
    'mins_left'    => (int)$r['mins_left'],
    'is_today'     => ($r['end_bkk_date'] === date('Y-m-d')),
    'should_alert' => ($r['end_bkk_date'] === date('Y-m-d') && (int)$r['mins_left'] >= 0 && (int)$r['mins_left'] <= $warnMin),
  ];
}
echo json_encode(['DB_TIME_IS_UTC'=>DB_TIME_IS_UTC, 'warn_min'=>$warnMin, 'rows'=>$out], JSON_UNESCAPED_UNICODE);
