<?php
// manage_user.php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT user_id, fullname, profile_pic, role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$adminRes = $stmt->get_result();
$admin = $adminRes->fetch_assoc();
$stmt->close();

if (!$admin || ($admin['role'] ?? '') !== 'admin') { echo "คุณไม่มีสิทธิ์เข้าถึงหน้านี้"; exit(); }

$users = [];
$q = $conn->query("SELECT user_id, fullname, username, email, phone, profile_pic FROM users WHERE role='user' ORDER BY fullname ASC");
while ($row = $q->fetch_assoc()) { $users[] = $row; }
$conn->close();

function maskPartial($text) {
  $len = mb_strlen($text, 'UTF-8');
  if ($len <= 2) return str_repeat('•', $len);
  return mb_substr($text,0,1,'UTF-8') . str_repeat('•', max(1,$len-2)) . mb_substr($text,-1,1,'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>จัดการผู้ใช้</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- CSS libs -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">

  <!-- App CSS -->
  <link href="/coworking/style.css" rel="stylesheet">
  <link href="/coworking/style1.css" rel="stylesheet">
  <style>
.page-manage-user .container-fluid{ padding-left:0; padding-right:0; }
.page-manage-user .page-header{ display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin:24px 0 12px; }
.page-manage-user .page-title{ display:flex; align-items:center; gap:12px; }
.page-manage-user .page-title i{ font-size:28px; color: var(--accent, #6366f1); }
.page-manage-user .page-title h2{ margin:0; font-weight:700; }
.page-manage-user .card{ border:none; border-radius:16px; box-shadow:0 8px 24px rgba(15,23,42,.06); background:#fff; }
.page-manage-user .card-body{ padding:16px; }
.page-manage-user .table thead th{ background:#0d6efd; color:#fff; border-color:#0d6efd; vertical-align:middle; }
.page-manage-user .table tbody tr:hover{ background:#f9fbff; }
.page-manage-user .dt-search, .page-manage-user .dataTables_filter{ display:none!important; }
.page-manage-user .dataTables_wrapper .dataTables_paginate .paginate_button{ border-radius:10px!important; }
.page-manage-user .dataTables_wrapper .dataTables_paginate .paginate_button.current{ background:#0d6efd!important; color:#fff!important; border:none!important; }
.page-manage-user .dataTables_wrapper .dataTables_length select,
.page-manage-user .dataTables_wrapper .dataTables_paginate .paginate_button{ border-radius:10px; }
.page-manage-user .avatar{ width:38px; height:38px; border-radius:50%; object-fit:cover; border:1px solid #e9ecef; }
.page-manage-user .avatar-fallback, .page-manage-user .avatar-fallback-lg{
  display:inline-flex; align-items:center; justify-content:center; background:#f8f9fa; color:#adb5bd; border:1px solid #e9ecef; border-radius:50%;
}
.page-manage-user .avatar-fallback{ width:38px; height:38px; }
.page-manage-user .avatar-fallback-lg{ width:56px; height:56px; font-size:28px; }
.page-manage-user .badge-role{ background:#eef2ff; color:#4f46e5; font-weight:600; border:1px solid #e5e7eb; }
.page-manage-user #viewModal .modal-content{ border:0; border-radius:16px; }
.page-manage-user #viewModal .modal-header, .page-manage-user #viewModal .modal-footer{ border:0; }
:root { --sidebar-w:240px; --topbar-h:60px; }
html,body{height:100%}
body{margin:0;background:#f6f7fb;overflow-x:hidden;padding-top:var(--topbar-h);}
.wrapper{position:relative;min-height:100vh}
.main-sidebar{position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;overflow:hidden;scrollbar-width:none;z-index:1040}
.main-sidebar::-webkit-scrollbar{display:none}
.main-content{margin-left:var(--sidebar-w);min-height:100vh;padding:0 0 16px}
.app-topbar{position:fixed!important;top:0;right:0;left:var(--sidebar-w);height:var(--topbar-h);z-index:1050;background:#fff;border-bottom:1px solid #e9ecef;padding-left:0!important;padding-right:0!important;margin:0!important;border-radius:0!important;box-shadow:none!important}
.app-topbar .container-fluid{height:100%}
  /* ===== บีบหัวข้อให้ชิดแถบนำทางมากที่สุด ===== */

/* 1) ให้ความสูง topbar และระยะเว้นด้านบนตรงกันเป๊ะ */
:root{ --topbar-h: 60px; }                    /* ปรับให้เท่ากับ .app-topbar จริง (56/60 ตามของคุณ) */
body{ padding-top: var(--topbar-h) !important; }

/* 2) ตัดระยะบนของโครงครอบเนื้อหาในหน้านี้ */
.page-manage-user .content{ padding-top:0 !important; margin-top:0 !important; }
.page-manage-user .container-fluid{ padding-top:0 !important; margin-top:0 !important; }

/* 3) ตัด margin ของหัวข้อ + ป้องกัน margin-collapse */
.page-manage-user .page-header{ margin:0 0 8px !important; padding-top:14px; }
.page-manage-user .page-title h2{ margin:0 !important; }

/* 4) เผื่อมี breadcrumb/แถบย่อยใน navbar_admin1.php ที่เผลอมี mt-3/pt-3 */
.app-topbar .breadcrumb,
.app-topbar .toolbar,
.app-topbar .container-fluid > .row:first-child{
  margin-top:0 !important; padding-top:0 !important;
}

/* 5) ยังรู้สึกห่าง? ดึงหัวข้อขึ้นอีกเล็กน้อยด้วย margin ลบ (ปลอดภัยต่อ overlay) */
@media (min-width: 0){
  .page-manage-user .page-header{ margin-top:2px !important; } /* ลอง -4px ถึง -10px ได้ */
}

  </style>
</head>
<body class="page-manage-user">
<?php
  if (!defined('NAV_API_BASE')) define('NAV_API_BASE', '/coworking/');
  if (!defined('APP_BOOTSTRAP_CSS')) define('APP_BOOTSTRAP_CSS', true);
  if (!defined('NAV_HOME_HREF'))     define('NAV_HOME_HREF', 'desk_status.php');
  include 'navbar_admin1.php';
?>

<div class="wrapper">
  <?php include 'sidebar_admin.php'; ?>

  <div class="content">
    <div class="container-fluid">
      <div class="page-header">
        <div class="page-title">
          <i class="bi bi-people-fill"></i>
          <h2>รายชื่อผู้ใช้ทั้งหมด</h2>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <div class="table-responsive">
            <table id="userTable" class="table table-hover align-middle">
              <thead>
                <tr class="text-center">
                  <th style="width:60px;">#</th>
                  <th>ชื่อ-นามสกุล</th>
                  <th>ชื่อผู้ใช้</th>
                  <th>อีเมล</th>
                  <th>เบอร์โทร</th>
                  <th style="width:140px;">จัดการ</th>
                </tr>
              </thead>
              <tbody>
              <?php if (!empty($users)): ?>
                <?php foreach($users as $i => $u): ?>
                  <?php
                    $pic = basename($u['profile_pic'] ?? '');
                    $exists = !empty($pic) && file_exists(__DIR__ . '/uploads/' . $pic);
                    $avatar = $exists ? 'uploads/' . htmlspecialchars($pic) : '';
                  ?>
                  <tr>
                    <td class="text-center"><?= $i+1 ?></td>
                    <td>
                      <div class="d-flex align-items-center gap-2">
                        <?php if ($avatar): ?>
                          <img class="avatar" src="<?= $avatar ?>" alt="">
                        <?php else: ?>
                          <span class="avatar-fallback"><i class="bi bi-person"></i></span>
                        <?php endif; ?>
                        <div>
                          <div class="fw-semibold"><?= htmlspecialchars($u['fullname']) ?></div>
                          <div class="small text-muted"><span class="badge rounded-pill badge-role">USER</span></div>
                        </div>
                      </div>
                    </td>
                    <td><?= htmlspecialchars(maskPartial($u['username'])) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['phone']) ?></td>
                    <td class="text-center">
                      <button type="button"
                              class="btn btn-outline-primary btn-sm btn-view"
                              title="ดูรายละเอียด"
                              data-fullname="<?= htmlspecialchars($u['fullname']) ?>"
                              data-username="<?= htmlspecialchars($u['username']) ?>"
                              data-email="<?= htmlspecialchars($u['email']) ?>"
                              data-phone="<?= htmlspecialchars($u['phone']) ?>"
                              data-avatar="<?= $avatar ?>">
                        <i class="bi bi-eye"></i>
                      </button>

                      <!-- ปุ่มลบแบบ AJAX -->
                      <button type="button"
                              class="btn btn-outline-danger btn-sm btn-delete"
                              title="ลบ"
                              data-id="<?= (int)$u['user_id'] ?>"
                              data-name="<?= htmlspecialchars($u['fullname']) ?>">
                        <i class="bi bi-trash"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="6" class="text-center text-muted py-4">ไม่มีข้อมูลผู้ใช้</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="small text-muted mt-2">
            * เพื่อความปลอดภัย จะไม่แสดงรหัสผ่านของผู้ใช้บนหน้าจอนี้
          </div>
        </div>
      </div>
    </div>

    <!-- View Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-body p-4">
            <div class="d-flex align-items-center gap-3 mb-3">
              <div id="vmAvatarWrap"></div>
              <div>
                <div class="fw-bold fs-5" id="vmFullname">-</div>
                <div class="small text-muted"><span class="badge rounded-pill badge-role">USER</span></div>
              </div>
            </div>
            <div class="row g-2">
              <div class="col-12">
                <label class="form-label small text-muted mb-0">ชื่อผู้ใช้</label>
                <div class="form-control" id="vmUsername" readonly>-</div>
              </div>
              <div class="col-md-6">
                <label class="form-label small text-muted mb-0">อีเมล</label>
                <div class="form-control" id="vmEmail" readonly>-</div>
              </div>
              <div class="col-md-6">
                <label class="form-label small text-muted mb-0">เบอร์โทร</label>
                <div class="form-control" id="vmPhone" readonly>-</div>
              </div>
            </div>
          </div>
          <div class="modal-footer px-4 pb-4">
            <button class="btn btn-light" data-bs-dismiss="modal">ปิด</button>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /content -->
</div><!-- /wrapper -->

<!-- JS libs -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
  // DataTable
  const dt = new DataTable('#userTable', {
    ordering: false,
    language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/th.json' },
    pageLength: 10,
    lengthMenu: [5,10,25,50,100],
    columnDefs: [{ className: 'text-center', targets: [0,5] }]
  });

  // ค้นหา: bind กับช่องค้นหาใน navbar_admin1.php
  (function bindNavbarSearch(){
    const form  = document.querySelector('.app-topbar form[role="search"]');
    const input = form ? form.querySelector('input[name="q"]') : null;
    if (!input) return;
    input.addEventListener('keyup', () => dt.search(input.value).draw());
    form.addEventListener('submit', (e) => { e.preventDefault(); dt.search(input.value).draw(); });
  })();

  // เปิดดูรายละเอียด (โมดอล)
  (function wireRowView(){
    const table = document.getElementById('userTable');
    const modalEl = document.getElementById('viewModal');
    const modal = new bootstrap.Modal(modalEl);

    const vmFullname = document.getElementById('vmFullname');
    const vmUsername = document.getElementById('vmUsername');
    const vmEmail    = document.getElementById('vmEmail');
    const vmPhone    = document.getElementById('vmPhone');
    const vmWrap     = document.getElementById('vmAvatarWrap');

    table.addEventListener('click', (e) => {
      const btn = e.target.closest('.btn-view');
      if (!btn) return;

      const fullname = btn.dataset.fullname || '-';
      const username = btn.dataset.username || '-';
      const email    = btn.dataset.email    || '-';
      const phone    = btn.dataset.phone    || '-';
      const avatar   = btn.dataset.avatar   || '';

      vmFullname.textContent = fullname;
      vmUsername.textContent = username;
      vmEmail.textContent    = email;
      vmPhone.textContent    = phone;

      vmWrap.innerHTML = '';
      if (avatar) {
        const img = document.createElement('img');
        img.className = 'avatar';
        img.style.cssText = 'width:56px;height:56px;border-radius:50%;object-fit:cover;border:1px solid #e9ecef;';
        img.src = avatar;
        img.onerror = () => vmWrap.innerHTML = '<span class="avatar-fallback-lg"><i class="bi bi-person"></i></span>';
        vmWrap.appendChild(img);
      } else {
        vmWrap.innerHTML = '<span class="avatar-fallback-lg"><i class="bi bi-person"></i></span>';
      }

      modal.show();
    });
  })();

  // ลบผู้ใช้แบบ AJAX
  (function wireDeleteUser(){
    const table = document.getElementById('userTable');
    table.addEventListener('click', async (e) => {
      const btn = e.target.closest('.btn-delete');
      if (!btn) return;

      const id   = btn.dataset.id;
      const name = btn.dataset.name || 'ผู้ใช้';

      if (!confirm(`ยืนยันการลบข้อมูลผู้ใช้: ${name} ?`)) return;

      try {
        const form = new FormData();
        form.append('id', id);

        const res = await fetch('delete_user.php', {
          method: 'POST',
          body: form,
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();

        if (!data.ok) {
          alert(data.error || 'ลบไม่สำเร็จ');
          return;
        }

        const row = btn.closest('tr');
        if (row) row.remove();
        if (window.dt) dt.rows().invalidate().draw(false);

        alert('ลบผู้ใช้สำเร็จ');
      } catch (err) {
        console.error(err);
        alert('เกิดข้อผิดพลาดระหว่างการลบ');
      }
    });
  })();
</script>
</body>
</html>
