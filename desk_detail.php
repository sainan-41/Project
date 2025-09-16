<?php  //modal ป็อปอัพทับหน้าแผนที่ แสดงรายละเอียดโต๊ะ และดึงเวลารายละเอียดในการค้นหาเข้ามาเพื่อทำการจองที่นั่ง
session_start();
require 'db_connect.php';

$desk_id = $_GET['desk_id'] ?? 0;
$booking_date = $_GET['date'] ?? date('Y-m-d');
$start_time = $_GET['start_time'] ?? '08:00';
$end_time = $_GET['end_time'] ?? '08:30';
$fullname = $_GET['fullname'] ?? '';

$stmt = $conn->prepare("SELECT * FROM desks WHERE desk_id = ?");
$stmt->bind_param("i", $desk_id);
$stmt->execute();
$desk = $stmt->get_result()->fetch_assoc();
?>
<div class="modal-header">
  <h5 class="modal-title"><?= htmlspecialchars($desk['desk_name']) ?></h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
</div>
<div class="modal-body">
  <div class="row">
    <div class="col-md-6 mb-3">
      <img src="desk_images/<?= htmlspecialchars($desk['image']) ?>" class="img-fluid rounded mb-3" alt="รูปโต๊ะ">
    </div>
    <div class="col-md-6">
      <p><?= nl2br(htmlspecialchars($desk['description'])) ?></p>
      <form action="reserve.php" method="POST">
        <input type="hidden" name="desk_id" value="<?= $desk_id ?>">
        <div class="mb-3">
          <label>ชื่อผู้จอง</label>
          <input type="text" name="customer_name" class="form-control" value="<?= htmlspecialchars($fullname) ?>" required>
        </div>
        <div class="mb-3">
          <label>วันจอง</label>
          <input type="date" name="booking_date" class="form-control" value="<?= htmlspecialchars($booking_date) ?>" required>
        </div>
        <div class="mb-3">
          <label>เวลาเริ่ม</label>
          <input type="time" name="booking_start_time" class="form-control" value="<?= htmlspecialchars($start_time) ?>" required>
        </div>
        <div class="mb-3">
          <label>เวลาสิ้นสุด</label>
          <input type="time" name="booking_end_time" class="form-control" value="<?= htmlspecialchars($end_time) ?>" required>
        </div>
        <div class="mb-3">
          <label>เบอร์โทรติดต่อ</label>
          <input type="text" name="phone" class="form-control" required>
        </div>
        <div class="mb-3">
          <label>หมายเหตุเพิ่มเติม</label>
          <textarea name="note" class="form-control"></textarea>
        </div>
        <button type="submit" class="btn btn-success">ยืนยันการจองและชำระเงิน</button>
      </form>
    </div>
  </div>
</div>
