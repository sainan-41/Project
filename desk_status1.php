<?php
session_start();
require 'db_connect.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
$admin_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT fullname, profile_pic FROM users WHERE user_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

$area = $_GET['area'] ?? 'ชั้น 1';
$image_map = [
  'ชั้น 1' => ['file' => 'floor1.png', 'width' => 601, 'height' => 491],
  'ชั้น 2' => ['file' => 'floor2.png', 'width' => 605, 'height' => 491],
  'ชั้น 3' => ['file' => 'floor3.png', 'width' => 601, 'height' => 520],
];
$current_map = $image_map[$area] ?? $image_map['ชั้น 1'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>ภาพรวม/สถานะ</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

  <!-- App CSS -->
  <link href="/coworking/style.css" rel="stylesheet">
  <link href="/coworking/style1.css" rel="stylesheet">

  <style>
    .page-desk-status .map-wrapper{ position: relative; width: 950px; background:#e6e3e3; border-radius: 20px; overflow: hidden;}
    .page-desk-status .map-wrapper img{ width:100%; display:block; }
    .page-desk-status .desk{ position:absolute; width:30px; height:30px; border-radius:50%; text-align:center; line-height:30px; font-weight:bold; font-size:13px; color:#fff; cursor:pointer; z-index:5; }
    .page-desk-status .in_use{ background-color: yellow; color:#000 !important; }
    .page-desk-status .available{ background-color: green; }
    .page-desk-status .reserved{ background-color: lightcoral; }
    .page-desk-status .unavailable{ background-color: gray; }

    .page-desk-status .small-box{ border-radius:.25rem; color:#fff; padding:15px; height:120px; position:relative; overflow:hidden; box-shadow:0 0 3px rgba(0,0,0,.2); max-width:90%; width:100%; min-height:100px;}
    .page-desk-status .small-box h3{ font-size:2.2rem; font-weight:bold; margin:0; }
    .page-desk-status .small-box p{ font-size:1rem; margin-bottom:0; }
    .page-desk-status .small-box .icon{ position:absolute; top:10px; right:15px; font-size:2rem; opacity:.5; }

    .page-desk-status .filter-container{ background:#fff;border-radius:14px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.08); margin-top:10px;margin-bottom:20px;}
    .page-desk-status .btn-sm{ padding:4px 8px; font-size:.85rem; line-height:1.2; }

    .page-desk-status .mini-calendar{ background:#6c757d;color:#fff;border-radius:12px;padding:12px;width:220px; }
    .page-desk-status .mini-calendar .cal-header{ display:flex; justify-content:space-between; align-items:center;font-weight:600; margin-bottom:8px; }
    .page-desk-status .mini-calendar button{ background:transparent; border:0; color:#fff; font-size:18px; line-height:1; padding:2px 6px; cursor:pointer; }
    .page-desk-status .mini-calendar .daynames,
    .page-desk-status .mini-calendar .days{ display:grid; grid-template-columns:repeat(7,1fr); gap:4px; }
    .page-desk-status .mini-calendar .dayname{ font-size:12px; opacity:.8; text-align:center; }
    .page-desk-status .mini-calendar .day{ font-size:13px; text-align:center; padding:6px 0; border-radius:8px; }
    .page-desk-status .mini-calendar .other{ opacity:.35; }
    .page-desk-status .mini-calendar .today{ background:#ffc107; color:#000; font-weight:700; }
    .page-desk-status .mini-calendar .cal-footer{ text-align:center; font-size:12px; margin-top:8px; opacity:.85; }

    @media print {
      .no-print { display:none !important; }
      .main-sidebar { display:none !important; }
      .main-content { margin-left:0 !important; padding-top:0 !important; }
      body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
    @media screen { .print-only{display:none;} }
    :root { --sidebar-w:240px; --topbar-h:60px; }
    html,body{height:100%}
    body{margin:0;background:#f6f7fb;overflow-x:hidden;padding-top:var(--topbar-h);}
    .wrapper{position:relative;min-height:100vh}
    .main-sidebar{position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;overflow:hidden;scrollbar-width:none;z-index:1040}
    .main-sidebar::-webkit-scrollbar{display:none}
    .main-content{margin-left:var(--sidebar-w);min-height:100vh;padding:0 0 16px}
    .app-topbar{position:fixed!important;top:0;right:0;left:var(--sidebar-w);height:var(--topbar-h); z-index:1050;background:#fff;border-bottom:1px solid #e9ecef; padding-left:0!important;padding-right:0!important;margin:0!important;border-radius:0!important;box-shadow:none!important}
    .app-topbar .container-fluid{height:100%}
  </style>
</head>
<body class="page-desk-status">
<div class="wrapper">
  <?php include 'sidebar_admin.php'; ?>

  <div class="main-content">
    <div class="no-print">
      <?php
        if (!defined('NAV_API_BASE')) define('NAV_API_BASE', '/coworking/');
        if (!defined('NAV_HOME_HREF')) define('NAV_HOME_HREF', 'desk_status1.php?area=' . rawurlencode($area));
        if (!defined('APP_BOOTSTRAP_CSS'))      define('APP_BOOTSTRAP_CSS', true);
        if (!defined('BOOTSTRAP_ICONS_LOADED')) define('BOOTSTRAP_ICONS_LOADED', true);
        if (!defined('BOOTSTRAP_JS_LOADED'))    define('BOOTSTRAP_JS_LOADED', true);
        include 'navbar_admin1.php';
      ?>
    </div>

    <div class="container ms-auto mt-3">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">ภาพรวม / สถานะ</h2>
        <div class="no-print">
          <button class="btn btn-success me-2" id="btnExcel"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Excel</button>
          <button class="btn btn-danger me-2" id="btnPDF"><i class="bi bi-filetype-pdf me-1"></i> PDF</button>
          <button class="btn btn-dark" onclick="window.print()"><i class="bi bi-printer me-1"></i> พิมพ์</button>
        </div>
      </div>
    </div>
    <hr class="mt-0">

    <div class="container ms-auto">
      <div class="filter-container">
        <div class="row g-3 align-items-stretch">
          <div class="col-md-3 col-sm-6">
            <div class="small-box" style="background-color:rgba(20, 146, 29, 1);">
              <div class="inner"><h3><span id="available_desks">0</span></h3><p>จำนวนที่นั่งว่างทั้งหมด</p></div>
              <div class="icon"><i class="bi bi-grid"></i></div>
            </div>
          </div>
          <div class="col-md-3 col-sm-6">
            <div class="small-box" style="background-color:rgb(255, 255, 0); color:#000;">
              <div class="inner"><h3><span id="in_use_now">0</span></h3><p>กำลังใช้งาน</p></div>
              <div class="icon"><i class="bi bi-person-check"></i></div>
            </div>
          </div>
          <div class="col-md-3 col-sm-6">
            <div class="small-box" style="background-color:rgb(46, 60, 251);">
              <div class="inner"><h3><span id="total_users_today">0</span></h3><p>ผู้ใช้งานวันนี้</p></div>
              <div class="icon"><i class="bi bi-people-fill"></i></div>
            </div>
          </div>
          <div class="col-md-3 col-sm-6">
            <div class="small-box" style="background-color:#dc3545;">
              <div class="inner"><h3><span id="total_revenue">0.00 บาท</span></h3><p>รายรับรวมวันนี้</p></div>
              <div class="icon"><i class="bi bi-cash-stack"></i></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- แผนผัง + ปฏิทิน -->
    <div class="container mt-5 no-print">
      <div class="d-flex">
        <div class="map-wrapper me-4 mb-3">
          <img src="floorplans/<?= $current_map['file'] ?>" alt="แผนผัง <?= htmlspecialchars($area) ?>">
          <!-- จุดโต๊ะจะถูกแทรกด้วย JS -->
        </div>
        <div class="d-flex flex-column gap-2">
          <a href="?area=ชั้น 1" class="btn <?= $area == 'ชั้น 1' ? 'btn-primary' : 'btn-outline-secondary' ?>">ชั้น 1</a>
          <a href="?area=ชั้น 2" class="btn <?= $area == 'ชั้น 2' ? 'btn-primary' : 'btn-outline-secondary' ?>">ชั้น 2</a>
          <a href="?area=ชั้น 3" class="btn <?= $area == 'ชั้น 3' ? 'btn-primary' : 'btn-outline-secondary' ?>">ชั้น 3</a>
          <hr class="my-2">
          <div><strong>หมายเหตุ:</strong></div>
          <div><span style="display:inline-block;width:20px;height:20px;background-color:green;margin-right:5px;border-radius:50%;"></span>ว่าง</div>
          <div><span style="display:inline-block;width:20px;height:20px;background-color:lightcoral;margin-right:5px;border-radius:50%;"></span>จองแล้ว</div>
          <div><span style="display:inline-block;width:20px;height:20px;background-color:yellow;margin-right:5px;border-radius:50%;"></span>กำลังใช้งาน</div>
          <div><span style="display:inline-block;width:20px;height:20px;background-color:gray;margin-right:5px;border-radius:50%;"></span>ไม่สามารถใช้งานได้</div>
          <div class="no-print mt-3"><div id="miniCal" class="mini-calendar"></div></div>
        </div>
      </div>
    </div>

    <!-- โซนสำหรับพิมพ์ -->
    <div class="container print-only mt-4" id="printTodayContainer">
      <h4 class="mb-3">ผู้จองวันนี้ (<span id="printReportDate"></span>)</h4>
      <div id="printTodayBookings"></div>
    </div>

    <!-- Modal หลัก -->
    <div class="modal fade" id="deskModal" tabindex="-1">
      <div class="modal-dialog"><div class="modal-content" id="deskModalContent"></div></div>
    </div>

    <!-- Modal ต่อเวลาใช้งาน -->
    <div class="modal fade" id="extendModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>ต่อเวลาใช้งาน</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body">
            <div class="alert alert-light border">
              <div class="d-flex align-items-center">
                <i class="bi bi-info-circle me-2"></i>
                <div>
                  <div><strong>โต๊ะ: <span id="extDeskName">-</span></strong></div>
                  <div>ช่วงเวลาเดิม: <span id="extOldRange">-</span></div>
                  <div>ราคาต่อชั่วโมง: <span id="extPrice">-</span> บาท</div>
                </div>
              </div>
            </div>

            <form id="extendForm" class="needs-validation" novalidate>
              <input type="hidden" name="desk_id" id="extDeskId">
              <input type="hidden" name="booking_id" id="extBookingId">
              <input type="hidden" name="booking_date" id="extBookingDate">
              <input type="hidden" name="old_end_time" id="extOldEnd">

              <div class="mb-3">
                <label class="form-label">เลือกเวลาสิ้นสุดใหม่</label>
                <div class="row g-2">
                  <div class="col-7">
                    <input type="time" class="form-control" id="extNewEnd" required>
                    <div class="form-text">ต้องมากกว่าเวลาสิ้นสุดเดิม</div>
                    <div class="invalid-feedback">กรุณาเลือกเวลาใหม่ให้ถูกต้อง</div>
                  </div>
                  <div class="col-5">
                    <select id="extQuick" class="form-select">
                      <option value="">+ เพิ่มด่วน</option>
                      <option value="15">+15 นาที</option>
                      <option value="30">+30 นาที</option>
                      <option value="45">+45 นาที</option>
                      <option value="60">+60 นาที</option>
                      <option value="90">+90 นาที</option>
                      <option value="120">+120 นาที</option>
                    </select>
                  </div>
                </div>
                <div class="form-text">สูงสุดถึง: <span id="extMaxHint">—</span></div>
              </div>

              <div id="extCheckBox" class="p-3 rounded border bg-light">
                <div class="d-flex justify-content-between">
                  <div>
                    <div>เวลาที่ต่อเพิ่ม: <strong><span id="extAdded">0</span> นาที</strong></div>
                    <div>เวลาสิ้นสุดใหม่: <strong><span id="extNewEndShow">—:—</span></strong></div>
                  </div>
                  <div class="text-end">
                    <div>คิดเป็นชั่วโมง: <strong><span id="extHours">0.00</span> ชม.</strong></div>
                    <div>ยอดชำระเพิ่ม: <strong><span id="extAmount">0.00</span> บาท</strong></div>
                  </div>
                </div>
                <div class="mt-2" id="extStatus"></div>
              </div>
            </form>
          </div>

          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>

            <!-- แก้เฉพาะจุด: ไม่ไป payment แล้ว เหลือปุ่มเดียวสำหรับ finalize -->
            <button type="button" id="extAdminFinalizeBtn" class="btn btn-primary ms-2" disabled>
              ยืนยันการต่อเวลา
            </button>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /.main-content -->
</div><!-- /.wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
  document.getElementById('searchBox')?.addEventListener('input', function () {
    const keyword = this.value.toLowerCase();
    document.querySelectorAll('.desk').forEach(desk => {
      const name = desk.textContent.toLowerCase();
      const status = desk.dataset.status?.toLowerCase() || '';
      desk.style.display = (name.includes(keyword) || status.includes(keyword)) ? '' : 'none';
    });
  });
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const area = <?= json_encode($area, JSON_UNESCAPED_UNICODE) ?>;
  const mapWrapper = document.querySelector('.map-wrapper');
  const imageSize = <?= json_encode($current_map) ?>;

  window.latestDesks = [];
  window.latestSummary = { available_desks: 0, in_use_now: 0, total_users_today: 0, total_revenue: '0.00' };
  window.todayBookings = [];

  function nowMinutesBKK() {
    const d = new Date();
    return d.getHours()*60 + d.getMinutes();
  }
  function hhmmToMinutes(hhmm) {
    if (!hhmm) return null;
    const [h,m] = hhmm.split(':').map(Number);
    return (isFinite(h) && isFinite(m)) ? (h*60+m) : null;
  }
  function computeClass(desk) {
    const label = (desk.status_label || '').trim();
    const code  = (desk.status_code  || '').trim().toLowerCase();
    const isClosed = desk.is_closed ? String(desk.is_closed) === '1' : false;

    if (isClosed) return 'unavailable';

    if (code) {
      if (code === 'available') return 'available';
      if (code === 'reserved')  return 'reserved';
      if (code === 'in_use')    return 'in_use';
      if (code === 'closed')    return 'unavailable';
    }
    if (label === 'ว่าง') return 'available';
    if (label === 'จองแล้ว') return 'reserved';
    if (label === 'กำลังใช้งาน') return 'in_use';
    if (label === 'ไม่สามารถใช้งานได้') return 'unavailable';

    const hasBooking = !!desk.booking_id;
    if (!hasBooking) return 'available';

    const today = new Date().toLocaleDateString('en-CA');
    const isToday = (desk.booking_date || '').startsWith(today);
    if (isToday) {
      const nowMin = nowMinutesBKK();
      const stMin = hhmmToMinutes((desk.start_time || '').slice(0,5));
      const etMin = hhmmToMinutes((desk.end_time   || '').slice(0,5));
      if (stMin != null && etMin != null && nowMin >= stMin && nowMin < etMin) {
        return 'in_use';
      }
      return 'reserved';
    }
    return 'available';
  }

  async function loadDesks() {
    try {
      const res = await fetch(`desk_status_api.php?area=${encodeURIComponent(area)}&nocache=${Date.now()}`);
      if (!res.ok) throw new Error(`desk_status_api.php ${res.status}`);
      const data = await res.json();
      window.latestDesks = Array.isArray(data) ? data : [];

      mapWrapper?.querySelectorAll('.desk').forEach(el => el.remove());

      data.forEach(desk => {
        const topPct  = (Number(desk.pos_top)  / Number(imageSize.height)) * 100;
        const leftPct = (Number(desk.pos_left) / Number(imageSize.width))  * 100;

        const cssClass = computeClass(desk);

        const div = document.createElement('div');
        div.className = `desk ${cssClass}`;
        div.id = `desk-${desk.desk_name}`;
        div.textContent = desk.desk_name;
        div.style.top  = `${topPct}%`;
        div.style.left = `${leftPct}%`;

        div.dataset.status       = (desk.status_label || '').trim();
        div.dataset.fullname     = desk.fullname || '';
        div.dataset.booking_date = desk.booking_date || '';
        div.dataset.start_time   = desk.start_time || '';
        div.dataset.end_time     = desk.end_time || '';
        div.dataset.desk_id      = desk.desk_id || '';
        div.dataset.booking_id   = desk.booking_id || '';
        div.dataset.is_closed    = desk.is_closed ? '1' : '0';

        div.setAttribute('data-bs-toggle', 'modal');
        div.setAttribute('data-bs-target', '#deskModal');

        mapWrapper?.appendChild(div);
      });
    } catch (err) {
      console.error('loadDesks error:', err);
    }
  }

  async function loadTodayBookings() {
    try {
      const res = await fetch('today_bookings_api.php?nocache=' + Date.now());
      if (!res.ok) throw new Error('today_bookings_api.php ' + res.status);
      const json = await res.json();
      window.todayBookings = Array.isArray(json) ? json : [];
      const todayTH = new Date().toLocaleDateString('th-TH', { year:'numeric', month:'long', day:'numeric' });
      document.getElementById('printReportDate').textContent = todayTH;
      renderPrintTable(window.todayBookings);
    } catch(e) { console.error('loadTodayBookings error:', e); }
  }

  function renderPrintTable(list) {
    const wrap = document.getElementById('printTodayBookings');
    if (!wrap) return;
    if (!list || list.length === 0) {
      wrap.innerHTML = '<div>— ไม่มีรายการผู้จองวันนี้ —</div>';
      return;
    }
    let html = `
      <table class="table table-bordered table-striped">
        <thead>
          <tr>
            <th style="width:60px">ลำดับ</th>
            <th>ผู้จอง</th>
            <th>ชั้น</th>
            <th>โต๊ะ</th>
            <th>วันที่</th>
            <th>เวลา</th>
            <th class="text-end">ยอดชำระ (บาท)</th>
          </tr>
        </thead>
        <tbody>
    `;
    list.forEach((row, idx) => {
      const time = (row.booking_start_time?.slice(0,5) || '') + ' - ' + (row.booking_end_time?.slice(0,5) || '');
      html += `
        <tr>
          <td>${idx+1}</td>
          <td>${row.fullname || ''}</td>
          <td>${row.area || ''}</td>
          <td>${row.desk_name || ''}</td>
          <td>${row.booking_date || ''}</td>
          <td>${time}</td>
          <td class="text-end">${Number(row.amount || 0).toFixed(2)}</td>
        </tr>`;
    });
    html += '</tbody></table>';
    wrap.innerHTML = html;
  }

  async function toggleDeskClosed(deskId, toClose = null) {
    try {
      const formData = new FormData();
      formData.append('desk_id', deskId);
      formData.append('action', toClose === null ? 'toggle' : (toClose ? 'close' : 'open'));
      const res = await fetch('toggle_desk_status.php', { method: 'POST', body: formData });
      const json = await res.json();
      if (!json.ok) throw new Error(json.error || 'toggle failed');
      return json.is_closed;
    } catch (e) {
      alert('เกิดข้อผิดพลาดในการเปลี่ยนสถานะโต๊ะ');
      console.error(e);
      return null;
    }
  }

  loadDesks();
  loadTodayBookings();
  setInterval(loadDesks, 5000);
  setInterval(loadTodayBookings, 15000);

  // โมดัลหลัก
  const modal = document.getElementById('deskModal');
  modal.addEventListener('show.bs.modal', function (event) {
    const el = event.relatedTarget;

    const deskName   = el.textContent;
    const status     = el.dataset.status || '-';
    const fullname   = el.dataset.fullname || '';
    const bdate      = el.dataset.booking_date || '';
    const st         = el.dataset.start_time || '';
    const et         = el.dataset.end_time || '';
    const deskId     = el.dataset.desk_id || '';
    const bookingId  = el.dataset.booking_id || '';
    const isClosed   = el.dataset.is_closed === '1' || status === 'ไม่สามารถใช้งานได้';

    const isInUseUI  = (status === 'กำลังใช้งาน');
    const canCheckout = (status === 'กำลังใช้งาน' || status === 'จองแล้ว') && bookingId !== '';

    const html = `
      <div class="modal-header">
        <h5 class="modal-title">โต๊ะ: ${deskName}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <p><strong>สถานะ:</strong> <span id="deskStatusText">${status}</span></p>
        ${fullname ? `<p><strong>ผู้ใช้งาน:</strong> ${fullname}</p>` : ''}
        ${bdate ? `<p><strong>วันที่จอง:</strong> ${bdate}</p>` : ''}
        ${st ? `<p><strong>เวลาเริ่ม:</strong> ${st}</p>` : ''}
        ${et ? `<p><strong>หมดเวลา:</strong> ${et}</p>` : ''}

        <div class="d-flex justify-content-end gap-2 mt-3">
          ${canCheckout
            ? `<a href="checkout.php?desk_id=${encodeURIComponent(deskId)}&booking_id=${encodeURIComponent(bookingId)}&return_url=${encodeURIComponent(window.location.href)}"
                 class="btn btn-success btn-sm">เสร็จสิ้น</a>` : ''
          }
          ${isInUseUI && bookingId
            ? `<button type="button" class="btn btn-primary btn-sm" id="extendTimeBtn">ต่อเวลาใช้งาน</button>`
            : ''
          }
          <button type="button" class="btn ${isClosed ? 'btn-secondary' : 'btn-dark'} btn-sm" id="toggleDeskBtn">
            ${isClosed ? 'เปิดโต๊ะ' : 'ปิดโต๊ะ'}
          </button>
        </div>
      </div>
    `;
    document.getElementById('deskModalContent').innerHTML = html;

    const btn = document.getElementById('toggleDeskBtn');
    const statusText = document.getElementById('deskStatusText');
    btn?.addEventListener('click', async () => {
      btn.disabled = true;
      const newClosed = await toggleDeskClosed(deskId, null);
      btn.disabled = false;

      if (newClosed === null) return;
      btn.classList.remove('btn-dark', 'btn-secondary');
      if (newClosed) {
        btn.classList.add('btn-secondary');
        btn.textContent = 'เปิดโต๊ะ';
        if (statusText) statusText.textContent = 'ไม่สามารถใช้งานได้';
      } else {
        btn.classList.add('btn-dark');
        btn.textContent = 'ปิดโต๊ะ';
        if (statusText) statusText.textContent = 'ว่าง';
      }
      await loadDesks();
      loadSummary();
    });
  });

  // ปฏิทินเล็ก
  function renderMiniCalendar(targetId){
    const el = document.getElementById(targetId);
    if(!el) return;
    let viewDate = new Date();
    function build(date){
      const year = date.getFullYear();
      const month = date.getMonth();
      const first = new Date(year, month, 1);
      const last  = new Date(year, month+1, 0);
      const startDay = (first.getDay() + 6) % 7;
      const totalCells = startDay + last.getDate();
      const weeks = Math.ceil(totalCells/7)*7;
      const today = new Date(); today.setHours(0,0,0,0);
      const monthName = date.toLocaleString('th-TH', { month:'long', year:'numeric' });
      let html = `
        <div class="cal-header">
          <button aria-label="Prev" id="calPrev">&lsaquo;</button>
          <div>${monthName}</div>
          <button aria-label="Next" id="calNext">&rsaquo;</button>
        </div>
        <div class="daynames">${['จ','อ','พ','พฤ','ศ','ส','อา'].map(d=>`<div class="dayname">${d}</div>`).join('')}</div>
        <div class="days">`;
      const cells = [];
      for(let i=0;i<weeks;i++){
        const dayNum = i - startDay + 1;
        const cellDate = new Date(year, month, dayNum);
        const inMonth = dayNum >= 1 && dayNum <= last.getDate();
        const isToday = inMonth && cellDate.getTime() === today.setHours(0,0,0,0);
        cells.push(`<div class="day ${inMonth?'':'other'} ${isToday?'today':''}">${cellDate.getDate()}</div>`);
      }
      html += cells.join('') + `</div>
        <div class="cal-footer">${new Date().toLocaleDateString('th-TH',{weekday:'long', day:'numeric', month:'long', year:'numeric'})}</div>`;
      el.innerHTML = html;
      el.querySelector('#calPrev').onclick = () => { viewDate = new Date(year, month-1, 1); build(viewDate); };
      el.querySelector('#calNext').onclick = () => { viewDate = new Date(year, month+1, 1); build(viewDate); };
    }
    build(viewDate);
  }
  renderMiniCalendar('miniCal');
});
</script>

<!-- สรุปค่า + Sticky ผู้ใช้งานวันนี้ -->
<script>
(function(){
  function todayStrBKK() {
    return new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Bangkok' });
  }
  const STICKY_KEY = 'total_users_today_sticky';
  const STICKY_DATE_KEY = 'total_users_today_date';

  function getSticky() {
    const d = localStorage.getItem(STICKY_DATE_KEY);
    const v = localStorage.getItem(STICKY_KEY);
    return { date: d || null, value: v ? parseInt(v, 10) : 0 };
  }
  function setSticky(dateStr, value) {
    localStorage.setItem(STICKY_DATE_KEY, dateStr);
    localStorage.setItem(STICKY_KEY, String(value));
  }
  function updateStickyForToday(currentValue) {
    const today = todayStrBKK();
    const { date, value } = getSticky();
    if (date !== today) { setSticky(today, currentValue); return currentValue; }
    const maxVal = Math.max(value || 0, currentValue || 0);
    if (maxVal !== value) setSticky(today, maxVal);
    return maxVal;
  }

  window.loadSummary = function loadSummary() {
    fetch('summary_status_api.php?nocache=' + Date.now())
      .then(r => r.json())
      .then(data => {
        document.getElementById("available_desks").textContent   = data.available_desks ?? 0;
        document.getElementById("in_use_now").textContent        = data.in_use_now ?? 0;

        const apiUsersToday = data.total_users_today ?? 0;
        const stickyUsersToday = updateStickyForToday(apiUsersToday);
        document.getElementById("total_users_today").textContent = stickyUsersToday;

        document.getElementById("total_revenue").textContent     = (data.total_revenue ?? '0.00') + " บาท";

        window.latestSummary = {
          ...(window.latestSummary || {}),
          available_desks: data.available_desks ?? 0,
          in_use_now: data.in_use_now ?? 0,
          total_users_today: stickyUsersToday,
          total_revenue: data.total_revenue ?? '0.00'
        };
      })
      .catch(console.error);
  };

  document.addEventListener("DOMContentLoaded", function () {
    loadSummary();
    setInterval(loadSummary, 5000);
  });
})();
</script>

<!-- Excel/PDF libs -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script src="font-thsarabun.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js"></script>

<script>
  function buildReportRows() {
    const rows = (window.latestDesks || []).map((d, idx) => {
      const timeRange = (d.start_time && d.end_time) ? `${d.start_time} - ${d.end_time}` : '';
      return [ String(idx + 1), d.desk_name || '', d.status_label || '', d.fullname || '', d.booking_date || '', timeRange ];
    });
    return rows;
  }
  function buildTodayBookingRows() {
    const list = window.todayBookings || [];
    return list.map((r, i) => {
      const timeRange = ((r.booking_start_time || '').slice(0,5)) + ' - ' + ((r.booking_end_time || '').slice(0,5));
      return [ String(i+1), r.fullname || '', r.area || '', r.desk_name || '', r.booking_date || '', timeRange, Number(r.amount || 0).toFixed(2) ];
    });
  }

  const AREAS = ["ชั้น 1", "ชั้น 2", "ชั้น 3"];

  async function fetchDesksByArea(area) {
    const res = await fetch(`desk_status_api.php?area=${encodeURIComponent(area)}&nocache=${Date.now()}`);
    if (!res.ok) throw new Error('desk_status_api.php ' + res.status);
    const data = await res.json();
    return Array.isArray(data) ? data.map(d => ({ ...d, area })) : [];
  }

  async function getAllBookedDesksAcrossFloors() {
    const lists = await Promise.all(AREAS.map(fetchDesksByArea));
    const all = lists.flat();
    return all
      .filter(d => d.booking_id != null && String(d.booking_id).trim() !== '')
      .sort((a, b) => (a.area || '').localeCompare(b.area || '', 'th') || (a.desk_name || '').localeCompare(b.desk_name || '', 'th'));
  }

  function buildBookedRowsForExcel(list) {
    return list.map((d, i) => {
      const st = (d.start_time || '').slice(0, 5);
      const et = (d.end_time   || '').slice(0, 5);
      const timeRange = (st && et) ? `${st} - ${et}` : '';
      return [ String(i + 1), d.area || '', d.desk_name || '', d.fullname || '', d.booking_date || '', timeRange ];
    });
  }

  document.getElementById('btnExcel').addEventListener('click', async function() {
    try {
      const booked = await getAllBookedDesksAcrossFloors();
      const ws1 = XLSX.utils.aoa_to_sheet([['ลำดับ','ชั้น','โต๊ะ','ผู้ใช้งาน','วันที่จอง','เวลา'], ...buildBookedRowsForExcel(booked)]);
      const ws2 = XLSX.utils.aoa_to_sheet([['ลำดับ','ผู้จอง','ชั้น','โต๊ะ','วันที่','เวลา','ยอดชำระ (บาท)'], ...buildTodayBookingRows()]);
      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws1, "โต๊ะที่มีการจอง");
      XLSX.utils.book_append_sheet(wb, ws2, "ผู้จองวันนี้");
      const stamp = new Date().toISOString().slice(0,19).replace('T','_').replace(/:/g,'-');
      XLSX.writeFile(wb, `รายงานเฉพาะโต๊ะที่มีการจอง_${stamp}.xlsx`);
    } catch (e) {
      console.error(e);
      alert('ไม่สามารถสร้างไฟล์ Excel ได้ กรุณาลองใหม่อีกครั้ง');
    }
  });

  document.getElementById('btnPDF').addEventListener('click', function() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ unit: 'pt', format: 'a4' });
    try { doc.setFont('THSarabunNew', 'normal'); } catch(e) {}

    const title = 'รายงานภาพรวม/สถานะ';
    const stamp = new Date().toLocaleString('th-TH', { hour12:false });
    const sum = window.latestSummary || {};

    doc.setFontSize(20); doc.text(title, 40, 50);
    doc.setFontSize(13);
    let y = 78;
    const summaryLines = [
      `วันที่ออกรายงาน: ${stamp}`,
      `ว่างทั้งหมด: ${sum.available_desks ?? 0}`,
      `กำลังใช้งาน: ${sum.in_use_now ?? 0}`,
      `ผู้ใช้งานวันนี้ (เช็กอินทั้งหมด): ${sum.total_users_today ?? 0}`,
      `รายรับรวมวันนี้: ${sum.total_revenue ?? '0.00'} บาท`
    ];
    summaryLines.forEach(line => { doc.text(line, 40, y); y += 18; });

    const after1 = doc.lastAutoTable?.finalY || (y + 40);
    doc.setFontSize(18);
    doc.text('ผู้ที่จองและเข้าใช้งานวันนี้', 40, after1 + 30);

    doc.autoTable({
      head: [['ลำดับ','ผู้จอง','ชั้น','โต๊ะ','วันที่','เวลา','ยอดชำระ (บาท)']],
      body: buildTodayBookingRows(),
      startY: after1 + 40,
      styles: { font: 'THSarabunNew', fontSize: 16, cellPadding: 4 },
      headStyles: { fillColor: [230,230,230] }
    });

    doc.save(`รายงานภาพรวม_${new Date().toISOString().slice(0,10)}.pdf`);
  });
</script>

<!-- ========== ลอจิกต่อเวลา (แก้เฉพาะจุดให้ finalize ทันที) ========== -->
<script>
(function setupExtendFlow(){
  const deskModal = document.getElementById('deskModal');
  const extendModalEl = document.getElementById('extendModal');
  const extendModal = new bootstrap.Modal(extendModalEl);

  const pad2 = n => String(n).padStart(2,'0');
  const toMinutes = t => { if(!t) return 0; const [h,m]=t.split(':').map(Number); return h*60+(m||0); };
  const fromMinutes = mins => `${pad2(Math.floor(mins/60))}:${pad2(mins%60)}`;

  let _ctx = {
    desk_id: null,
    desk_name: null,
    booking_id: null,
    booking_date: null,
    old_start: null,
    old_end: null,
    pricePerHour: 0,
    maxEnd: null
  };

  deskModal.addEventListener('shown.bs.modal', () => {
    const btn = document.getElementById('extendTimeBtn');
    if (!btn) return;

    btn.addEventListener('click', async () => {
      const titleText = deskModal.querySelector('.modal-title')?.textContent || '';
      const deskName = titleText.replace('โต๊ะ:','').trim();
      let selectedDesk = null;
      document.querySelectorAll('.desk').forEach(d => {
        if (d.textContent.trim() === deskName) selectedDesk = d;
      });
      if (!selectedDesk) return;

      _ctx.desk_id      = selectedDesk.dataset.desk_id || '';
      _ctx.booking_id   = selectedDesk.dataset.booking_id || '';
      _ctx.booking_date = selectedDesk.dataset.booking_date?.slice(0,10) || '';
      _ctx.desk_name    = deskName;
      _ctx.old_start    = (selectedDesk.dataset.start_time || '').slice(0,5);
      _ctx.old_end      = (selectedDesk.dataset.end_time || '').slice(0,5);

      try {
        const url = new URL('extend_check.php', window.location.href);
        url.searchParams.set('desk_id', _ctx.desk_id);
        url.searchParams.set('booking_id', _ctx.booking_id);
        url.searchParams.set('booking_date', _ctx.booking_date);
        url.searchParams.set('old_end_time', _ctx.old_end);

        const res = await fetch(url.toString(), { cache: 'no-store' });
        const info = await res.json();
        if (!info.ok) { alert(info.error || 'ไม่สามารถต่อเวลาได้'); return; }

        _ctx.pricePerHour = Number(info.price_per_hour || 0);
        _ctx.maxEnd       = (info.max_end_time || '').slice(0,5);

        document.getElementById('extDeskName').textContent  = _ctx.desk_name;
        document.getElementById('extOldRange').textContent  = `${_ctx.old_start} - ${_ctx.old_end}`;
        document.getElementById('extPrice').textContent     = _ctx.pricePerHour.toFixed(2);

        document.getElementById('extDeskId').value          = _ctx.desk_id;
        document.getElementById('extBookingId').value       = _ctx.booking_id;
        document.getElementById('extBookingDate').value     = _ctx.booking_date;
        document.getElementById('extOldEnd').value          = _ctx.old_end;
        document.getElementById('extMaxHint').textContent   = _ctx.maxEnd || '—';

        const proposalMin = Math.min(
          toMinutes(_ctx.old_end) + 30,
          _ctx.maxEnd ? toMinutes(_ctx.maxEnd) : (toMinutes(_ctx.old_end) + 30)
        );
        document.getElementById('extNewEnd').value = fromMinutes(proposalMin);

        await recalcAndValidate();
        extendModal.show();

      } catch (e) {
        console.error(e);
        alert('เกิดข้อผิดพลาดในการเตรียมข้อมูลต่อเวลา');
      }
    }, { once: true });
  });

  document.getElementById('extQuick').addEventListener('change', async function(){
    const addMin = parseInt(this.value || '0', 10);
    if (!addMin) return;
    const targetMin = toMinutes(_ctx.old_end) + addMin;
    const maxMin = _ctx.maxEnd ? toMinutes(_ctx.maxEnd) : targetMin;
    document.getElementById('extNewEnd').value = fromMinutes(Math.min(targetMin, maxMin));
    await recalcAndValidate();
    this.value = '';
  });

  document.getElementById('extNewEnd').addEventListener('input', recalcAndValidate);

  async function recalcAndValidate(){
    const newEnd = document.getElementById('extNewEnd').value;
    const statusEl = document.getElementById('extStatus');
    const btnFinalize = document.getElementById('extAdminFinalizeBtn');

    const addMin = Math.max(0, toMinutes(newEnd) - toMinutes(_ctx.old_end));
    const hours  = addMin / 60;
    const amount = hours * _ctx.pricePerHour;

    document.getElementById('extAdded').textContent      = addMin;
    document.getElementById('extNewEndShow').textContent = newEnd || '—:—';
    document.getElementById('extHours').textContent      = hours.toFixed(2);
    document.getElementById('extAmount').textContent     = amount.toFixed(2);

    if (!newEnd || toMinutes(newEnd) <= toMinutes(_ctx.old_end)) {
      statusEl.innerHTML = `<div class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>เวลาใหม่ต้องมากกว่าเวลาเดิม</div>`;
      btnFinalize.disabled = true; return;
    }
    if (_ctx.maxEnd && toMinutes(newEnd) > toMinutes(_ctx.maxEnd)) {
      statusEl.innerHTML = `<div class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>เกินเวลาสูงสุดที่ต่อได้ (${_ctx.maxEnd})</div>`;
      btnFinalize.disabled = true; return;
    }

    try {
      const url = new URL('extend_check.php', window.location.href);
      url.searchParams.set('desk_id', _ctx.desk_id);
      url.searchParams.set('booking_id', _ctx.booking_id);
      url.searchParams.set('booking_date', _ctx.booking_date);
      url.searchParams.set('old_end_time', _ctx.old_end);
      url.searchParams.set('new_end_time', newEnd);

      const res = await fetch(url.toString(), { cache: 'no-store' });
      const data = await res.json();

      if (!data.ok) {
        statusEl.innerHTML = `<div class="text-danger"><i class="bi bi-x-circle me-1"></i>${data.error || 'มีการจองทับซ้อน'}</div>`;
        btnFinalize.disabled = true; return;
      }
      statusEl.innerHTML = `<div class="text-success"><i class="bi bi-check-circle me-1"></i>ไม่มีการจองทับซ้อน สามารถต่อเวลาได้</div>`;
      btnFinalize.disabled = false;

    } catch (e) {
      console.error(e);
      statusEl.innerHTML = `<div class="text-danger"><i class="bi bi-exclamation-octagon me-1"></i>ตรวจสอบทับซ้อนล้มเหลว กรุณาลองใหม่</div>`;
      btnFinalize.disabled = true;
    }
  }

  // แก้เฉพาะจุด: ปุ่มยืนยันเพื่อสร้าง "การจองใหม่" ทันที (ไม่ไป payment)
  document.getElementById('extAdminFinalizeBtn')?.addEventListener('click', async () => {
    const newEnd = document.getElementById('extNewEnd').value;
    const fd = new FormData();
    fd.append('finalize','1');
    fd.append('desk_id', _ctx.desk_id);
    fd.append('booking_id', _ctx.booking_id);
    fd.append('booking_date', _ctx.booking_date);
    fd.append('old_end_time', _ctx.old_end);
    fd.append('new_end_time', newEnd);

    try {
      const res = await fetch('extend_check.php', { method: 'POST', body: fd, cache: 'no-store' });
      const data = await res.json();
      if (!data.ok) { alert(data.error || 'ไม่สามารถต่อเวลา'); return; }

      bootstrap.Modal.getInstance(document.getElementById('extendModal'))?.hide();
      alert(`ต่อเวลาเรียบร้อย (สร้างการจองใหม่ #${data.new_booking_id})\nช่วงเวลา: ${data.start_time} - ${data.new_end_time}\nยอดชำระ: ${Number(data.amount).toFixed(2)} บาท`);
      await loadDesks();
      loadSummary();
    } catch (e) {
      console.error(e);
      alert('เกิดข้อผิดพลาดในการยืนยันต่อเวลา');
    }
  });

})();
</script> 
</body>
</html>
