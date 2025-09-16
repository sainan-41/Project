<?php
// get_sales_data.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require 'db_connect.php';

date_default_timezone_set('Asia/Bangkok');

$start = $_GET['start'] ?? null;
$end   = $_GET['end']   ?? null;

$reDate = '/^\d{4}-\d{2}-\d{2}$/';
if (!$start || !$end || !preg_match($reDate, $start) || !preg_match($reDate, $end)) {
  http_response_code(400);
  echo json_encode(['error' => 'missing_or_invalid_date_range'], JSON_UNESCAPED_UNICODE);
  exit;
}

$result = [
  'total' => 0,
  'count' => 0,
  'top'   => [],
  'chart' => ['labels' => [], 'values' => []]
];

/* 1) รายได้รวม/จำนวนการจอง (เฉพาะอนุมัติ) + รายได้ต่อวัน */
$sqlAgg = "
  SELECT 
      b.booking_date,
      COUNT(*) AS total_bookings,
      COALESCE(SUM(CAST(p.amount AS DECIMAL(18,2))), 0) AS total_amount
  FROM bookings b
  JOIN payments p ON p.booking_id = b.booking_id
  WHERE b.booking_date BETWEEN ? AND ?
    AND LOWER(p.payment_verified) = 'approved'
  GROUP BY b.booking_date
  ORDER BY b.booking_date
";
$stmt = $conn->prepare($sqlAgg);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['error' => 'prepare_failed', 'detail' => $conn->error], JSON_UNESCAPED_UNICODE);
  exit;
}
$stmt->bind_param('ss', $start, $end);
$stmt->execute();
$rs = $stmt->get_result();

$revenueByDate = [];
$totalRevenue  = 0.0;
$totalBookings = 0;

while ($row = $rs->fetch_assoc()) {
  $date = $row['booking_date'];
  $amt  = (float)$row['total_amount'];
  $cnt  = (int)$row['total_bookings'];

  $revenueByDate[$date] = ($revenueByDate[$date] ?? 0) + $amt;
  $totalRevenue += $amt;
  $totalBookings += $cnt;
}
$stmt->close();

$result['total'] = round($totalRevenue, 2);
$result['count'] = (int)$totalBookings;

/* เติมวันให้ครบช่วงสำหรับกราฟ */
$labels = [];
$values = [];
try {
  $period = new DatePeriod(
    new DateTime($start),
    new DateInterval('P1D'),
    (new DateTime($end))->modify('+1 day')
  );
  foreach ($period as $d) {
    $k = $d->format('Y-m-d');
    $labels[] = $k;
    $values[] = isset($revenueByDate[$k]) ? (float)$revenueByDate[$k] : 0.0;
  }
} catch (Exception $e) {
  ksort($revenueByDate);
  $labels = array_keys($revenueByDate);
  $values = array_map('floatval', array_values($revenueByDate));
}
$result['chart'] = ['labels' => $labels, 'values' => $values];

/* 2) Top 3 โต๊ะ (เฉพาะอนุมัติ) */
$sqlTop = "
  SELECT d.desk_name, COUNT(*) AS total_bookings
  FROM bookings b
  JOIN payments p ON p.booking_id = b.booking_id
  LEFT JOIN desks d ON d.desk_id = b.desk_id
  WHERE b.booking_date BETWEEN ? AND ?
    AND LOWER(p.payment_verified) = 'approved'
  GROUP BY b.desk_id, d.desk_name
  ORDER BY total_bookings DESC
  LIMIT 3
";
$stmt = $conn->prepare($sqlTop);
$stmt->bind_param('ss', $start, $end);
$stmt->execute();
$top = [];
$qr = $stmt->get_result();
while ($row = $qr->fetch_assoc()) {
  $top[] = [
    'desk_name' => $row['desk_name'] ?? 'ไม่ระบุ',
    'total_bookings' => (int)$row['total_bookings'],
  ];
}
$stmt->close();
$result['top'] = $top;

/* ส่งออก */
echo json_encode($result, JSON_UNESCAPED_UNICODE);
