<?php
require 'db_connect.php';
header('Content-Type: application/json; charset=utf-8');
@date_default_timezone_set('Asia/Bangkok');
@$conn->query("SET time_zone = '+07:00'");

/* ✅ คืนตำแหน่งโต๊ะตามชั้น */
if (isset($_GET['area'])) {
    $area = (string)$_GET['area'];

    $stmt = $conn->prepare("
        SELECT desk_id, desk_name, pos_left, pos_top
        FROM desks
        WHERE areas = ?
    ");
    $stmt->bind_param("s", $area);
    $stmt->execute();
    $result = $stmt->get_result();

    $desks = [];
    while ($row = $result->fetch_assoc()) {
        // แปลงชนิดเล็กน้อยให้เป็นตัวเลข
        $row['desk_id']  = (int)$row['desk_id'];
        $row['pos_left'] = (float)$row['pos_left'];
        $row['pos_top']  = (float)$row['pos_top'];
        $desks[] = $row;
    }

    echo json_encode($desks, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ✅ คืนข้อมูลการจองของโต๊ะ (รองรับช่วงวัน start_date / end_date) */
if (isset($_GET['desk_id'])) {
    $desk_id   = (int)$_GET['desk_id'];
    $startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
    $endDate   = isset($_GET['end_date'])   ? trim($_GET['end_date'])   : '';

    // ใช้วันที่จาก booking_date ถ้ามี; ถ้าไม่มี ใช้วันที่จาก booking_start_time
    $dateExpr = "IFNULL(DATE(b.booking_date), DATE(b.booking_start_time))";

    // สร้าง WHERE/params แบบยืดหยุ่นตามช่วงวันที่ที่ส่งมา
    $where  = "$dateExpr IS NOT NULL AND b.desk_id = ? AND b.payment_verified = 'approved'";
    $types  = "i";
    $params = [$desk_id];

    if ($startDate !== '' && $endDate !== '') {
        $where  .= " AND $dateExpr BETWEEN ? AND ?";
        $types  .= "ss";
        $params[] = $startDate;
        $params[] = $endDate;
    } elseif ($startDate !== '') {
        $where  .= " AND $dateExpr >= ?";
        $types  .= "s";
        $params[] = $startDate;
    } elseif ($endDate !== '') {
        $where  .= " AND $dateExpr <= ?";
        $types  .= "s";
        $params[] = $endDate;
    }

    // ดึงรายการจอง
    $sql = "
        SELECT
            $dateExpr AS booking_date,
            TIME_FORMAT(b.booking_start_time, '%H:%i') AS booking_start_time,
            TIME_FORMAT(b.booking_end_time,   '%H:%i') AS booking_end_time,
            d.price_per_hour AS price,
            u.fullname
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.user_id
        LEFT JOIN desks d ON b.desk_id = d.desk_id
        WHERE $where
        ORDER BY $dateExpr DESC, b.booking_start_time DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $bookings = [];
    $total = 0.0;
    while ($row = $result->fetch_assoc()) {
        $price = (float)$row['price'];
        $total += $price;

        $bookings[] = [
            'booking_date'       => $row['booking_date'],
            'booking_start_time' => $row['booking_start_time'],
            'booking_end_time'   => $row['booking_end_time'],
            'price'              => $price,
            'fullname'           => $row['fullname'],
        ];
    }
    $stmt->close();

    echo json_encode([
        'count'    => count($bookings),
        'revenue'  => $total,
        'bookings' => $bookings
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ❌ fallback */
http_response_code(400);
echo json_encode(["error" => "invalid request"], JSON_UNESCAPED_UNICODE);
