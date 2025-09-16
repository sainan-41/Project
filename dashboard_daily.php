<?php
// dashboard_daily.php
session_start();
require 'db_connect.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

/* (เพิ่ม) ดึงข้อมูลแอดมินสำหรับ navbar_admin1.php */
$admin_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT fullname, profile_pic FROM users WHERE user_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>สรุปยอดขาย</title>

  <!-- Bootstrap & Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

  <!-- 1) Layout & Sidebar ก่อน -->
  <link href="style1.css" rel="stylesheet">

  <!-- 2) Navbar ทีหลัง (อย่าแตะ .main-sidebar/.main-content ในไฟล์นี้) -->
  <link href="style.css" rel="stylesheet">

  <!-- Chart.js + Datalabels -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Export libs -->
  <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js"></script>
  <!-- ฟอนต์ไทยสำหรับ PDF (วางไฟล์นี้ไว้ข้างๆ หน้า) -->
  <script src="font-thsarabun.js"></script>

  <style>
    .filter-card{background:#f8f9fa;border:1px solid #e9ecef;border-radius:14px;padding:16px 18px}
    .filter-topbar{display:flex;align-items:center;gap:12px;margin-bottom:12px;flex-wrap:wrap}
    .segmented .btn{border-radius:10px 0 0 10px;padding:10px 14px;font-weight:600}
    .segmented .btn + .btn{border-radius:0}
    .segmented .btn:last-child{border-radius:0 10px 10px 0}
    .segmented .btn i{margin-right:6px}
    .btn-check:checked + .btn.btn-outline-secondary,
    .segmented .btn.btn-outline-secondary.active{color:#fff;background:#0d6efd;border-color:#0d6efd}
    .filter-bottombar{display:grid;grid-template-columns:1fr 1fr auto;gap:14px 18px;align-items:end}
    @media (max-width: 992px){.filter-bottombar{grid-template-columns:1fr}}
    .form-label{font-weight:600;margin-bottom:6px}
    .btn-run{padding:10px 18px;font-weight:600;height:44px}

    .small-box{border-radius:.6rem;box-shadow:0 4px 10px rgba(0,0,0,.08);padding:18px;color:#fff;position:relative}
    .small-box h3{font-size:2rem;font-weight:700;margin:0}
    .small-box p{font-size:1rem;margin:0;margin-top:6px}
    .small-box .icon{position:absolute;top:10px;right:14px;font-size:44px;opacity:.35}
    .bg-success{background-color:#dc3545!important}
    .bg-info{background-color:#17a2b8!important}
    .bg-warning{background-color:#ffc107!important}

    #salesChart{height:380px!important;max-height:380px}

    .header-row{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
    .header-row h2{margin:0}

    @media print{
      .no-print{display:none!important}
      .filter-card{display:none!important}
      .card{break-inside:avoid}
      #salesChart{height:430px!important;max-height:430px}
      body{-webkit-print-color-adjust:exact;print-color-adjust:exact}
    }
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
  /* ===== Force center the page content inside main-content (override) ===== */
.main-content > section.content{
  max-width: 1250px;           /* ปรับกว้างสุดได้ตามต้องการ */
  width: 100%;
  margin-left: auto !important;
  margin-right: auto !important;
  /* กันสไตล์เดิมบางไฟล์ที่อาจดันให้ชิดซ้าย */
  float: none !important;
}

/* ถ้าไฟล์อื่นไปยุ่ง .container ให้คงการจัดกลางไว้ */
.main-content > section.content > .container{
  max-width: 1250px;           /* ให้สอดคล้องกับด้านบน */
  width: 100% !important;
  margin-left: auto !important;
  margin-right: auto !important;
  padding-left: 12px;          /* ระยะเผื่อซ้าย/ขวาเล็กน้อย */
  padding-right: 12px;
}

/* บางธีมตั้ง margin ของ .content เอง ให้ลบทิ้งเฉพาะหน้านี้ */
section.content{
  margin-left: 0 !important;
  margin-right: 0 !important;
}

  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

  <!-- (เพิ่ม) include Sidebar -->
  <?php include 'sidebar_admin.php'; ?>

  <!-- (เพิ่ม) ครอบส่วนเนื้อหาด้วย main-content -->
  <div class="main-content">

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

    <section class="content pt-4 px-3">
      <div class="container">

        <!-- หัวข้อซ้าย + ปุ่มขวา -->
        <div class="header-row mb-3">
          <h2>สรุปยอดขาย</h2>
          <div class="d-flex flex-wrap gap-2 no-print" id="exportButtons">
            <button class="btn btn-secondary" id="refreshBtn"><i class="bi bi-arrow-clockwise me-1"></i>รีเฟรช</button>
            <button class="btn btn-warning" id="btnClear"><i class="bi bi-eraser me-1"></i>ล้างตัวกรอง</button>
            <button class="btn btn-success" id="btnExcel"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel</button>
            <button class="btn btn-danger" id="btnPDF"><i class="bi bi-filetype-pdf me-1"></i>PDF</button>
            <button class="btn btn-dark" onclick="window.print()"><i class="bi bi-printer me-1"></i>พิมพ์</button>
          </div>
        </div>
        <hr>

        <!-- ตัวกรอง -->
        <div class="filter-card mb-4 no-print">
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
              <label class="form-label">จากวันที่</label>
              <input id="fromInput" class="form-control" type="date" autocomplete="off">
            </div>
            <div>
              <label class="form-label">ถึงวันที่</label>
              <input id="toInput" class="form-control" type="date" autocomplete="off">
            </div>
            <div class="text-end">
              <button id="runReport" class="btn btn-primary btn-run">
                <i class="bi bi-funnel"></i> ดูรายงาน
              </button>
            </div>
          </div>
        </div>

        <!-- สรุป -->
        <div class="dashboard-summary">
          <div class="row g-3">
            <div class="col-md-4">
              <div class="small-box bg-success">
                <div class="inner"><h3 id="totalRevenue">0 บาท</h3><p>ยอดขายรวม</p></div>
                <div class="icon"><i class="bi bi-cash-coin"></i></div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="small-box bg-info">
                <div class="inner"><h3 id="bookingCount">0 รายการ</h3><p>จำนวนการจอง</p></div>
                <div class="icon"><i class="bi bi-calendar-check"></i></div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="small-box bg-warning">
                <div class="inner">
                  <h3 style="font-size:1.2rem;">โต๊ะยอดนิยม (Top 3)</h3>
                  <ul class="mb-0" id="topDesks" style="font-size:14px;list-style:none;padding-left:0;"></ul>
                </div>
                <div class="icon"><i class="bi bi-trophy"></i></div>
              </div>
            </div>
          </div>
        </div>

        <!-- กราฟ -->
        <div class="card mt-4">
          <div class="card-header"><h3 class="card-title">กราฟแสดงยอดขาย</h3></div>
          <div class="card-body"><canvas id="salesChart" height="140"></canvas></div>
        </div>
      </div>
    </section>

  </div><!-- /main-content -->
</div><!-- /wrapper -->

<script>
/* ---------- Utils ---------- */
function pad2(n){return n<10?'0'+n:''+n;}
function todayISO(){const d=new Date();return d.getFullYear()+'-'+pad2(d.getMonth()+1)+'-'+pad2(d.getDate());}
function lastDayOfMonth(y,m){return new Date(y,m,0).getDate();}
function fmtBaht(n){const x=Number(n);return Number.isFinite(x)?x.toLocaleString('th-TH',{minimumFractionDigits:2,maximumFractionDigits:2}):'0.00';}
function renderError(msg){console.error(msg);alert('ดึงข้อมูลไม่สำเร็จ: '+msg);}

/* ---------- Inputs / Modes ---------- */
const fromInput = document.getElementById('fromInput');
const toInput   = document.getElementById('toInput');
let currentMode = 'day';

document.getElementById('modeDay').addEventListener('change', () => {
  currentMode='day'; fromInput.type='date'; toInput.type='date';
  fromInput.value=todayISO(); toInput.value=todayISO();
});
document.getElementById('modeMonth').addEventListener('change', () => {
  currentMode='month'; fromInput.type='month'; toInput.type='month';
  fromInput.value=''; toInput.value='';
});
document.getElementById('modeYear').addEventListener('change', () => {
  currentMode='year'; fromInput.type='number'; toInput.type='number';
  fromInput.placeholder='ปปปป'; toInput.placeholder='ปปปป';
  fromInput.min=2020; toInput.min=2020; fromInput.max=2099; toInput.max=2099;
  fromInput.value=''; toInput.value='';
});

// default today
fromInput.value = todayISO();
toInput.value   = todayISO();

/* ---------- ปุ่มรีเฟรช/ล้าง ---------- */
document.getElementById('refreshBtn').addEventListener('click', () => {
  let s=fromInput.value, e=toInput.value;
  if(currentMode==='month' && s && e){ const [y1,m1]=s.split('-'); const [y2,m2]=e.split('-');
    s=`${y1}-${pad2(m1)}-01`; e=`${y2}-${pad2(m2)}-${pad2(lastDayOfMonth(+y2,+m2))}`;}
  else if(currentMode==='year' && s && e){ s=`${s}-01-01`; e=`${e}-12-31`; }
  fetchSalesData(s||todayISO(), e||todayISO());
});
document.getElementById('btnClear').addEventListener('click', () => {
  currentMode='day';
  document.getElementById('modeDay').checked = true;
  fromInput.type='date'; toInput.type='date';
  fromInput.value=todayISO(); toInput.value=todayISO();
  fetchSalesData(fromInput.value, toInput.value);
});

/* ---------- Run report ---------- */
document.getElementById('runReport').addEventListener('click', () => {
  let start='', end='';
  if(currentMode==='day'){ start=fromInput.value; end=toInput.value; }
  else if(currentMode==='month'){
    if(fromInput.value){const [y,m]=fromInput.value.split('-').map(Number); start=`${y}-${pad2(m)}-01`; }
    if(toInput.value){const [y,m]=toInput.value.split('-').map(Number); const ld=lastDayOfMonth(y,m); end=`${y}-${pad2(m)}-${pad2(ld)}`;}
  }else{
    if(fromInput.value) start=`${fromInput.value}-01-01`;
    if(toInput.value)   end  =`${toInput.value}-12-31`;
  }
  if(!start||!end){renderError('กรุณาเลือกช่วงวันที่ให้ครบ');return;}
  fetchSalesData(start,end);
});

/* ---------- Fetch & Render ---------- */
let lastData = null;
let lastRange = {start: todayISO(), end: todayISO()};
async function fetchSalesData(start, end) {
  lastRange = {start, end};
  const url = `get_sales_data.php?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`;
  let res, text;
  try { res = await fetch(url, {cache:'no-store'}); } catch(e){ renderError('เครือข่ายล้มเหลว/พาธผิด'); return; }
  try { text = await res.text(); } catch(e){ renderError('อ่านผลลัพธ์จากเซิร์ฟเวอร์ไม่สำเร็จ'); return; }
  if(!res.ok){ renderError(`HTTP ${res.status} - ${text.slice(0,300)}`); return; }
  let data; try { data = JSON.parse(text); } catch(e){ renderError('ผลลัพธ์ไม่ใช่ JSON: '+text.slice(0,300)); return; }
  if(data.error){ renderError(typeof data.error==='string'?data.error:JSON.stringify(data.error)); return; }
  lastData = data;

  // Summary
  document.getElementById('totalRevenue').textContent = fmtBaht(data.total ?? 0) + ' บาท';
  document.getElementById('bookingCount').textContent = (data.count ?? 0) + ' รายการ';

  // Top desks
  const topList = document.getElementById('topDesks'); topList.innerHTML='';
  (data.top||[]).forEach(d=>{
    const li=document.createElement('li');
    li.textContent=`${d.desk_name} (${d.total_bookings} ครั้ง)`;
    topList.appendChild(li);
  });

  // ===== กราฟ (แสดงยอดบนแท่งด้วย DataLabels) =====
  const labels = (data.chart?.labels)||[];
  const values = (data.chart?.values||[]).map(Number);

  const maxVal = values.length ? Math.max(...values) : 0;
  const suggestedMax = maxVal > 0 ? Math.ceil(maxVal * 1.15) : 10;

  const ctx = document.getElementById('salesChart').getContext('2d');
  if(window.salesChart){ try{window.salesChart.destroy();}catch{} }

  Chart.register(ChartDataLabels);

  window.salesChart = new Chart(ctx, {
    type:'bar',
    data:{
      labels,
      datasets:[{
        label:'ยอดขาย (บาท)',
        data:values,
        backgroundColor: labels.map((_,i)=>{
          const cs=['#3b82f6','#3b82f6','#3b82f6','#3b82f6','#3b82f6'];
          return cs[i%cs.length];
        }),
        borderWidth:0
      }]
    },
    options:{
      responsive:true,
      maintainAspectRatio:false,
      scales:{
        y:{
          beginAtZero:true,
          suggestedMax:suggestedMax,
          ticks:{ callback:(v)=>Number(v).toLocaleString('th-TH') }
        },
        x:{
          ticks:{ autoSkip:true, maxRotation:0, minRotation:0 }
        }
      },
      plugins:{
        legend:{ display:false },
        tooltip:{
          callbacks:{
            label:(ctx)=>'ยอดขาย: '+Number(ctx.parsed.y).toLocaleString('th-TH',{minimumFractionDigits:2})+' บาท'
          }
        },
        datalabels:{
          anchor:'end',
          align:'top',
          offset:2,
          formatter:(value)=>Number(value).toLocaleString('th-TH',{minimumFractionDigits:0}),
          color:'#111',
          font:{ weight:'600' },
          clip:false
        }
      }
    }
  });
}

// โหลดข้อมูลเริ่มต้น (วันนี้)
fetchSalesData(todayISO(), todayISO());

/* ---------- Excel ---------- */
document.getElementById('btnExcel').addEventListener('click', () => {
  if(!lastData){renderError('กรุณากดดูรายงานก่อน');return;}
  const wb = XLSX.utils.book_new();

  const summary = [
    ['ช่วงวันที่', `${lastRange.start} ถึง ${lastRange.end}`],
    ['ยอดขายรวม (บาท)', Number(lastData.total||0)],
    ['จำนวนการจอง (ครั้ง)', Number(lastData.count||0)]
  ];
  XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(summary), 'Summary');

  const top = [['โต๊ะ','จำนวนการจอง']];
  (lastData.top||[]).forEach(t=> top.push([t.desk_name, t.total_bookings]));
  XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(top), 'Top Desks');

  const chart = [['วันที่','ยอดขาย (บาท)']];
  (lastData.chart?.labels||[]).forEach((d,i)=> chart.push([d, Number((lastData.chart.values||[])[i]||0)]));
  XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(chart), 'Daily Revenue');

  const stamp = new Date().toISOString().slice(0,19).replace('T','_').replace(/:/g,'-');
  XLSX.writeFile(wb, `รายงานสรุปยอดขาย_${stamp}.xlsx`);
});

/* ---------- PDF ---------- */
document.getElementById('btnPDF').addEventListener('click', async () => {
  if(!lastData){renderError('กรุณากดดูรายงานก่อน');return;}
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF({ unit:'pt', format:'a4' });

  try { doc.setFont('THSarabunNew', 'normal'); } catch(e) {}

  const pageW = doc.internal.pageSize.getWidth();
  const pageH = doc.internal.pageSize.getHeight();
  const marginX = 40;
  let y = 52;

  doc.setFontSize(20);
  doc.text('รายงานสรุปยอดขาย', marginX, y); y += 24;
  doc.setFontSize(12);
  doc.text(`ช่วงวันที่: ${lastRange.start} ถึง ${lastRange.end}`, marginX, y); y += 18;
  doc.text(`ออกรายงาน: ${new Date().toLocaleString('th-TH',{hour12:false})}`, marginX, y); y += 12;

  doc.autoTable({
    startY: y + 6,
    head: [['หัวข้อ','ค่า']],
    body: [
      ['ยอดขายรวม (บาท)', (Number(lastData.total||0)).toLocaleString('th-TH',{minimumFractionDigits:2})],
      ['จำนวนการจอง (ครั้ง)', String(lastData.count||0)]
    ],
    styles: { font: 'THSarabunNew', fontSize: 13, cellPadding: 6, overflow:'linebreak' },
    headStyles: { fillColor: [0,102,204], textColor: 255, halign:'center' },
    theme: 'grid',
    margin: { left: marginX, right: marginX }
  });
  y = doc.lastAutoTable.finalY + 18;

  doc.setFontSize(14); doc.text('โต๊ะยอดนิยม (Top 3)', marginX, y); y += 10;
  doc.autoTable({
    startY: y,
    head: [['โต๊ะ','จำนวนการจอง']],
    body: (lastData.top||[]).map(t=>[t.desk_name, t.total_bookings]),
    styles: { font:'THSarabunNew', fontSize: 13, cellPadding: 6 },
    headStyles: { fillColor: [0,102,204], textColor:255 },
    theme: 'grid',
    margin: { left: marginX, right: marginX }
  });
  y = doc.lastAutoTable.finalY + 18;

  const canvas = document.getElementById('salesChart');
  const imgData = canvas.toDataURL('image/png', 1.0);
  const imgW = pageW - marginX*2;
  const imgH = imgW * (canvas.height / canvas.width);
  if (y + imgH > pageH - 40) { doc.addPage(); y = 40; }
  doc.setFontSize(14); doc.text('กราฟยอดขายรายวัน', marginX, y); y += 8;
  doc.addImage(imgData, 'PNG', marginX, y, imgW, imgH);

  doc.save(`รายงานสรุปยอดขาย_${new Date().toISOString().slice(0,10)}.pdf`);
});
</script>
</body>
</html>
