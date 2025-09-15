<?php
// สลับสถานะโต๊ะ (close/open/toggle) — อัปเดตทั้ง DB และ session
session_start();
header('Content-Type: application/json; charset=utf-8');
require 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit();
}

$desk_id = isset($_POST['desk_id']) ? (int)$_POST['desk_id'] : 0;
$action  = $_POST['action'] ?? ''; // 'close' | 'open' | 'toggle'

if ($desk_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid desk_id'], JSON_UNESCAPED_UNICODE);
    exit();
}

// โหลดสถานะปัจจุบันจาก DB
$stmt = $conn->prepare("SELECT status FROM desks WHERE desk_id = ?");
$stmt->bind_param("i", $desk_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Desk not found'], JSON_UNESCAPED_UNICODE);
    exit();
}
$current = strtolower((string)$row['status']);

// คำนวณสถานะใหม่
if ($action === 'toggle') {
    $new = ($current === 'unavailable') ? 'available' : 'unavailable';
} elseif ($action === 'close') {
    $new = 'unavailable';
} elseif ($action === 'open') {
    $new = 'available';
} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid action'], JSON_UNESCAPED_UNICODE);
    exit();
}

// อัปเดต DB
$upd = $conn->prepare("UPDATE desks SET status = ? WHERE desk_id = ?");
$upd->bind_param("si", $new, $desk_id);
$ok = $upd->execute();

if (!$ok) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Update failed'], JSON_UNESCAPED_UNICODE);
    exit();
}

// อัปเดต session (เพื่อให้หน้าที่อ่าน session เห็นด้วย)
$_SESSION['closed_desks'] = $_SESSION['closed_desks'] ?? [];
$closed = &$_SESSION['closed_desks'];

if ($new === 'unavailable') {
    if (!in_array($desk_id, $closed, true)) { $closed[] = $desk_id; }
} else {
    $closed = array_values(array_filter($closed, fn($id) => (int)$id !== $desk_id));
}

echo json_encode([
    'ok'        => true,
    'desk_id'   => $desk_id,
    'newStatus' => $new,
    'is_closed' => ($new === 'unavailable'),
], JSON_UNESCAPED_UNICODE);
