<?php
/**
 * report_summary.php
 * Coworking Space – Admin Summary Report Dashboard
 */
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header('Location: login.php'); exit();
}
require_once 'db_connect.php';

// โหลดรายการพื้นที่ (ชั้น)
$areas = [];
$area_q = $conn->query("SELECT DISTINCT areas FROM desks ORDER BY areas");
while ($row = $area_q->fetch_assoc()) { $areas[] = $row['areas']; }
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>รายงานสรุป - Coworking Admin</title>

  <!-- Libs (CSS) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />

  <!-- 1) Layout & Sidebar ก่อน -->
  <link href="style1.css" rel="stylesheet">
  <!-- 2) Navbar ทีหลัง (อย่าแตะ .main-sidebar/.main-content ในไฟล์นี้) -->
  <link href="style.css" rel="stylesheet">

  <!-- Charts / Export -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.1/jspdf.plugin.autotable.min.js"></script>
  <script src="font-thsarabun.js"></script>

  <style>
  /* ========== Base & Variables ========== */
  :root { --sidebar-w:260px; --topbar-h:64px; }
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

  /* ========== Cards & Small Boxes ========== */
  .card{border:0;box-shadow:0 6px 18px rgba(0,0,0,.06)}
  .small-box{position:relative;overflow:hidden;border-radius:.5rem;padding:1.25rem;color:#fff}
  .small-box .icon{position:absolute;right:12px;top:12px;font-size:2.25rem;opacity:.25}
  .small-box.bg-danger{background:linear-gradient(135deg,#ef4444,#b91c1c)}
  .small-box.bg-info{background:linear-gradient(135deg,#06b6d4,#0891b2)}
  .small-box.bg-success{background:linear-gradient(135deg,#22c55e,#16a34a)}
  .small-box.bg-warning{background:linear-gradient(135deg,#f59e0b,#d97706)}

  /* Panel (ใช้แทน card สำหรับโซนกราฟใหม่) */
  .panel{border:0;background:#fff;border-radius:.5rem;box-shadow:0 6px 18px rgba(0,0,0,.06)}
  .panel.pad{padding:14px}
  .card-head{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:8px}
  .muted{color:#6b7280;font-size:.95rem}

  /* ตาราง (หน้าจอ) */
  #tableRecent thead th,
  #tableRecent tbody td{ text-align:center; vertical-align:middle }
  .table .text-end{ text-align:right } /* ยอดชำระชิดขวา */

  /* ฟิลด์โหมด */
  .mode-input{display:none!important}
  .mode-input.active{display:block!important}

  /* ============ PRINT ============ */
  .report-header{display:none}
  @media print{
    @page{size:A4 portrait;margin:14mm}
    html{-webkit-print-color-adjust:exact;print-color-adjust:exact}

    #filters,#exportButtons,#refreshBtn,nav,footer,.main-sidebar,.app-topbar{display:none!important}
    .main-content{margin-left:0!important;width:100%!important}
    .card,.panel{box-shadow:none!important}

    .report-header{display:block!important;text-align:center;margin-bottom:12px}
    .report-title{font-size:20px;font-weight:700}
    .report-subtitle{font-size:14px;color:#555}

    .small-box-row{
      display:grid!important;grid-template-columns:repeat(4,1fr)!important;
      gap:12px!important;width:100%!important;margin:6px 0 14px!important
    }
    .small-box-row>[class^="col-"],.small-box-row>[class*=" col-"]{display:contents!important}
    .small-box{
      height:92px!important;padding:14px 16px!important;border-radius:12px!important;color:#fff!important;
      break-inside:avoid;page-break-inside:avoid
    }
    .small-box .inner h3{font-size:28px!important;margin:0 0 6px!important;color:#fff!important}
    .small-box .inner p{font-size:14px!important;margin:0!important;color:#fff!important}
    .small-box .icon{font-size:28px!important;opacity:.35!important;top:10px!important;right:10px!important}

    .card-header{padding:6pt 10pt!important}
    .card-body{padding:8pt 10pt!important}
    .card,.panel,.card-body{break-inside:auto!important;page-break-inside:auto!important;margin-bottom:8pt!important}
    .print-keep{break-inside:avoid!important;page-break-inside:avoid!important}
    .print-break-before{break-before:auto!important;page-break-before:auto!important}
    .print-keep-head{break-after:avoid!important;page-break-after:avoid!important}

    #tableRecent thead{display:table-header-group}
    #tableRecent tfoot{display:table-footer-group}
    #tableRecent tr{break-inside:avoid;page-break-inside:avoid}
    #tableRecent thead th,#tableRecent tbody td{ text-align:center!important;vertical-align:middle!important }
    .table .text-end{ text-align:right!important }

    .row.g-3>.col-xl-8,.row.g-3>.col-xl-4{flex:0 0 100%!important;max-width:100%!important}
    .panel canvas{max-height:60mm!important}

    .row.g-3{row-gap:8pt!important}

    .print-new-page{
      break-before: page !important;
      page-break-before: always !important;
    }
  }
  </style>
</head>
<body>

<div class="wrapper">
  <!-- Sidebar -->
  <?php include 'sidebar_admin.php'; ?>

  <div class="main-content">
    <!-- Topbar -->
    <?php
      if (!defined('NAV_API_BASE')) define('NAV_API_BASE', '/coworking/');
      if (!defined('NAV_HOME_HREF')) define('NAV_HOME_HREF', 'desk_status.php');
      /* ธงเดียวที่ navbar_admin.php รู้จัก: กันโหลด asset ซ้ำ */
      if (!defined('APP_BOOTSTRAP_CSS')) define('APP_BOOTSTRAP_CSS', true);
      include 'navbar_admin.php';
    ?>

    <div class="container-fluid py-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h1 class="page-title mb-0">รายงานสรุป</h1>
          <div class="text-muted">Coworking Space – Admin Dashboard</div>
        </div>
        <div class="d-flex flex-wrap gap-2" id="exportButtons">
          <button class="btn btn-secondary" id="refreshBtn"><i class="bi bi-arrow-clockwise me-1"></i>รีเฟรช</button>
          <button class="btn btn-warning" id="btnClear"><i class="bi bi-eraser me-1"></i>ล้างตัวกรอง</button>
          <button class="btn btn-success" id="btnExcel"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel</button>
          <button class="btn btn-danger" id="btnPDF"><i class="bi bi-filetype-pdf me-1"></i>PDF</button>
          <button class="btn btn-dark" onclick="window.print()"><i class="bi bi-printer me-1"></i>พิมพ์</button>
        </div>
      </div>

      <!-- Header ที่ใช้ตอนพิมพ์ -->
      <div class="report-header">
        <div class="report-subtitle" id="printRange"></div>
      </div>

      <!-- ฟิลเตอร์ -->
      <div class="card mb-4" id="filters">
        <div class="card-body">
          <div class="row g-3 align-items-end">
            <!-- โหมด -->
            <div class="col-12">
              <div class="btn-group" role="group" aria-label="Modes">
                <input type="radio" class="btn-check" name="mode" id="mode-range" value="range" checked>
                <label class="btn btn-outline-secondary" for="mode-range"><i class="bi bi-calendar3 me-1"></i>ช่วงวัน</label>

                <input type="radio" class="btn-check" name="mode" id="mode-month" value="month">
                <label class="btn btn-outline-secondary" for="mode-month"><i class="bi bi-calendar-month me-1"></i>รายเดือน</label>

                <input type="radio" class="btn-check" name="mode" id="mode-year" value="year">
                <label class="btn btn-outline-secondary" for="mode-year"><i class="bi bi-calendar-date me-1"></i>รายปี</label>
              </div>
            </div>

            <!-- ช่วงวัน -->
            <div class="col-12 col-md-6 mode-input active" id="mode-range-inputs">
              <div class="row g-3">
                <div class="col-6">
                  <label class="form-label mb-1">จากวันที่</label>
                  <input type="text" id="startDate" class="form-control" placeholder="วว/ดด/ปป" autocomplete="off" />
                </div>
                <div class="col-6">
                  <label class="form-label mb-1">ถึงวันที่</label>
                  <input type="text" id="endDate" class="form-control" placeholder="วว/ดด/ปป" autocomplete="off" />
                </div>
              </div>
            </div>

            <!-- รายเดือน -->
            <div class="col-12 col-md-3 mode-input" id="mode-month-inputs">
              <label class="form-label mb-1">เลือกเดือน</label>
              <input type="month" id="monthInput" class="form-control" placeholder="เดือน" autocomplete="off" />
            </div>

            <!-- รายปี -->
            <div class="col-12 col-md-3 mode-input" id="mode-year-inputs">
              <label class="form-label mb-1">เลือกปี</label>
              <select id="yearInput" class="form-select" placeholder="ปี"></select>
            </div>

            <!-- พื้นที่ -->
            <div class="col-12 col-md-3">
              <label class="form-label mb-1">เลือกพื้นที่ (ชั้น)</label>
              <select id="area" class="form-select">
                <option value="">ทั้งหมด</option>
                <?php foreach ($areas as $a): ?>
                  <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 col-md-3">
              <button class="btn btn-primary w-100" id="applyFilter"><i class="bi bi-funnel me-1"></i>ดูรายงาน</button>
            </div>
          </div>
        </div>
      </div>

      <!-- กล่องสรุป -->
      <div class="row g-3 mb-4 small-box-row">
        <div class="col-12 col-md-6 col-xl-3">
          <div class="small-box bg-danger">
            <div class="inner"><h3 id="totalRevenue">0</h3><p>รายได้รวม (บาท)</p></div>
            <div class="icon"><i class="bi bi-cash-stack"></i></div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="small-box bg-info">
            <div class="inner"><h3 id="totalBookings">0</h3><p>จำนวนการจอง</p></div>
            <div class="icon"><i class="bi bi-calendar-check"></i></div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="small-box bg-success">
            <div class="inner"><h3 id="occupancy">0%</h3><p>อัตราการใช้งาน</p></div>
            <div class="icon"><i class="bi bi-activity"></i></div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="small-box bg-warning">
            <div class="inner"><h3 id="topDesk">-</h3><p>โต๊ะยอดนิยม</p></div>
            <div class="icon"><i class="bi bi-trophy"></i></div>
          </div>
        </div>
      </div>

      <!-- Charts (แทนที่กราฟเดิมทั้งชุด) -->
      <!-- Charts -->
      <div class="row grid-gap mt-2">
        <div class="col-12 col-xl-8">
          <div class="panel pad">
            <div class="card-head">
              <div class="fw-bold">กราฟรายได้</div>
              <div class="muted" id="rev-cap">—</div>
            </div>
            <canvas id="revChart" height="120"></canvas>
          </div>
        </div>
        <div class="col-12 col-xl-4">
          <div class="panel pad">
            <div class="card-head">
              <div class="fw-bold">สัดส่วนรายได้ตามพื้นที่</div>
              <div class="muted" id="pie-cap">—</div>
            </div>
            <canvas id="areaPie" height="120"></canvas>
          </div>
        </div>
      </div>

      <div class="row grid-gap mt-2">
        <div class="col-12 col-xl-8">
          <div class="panel pad">
            <div class="card-head">
              <div class="fw-bold">กราฟจำนวนการจอง</div>
              <div class="muted" id="book-cap">—</div>
            </div>
            <canvas id="bookChart" height="120"></canvas>
          </div>
        </div>
        <div class="col-12 col-xl-4">
          <div class="panel pad">
            <div class="fw-bold mb-2">โต๊ะทำรายได้สูงสุด 5 อันดับ</div>
            <ol id="top-desks" style="padding-left:18px;margin:0">
              <li class="muted">—</li>
            </ol>
          </div>
        </div>
      </div>

      <!-- ตารางรายการ -->
      <div class="card mt-4 print-new-page">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
          รายการจองทั้งหมด
          <small class="text-muted" id="resultRange"></small>
        </div>
        <div class="card-body table-responsive">
          <table class="table table-striped" id="tableRecent">
            <thead>
              <tr>
                <th>วันที่</th>
                <th>เวลาเริ่ม-สิ้นสุด</th>
                <th>ชื่อผู้ใช้</th>
                <th>ชื่อโต๊ะ</th>
                <th class="text-end">ยอดชำระ (บาท)</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div><!-- /container-fluid -->
  </div><!-- /main-content -->
</div><!-- /wrapper -->

<script>
/* ---------- Helpers ---------- */
const $ = (s)=>document.querySelector(s);
const fmtTHB = (n)=> new Intl.NumberFormat('th-TH', { minimumFractionDigits:2, maximumFractionDigits:2 }).format(Number(n||0));
const fmtDate = (d)=> new Date(d).toLocaleDateString('th-TH', { year:'numeric', month:'2-digit', day:'2-digit'});

/* Placeholder สำหรับช่องวันที่ */
function applyDatePlaceholder(el){
  if(!el) return;
  if(!el.value){ el.type='text'; el.placeholder='วว/ดด/ปป'; }
  el.addEventListener('focus', ()=>{ el.type='date'; });
  el.addEventListener('blur',  ()=>{ if(!el.value){ el.type='text'; el.placeholder='วว/ดด/ปป'; } });
}
function clearDateWithPlaceholder(el){
  if(!el) return;
  el.value=''; el.type='text'; el.placeholder='วว/ดด/ปป';
}

/* ล้างช่องวันที่แบบบังคับ */
function forceClearDateInputs(){
  const sd = document.getElementById('startDate');
  const ed = document.getElementById('endDate');
  [sd,ed].forEach(el=>{
    if(!el) return;
    el.value=''; try{ el.valueAsDate=null; }catch(e){}
    el.setAttribute('value',''); el.removeAttribute('min'); el.removeAttribute('max');
    el.dispatchEvent(new Event('input',{bubbles:true}));
    el.dispatchEvent(new Event('change',{bubbles:true}));
    clearDateWithPlaceholder(el);
  });
}

/* ซ่อน/แสดงอินพุตตามโหมด */
function toggleModeInputs(mode){
  const rangeEl = document.getElementById('mode-range-inputs');
  const monthEl = document.getElementById('mode-month-inputs');
  const yearEl  = document.getElementById('mode-year-inputs');

  [rangeEl, monthEl, yearEl].forEach(el => { if (el) el.classList.remove('active'); });
  if (mode === 'range' && rangeEl) rangeEl.classList.add('active');
  if (mode === 'month' && monthEl) monthEl.classList.add('active');
  if (mode === 'year'  && yearEl)  yearEl.classList.add('active');

  const sd = document.getElementById('startDate');
  const ed = document.getElementById('endDate');

  if (mode === 'range') {
    if (sd) sd.disabled = false;
    if (ed) ed.disabled = false;
  } else {
    if (sd) sd.disabled = true;
    if (ed) ed.disabled = true;
    forceClearDateInputs();
    if (mode === 'month' && $('#monthInput') && !$('#monthInput').value) {
      $('#monthInput').value = new Date().toISOString().slice(0,7);
    }
    if (mode === 'year' && $('#yearInput') && !$('#yearInput').value) {
      $('#yearInput').value = new Date().getFullYear();
    }
  }
}

/* ---------- Init ---------- */
(function initControls(){
  document.getElementById('mode-range').checked = true;
  toggleModeInputs('range');

  const now = new Date();
  const ym = now.toISOString().slice(0,7);
  if ($('#monthInput')) $('#monthInput').value = ym;

  const yearSel = $('#yearInput');
  if (yearSel){
    const currentY = now.getFullYear();
    for(let y=currentY+1; y>=currentY-5; y--){
      const opt=document.createElement('option'); opt.value=y; opt.textContent=y;
      if (y===currentY) opt.selected=true;
      yearSel.appendChild(opt);
    }
  }

  applyDatePlaceholder(document.getElementById('startDate'));
  applyDatePlaceholder(document.getElementById('endDate'));

  document.querySelectorAll('input[name="mode"]').forEach(r=>{
    r.addEventListener('change', (e)=> toggleModeInputs(e.target.value));
  });
})();

/* ---------- Charts ---------- */
if (window.ChartDataLabels) { Chart.register(ChartDataLabels); }
let chartRev=null, chartAreaPie=null, chartBook=null;

/* กราฟรายได้ต่อวัน/เดือน/ปี */
function buildRevChart(ctx, labels, values){
  if(chartRev){ chartRev.destroy(); chartRev=null; }
  chartRev = new Chart(ctx,{
    type:'bar',
    data:{ labels, datasets:[{ label:'รายได้ (บาท)', data: values }]},
    options:{
      responsive:true,
      scales:{ y:{ beginAtZero:true, ticks:{ callback:(v)=>fmtTHB(v) } } },
      plugins:{
        legend:{ display:false },
        tooltip:{ callbacks:{ label:(it)=>`฿ ${fmtTHB(it.raw)}` } },
        datalabels:{ formatter:(v)=>fmtTHB(v), anchor:'end', align:'top', offset:2, clamp:true, color:'#111', font:{ weight:'600' } }
      }
    }
  });
}

/* พายสัดส่วนรายได้ตามพื้นที่ */
function buildAreaPieChart(ctx, labels, values){
  if(chartAreaPie){ chartAreaPie.destroy(); chartAreaPie=null; }
  chartAreaPie = new Chart(ctx,{
    type:'doughnut',
    data:{ labels, datasets:[{ label:'สัดส่วนรายได้', data: values }]},
    options:{
      responsive:true, cutout:'55%',
      plugins:{
        legend:{ position:'bottom' },
        tooltip:{ callbacks:{ label:(it)=>`${it.label}: ฿ ${fmtTHB(it.raw)} (${(it.raw / values.reduce((a,b)=>a+b,0) * 100 || 0).toFixed(1)}%)` } },
        datalabels:{ formatter:(v)=> (v>0? fmtTHB(v): ''), color:'#111', font:{ weight:'600' } }
      }
    }
  });
}

/* กราฟจำนวนการจอง */
function buildBookChart(ctx, labels, counts){
  if(chartBook){ chartBook.destroy(); chartBook=null; }
  chartBook = new Chart(ctx,{
    type:'line',
    data:{ labels, datasets:[{ label:'จำนวนการจอง (ครั้ง)', data: counts, tension:.25, fill:false }]},
    options:{
      responsive:true,
      scales:{ y:{ beginAtZero:true, ticks:{ precision:0 } } },
      plugins:{
        legend:{ display:false },
        tooltip:{ callbacks:{ label:(it)=>`${it.raw} ครั้ง` } },
        datalabels:{ formatter:(v)=> (v>0? v: ''), anchor:'end', align:'top', offset:2, clamp:true, color:'#111', font:{ weight:'600' } }
      }
    }
  });
}

/* ---------- Load Report ---------- */
async function loadReport(pushToHeader=false){
  const mode = document.querySelector('input[name="mode"]:checked')?.value || 'range';
  const area = $('#area').value;
  const params = new URLSearchParams({ mode, area });
  let rangeText='';

  if (mode === 'range') {
    const startDate = $('#startDate').value;
    const endDate   = $('#endDate').value;
    if (!startDate || !endDate) return;
    params.set('start_date', startDate);
    params.set('end_date', endDate);
    rangeText = `${fmtDate(startDate)} - ${fmtDate(endDate)}`;
  } else if (mode === 'month') {
    const month = $('#monthInput').value;
    if (!month) return;
    params.set('month', month);
    const [y,m] = month.split('-').map(Number);
    const s = new Date(y, m-1, 1);
    const e = new Date(y, m, 0);
    rangeText = `${fmtDate(s.toISOString().slice(0,10))} - ${fmtDate(e.toISOString().slice(0,10))}`;
  } else {
    const year = $('#yearInput').value;
    if (!year) return;
    params.set('year', year);
    rangeText = `01/01/${year} - 31/12/${year}`;
  }

  const res = await fetch('get_report_summary.php?' + params.toString(), { cache:'no-store' });
  if(!res.ok) return;
  const data = await res.json().catch(()=>null);
  if(!data) return;

  // สรุปบนกล่อง
  document.getElementById('totalRevenue').textContent  = fmtTHB(data.total_revenue || 0);
  document.getElementById('totalBookings').textContent = data.total_bookings || 0;
  document.getElementById('occupancy').textContent     = (data.occupancy_pct || 0).toFixed(1) + '%';
  document.getElementById('topDesk').textContent       = data.top_desk?.desk_name ? `${data.top_desk.desk_name} (${data.top_desk.count} ครั้ง)` : '-';

  // ช่วงวันที่โชว์มุมหัวการ์ด/พิมพ์
  const finalRange = area ? (rangeText + ' • ' + area) : rangeText;
  document.getElementById('resultRange').textContent = finalRange;
  if (pushToHeader) document.getElementById('printRange').textContent = finalRange;

  // === กราฟรายได้ (bar) ===
  const revLabels = (data.revenue_by_day || []).map(r=>r.label);
  const revValues = (data.revenue_by_day || []).map(r=>Number(r.revenue||0));
  buildRevChart(document.getElementById('revChart'), revLabels, revValues);
  document.getElementById('rev-cap').textContent = finalRange || '—';

  // === กราฟจำนวนการจอง (line) ===
  // หาก API ไม่มี bookings_by_day ให้ fallback เป็น count (หรือ 1 ถ้าไม่มี) จาก revenue_by_day
  const bookLabels = (data.bookings_by_day || data.revenue_by_day || []).map(r=>r.label);
  const bookCounts = (data.bookings_by_day || []).length
    ? data.bookings_by_day.map(r=>Number(r.count||0))
    : (data.revenue_by_day || []).map(r=> (typeof r.count!=='undefined' ? Number(r.count): (Number(r.revenue)>0 ? 1 : 0)));
  buildBookChart(document.getElementById('bookChart'), bookLabels, bookCounts);
  document.getElementById('book-cap').textContent = finalRange || '—';

  // === พายรายได้ตามพื้นที่ ===
  // คาดหวัง data.revenue_by_area = [{area:'ชั้น 1', revenue:12345}, ...]
  const areaArr = (data.revenue_by_area || []);
  if (areaArr.length){
    const lab = areaArr.map(a=>a.area || a.areas || '—');
    const val = areaArr.map(a=>Number(a.revenue||0));
    buildAreaPieChart(document.getElementById('areaPie'), lab, val);
    document.getElementById('pie-cap').textContent = finalRange || '—';
  } else {
    // ไม่มีข้อมูล -> ล้างกราฟ
    if(chartAreaPie){ chartAreaPie.destroy(); chartAreaPie=null; }
    const ctx = document.getElementById('areaPie').getContext('2d');
    ctx.clearRect(0,0,ctx.canvas.width,ctx.canvas.height);
    document.getElementById('pie-cap').textContent = '—';
  }

  // === Top desks (ลิสต์) ===
  const topList = document.getElementById('top-desks');
  topList.innerHTML = '';
  const desks = (data.top_desks || []).slice(0,5);
  if (!desks.length){
    topList.innerHTML = '<li class="muted">—</li>';
  } else {
    desks.forEach((d,i)=>{
      const li = document.createElement('li');
      const cnt = (typeof d.revenue!=='undefined')
        ? `฿ ${fmtTHB(d.revenue)}`
        : `${Number(d.count||0)} ครั้ง`;
      li.textContent = `${d.desk_name || '—'} — ${cnt}`;
      topList.appendChild(li);
    });
  }

  // === ตารางรายการล่าสุด ===
  const tbody = document.querySelector('#tableRecent tbody');
  tbody.innerHTML = '';
  (data.recent || []).forEach(r=>{
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${fmtDate(r.booking_date)}</td>
      <td>${r.booking_start_time?.slice(0,5) || ''} - ${r.booking_end_time?.slice(0,5) || ''}</td>
      <td>${r.customer_name || ''}</td>
      <td>${r.desk_name || ''}</td>
      <td class="text-end">${fmtTHB(r.amount || 0)}</td>`;
    tbody.appendChild(tr);
  });
}

/* ---------- Clear / Export ---------- */
function clearUI(){
  document.getElementById('totalRevenue').textContent  = '0';
  document.getElementById('totalBookings').textContent = '0';
  document.getElementById('occupancy').textContent     = '0%';
  document.getElementById('topDesk').textContent       = '-';
  document.getElementById('resultRange').textContent   = '';
  ['rev-cap','pie-cap','book-cap'].forEach(id=>{ const el=document.getElementById(id); if(el) el.textContent='—'; });

  const tbody = document.querySelector('#tableRecent tbody'); if (tbody) tbody.innerHTML = '';
  const topList = document.getElementById('top-desks'); if (topList) topList.innerHTML = '<li class="muted">—</li>';

  if (chartRev) { chartRev.destroy(); chartRev = null; }
  if (chartAreaPie) { chartAreaPie.destroy(); chartAreaPie = null; }
  if (chartBook) { chartBook.destroy(); chartBook = null; }
}
function exportExcel(){
  const table = document.getElementById('tableRecent');
  const wb = XLSX.utils.table_to_book(table, { sheet: 'Recent' });
  XLSX.writeFile(wb, 'summary_recent.xlsx');
}
async function exportPDF(){
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF('p','pt','a4');

  if (window.THSarabunNew) {
    try { doc.addFileToVFS('THSarabunNew.ttf', THSarabunNew); } catch(e){}
    try { doc.addFont('THSarabunNew.ttf', 'THSarabunNew', 'normal'); } catch(e){}
  }
  doc.setFont('THSarabunNew', 'normal');

  const pageW = doc.internal.pageSize.getWidth();
  const pageH = doc.internal.pageSize.getHeight();
  const margin = 40;
  let y = 60;

  const range = document.getElementById('resultRange').textContent || '';
  doc.setFontSize(18); doc.text('รายงานสรุป Co-working Space', margin, y);
  doc.setFontSize(14); doc.text(range, margin, y + 20);
  y += 40;

  const sRevenue  = document.getElementById('totalRevenue').textContent || '0';
  const sBookings = document.getElementById('totalBookings').textContent || '0';
  const sOcc      = document.getElementById('occupancy').textContent || '0%';
  const sTopDesk  = document.getElementById('topDesk').textContent || '-';
  const boxW = (pageW - margin*2 - 18*3) / 4;
  const boxH = 64;
  [
    { t:'รายได้รวม (บาท)', v:sRevenue,  c:[239,68,68]  },
    { t:'จำนวนการจอง',     v:sBookings, c:[6,182,212]  },
    { t:'อัตราการใช้งาน',   v:sOcc,      c:[34,197,94]  },
    { t:'โต๊ะยอดนิยม',      v:sTopDesk,  c:[245,158,11] },
  ].forEach((b,i)=>{
    const x = margin + i*(boxW+18);
    doc.setFillColor(...b.c);
    doc.roundedRect(x, y, boxW, boxH, 8, 8, 'F');
    doc.setTextColor(255,255,255);
    doc.setFontSize(16); doc.text(b.t, x+10, y+20);
    doc.setFontSize(18); doc.text(String(b.v), x+10, y+43);
  });
  doc.setTextColor(0,0,0);
  y += boxH + 18;

  const IMG_MAX_W = pageW - margin*2;
  const IMG_MAX_H = 160;
  function addCanvasImage(canvas, yPos, title){
    if (!canvas) return yPos;
    try {
      const img = canvas.toDataURL('image/png', 1.0);
      const ratio = canvas.height / canvas.width;
      const targetH = IMG_MAX_H;
      const targetW = Math.min(IMG_MAX_W, targetH / ratio);
      const x = margin + (IMG_MAX_W - targetW)/2;

      doc.setFontSize(18);
      if (title) { doc.text(title, margin, yPos); yPos += 10; }

      if (yPos + 8 + targetH > pageH - 100) { doc.addPage(); yPos = 60; }
      doc.addImage(img, 'PNG', x, yPos + 8, targetW, targetH);
      return yPos + 8 + targetH + 16;
    } catch { return yPos; }
  }

  // ใส่กราฟทั้งสาม
  y = addCanvasImage(document.getElementById('revChart'), y, 'กราฟรายได้');
  y = addCanvasImage(document.getElementById('areaPie'), y, 'สัดส่วนรายได้ตามพื้นที่');
  y = addCanvasImage(document.getElementById('bookChart'), y, 'กราฟจำนวนการจอง');

  // หน้าใหม่สำหรับตาราง
  doc.addPage();
  y = 60;
  doc.setFontSize(18);
  doc.text('รายการจองทั้งหมด', margin, y);
  y += 18;

  const lastCol = document.querySelectorAll('#tableRecent thead th').length - 1;
  const columnStyles = {}; columnStyles[lastCol] = { halign: 'right' };

  doc.autoTable({
    html: '#tableRecent',
    startY: y,
    styles: { font: 'THSarabunNew', fontSize: 12, cellPadding: 3, halign: 'center' },
    headStyles: { fillColor: [0,102,204], textColor: 255, halign: 'center' },
    columnStyles,
    didParseCell: (data) => {
      if (data.section === 'body' && data.column.index === lastCol) {
        data.cell.styles.halign = 'right';
      }
    },
    margin: { left: margin, right: margin }
  });

  doc.save('summary_report.pdf');
}

/* ---------- Events ---------- */
document.getElementById('applyFilter').addEventListener('click', ()=>loadReport(true));
document.getElementById('refreshBtn').addEventListener('click', ()=>loadReport());
document.getElementById('btnClear').addEventListener('click', () => {
  const mode = document.querySelector('input[name="mode"]:checked')?.value || 'range';
  if (mode === 'range') {
    clearDateWithPlaceholder(document.getElementById('startDate'));
    clearDateWithPlaceholder(document.getElementById('endDate'));
    document.getElementById('startDate').disabled = false;
    document.getElementById('endDate').disabled   = false;
  } else if (mode === 'month') {
    forceClearDateInputs();
    document.getElementById('startDate').disabled = true;
    document.getElementById('endDate').disabled   = true;
    document.getElementById('monthInput').value   = '';
  } else {
    forceClearDateInputs();
    document.getElementById('startDate').disabled = true;
    document.getElementById('endDate').disabled   = true;
    document.getElementById('yearInput').value    = '';
    const mi = document.getElementById('monthInput'); if (mi) mi.value = '';
  }
  document.getElementById('area').value = '';
  clearUI();
});
document.getElementById('btnExcel').addEventListener('click', exportExcel);
document.getElementById('btnPDF').addEventListener('click', exportPDF);

/* โหลดครั้งแรก */
loadReport(true);
</script>

<script>
/* ก่อนพิมพ์: ทำให้การ์ด/พาเนลที่มีกราฟอยู่หน้าเดียวกัน แล้วค่อยเอาออกหลังพิมพ์ */
(function keepChartCardsOnOnePage(){
  function mark() {
    document.querySelectorAll('canvas').forEach(cv => {
      const host = cv.closest('.panel, .card');
      if (host) host.classList.add('print-keep');
    });
  }
  function unmark() {
    document.querySelectorAll('.print-keep').forEach(el => el.classList.remove('print-keep'));
  }
  window.addEventListener('beforeprint', mark);
  window.addEventListener('afterprint',  unmark);
})();
</script>

<!-- ✅ Bootstrap JS (bundle) โหลดครั้งเดียวไว้ท้าย body -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
