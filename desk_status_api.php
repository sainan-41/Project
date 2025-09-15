<?php
// desk_status_api1.php
// ส่งสถานะโต๊ะในพื้นที่ + ข้อมูลการจอง "วันนี้" ที่อนุมัติแล้ว (ถ้ามี)
// — ล้างข้อมูลผู้ใช้/เวลาเมื่อสถานะสรุปว่า "ว่าง" หรือ "ไม่สามารถใช้งานได้"

session_start();
header('Content-Type: application/json; charset=utf-8');
require 'db_connect.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit();
}

date_default_timezone_set('Asia/Bangkok');

$area  = $_GET['area'] ?? 'ชั้น 1';
$today = (new DateTime())->format('Y-m-d');
$now   = new DateTime();

// รองรับปิดโต๊ะจาก session เสริม (ถ้ามีใช้อยู่)
$closedSession = $_SESSION['closed_desks'] ?? [];
if (!is_array($closedSession)) $closedSession = [];

/**
 * กลยุทธ์เลือก booking วันนี้ (อนุมัติแล้ว) สำหรับแต่ละโต๊ะ:
 * - ให้ความสำคัญกับที่ "เช็คอินแล้วยังไม่เช็คเอาท์" ก่อน
 * - ถัดมา "ยังไม่เช็คอินแต่ยังไม่หมดเวลา"
 * - อื่น ๆ ตามเวลาเริ่ม
 * จากนั้นสรุปสีสถานะ: ว่าง / จองแล้ว / กำลังใช้งาน / ไม่สามารถใช้งานได้
 */
$sql = "
  SELECT 
    d.desk_id, d.desk_name, d.pos_top, d.pos_left, d.areas, d.status AS desk_status,
    b.booking_id, b.user_id, b.customer_name,
    b.booking_date, b.booking_start_time, b.booking_end_time,
    b.checkin_status, b.checkout_status,
    u.fullname,
    p.payment_verified
  FROM desks d
  LEFT JOIN (
    SELECT b1.*
    FROM bookings b1
    JOIN payments p1 
      ON p1.booking_id = b1.booking_id 
     AND LOWER(p1.payment_verified) = 'approved'
    WHERE b1.booking_date = ?
    ORDER BY
      CASE
        WHEN (b1.checkin_status = 'checked_in' AND (b1.checkout_status IS NULL OR b1.checkout_status <> 'checked_out')) THEN 0
        WHEN (b1.checkin_status IS NULL OR b1.checkin_status <> 'checked_in') THEN 1
        ELSE 2
      END,
      b1.booking_start_time ASC
  ) b ON b.desk_id = d.desk_id
  LEFT JOIN users u   ON u.user_id = b.user_id
  LEFT JOIN payments p ON p.booking_id = b.booking_id AND LOWER(p.payment_verified) = 'approved'
  WHERE d.areas = ?
  GROUP BY d.desk_id
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $today, $area);
$stmt->execute();
$res = $stmt->get_result();

$out = [];

while ($row = $res->fetch_assoc()) {
    $deskId       = (int)$row['desk_id'];
    $deskStatusDB = strtolower((string)$row['desk_status']);

    $isClosedDb  = ($deskStatusDB === 'unavailable');
    $isClosedSes = in_array($deskId, $closedSession, true);
    $isClosed    = $isClosedDb || $isClosedSes;

    // ค่าเริ่มต้น
    $status_label = 'ว่าง';
    $fullname     = '';
    $booking_date = '';
    $start_time   = '';
    $end_time     = '';
    $bookingId    = !empty($row['booking_id']) ? (int)$row['booking_id'] : null;
    $approved     = strtolower((string)$row['payment_verified']) === 'approved';

    if ($isClosed) {
        // ปิดโต๊ะ: บังคับ "ไม่สามารถใช้งานได้" และไม่ผูก booking ใด ๆ
        $status_label = 'ไม่สามารถใช้งานได้';
        $bookingId    = null;
        $fullname = $booking_date = $start_time = $end_time = '';
    } else {
        if ($bookingId && $approved) {
            // แปลงเป็น DateTime เพื่อเทียบกับตอนนี้
            $startDT = (!empty($row['booking_date']) && !empty($row['booking_start_time']))
                ? DateTime::createFromFormat('Y-m-d H:i:s', $row['booking_date'].' '.$row['booking_start_time'])
                : null;
            $endDT = (!empty($row['booking_date']) && !empty($row['booking_end_time']))
                ? DateTime::createFromFormat('Y-m-d H:i:s', $row['booking_date'].' '.$row['booking_end_time'])
                : null;

            if ($startDT) { $booking_date = $startDT->format('Y-m-d'); $start_time = $startDT->format('H:i'); }
            if ($endDT)   { $end_time     = $endDT->format('H:i'); }

            $checkedIn  = strtolower(trim((string)$row['checkin_status']))  === 'checked_in';
            $checkedOut = strtolower(trim((string)$row['checkout_status'])) === 'checked_out';

            // กฎสรุปสถานะ
            if ($checkedOut) {
                // เช็คเอาท์แล้ว => ว่าง + ล้างข้อมูลการจอง
                $status_label = 'ว่าง';
                $bookingId = null;
            } elseif ($endDT && $now > $endDT) {
                // หมดเวลาแล้ว => ว่าง + ล้างข้อมูลการจอง
                $status_label = 'ว่าง';
                $bookingId = null;
            } elseif ($checkedIn) {
                // เช็คอินอยู่และยังไม่หมดเวลา/ยังไม่เช็คเอาท์
                $status_label = 'กำลังใช้งาน';
                $fullname = $row['fullname'] ?? '';
            } else {
                // อนุมัติแล้วของวันนี้ แต่ยังไม่เช็คอินและยังไม่หมดเวลา
                $status_label = 'จองแล้ว';
                $fullname = $row['fullname'] ?? '';
            }
        } else {
            // ไม่มี booking อนุมัติของวันนี้
            $status_label = 'ว่าง';
            $bookingId = null;
        }

        // 🔑 สำคัญ: ถ้าสรุปว่า "ว่าง" ให้ล้างข้อมูลการจองออกเสมอ
        if ($status_label === 'ว่าง') {
            $bookingId = null;
            $fullname = '';
            $booking_date = '';
            $start_time = '';
            $end_time = '';
        }
    }

    $out[] = [
        'desk_id'      => $deskId,
        'desk_name'    => $row['desk_name'],
        'status_label' => $status_label,          // ว่าง / จองแล้ว / กำลังใช้งาน / ไม่สามารถใช้งานได้
        'is_closed'    => $isClosed,              // true/false
        'fullname'     => $fullname,              // ชื่อผู้ใช้งาน (ถ้ามี)
        'booking_id'   => $bookingId,             // id การจอง (ถ้ามีและยัง active)
        'booking_date' => $booking_date,          // YYYY-mm-dd (ถ้ามี)
        'start_time'   => $start_time,            // HH:ii (ถ้ามี)
        'end_time'     => $end_time,              // HH:ii (ถ้ามี)
        'pos_top'      => $row['pos_top'],
        'pos_left'     => $row['pos_left'],
    ];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
