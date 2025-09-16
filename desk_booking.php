<?php
session_start();
require 'db_connect.php';
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

/* พื้นที่/แผนผัง */
$area = $_GET['area'] ?? 'ชั้น 1';
$image_map = [
  'ชั้น 1' => ['file' => 'floor1.png', 'width' => 601, 'height' => 491],
  'ชั้น 2' => ['file' => 'floor2.png', 'width' => 605, 'height' => 491],
  'ชั้น 3' => ['file' => 'floor3.png', 'width' => 601, 'height' => 520],
];
$areas = array_keys($image_map);
$current_map = $image_map[$area] ?? $image_map['ชั้น 1'];

/* ดึงข้อมูลโต๊ะ “ทุกชั้น” */
$allDesks = [];
$res = $conn->query("SELECT desk_id, desk_name, pos_top, pos_left, areas FROM desks");
while ($row = $res->fetch_assoc()) {
  $ar = $row['areas'] ?: 'ชั้น 1';
  if (!isset($allDesks[$ar])) $allDesks[$ar] = [];
  $allDesks[$ar][] = [
    'desk_id'   => (int)$row['desk_id'],
    'desk_name' => $row['desk_name'],
    'pos_top'   => (float)$row['pos_top'],
    'pos_left'  => (float)$row['pos_left'],
  ];
}

/* รวม Top 3 ทุกชั้น แล้วส่งให้ JS */
$topByArea = [];
$topStmt = $conn->prepare("
  SELECT d.desk_name, COUNT(b.booking_id) AS total_bookings
  FROM bookings b
  JOIN desks d ON b.desk_id = d.desk_id
  WHERE d.areas = ?
  GROUP BY d.desk_name
  ORDER BY total_bookings DESC
  LIMIT 3
");
foreach ($areas as $arLabel) {
  $topStmt->bind_param("s", $arLabel);
  $topStmt->execute();
  $rs = $topStmt->get_result();

  $labels = [];
  $data   = [];
  while ($row = $rs->fetch_assoc()) {
    $labels[] = $row['desk_name'];
    $data[]   = (int)$row['total_bookings'];
  }
  $topByArea[$arLabel] = ['labels'=>$labels, 'data'=>$data];
}
$topStmt->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>แผนผังข้อมูลการจอง</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Asset หลักของหน้า -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <!-- 1) Layout & Sidebar ก่อน -->
  <link href="style1.css" rel="stylesheet">
  <!-- 2) Navbar ทีหลัง (อย่าแตะ .main-sidebar/.main-content ในไฟล์นี้) -->
  <link href="style.css" rel="stylesheet">

  <style>
  :root { --sidebar-w:240px; --topbar-h:60px; }
  html,body{height:100%}
  body{margin:0;background:#f6f7fb;overflow-x:hidden;padding-top:var(--topbar-h);}
  .wrapper{position:relative;min-height:100vh}

  /* ========== Sidebar / Content / Topbar ========== */
  .main-sidebar{position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;overflow:hidden;scrollbar-width:none;z-index:1040}
  .main-sidebar::-webkit-scrollbar{display:none}
  .main-content{margin-left:var(--sidebar-w);min-height:100vh;padding:0 0 16px}
  .app-topbar{position:fixed!important;top:0;right:0;left:var(--sidebar-w);height:var(--topbar-h);
    z-index:1050;background:#fff;border-bottom:1px solid #e9ecef;
    padding-left:0!important;padding-right:0!important;margin:0!important;border-radius:0!important;box-shadow:none!important}
  .app-topbar .container-fluid{height:100%}

  /* ผังโต๊ะ */
  .map-wrapper { position: relative; width: 950px; max-width:100%; background:#e6e3e3; border-radius:20px; overflow:hidden; }
  .map-wrapper img { width:100%; display:block; }
  .desk{ position:absolute; width:30px; height:30px; border-radius:50%; text-align:center; line-height:30px; font-weight:700; font-size:13px; color:#fff; cursor:pointer; padding:0; transition:transform .12s ease; }
  .desk:hover{ transform:scale(1.1); }
  .desk.marked { outline:3px solid #0d6efd; outline-offset:2px; }
  .floor-btn{ width:130px; height:40px; font-size:16px; font-weight:700; }

  .small-box { border-radius:.5rem; position:relative; display:block; padding:1.0rem; color:#fff; box-shadow:0 2px 8px rgba(0,0,0,.06); text-align:left; overflow:hidden; font-size:.9rem; max-width:380px; }
  .small-box .icon{ position:absolute; top:10px; right:10px; font-size:2.5rem; opacity:.5; color:#fff; }

  /* ===== พื้นที่พิมพ์ให้เหมือน PDF ===== */
  #printArea{ display:none; padding:40px; }
  #printArea .h-title{ font-size:20pt; font-weight:700; margin-bottom:8px; }
  #printArea .h-sub{ font-size:12pt; margin-bottom:16px; }
  #printArea .row-cards{ display:flex; gap:20px; margin-bottom:16px; }
  #printArea .p-card{ width:240px; height:78px; border-radius:10px; padding:10px 14px; color:#fff; display:flex; flex-direction:column; justify-content:center; }
  #printArea .p-card .t1{ font-size:12pt; line-height:1.2; }
  #printArea .p-card .t2{ font-size:18pt; font-weight:700; line-height:1.3; }
  .bg-info-print   { background: rgb(23,162,184); }
  .bg-danger-print { background: rgb(220,53,69);  }

  @media print {
    * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    body *:not(#printArea):not(#printArea *) { display: none !important; }
    #printArea { display:block !important; position:static !important; width:100% !important; padding:40px !important; margin:0 !important; }
    .modal, .modal-backdrop { display:none !important; }
    @page { margin: 10mm; }
  }

  /* ===== Filter in modal ===== */
  .filter-card{border:1px solid #e9ecef;border-radius:10px;background:#fff}
  .filter-topbar{padding:10px 12px;border-bottom:1px solid #eef1f4;display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap}
  .filter-bottombar{display:grid;grid-template-columns:1fr 1fr auto;gap:10px;padding:12px}
  .filter-bottombar .form-label{margin-bottom:4px;font-size:.9rem;color:#6c757d}
  .segmented .btn{padding:.35rem .6rem}
  .btn-run{height:100%;white-space:nowrap}
  @media (max-width:576px){ .filter-bottombar{grid-template-columns:1fr;gap:8px} .btn-run{width:100%} }
  </style>
</head>
<body>
<div class="wrapper">

  <?php include 'sidebar_admin.php'; ?>

  <!-- Content -->
  <div class="main-content">

    <?php
        if (!defined('NAV_API_BASE')) define('NAV_API_BASE', '/coworking/');
        if (!defined('NAV_HOME_HREF')) define('NAV_HOME_HREF', 'desk_status.php');
        if (!defined('APP_BOOTSTRAP_CSS'))      define('APP_BOOTSTRAP_CSS', true);
        if (!defined('BOOTSTRAP_ICONS_LOADED')) define('BOOTSTRAP_ICONS_LOADED', true);
        if (!defined('BOOTSTRAP_JS_LOADED'))    define('BOOTSTRAP_JS_LOADED', true);
        include 'navbar_admin1.php';
    ?>
    <div class="container mt-3">
      <h2><i class="bi bi-calendar-check text-teal me-2"></i>แผนผังข้อมูลโต๊ะ</h2>
      <hr>
      <div class="d-flex">
        <!-- ผัง + ปุ่มโต๊ะจะเรนเดอร์ด้วย JS -->
        <div class="map-wrapper me-4 mb-3" id="mapWrapper">
          <img id="mapImage" src="floorplans/<?= htmlspecialchars($current_map['file']) ?>" alt="แผนผัง <?= htmlspecialchars($area) ?>">
        </div>
        <div class="d-flex flex-column gap-2">
          <?php foreach ($areas as $label): ?>
            <button type="button"
              class="btn floor-btn <?= $area === $label ? 'btn-primary' : 'btn-outline-secondary' ?>"
              data-area="<?= htmlspecialchars($label) ?>"><?= htmlspecialchars($label) ?></button>
          <?php endforeach; ?>
          <hr>
          <div class="card mt-4">
            <div class="card-body">
              <h6 class="card-title">3 อันดับโต๊ะยอดนิยม (<?= htmlspecialchars($area) ?>)</h6>
              <canvas id="topDesksChart" height="300"></canvas>
            </div>
          </div>
        </div>
      </div>
      <div id="searchResultHint" class="text-muted small mt-2" style="display:none;"></div>
    </div>
  </div><!-- /.main-content -->
</div><!-- /.wrapper -->
<!-- พื้นที่สำหรับ "พิมพ์" แบบเหมือน PDF -->
<div id="printArea">
  <div class="h-title" id="pTitle"></div>
  <div class="h-sub" id="pSub"></div>

  <div class="row-cards">
    <div class="p-card bg-info-print">
      <div class="t1">จำนวนการจอง</div>
      <div class="t2" id="pCount">-</div>
    </div>
    <div class="p-card bg-danger-print">
      <div class="t1">รายได้รวม</div>
      <div class="t2" id="pRevenue">-</div>
    </div>
  </div>

  <table class="table table-bordered table-striped" id="pTable">
    <thead>
      <tr>
        <th>ชื่อผู้จอง</th>
        <th>วันที่จอง</th>
        <th>เวลาเริ่ม</th>
        <th>เวลาสิ้นสุด</th>
        <th class="text-end">ราคา (บาท)</th>
      </tr>
    </thead>
    <tbody id="pTbody"></tbody>
  </table>
</div>
<!-- Modal -->
<div class="modal fade" id="deskModal" tabindex="-1" aria-labelledby="deskModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="deskModalLabel">ข้อมูลโต๊ะ <span id="modalDeskName"></span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <!-- ตัวกรอง (เพิ่มใหม่) -->
        <div class="filter-card mb-3 no-print">
          <div class="filter-topbar">
            <div class="btn-group segmented" role="group" aria-label="range-mode">
              <input type="radio" class="btn-check" name="mode" id="modeDay" autocomplete="off" checked>
              <label class="btn btn-outline-secondary" for="modeDay"><i class="bi bi-calendar3"></i> ช่วงวัน</label>
              <input type="radio" class="btn-check" name="mode" id="modeMonth" autocomplete="off">
              <label class="btn btn-outline-secondary" for="modeMonth"><i class="bi bi-calendar3-event"></i> รายเดือน</label>
              <input type="radio" class="btn-check" name="mode" id="modeYear" autocomplete="off">
              <label class="btn btn-outline-secondary" for="modeYear"><i class="bi bi-calendar3-week"></i> รายปี</label>
            </div>
          </div>
          <div class="filter-bottombar">
            <div>
              <label class="form-label" id="fromLabel">จากวันที่</label>
              <input id="fromInput" class="form-control" type="date" autocomplete="off">
            </div>
            <div>
              <label class="form-label" id="toLabel">ถึงวันที่</label>
              <input id="toInput" class="form-control" type="date" autocomplete="off">
            </div>
            <div class="text-end">
              <button id="runReport" class="btn btn-primary btn-run">
                <i class="bi bi-funnel"></i> ดูข้อมูล
              </button>
            </div>
          </div>
        </div>
        <!-- /ตัวกรอง -->
        <div class="row mb-3">
          <div class="col-md-6">
            <div class="small-box bg-info">
              <div class="inner"><h5>จำนวนการจอง</h5><h3 id="bookingCount">-</h3></div>
              <div class="icon"><i class="bi bi-calendar-check-fill"></i></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="small-box bg-danger">
              <div class="inner"><h5>รายได้รวม</h5><h3 id="totalRevenue">-</h3></div>
              <div class="icon"><i class="bi bi-cash-stack"></i></div>
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered table-striped" id="bookingTable">
            <thead>
              <tr>
                <th>ชื่อผู้จอง</th>
                <th>วันที่จอง</th>
                <th>เวลาเริ่ม</th>
                <th>เวลาสิ้นสุด</th>
                <th>ราคา</th>
              </tr>
            </thead>
            <tbody id="bookingTableBody"></tbody>
          </table>
        </div>
      </div>

      <div class="modal-footer d-flex justify-content-end no-print">
        <button class="btn btn-success me-2" id="btnExcelModal"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Excel</button>
        <button class="btn btn-danger me-2" id="btnPDFModal"><i class="bi bi-filetype-pdf me-1"></i> PDF</button>
        <button class="btn btn-dark" id="btnPrintLikePDF"><i class="bi bi-printer me-1"></i> พิมพ์</button>
      </div>
    </div>
  </div>
</div>

<!-- JS หลักของหน้า -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
/* ==== ส่งข้อมูลไป JS ==== */
window.AREAS = <?= json_encode($areas, JSON_UNESCAPED_UNICODE) ?>;
window.IMAGE_MAP = <?= json_encode($image_map, JSON_UNESCAPED_UNICODE) ?>;
window.ALL_DESKS_BY_AREA = <?= json_encode($allDesks, JSON_UNESCAPED_UNICODE) ?>;
window.CURRENT_AREA = <?= json_encode($area, JSON_UNESCAPED_UNICODE) ?>;
window.TOP3_BY_AREA = <?= json_encode($topByArea, JSON_UNESCAPED_UNICODE) ?>;
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const mapWrapper = document.getElementById('mapWrapper');
  const mapImage   = document.getElementById('mapImage');
  const hint       = document.getElementById('searchResultHint');

  function renderMap(area, markNames = []) {
    window.CURRENT_AREA = area;
    const file = (window.IMAGE_MAP?.[area]?.file) || '';
    if (file) mapImage.src = 'floorplans/' + file;
    mapImage.alt = 'แผนผัง ' + area;

    mapWrapper.querySelectorAll('.desk').forEach(el => el.remove());

    const imgW = Number(window.IMAGE_MAP?.[area]?.width)  || 1;
    const imgH = Number(window.IMAGE_MAP?.[area]?.height) || 1;

    const desks = (window.ALL_DESKS_BY_AREA?.[area] || []);
    desks.forEach(d => {
      const topPct  = (Number(d.pos_top)  / imgH) * 100;
      const leftPct = (Number(d.pos_left) / imgW)  * 100;

      const btn = document.createElement('button');
      btn.className = 'desk btn btn-primary';
      btn.style.top  = topPct + '%';
      btn.style.left = leftPct + '%';
      btn.textContent = d.desk_name;
      btn.dataset.deskId   = d.desk_id;
      btn.dataset.deskName = d.desk_name;
      btn.setAttribute('data-bs-toggle','modal');
      btn.setAttribute('data-bs-target','#deskModal');

      btn.addEventListener('click', () => openDeskModal(d.desk_id, d.desk_name));
      if (markNames.includes((d.desk_name||'').toLowerCase())) btn.classList.add('marked');

      mapWrapper.appendChild(btn);
    });

    document.querySelectorAll('.floor-btn').forEach(b=>{
      if (b.dataset.area === area) { b.classList.remove('btn-outline-secondary'); b.classList.add('btn-primary'); }
      else { b.classList.add('btn-outline-secondary'); b.classList.remove('btn-primary'); }
    });

    updateTopChart(area);
  }

  /* ===== โมดัล + ฟิลเตอร์ ===== */
  function lastDayOfMonth(y, m){ return new Date(y, m, 0).getDate(); }
  function formatDate(d){ const y=d.getFullYear(), m=String(d.getMonth()+1).padStart(2,'0'), dd=String(d.getDate()).padStart(2,'0'); return `${y}-${m}-${dd}`; }

  let currentFilter = { mode:'day' };

  function setFilterInputsByMode(mode){
    const from = document.getElementById('fromInput');
    const to   = document.getElementById('toInput');
    const fromLabel = document.getElementById('fromLabel');
    const toLabel   = document.getElementById('toLabel');

    if(mode==='day'){
      from.type='date'; to.type='date';
      fromLabel.textContent='จากวันที่'; toLabel.textContent='ถึงวันที่';
      const today=new Date(), iso=formatDate(today);
      if(!from.value) from.value=iso;
      if(!to.value)   to.value=iso;
    }else if(mode==='month'){
      from.type='month'; to.type='month';
      fromLabel.textContent='จากเดือน'; toLabel.textContent='ถึงเดือน';
      const d=new Date(), ym=`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;
      if(!from.value) from.value=ym;
      if(!to.value)   to.value=ym;
    }else{
      from.type='number'; to.type='number';
      from.min='2000'; to.min='2000'; from.max='2100'; to.max='2100';
      fromLabel.textContent='จากปี (ค.ศ.)'; toLabel.textContent='ถึงปี (ค.ศ.)';
      const y=new Date().getFullYear();
      if(!from.value) from.value=y;
      if(!to.value)   to.value=y;
    }
  }
  function getRangeFromInputs(){
    const mode = currentFilter.mode;
    const from = document.getElementById('fromInput').value;
    const to   = document.getElementById('toInput').value;
    let start='', end='';
    if(mode==='day'){
      start = from || to; end = to || from;
    }else if(mode==='month'){
      const [yf,mf] = (from||to).split('-').map(Number);
      const [yt,mt] = (to||from).split('-').map(Number);
      const lastTo = lastDayOfMonth(yt, mt);
      start = `${yf}-${String(mf).padStart(2,'0')}-01`;
      end   = `${yt}-${String(mt).padStart(2,'0')}-${String(lastTo).padStart(2,'0')}`;
    }else{
      const yf = Number(from||to), yt = Number(to||from);
      start = `${yf}-01-01`; end = `${yt}-12-31`;
    }
    return { start, end };
  }
  function openDeskModal(deskId, deskName){
    document.getElementById('modalDeskName').textContent = deskName;
    currentFilter.mode = (document.getElementById('modeMonth').checked? 'month' :
                         document.getElementById('modeYear').checked ? 'year' : 'day');
    setFilterInputsByMode(currentFilter.mode);
    const range = getRangeFromInputs();
    fetchDeskDataWithRange(deskId, deskName, range.start, range.end);
  }
  function fetchDeskDataWithRange(deskId, deskName, start, end){
    const url = new URL('fetch_desk_info.php', location.href);
    url.searchParams.set('desk_id', deskId);
    if(start) url.searchParams.set('start_date', start);
    if(end)   url.searchParams.set('end_date',   end);
    fetch(url.toString())
      .then(res => res.json())
      .then(data => {
        window._currentDeskData = {
          deskName,
          count: Number(data.count||0),
          revenue: Number(data.revenue||0),
          bookings: Array.isArray(data.bookings)? data.bookings : [],
          range: { start, end }
        };
        document.getElementById('bookingCount').textContent = window._currentDeskData.count.toLocaleString('th-TH');
        document.getElementById('totalRevenue').textContent = window._currentDeskData.revenue.toLocaleString('th-TH') + ' บาท';
        const tbody = document.getElementById('bookingTableBody');
        tbody.innerHTML = '';
        if (window._currentDeskData.bookings.length > 0) {
          window._currentDeskData.bookings.forEach(b => {
            tbody.insertAdjacentHTML('beforeend', `<tr>
              <td>${b.fullname ?? ''}</td>
              <td>${b.booking_date ?? ''}</td>
              <td>${b.booking_start_time ?? ''}</td>
              <td>${b.booking_end_time ?? ''}</td>
              <td class="text-end">${parseFloat(b.price ?? 0).toLocaleString('th-TH')} บาท</td>
            </tr>`);
          });
        } else {
          tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">ไม่มีข้อมูล</td></tr>';
        }
      });
  }
  function applyGlobalSearch(q){
    const kw = (q || '').trim().toLowerCase();
    mapWrapper.querySelectorAll('.desk').forEach(d => { d.classList.remove('marked'); d.style.display=''; });
    hint.style.display = 'none';
    if (!kw) return;
    const hits = [];
    (window.AREAS || []).forEach(ar => {
      (window.ALL_DESKS_BY_AREA?.[ar] || []).forEach(d => {
        const name = (d.desk_name || '').toLowerCase();
        if (name.includes(kw)) hits.push({ area: ar, name: d.desk_name });
      });
    });
    if (hits.length === 0) {
      hint.textContent = `ไม่พบ “${q}” ในทุกชั้น`;
      hint.style.display = '';
      return;
    }
    const firstArea = hits[0].area;
    renderMap(firstArea, hits.filter(h => h.area === firstArea).map(h => h.name.toLowerCase()));
    const byAreaCount = {};
    hits.forEach(h => byAreaCount[h.area] = (byAreaCount[h.area]||0)+1);
    const parts = Object.keys(byAreaCount).map(ar => `${ar}: ${byAreaCount[ar]} รายการ`);
    hint.textContent = `ผลการค้นหา “${q}” พบทั้งหมด ${hits.length} รายการ (${parts.join(' · ')})`;
    hint.style.display = '';

    const firstBtn = mapWrapper.querySelector('.desk.marked');
    if (firstBtn) firstBtn.scrollIntoView({ behavior:'smooth', block:'center', inline:'center' });
  }

  document.querySelectorAll('.floor-btn').forEach(b=>{
    b.addEventListener('click', ()=> renderMap(b.dataset.area));
  });

  const topbarSearchForm  = document.querySelector('.app-topbar form[role="search"]');
  const topbarSearchInput = topbarSearchForm?.querySelector('input[name="q"]');
  topbarSearchForm?.addEventListener('submit', (e)=>{ e.preventDefault(); applyGlobalSearch(topbarSearchInput?.value || ''); });
  topbarSearchInput?.addEventListener('input', (e)=> applyGlobalSearch(e.target.value || ''));
  buildTopChart(window.CURRENT_AREA);
  renderMap(window.CURRENT_AREA);

  /* ========= Print ========= */
  function buildPrintViewFromCurrent(){
    const d = window._currentDeskData || { deskName:'-', count:0, revenue:0, bookings:[] };
    document.getElementById('pTitle').textContent = `รายงานข้อมูลโต๊ะ: ${d.deskName || '-'}`;
    document.getElementById('pSub').textContent   = `วันที่ออกรายงาน: ` + new Date().toLocaleString('th-TH',{hour12:false});
    document.getElementById('pCount').textContent   = String(d.count || 0);
    document.getElementById('pRevenue').textContent = (d.revenue||0).toLocaleString() + ' บาท';

    const tb = document.getElementById('pTbody');
    tb.innerHTML = '';
    (d.bookings || []).forEach(b=>{
      tb.insertAdjacentHTML('beforeend', `
        <tr>
          <td>${b.fullname || ''}</td>
          <td>${b.booking_date || ''}</td>
          <td>${b.booking_start_time || ''}</td>
          <td>${b.booking_end_time || ''}</td>
          <td class="text-end">${(parseFloat(b.price||0)).toLocaleString()}</td>
        </tr>
      `);
    });
    if (!(d.bookings||[]).length){
      tb.innerHTML = `<tr><td colspan="5" class="text-center text-muted">ไม่มีข้อมูล</td></tr>`;
    }
  }
  document.getElementById('btnPrintLikePDF')?.addEventListener('click', () => {
    const modalEl = document.getElementById('deskModal');
    const m = bootstrap.Modal.getOrCreateInstance(modalEl);
    m.hide();
    modalEl.addEventListener('hidden.bs.modal', () => {
      buildPrintViewFromCurrent();
      requestAnimationFrame(()=> setTimeout(()=> window.print(), 50));
    }, { once:true });
  });

  /* === ตัวกรอง: bind โหมดและปุ่ม === */
  const modeRadios = {
    day:   document.getElementById('modeDay'),
    month: document.getElementById('modeMonth'),
    year:  document.getElementById('modeYear')
  };
  const fromInput = document.getElementById('fromInput');
  const toInput   = document.getElementById('toInput');
  const runBtn    = document.getElementById('runReport');

  currentFilter.mode = modeRadios.month?.checked ? 'month' : (modeRadios.year?.checked ? 'year' : 'day');
  setFilterInputsByMode(currentFilter.mode);

  Object.entries(modeRadios).forEach(([mode, el])=>{
    el?.addEventListener('change', ()=>{
      if(!el.checked) return;
      currentFilter.mode = mode;
      fromInput.value=''; toInput.value='';
      setFilterInputsByMode(mode);
    });
  });

  runBtn?.addEventListener('click', ()=>{
    const range = getRangeFromInputs();
    if(!window._currentDeskData){ return; }
    const deskName = window._currentDeskData.deskName;
    let deskId = null;
    (window.ALL_DESKS_BY_AREA?.[window.CURRENT_AREA] || []).some(d=>{
      if(d.desk_name===deskName){ deskId=d.desk_id; return true; }
      return false;
    });
    if(!deskId){ alert('ไม่พบรหัสโต๊ะสำหรับการกรอง'); return; }
    fetchDeskDataWithRange(deskId, deskName, range.start, range.end);
  });
});
</script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let topChart;
function buildTopChart(initialArea) {
  const ctx = document.getElementById('topDesksChart').getContext('2d');
  const pack = (window.TOP3_BY_AREA?.[initialArea]) || {labels:[], data:[]};
  topChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: pack.labels,
      datasets: [{
        label: 'จำนวนการจอง',
        data: pack.data,
        backgroundColor: ['#f9a1bc', '#36b9cc', '#f6c23e'],
        borderWidth: 1
      }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
  });
}
function updateTopChart(area) {
  const pack = (window.TOP3_BY_AREA?.[area]) || {labels:[], data:[]};
  if (!topChart) return;
  topChart.data.labels = pack.labels;
  topChart.data.datasets[0].data = pack.data;
  topChart.update();
  const titleEl = document.querySelector('.card .card-title');
  if (titleEl) titleEl.textContent = `3 อันดับโต๊ะยอดนิยม (${area})`;
}
</script>

<!-- Export: SheetJS + jsPDF + Thai font + AutoTable -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script src="font-thsarabun.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js"></script>

<script>
// สร้างข้อมูลแถวจากข้อมูลในโมดัล
function buildModalRows() {
  const list = (window._currentDeskData && window._currentDeskData.bookings) ? window._currentDeskData.bookings : [];
  if (!Array.isArray(list) || list.length === 0) return [];
  return list.map(b => [ b.fullname||'', b.booking_date||'', b.booking_start_time||'', b.booking_end_time||'', (parseFloat(b.price||0)).toFixed(2) ]);
}
// Excel
document.getElementById('btnExcelModal').addEventListener('click', function() {
  const rows = buildModalRows();
  const ws = XLSX.utils.aoa_to_sheet([['ชื่อผู้จอง','วันที่จอง','เวลาเริ่ม','เวลาสิ้นสุด','ราคา (บาท)'], ...rows]);
  const wb = XLSX.utils.book_new();
  const sheetName = (window._currentDeskData?.deskName || 'โต๊ะ').toString().substring(0,31);
  XLSX.utils.book_append_sheet(wb, ws, sheetName);
  const stamp = new Date().toISOString().slice(0,19).replace('T','_').replace(/:/g,'-');
  XLSX.writeFile(wb, `รายงานโต๊ะ_${(window._currentDeskData?.deskName||'ไม่ระบุ')}_${stamp}.xlsx`);
});
// PDF
document.getElementById('btnPDFModal').addEventListener('click', function() {
  const d = window._currentDeskData;
  if (!d) { alert('กรุณาเปิดข้อมูลโต๊ะก่อน'); return; }
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF({ unit: 'pt', format: 'a4' });
  try { doc.setFont('THSarabunNew', 'normal'); } catch(e) {}

  const marginX = 40; let y = 50;
  doc.setFontSize(20); doc.text(`รายงานข้อมูลโต๊ะ: ${d.deskName || '-'}`, marginX, y); y += 28;
  doc.setFontSize(14); const stamp = new Date().toLocaleString('th-TH', { hour12:false });
  doc.text(`วันที่ออกรายงาน: ${stamp}`, marginX, y); y += 18;

  const gap = 20, boxW = 240, boxH = 78; const x1 = marginX, x2 = marginX + boxW + gap, by = y + 6;
  doc.setFillColor(23,162,184); doc.roundedRect(x1, by, boxW, boxH, 10,10,'F');
  doc.setFillColor(220,53,69);  doc.roundedRect(x2, by, boxW, boxH, 10,10,'F');
  function centerText(str, cx, top, fontSize, white=false){
    if (white) doc.setTextColor(255,255,255);
    doc.setFontSize(fontSize);
    const w = doc.getTextWidth(str);
    doc.text(str, cx - w/2, top);
    doc.setTextColor(0,0,0);
  }
  centerText('จำนวนการจอง', x1 + boxW/2, by + 24, 18, true);
  centerText('รายได้รวม',    x2 + boxW/2, by + 24, 18, true);
  centerText(String(d.count || 0), x1 + boxW/2, by + 56, 22, true);
  centerText(`${(d.revenue||0).toLocaleString()} บาท`, x2 + boxW/2, by + 56, 18, true);
  y = by + boxH + 24;

  const body = (d.bookings || []).map(b => ([
    b.fullname || '',
    b.booking_date || '',
    b.booking_start_time || '',
    b.booking_end_time || '',
    (parseFloat(b.price||0)).toFixed(2)
  ]));

  doc.setFontSize(18); doc.text('รายละเอียดการจอง', marginX, y); y += 12;
  doc.autoTable({
    head: [['ชื่อผู้จอง','วันที่จอง','เวลาเริ่ม','เวลาสิ้นสุด','ราคา (บาท)']],
    body,
    startY: y + 6,
    styles: { font: 'THSarabunNew', fontSize: 16, cellPadding: 4 },
    headStyles: { fillColor: [13,110,253] },
    margin: { left: marginX, right: marginX }
  });

  const fname = `รายงานโต๊ะ_${d.deskName || '-'}_${new Date().toISOString().slice(0,10)}.pdf`;
  doc.save(fname);
});
</script>
</body>
</html>
