<?php
include 'db_connect.php';
$date = $_POST['date'];

$sql = "SELECT 
          b.customer_name,
          d.desk_name,
          d.areas,
          b.booking_date,
          b.booking_start_time,
          b.booking_end_time,
          b.phone,
          b.note
        FROM bookings b
        LEFT JOIN desks d ON b.desk_id = d.desk_id
        WHERE b.booking_date = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    echo "<tr>
            <td>{$row['customer_name']}</td>
            <td>{$row['desk_name']}</td>
            <td>{$row['areas']}</td>
            <td>{$row['booking_date']}</td>
            <td>{$row['booking_start_time']}</td>
            <td>{$row['booking_end_time']}</td>
            <td>{$row['phone']}</td>
            <td>{$row['note']}</td>
          </tr>";
  }
} else {
  echo "<tr><td colspan='8'>ไม่พบรายการจอง</td></tr>";
}
?>
