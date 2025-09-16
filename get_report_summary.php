<?php
// =============================
// File: get_report_summary.php
// =============================
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  http_response_code(403);
  echo json_encode(['error'=>'Unauthorized'], JSON_UNESCAPED_UNICODE);
  exit();
}

// ----- Read params -----
$mode = $_GET['mode'] ?? 'month'; // range | month | year (default: month)
$area = trim($_GET['area'] ?? '');

try {
  if ($mode === 'range') {
    $start = $_GET['start_date'] ?? date('Y-m-01');
    $end   = $_GET['end_date']   ?? date('Y-m-t');
  } elseif ($mode === 'year') {
    $year = intval($_GET['year'] ?? date('Y'));
    $start = sprintf('%04d-01-01', $year);
    $end   = sprintf('%04d-12-31', $year);
  } else { // month
    $month = $_GET['month'] ?? date('Y-m');
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');
    [$y,$m] = array_map('intval', explode('-', $month));
    $start = sprintf('%04d-%02d-01', $y, $m);
    $end   = date('Y-m-t', strtotime($start));
  }
} catch (Throwable $e) {
  $start = date('Y-m-01'); $end = date('Y-m-t');
}

$params = [ $start, $end ];
$types  = 'ss';
$areaSql = '';
if ($area !== '') { $areaSql = ' AND d.areas = ? '; $params[] = $area; $types .= 's'; }

// ---------- Totals ----------
$sqlTotals = "
  SELECT COUNT(*) AS total_bookings, COALESCE(SUM(p.amount),0) AS total_revenue
  FROM bookings b
  JOIN payments p ON p.booking_id = b.booking_id AND p.payment_verified = 'approved'
  JOIN desks d ON d.desk_id = b.desk_id
  WHERE b.booking_date BETWEEN ? AND ? $areaSql
";
$stmt = $conn->prepare($sqlTotals);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$totals = $stmt->get_result()->fetch_assoc() ?: ['total_bookings'=>0,'total_revenue'=>0];
$stmt->close();

// ---------- Occupancy % ----------
$sqlOcc = "
  SELECT COUNT(DISTINCT CONCAT(b.booking_date,'-',b.desk_id)) AS occupied
  FROM bookings b
  JOIN payments p ON p.booking_id = b.booking_id AND p.payment_verified = 'approved'
  JOIN desks d ON d.desk_id = b.desk_id
  WHERE b.booking_date BETWEEN ? AND ? $areaSql
";
$stmt = $conn->prepare($sqlOcc);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$occ = $stmt->get_result()->fetch_assoc();
$stmt->close();

$occupied = intval($occ['occupied'] ?? 0);

// desk count (capacity)
if ($area !== '') {
  $deskStmt = $conn->prepare("SELECT COUNT(*) AS c FROM desks WHERE areas = ?");
  $deskStmt->bind_param('s', $area);
  $deskStmt->execute();
  $deskCount = $deskStmt->get_result()->fetch_assoc()['c'] ?? 0;
  $deskStmt->close();
} else {
  $deskCount = $conn->query("SELECT COUNT(*) AS c FROM desks")->fetch_assoc()['c'] ?? 0;
}
$days = max(1, (new DateTime($start))->diff(new DateTime($end))->days + 1);
$totalSlots = max(1, $deskCount * $days);
$occupancy_pct = $totalSlots > 0 ? ($occupied / $totalSlots) * 100 : 0;

// ---------- Revenue by day ----------
$sqlByDay = "
  SELECT b.booking_date AS label, COALESCE(SUM(p.amount),0) AS revenue
  FROM bookings b
  JOIN payments p ON p.booking_id = b.booking_id AND p.payment_verified = 'approved'
  JOIN desks d ON d.desk_id = b.desk_id
  WHERE b.booking_date BETWEEN ? AND ? $areaSql
  GROUP BY b.booking_date
  ORDER BY b.booking_date
";
$stmt = $conn->prepare($sqlByDay);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$revenue_by_day = [];
while ($row = $res->fetch_assoc()) {
  $revenue_by_day[] = [
    'label'   => $row['label'],
    'revenue' => (float)$row['revenue'],
  ];
}
$stmt->close();

/* === NEW: Bookings by day (สำหรับกราฟจำนวนการจอง) === */
$sqlBkDay = "
  SELECT b.booking_date AS label, COUNT(*) AS cnt
  FROM bookings b
  JOIN payments p ON p.booking_id = b.booking_id AND p.payment_verified = 'approved'
  JOIN desks d ON d.desk_id = b.desk_id
  WHERE b.booking_date BETWEEN ? AND ? $areaSql
  GROUP BY b.booking_date
  ORDER BY b.booking_date
";
$stmt = $conn->prepare($sqlBkDay);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$bookings_by_day = [];
while ($row = $res->fetch_assoc()) {
  $bookings_by_day[] = [
    'label' => $row['label'],
    'count' => (int)$row['cnt'],
  ];
}
$stmt->close();

/* === NEW: Revenue by area (สำหรับกราฟสัดส่วนรายได้ตามพื้นที่) === */
$sqlArea = "
  SELECT COALESCE(d.areas,'ไม่ระบุ') AS area, COALESCE(SUM(p.amount),0) AS revenue
  FROM bookings b
  JOIN payments p ON p.booking_id = b.booking_id AND p.payment_verified = 'approved'
  LEFT JOIN desks d ON d.desk_id = b.desk_id
  WHERE b.booking_date BETWEEN ? AND ? " . ($area!=='' ? " AND d.areas = ? " : "") . "
  GROUP BY COALESCE(d.areas,'ไม่ระบุ')
  ORDER BY revenue DESC
";
$stmt = $conn->prepare($sqlArea);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$revenue_by_area = [];
while ($row = $res->fetch_assoc()) {
  $revenue_by_area[] = [
    'area'    => $row['area'],
    'revenue' => (float)$row['revenue'],
  ];
}
$stmt->close();

// ---------- Top 5 desks ----------
$sqlTop = "
  SELECT d.desk_name, COUNT(*) AS count, COALESCE(SUM(p.amount),0) AS revenue
  FROM bookings b
  JOIN payments p ON p.booking_id = b.booking_id AND p.payment_verified = 'approved'
  JOIN desks d ON d.desk_id = b.desk_id
  WHERE b.booking_date BETWEEN ? AND ? $areaSql
  GROUP BY d.desk_id
  ORDER BY count DESC, revenue DESC
  LIMIT 5
";
$stmt = $conn->prepare($sqlTop);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$top_desks = [];
$top_desk  = null;
while ($row = $res->fetch_assoc()) {
  $row['count']   = (int)$row['count'];
  $row['revenue'] = (float)$row['revenue'];
  $top_desks[] = $row;
  if ($top_desk===null) $top_desk = $row;
}
$stmt->close();

// ---------- Recent bookings ----------
$sqlRecent = "
  SELECT b.booking_date, b.booking_start_time, b.booking_end_time, b.customer_name, d.desk_name, p.amount
  FROM bookings b
  JOIN payments p ON p.booking_id = b.booking_id AND p.payment_verified = 'approved'
  JOIN desks d ON d.desk_id = b.desk_id
  WHERE b.booking_date BETWEEN ? AND ? $areaSql
  ORDER BY b.booking_date DESC, b.booking_start_time DESC
  LIMIT 10000
";
$stmt = $conn->prepare($sqlRecent);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$recent = [];
while ($row = $res->fetch_assoc()) {
  $recent[] = [
    'booking_date'       => $row['booking_date'],
    'booking_start_time' => $row['booking_start_time'],
    'booking_end_time'   => $row['booking_end_time'],
    'customer_name'      => $row['customer_name'],
    'desk_name'          => $row['desk_name'],
    'amount'             => (float)$row['amount'],
  ];
}
$stmt->close();

echo json_encode([
  'total_revenue'   => (float)($totals['total_revenue'] ?? 0),
  'total_bookings'  => (int)($totals['total_bookings'] ?? 0),
  'occupancy_pct'   => (float)$occupancy_pct,

  'revenue_by_day'  => $revenue_by_day,   // [{label:'YYYY-MM-DD', revenue:123}]
  'bookings_by_day' => $bookings_by_day,  // [{label:'YYYY-MM-DD', count:5}]
  'revenue_by_area' => $revenue_by_area,  // [{area:'ชั้น 1', revenue:9999}]

  'top_desk'        => $top_desk,
  'top_desks'       => $top_desks,
  'recent'          => $recent,
], JSON_UNESCAPED_UNICODE);
