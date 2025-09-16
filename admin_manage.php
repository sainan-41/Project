<?php
// admin_manage.php
session_start();
require 'db_connect.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  header('Location: login.php');
  exit();
}
$admin_id = (int)$_SESSION['user_id'];

/* ===== Admin info for sidebar ===== */
$stmt = $conn->prepare("SELECT fullname, profile_pic FROM users WHERE user_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* ===== CSRF ===== */
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];

/* ===== Helpers: schema checks ===== */
function hasCol(mysqli $conn, string $table, string $col): bool {
  $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
  $st = $conn->prepare($sql);
  $st->bind_param("ss", $table, $col);
  $st->execute();
  $ok = (bool)$st->get_result()->fetch_row();
  $st->close();
  return $ok;
}

$hasUsername   = hasCol($conn, 'users', 'username');
$hasPhone      = hasCol($conn, 'users', 'phone');
$hasRole       = hasCol($conn, 'users', 'role');
$hasProfilePic = hasCol($conn, 'users', 'profile_pic');
$hasCreatedAt  = hasCol($conn, 'users', 'created_at');

/* ===== Build SELECT list (format time in MySQL as TH) ===== */
$selectCols = ['user_id','fullname','email'];
if ($hasUsername)   $selectCols[] = 'username';
if ($hasPhone)      $selectCols[] = 'phone';
if ($hasRole)       $selectCols[] = 'role';
if ($hasProfilePic) $selectCols[] = 'profile_pic';

if ($hasCreatedAt) {
  // หากฐานข้อมูลของคุณเก็บ created_at เป็น UTC จริง ๆ ให้ใช้บรรทัดล่าง (CONVERT_TZ) แทนบรรทัด DATE_FORMAT ปกติ
  // $selectCols[] = "DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', '+07:00'), '%d/%m/%Y %H:%i') AS created_at_th";
  // แต่ถ้าค่าใน DB อยู่ในโซนเดียวกับเซิร์ฟเวอร์/SESSION แล้ว (+07:00 จาก SET time_zone) ใช้บรรทัดนี้พอ:
  $selectCols[] = "DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') AS created_at_th";
}
$selectList = implode(', ', $selectCols);

/* ===== Query list of admins ===== */
$where = $hasRole ? "WHERE role = 'admin'" : "";
$orderBy = $hasCreatedAt ? "created_at DESC" : "user_id DESC";
$sql = "SELECT $selectList FROM users $where ORDER BY $orderBy";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
$admins = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <title>จัดการแอดมิน</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Bootstrap + Icons + style หลัก -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
   <!-- App CSS: navbar ก่อน, แล้วค่อย layout+sidebar -->
  <link href="/coworking/style.css" rel="stylesheet">    <!-- แถบนำทาง -->
  <link href="/coworking/style1.css" rel="stylesheet">   <!-- Sidebar + Layout -->

  <style>
    :root { --sidebar-w:240px; --topbar-h:60px; }
  html,body{height:100%}
  body{margin:0;background:#f6f7fb;overflow-x:hidden;padding-top:var(--topbar-h); /* กันท็อปบาร์ทับเนื้อหา */}
  .wrapper{position:relative;min-height:100vh}

  /* ========== Sidebar / Content / Topbar ========== */
  .main-sidebar{position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;overflow:hidden;scrollbar-width:none;z-index:1040}
  .main-sidebar::-webkit-scrollbar{display:none}
  .main-content{margin-left:var(--sidebar-w);min-height:100vh;padding:0 0 16px}
  .app-topbar{position:fixed!important;top:0;right:0;left:var(--sidebar-w);height:var(--topbar-h);
    z-index:1050;background:#fff;border-bottom:1px solid #e9ecef;
    padding-left:0!important;padding-right:0!important;margin:0!important;border-radius:0!important;box-shadow:none!important}
  .app-topbar .container-fluid{height:100%}

    /* ตาราง */
    .table thead th { background:#0d6efd; color:#fff; text-align:center; vertical-align:middle; border-bottom:none; }
    .table tbody td { vertical-align:middle; }
    .table-responsive { margin-top:6px; }
    .avatar { width:80px; height:80px; object-fit:cover; border-radius:50%; border:3px solid #e9ecef; }
  </style>
</head>
<body>
<div class="wrapper">
  <?php include 'sidebar_admin.php'; ?>

  <div class="content">

    <?php
        if (!defined('NAV_API_BASE')) define('NAV_API_BASE', '/coworking/');
        // ลิงก์ "หน้าหลัก" ที่ไอคอนบ้าน
        if (!defined('NAV_HOME_HREF')) define('NAV_HOME_HREF', 'desk_status.php');

        // กันโหลด Bootstrap/Icons ซ้ำในไฟล์ navbar
        if (!defined('APP_BOOTSTRAP_CSS'))      define('APP_BOOTSTRAP_CSS', true);
        if (!defined('BOOTSTRAP_ICONS_LOADED')) define('BOOTSTRAP_ICONS_LOADED', true);
        if (!defined('BOOTSTRAP_JS_LOADED'))    define('BOOTSTRAP_JS_LOADED', true);

        include 'navbar_admin1.php';
      ?>

    <!-- HEADER -->
    <header class="page-header mt-2">
      <h4 class="mb-4"><i class="bi bi-person-gear text-teal me-2"></i>จัดการแอดมิน</h4>
    </header>

    <main class="container-fluid pb-3">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
            <h5 class="mb-0">รายชื่อผู้ดูแลระบบ (Admins<?= $hasRole ? '' : ' / Users'; ?>)</h5>
            <div class="d-flex align-items-center gap-2">
              <span class="text-muted">ทั้งหมด: <strong id="totalCount"><?= count($admins) ?></strong> คน</span>
              <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-circle"></i> เพิ่มแอดมิน
              </button>
            </div>
          </div>

          <div class="table-responsive">
            <table id="adminTable" class="table table-hover align-middle">
              <thead>
                <tr>
                  <th style="width:60px;">#</th>
                  <th>ชื่อ-สกุล</th>
                  <?php if ($hasUsername): ?><th>ชื่อผู้ใช้</th><?php endif; ?>
                  <th>อีเมล</th>
                  <?php if ($hasPhone): ?><th>เบอร์โทร</th><?php endif; ?>
                  <?php if ($hasCreatedAt): ?><th>วันที่สมัคร</th><?php endif; ?>
                  <?php if ($hasRole): ?><th>บทบาท</th><?php endif; ?>
                  <th style="width:260px;">จัดการ</th>
                </tr>
              </thead>
              <tbody>
              <?php if (empty($admins)): ?>
                <tr><td colspan="<?= 4 + ($hasUsername?1:0) + ($hasPhone?1:0) + ($hasCreatedAt?1:0) + ($hasRole?1:0) ?>" class="text-center text-muted py-4">ไม่พบข้อมูล</td></tr>
              <?php else: foreach ($admins as $i => $u):
                // data-avatar เป็นพาธปกติ + กันแคช
                $avatarRaw = $hasProfilePic ? trim($u['profile_pic'] ?? '') : '';
                $avatar = '';
                if ($avatarRaw !== '') {
                  if (preg_match('~^https?://~i', $avatarRaw)) {
                    $avatar = $avatarRaw;
                  } else {
                    $p = ltrim($avatarRaw, './');
                    if (strpos($p, 'uploads/') !== 0) $p = 'uploads/' . $p;
                    $p = preg_replace('~^uploads/uploads/~', 'uploads/', $p);
                    $avatar = $p;
                    if (@is_file($p)) $avatar .= '?v=' . filemtime($p);
                  }
                }
              ?>
                <tr
                  data-id="<?= (int)$u['user_id']; ?>"
                  data-name="<?= htmlspecialchars($u['fullname'] ?? '-'); ?>"
                  data-username="<?= htmlspecialchars($hasUsername ? ($u['username'] ?? '') : ''); ?>"
                  data-email="<?= htmlspecialchars($u['email'] ?? '-'); ?>"
                  data-phone="<?= htmlspecialchars($hasPhone ? ($u['phone'] ?? '') : ''); ?>"
                  data-created="<?= htmlspecialchars($hasCreatedAt ? ($u['created_at_th'] ?? '') : ''); ?>"
                  data-role="<?= htmlspecialchars($hasRole ? ($u['role'] ?? '') : ''); ?>"
                  data-avatar="<?= htmlspecialchars($avatar) ?>"
                >
                  <td class="text-center"><?= $i+1 ?></td>
                  <td><?= htmlspecialchars($u['fullname'] ?? '-'); ?></td>
                  <?php if ($hasUsername): ?><td><?= htmlspecialchars($u['username'] ?? ''); ?></td><?php endif; ?>
                  <td><?= htmlspecialchars($u['email'] ?? '-'); ?></td>
                  <?php if ($hasPhone): ?><td><?= htmlspecialchars($u['phone'] ?? ''); ?></td><?php endif; ?>
                  <?php if ($hasCreatedAt): ?><td class="text-nowrap">
                    <?= !empty($u['created_at_th']) ? htmlspecialchars($u['created_at_th']) : '-' ?>
                  </td><?php endif; ?>
                  <?php if ($hasRole): ?><td class="text-center"><?= htmlspecialchars($u['role'] ?? '-') ?></td><?php endif; ?>
                  <td class="text-center">
                    <div class="btn-group">
                      <button class="btn btn-outline-primary btn-sm btn-view"><i class="bi bi-eye"></i> ดูรายละเอียด</button>
                      <button class="btn btn-outline-warning btn-sm btn-edit"><i class="bi bi-pencil-square"></i> แก้ไข</button>
                      <button class="btn btn-outline-danger btn-sm btn-delete"><i class="bi bi-trash"></i> ลบ</button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<!-- Modals -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-badge me-2"></i>รายละเอียดผู้ใช้</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-center gap-3 mb-3">
          <img id="viewAvatar" class="avatar" src="" alt="avatar">
          <div>
            <div class="fw-bold fs-5" id="viewName">-</div>
            <div class="text-muted" id="viewEmail">-</div>
            <div class="text-muted" id="viewUsername" style="font-size:.9rem;">-</div>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <div class="small text-muted">เบอร์โทร</div>
            <div id="viewPhone" class="fw-semibold">-</div>
          </div>
          <div class="col-md-6">
            <div class="small text-muted">บทบาท</div>
            <div id="viewRole" class="fw-semibold">-</div>
          </div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button></div>
    </div>
  </div>
</div>

<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>ยืนยันการลบ</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
      </div>
      <div class="modal-body">
        ต้องการลบผู้ใช้: <span class="fw-bold" id="delName">-</span> ใช่หรือไม่?
        <input type="hidden" id="delId" value="">
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
        <button id="btnConfirmDelete" class="btn btn-danger"><i class="bi bi-trash"></i> ลบเลย</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title text-success"><i class="bi bi-person-plus me-2"></i>เพิ่มแอดมิน</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="addAdminForm" enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
          <div class="mb-2">
            <label class="form-label">ชื่อ-นามสกุล</label>
            <input type="text" name="fullname" class="form-control" required>
          </div>
          <?php if ($hasUsername): ?>
          <div class="mb-2">
            <label class="form-label">ชื่อผู้ใช้ (Username)</label>
            <input type="text" name="username" class="form-control">
          </div>
          <?php endif; ?>
          <div class="mb-2">
            <label class="form-label">อีเมล</label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <?php if ($hasPhone): ?>
          <div class="mb-2">
            <label class="form-label">เบอร์โทร</label>
            <input type="text" name="phone" class="form-control">
          </div>
          <?php endif; ?>
          <div class="mb-2">
            <label class="form-label">รหัสผ่าน</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <?php if ($hasProfilePic): ?>
          <div class="mb-2">
            <label class="form-label">รูปโปรไฟล์</label>
            <input type="file" name="profile_pic" accept="image/*" class="form-control">
          </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> บันทึก</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title text-warning"><i class="bi bi-pencil-square me-2"></i>แก้ไขแอดมิน</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="editAdminForm" enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
          <input type="hidden" name="user_id" id="editUserId">
          <div class="mb-2">
            <label class="form-label">ชื่อ-นามสกุล</label>
            <input type="text" name="fullname" id="editFullname" class="form-control" required>
          </div>
          <?php if ($hasUsername): ?>
          <div class="mb-2">
            <label class="form-label">ชื่อผู้ใช้ (Username)</label>
            <input type="text" name="username" id="editUsername" class="form-control">
          </div>
          <?php endif; ?>
          <div class="mb-2">
            <label class="form-label">อีเมล</label>
            <input type="email" name="email" id="editEmail" class="form-control" required>
          </div>
          <?php if ($hasPhone): ?>
          <div class="mb-2">
            <label class="form-label">เบอร์โทร</label>
            <input type="text" name="phone" id="editPhone" class="form-control">
          </div>
          <?php endif; ?>
          <div class="mb-2">
            <label class="form-label">รหัสผ่านใหม่</label>
            <input type="password" name="password" id="editPassword" class="form-control" placeholder="ไม่เปลี่ยนให้เว้นว่าง">
          </div>
          <?php if ($hasProfilePic): ?>
          <div class="mb-2">
            <label class="form-label">รูปโปรไฟล์ใหม่ (ถ้ามี)</label>
            <input type="file" name="profile_pic" accept="image/*" class="form-control">
          </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-warning"><i class="bi bi-save"></i> บันทึกการแก้ไข</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const csrf = "<?= $csrf ?>";
  const myId = <?= (int)$_SESSION['user_id'] ?>;

  const tableBody = document.querySelector('#adminTable tbody');
  const totalEl   = document.getElementById('totalCount');

  const viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
  const delModal  = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
  const editModal = new bootstrap.Modal(document.getElementById('editModal'));

  tableBody.addEventListener('click', (e) => {
    const tr = e.target.closest('tr'); if (!tr) return;

    if (e.target.closest('.btn-view')) {
      const name     = tr.dataset.name || '-';
      const email    = tr.dataset.email || '-';
      const username = tr.dataset.username || '-';
      const phone    = tr.dataset.phone || '-';
      const role     = tr.dataset.role || 'N/A';

      document.getElementById('viewName').textContent     = name;
      document.getElementById('viewEmail').textContent    = email;
      document.getElementById('viewUsername').textContent = (username && username !== '-') ? '@' + username : 'N/A';
      document.getElementById('viewPhone').textContent    = phone || 'N/A';
      document.getElementById('viewRole').textContent     = role || 'N/A';

      const avatar = tr.dataset.avatar || '';
      document.getElementById('viewAvatar').src =
        avatar ? avatar : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(name) + '&background=EEF2FF&color=27364B';

      viewModal.show();
      return;
    }

    if (e.target.closest('.btn-edit')) {
      document.getElementById('editUserId').value = tr.dataset.id || '';
      document.getElementById('editFullname').value = tr.dataset.name || '';
      document.getElementById('editEmail').value = tr.dataset.email || '';
      const editUsername = document.getElementById('editUsername');
      if (editUsername) editUsername.value = tr.dataset.username || '';
      const editPhone = document.getElementById('editPhone');
      if (editPhone) editPhone.value = tr.dataset.phone || '';
      document.getElementById('editPassword').value = '';
      editModal.show();
      return;
    }

    if (e.target.closest('.btn-delete')) {
      const id = parseInt(tr.dataset.id, 10);
      const name = tr.dataset.name || '-';
      if (id === myId) { alert('ไม่สามารถลบบัญชีของตัวเองได้'); return; }
      document.getElementById('delId').value = id;
      document.getElementById('delName').textContent = name;
      delModal.show();
      return;
    }
  });

  document.getElementById('btnConfirmDelete').addEventListener('click', async () => {
    const id = parseInt(document.getElementById('delId').value, 10);
    if (!id) return;
    try {
      const res = await fetch('delete_admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ user_id: id, csrf_token: '<?= $csrf ?>' })
      });
      const data = await res.json();
      if (!res.ok) { alert(data.error || 'ลบไม่สำเร็จ'); return; }
      const tr = document.querySelector(`tr[data-id="${id}"]`);
      if (tr) tr.remove();
      const visible = document.querySelectorAll('#adminTable tbody tr').length;
      totalEl.textContent = visible;
      delModal.hide();
    } catch { alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์'); }
  });

  document.getElementById('addAdminForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
      const res = await fetch('add_admin.php', { method: 'POST', body: formData });
      const data = await res.json();
      if (!res.ok) { alert(data.error || 'เพิ่มไม่สำเร็จ'); return; }
      alert('เพิ่มแอดมินสำเร็จ');
      location.reload();
    } catch { alert('เกิดข้อผิดพลาด'); }
  });

  document.getElementById('editAdminForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
      const res = await fetch('edit_admin.php', { method: 'POST', body: formData });
      const data = await res.json();
      if (!res.ok) { alert(data.error || 'แก้ไขไม่สำเร็จ'); return; }
      alert('แก้ไขข้อมูลสำเร็จ');
      location.reload();
    } catch { alert('เกิดข้อผิดพลาด'); }
  });
});
</script>
</body>
</html>
