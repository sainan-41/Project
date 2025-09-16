<?php
// sales_summary.php — Professional Sales Summary (TH)
// Stack: PHP + MySQLi, Bootstrap 5, Chart.js
// Data rules: payments.payment_verified='approved', use payments.amount, payments.payment_time (DATETIME)

session_start();
require 'db_connect.php';

// หากเป็น AJAX และสิทธิ์ไม่ใช่ admin → ตอบ JSON (กัน redirect login)
if (isset($_GET['action']) && (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin')) {
  header('Content-Type: application/json; charset=utf-8', true, 401);
  echo json_encode(['ok'=>false,'error'=>'unauthorized'], JSON_UNESCAPED_UNICODE);
  exit();
}

// ป้องกันหน้าเฉพาะแอดมิน (โหลด HTML)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  header('Location: login.php'); exit();
}

// TZ ไทย
date_default_timezone_set('Asia/Bangkok');
// หากโฮสต์ไม่อนุญาต ให้คอมเมนต์บรรทัดด้านล่าง
@$conn->query("SET time_zone = '+07:00'");

// ---------- Utility ----------
function moneyExpr($alias='p'){ return "$alias.amount"; } // ในฐานข้อมูลของคุณใช้คอลัมน์ amount แน่นอน

// ---------- API (AJAX) ----------
if (isset($_GET['action'])) {
  header('Content-Type: application/json; charset=utf-8');
  $action = $_GET['action'] ?? 'summary';

  $mode  = $_GET['mode']  ?? 'day'; // day|month|year
  $start = $_GET['start'] ?? '';
  $end   = $_GET['end']   ?? '';

  function normalizeRange($mode,$start,$end){
    if ($mode==='day'){
      $s = DateTime::createFromFormat('Y-m-d', $start) ?: new DateTime('today');
      $e = DateTime::createFromFormat('Y-m-d', $end)   ?: new DateTime('today');
      if ($e < $s) [$s,$e]=[$e,$s];
      return [$s->format('Y-m-d 00:00:00'), $e->format('Y-m-d 23:59:59')];
    }
    if ($mode==='month'){
      $s = DateTime::createFromFormat('Y-m', $start) ?: new DateTime('first day of this month');
      $e = DateTime::createFromFormat('Y-m', $end)   ?: new DateTime('last day of this month');
      if ($e < $s) [$s,$e]=[$e,$s];
      $s->modify('first day of this month 00:00:00');
      $e->modify('last day of this month 23:59:59');
      return [$s->format('Y-m-d H:i:s'), $e->format('Y-m-d H:i:s')];
    }
    $ys = is_numeric($start)? (int)$start : (int)date('Y');
    $ye = is_numeric($end)?   (int)$end   : (int)date('Y');
    if ($ye < $ys) [$ys,$ye]=[$ye,$ys];
    return ["$ys-01-01 00:00:00","$ye-12-31 23:59:59"];
  }

  [$fromDT,$toDT] = normalizeRange($mode,$start,$end);
  $money = moneyExpr('p');

  if ($action==='summary'){
    // KPI รวม + โต๊ะยอดนิยม
    $sqlSum = "
      SELECT SUM($money) AS total_revenue,
             COUNT(DISTINCT p.payment_id) AS bill_count
      FROM payments p
      JOIN bookings b ON p.booking_id=b.booking_id
      WHERE p.payment_verified='approved'
        AND p.payment_time BETWEEN ? AND ?
    ";
    $stmt=$conn->prepare($sqlSum);
    $stmt->bind_param('ss',$fromDT,$toDT);
    $stmt->execute();
    $sum = $stmt->get_result()->fetch_assoc() ?? ['total_revenue'=>0,'bill_count'=>0];
    $stmt->close();

    $avg = ($sum['bill_count']??0) > 0 ? ((float)$sum['total_revenue'] / (int)$sum['bill_count']) : 0;

    $sqlTop = "
      SELECT d.desk_id, d.desk_name, SUM($money) AS rev
      FROM payments p
      JOIN bookings b ON p.booking_id=b.booking_id
      JOIN desks d    ON b.desk_id=d.desk_id
      WHERE p.payment_verified='approved'
        AND p.payment_time BETWEEN ? AND ?
      GROUP BY d.desk_id, d.desk_name
      ORDER BY rev DESC
      LIMIT 1
    ";
    $stmt=$conn->prepare($sqlTop);
    $stmt->bind_param('ss',$fromDT,$toDT);
    $stmt->execute();
    $top = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    echo json_encode([
      'ok'=>true,
      'total_revenue'=>(float)($sum['total_revenue']??0),
      'bill_count'=>(int)($sum['bill_count']??0),
      'avg_per_bill'=>(float)$avg,
      'top_desk'=>$top['desk_name'] ?? '-',
      'range'=>[$fromDT,$toDT],
      'mode'=>$mode
    ], JSON_UNESCAPED_UNICODE);
    exit();
  }

  if ($action==='chart'){
    if ($mode==='day'){
      $sql="
        SELECT DATE(p.payment_time) AS grp, SUM($money) AS rev
        FROM payments p
        WHERE p.payment_verified='approved'
          AND p.payment_time BETWEEN ? AND ?
        GROUP BY DATE(p.payment_time)
        ORDER BY DATE(p.payment_time)
      ";
    } elseif ($mode==='month'){
      $sql="
        SELECT DATE_FORMAT(p.payment_time,'%Y-%m') AS grp, SUM($money) AS rev
        FROM payments p
        WHERE p.payment_verified='approved'
          AND p.payment_time BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(p.payment_time,'%Y-%m')
        ORDER BY DATE_FORMAT(p.payment_time,'%Y-%m')
      ";
    } else {
      $sql="
        SELECT DATE_FORMAT(p.payment_time,'%Y') AS grp, SUM($money) AS rev
        FROM payments p
        WHERE p.payment_verified='approved'
          AND p.payment_time BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(p.payment_time,'%Y')
        ORDER BY DATE_FORMAT(p.payment_time,'%Y')
      ";
    }
    $stmt=$conn->prepare($sql);
    $stmt->bind_param('ss',$fromDT,$toDT);
    $stmt->execute();
    $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['ok'=>true,'labels'=>array_column($rows,'grp'),'values'=>array_map('floatval',array_column($rows,'rev'))], JSON_UNESCAPED_UNICODE);
    exit();
  }

  if ($action==='top_desks'){
    $sql="
      SELECT d.desk_id, d.desk_name, SUM($money) AS rev
      FROM payments p
      JOIN bookings b ON p.booking_id=b.booking_id
      JOIN desks d    ON b.desk_id=d.desk_id
      WHERE p.payment_verified='approved'
        AND p.payment_time BETWEEN ? AND ?
      GROUP BY d.desk_id, d.desk_name
      ORDER BY rev DESC
      LIMIT 5
    ";
    $stmt=$conn->prepare($sql);
    $stmt->bind_param('ss',$fromDT,$toDT);
    $stmt->execute();
    $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['ok'=>true,'rows'=>$rows], JSON_UNESCAPED_UNICODE); exit();
  }

  if ($action==='table'){
    $sql="
      SELECT p.payment_id, p.payment_time, $money AS paid,
             b.booking_id, b.booking_start_time, b.booking_end_time,
             d.desk_name
      FROM payments p
      JOIN bookings b ON p.booking_id=b.booking_id
      LEFT JOIN desks d ON b.desk_id=d.desk_id
      WHERE p.payment_verified='approved'
        AND p.payment_time BETWEEN ? AND ?
      ORDER BY p.payment_time DESC
      LIMIT 200
    ";
    $stmt=$conn->prepare($sql);
    $stmt->bind_param('ss',$fromDT,$toDT);
    $stmt->execute();
    $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['ok'=>true,'rows'=>$rows], JSON_UNESCAPED_UNICODE); exit();
  }

  echo json_encode(['ok'=>false,'error'=>'Unknown action'], JSON_UNESCAPED_UNICODE); exit();
}

// ---------- VIEW ----------
$admin_id = (int)$_SESSION['user_id'];
$stmt=$conn->prepare("SELECT fullname, profile_pic FROM users WHERE user_id=? LIMIT 1");
$stmt->bind_param('i',$admin_id);
$stmt->execute();
$admin=$stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>สรุปยอดขาย</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

  <style>
    body{ background:#f6f7fb; }
    .page-wrap{ min-height:100vh; padding:20px; }
    .page-header{ display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }
    .page-title{ font-weight:700; font-size:1.4rem; }
    .control-strip{ background:#fff;border-radius:12px;padding:12px 16px;box-shadow:0 2px 6px rgba(0,0,0,.05); }
    .mode-btns .btn{ min-width:90px; }
    .kpi{ background:#fff;border-radius:14px;padding:18px;box-shadow:0 6px 16px rgba(17,24,39,.06);border:1px solid #eef2f7;height:100%; }
    .kpi .label{ color:#6b7280;font-size:.95rem }
    .kpi .value{ font-size:1.6rem;font-weight:800;letter-spacing:.3px }
    .kpi .sub{ color:#94a3b8;font-size:.9rem }
    .card-block{ background:#fff;border-radius:14px;padding:18px;box-shadow:0 6px 16px rgba(17,24,39,.06);border:1px solid #eef2f7; }
    table thead th{ white-space:nowrap }
    .muted{ color:#6b7280 }
  </style>
</head>
<body>

<?php /* include 'navbar_admin1.php'; */ ?>
<?php /* include 'sidebar_admin.php'; */ ?>

<div class="page-wrap container-fluid">
  <div class="page-header">
    <div class="page-title">สรุปยอดขาย</div>
    <div class="d-flex align-items-center gap-3">
      <div class="text-end">
        <div class="fw-semibold"><?= htmlspecialchars($admin['fullname'] ?? 'ผู้ดูแลระบบ') ?></div>
        <div class="muted" style="font-size:.9rem">Admin</div>
      </div>
      <img src="<?= !empty($admin['profile_pic']) ? 'uploads/'.htmlspecialchars($admin['profile_pic']) : 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/1f464.svg' ?>"
           alt="profile" style="width:44px;height:44px;border-radius:50%;object-fit:cover;border:1px solid #e5e7eb;">
    </div>
  </div>

  <!-- Control -->
  <div class="control-strip mb-3">
    <div class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label">โหมดสรุป</label>
        <div class="btn-group mode-btns" role="group">
          <button class="btn btn-outline-primary active" data-mode="day">รายวัน</button>
          <button class="btn btn-outline-primary" data-mode="month">รายเดือน</button>
          <button class="btn btn-outline-primary" data-mode="year">รายปี</button>
        </div>
      </div>
      <div class="col-md-8">
        <div class="row g-2" id="picker-day">
          <div class="col">
            <label class="form-label">วันที่เริ่ม</label>
            <input type="date" id="day-start" class="form-control">
          </div>
          <div class="col">
            <label class="form-label">วันที่สิ้นสุด</label>
            <input type="date" id="day-end" class="form-control">
          </div>
        </div>
        <div class="row g-2 d-none" id="picker-month">
          <div class="col">
            <label class="form-label">เดือนเริ่ม</label>
            <input type="month" id="month-start" class="form-control">
          </div>
          <div class="col">
            <label class="form-label">เดือนสิ้นสุด</label>
            <input type="month" id="month-end" class="form-control">
          </div>
        </div>
        <div class="row g-2 d-none" id="picker-year">
          <div class="col">
            <label class="form-label">ปีเริ่ม</label>
            <input type="number" id="year-start" class="form-control" min="2000" max="2100">
          </div>
          <div class="col">
            <label class="form-label">ปีสิ้นสุด</label>
            <input type="number" id="year-end" class="form-control" min="2000" max="2100">
          </div>
        </div>
      </div>
    </div>
    <div class="mt-2 text-end">
      <button id="btn-apply" class="btn btn-primary"><i class="bi bi-funnel"></i> ใช้ตัวกรอง</button>
    </div>
  </div>

  <!-- KPIs -->
  <div class="row g-3 mb-3">
    <div class="col-12 col-md-3">
      <div class="kpi">
        <div class="label">รายได้รวม</div>
        <div class="value" id="kpi-total">฿0</div>
        <div class="sub" id="kpi-range">—</div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="kpi">
        <div class="label">จำนวนบิล</div>
        <div class="value" id="kpi-bills">0</div>
        <div class="sub">อนุมัติแล้ว</div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="kpi">
        <div class="label">รายได้เฉลี่ย/บิล</div>
        <div class="value" id="kpi-avg">฿0</div>
        <div class="sub">เฉพาะบิลที่อนุมัติ</div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="kpi">
        <div class="label">โต๊ะยอดนิยม</div>
        <div class="value" id="kpi-topdesk">-</div>
        <div class="sub">ตามรายได้รวม</div>
      </div>
    </div>
  </div>

  <!-- Chart + Top Desks -->
  <div class="row g-3 mb-3">
    <div class="col-12 col-lg-8">
      <div class="card-block">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="fw-bold">กราฟรายได้</div>
          <div class="muted" id="chart-caption">—</div>
        </div>
        <canvas id="revChart" height="130"></canvas>
      </div>
    </div>
    <div class="col-12 col-lg-4">
      <div class="card-block">
        <div class="fw-bold mb-2">โต๊ะทำรายได้สูงสุด 5 อันดับ</div>
        <ol id="top-desks" class="mb-0" style="padding-left:18px;">
          <li class="muted">—</li>
        </ol>
      </div>
    </div>
  </div>

  <!-- Table -->
  <div class="card-block">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="fw-bold">รายการชำระเงิน (200 รายการล่าสุดในช่วงที่เลือก)</div>
      <div class="muted" id="table-caption">—</div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle">
        <thead class="table-light">
          <tr>
            <th>เวลา</th>
            <th>เลขบิล</th>
            <th>โต๊ะ</th>
            <th class="text-end">ยอดเงิน</th>
            <th>เริ่มใช้งาน</th>
            <th>สิ้นสุด</th>
          </tr>
        </thead>
        <tbody id="tbl-body">
          <tr><td colspan="6" class="text-center text-muted py-4">—</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  let mode='day';
  const today=new Date();
  const y=today.getFullYear(), m=String(today.getMonth()+1).padStart(2,'0'), d=String(today.getDate()).padStart(2,'0');

  // initial
  dayStart().value=`${y}-${m}-01`; dayEnd().value=`${y}-${m}-${d}`;
  monthStart().value=`${y}-${m}`;  monthEnd().value=`${y}-${m}`;
  yearStart().value=y;             yearEnd().value=y;

  document.querySelectorAll('.mode-btns .btn').forEach(btn=>{
    btn.addEventListener('click',e=>{
      document.querySelectorAll('.mode-btns .btn').forEach(b=>b.classList.remove('active'));
      e.currentTarget.classList.add('active');
      mode=e.currentTarget.dataset.mode;
      togglePickers();
    });
  });

  function dayStart(){return document.getElementById('day-start')}
  function dayEnd(){return document.getElementById('day-end')}
  function monthStart(){return document.getElementById('month-start')}
  function monthEnd(){return document.getElementById('month-end')}
  function yearStart(){return document.getElementById('year-start')}
  function yearEnd(){return document.getElementById('year-end')}

  function togglePickers(){
    document.getElementById('picker-day').classList.toggle('d-none', mode!=='day');
    document.getElementById('picker-month').classList.toggle('d-none', mode!=='month');
    document.getElementById('picker-year').classList.toggle('d-none', mode!=='year');
  }
  function getRange(){
    if(mode==='day')   return {start:dayStart().value||`${y}-${m}-01`, end:dayEnd().value||`${y}-${m}-${d}`};
    if(mode==='month') return {start:monthStart().value||`${y}-${m}`,    end:monthEnd().value||`${y}-${m}`};
    return {start:yearStart().value||`${y}`, end:yearEnd().value||`${y}`};
  }
  function fmtMoney(n){return (Number(n)||0).toLocaleString('th-TH',{style:'currency',currency:'THB',maximumFractionDigits:0})}
  function fmtRange(mode,s,e){return `${s} ถึง ${e}`;}

  async function fetchJSON(params){
    const url=new URL(window.location.href);
    Object.entries(params).forEach(([k,v])=>url.searchParams.set(k,v));
    const res=await fetch(url.toString(),{
      headers:{'X-Requested-With':'fetch'},
      credentials:'same-origin',
      cache:'no-store'
    });
    const ct=res.headers.get('content-type')||'';
    if(!ct.includes('application/json')){
      const text=await res.text(); console.error('Non-JSON:', text.slice(0,300));
      throw new Error('เซสชันหมดอายุ หรือคำตอบไม่ใช่ JSON');
    }
    const data=await res.json();
    if(!res.ok || data.ok===false){ console.error('API error:', data); throw new Error(data.error||'API error'); }
    return data;
  }

  async function applyFilters(){
    try{
      const {start,end}=getRange();

      const sum=await fetchJSON({action:'summary',mode,start,end});
      document.getElementById('kpi-total').textContent=fmtMoney(sum.total_revenue||0);
      document.getElementById('kpi-bills').textContent=(sum.bill_count||0).toLocaleString('th-TH');
      document.getElementById('kpi-avg').textContent=fmtMoney(sum.avg_per_bill||0);
      document.getElementById('kpi-topdesk').textContent=sum.top_desk||'-';
      document.getElementById('kpi-range').textContent=fmtRange(mode,start,end);

      const ch=await fetchJSON({action:'chart',mode,start,end});
      ensureChart(ch.labels||[], ch.values||[]);
      document.getElementById('chart-caption').textContent=`รวม ${fmtMoney((ch.values||[]).reduce((a,b)=>a+(b||0),0))}`;

      const td=await fetchJSON({action:'top_desks',mode,start,end});
      const list=document.getElementById('top-desks'); list.innerHTML='';
      if(td.rows.length){
        td.rows.forEach(r=>{
          const li=document.createElement('li');
          li.textContent=`${r.desk_name||'-'} — ${fmtMoney(Number(r.rev||0))}`;
          list.appendChild(li);
        });
      }else{ list.innerHTML='<li class="muted">—</li>'; }

      const tb=await fetchJSON({action:'table',mode,start,end});
      const tbody=document.getElementById('tbl-body'); tbody.innerHTML='';
      if(tb.rows.length){
        tb.rows.forEach(r=>{
          const tr=document.createElement('tr');
          tr.innerHTML=`
            <td>${r.payment_time??'-'}</td>
            <td>#${r.payment_id??'-'}</td>
            <td>${r.desk_name??'-'}</td>
            <td class="text-end">${fmtMoney(Number(r.paid||0))}</td>
            <td>${r.booking_start_time??'-'}</td>
            <td>${r.booking_end_time??'-'}</td>
          `;
          tbody.appendChild(tr);
        });
      }else{
        tbody.innerHTML='<tr><td colspan="6" class="text-center text-muted py-4">ไม่พบข้อมูลในช่วงที่เลือก</td></tr>';
      }
      document.getElementById('table-caption').textContent=`ช่วง: ${fmtRange(mode,start,end)}`;
    }catch(err){
      console.error(err); alert(err.message||'เกิดข้อผิดพลาดในการโหลดข้อมูล');
    }
  }

  let chart;
  function ensureChart(labels,values){
    const ctx=document.getElementById('revChart');
    if(chart) chart.destroy();
    chart=new Chart(ctx,{ type:'bar', data:{ labels, datasets:[{label:'', data:values}] },
      options:{ responsive:true, scales:{y:{beginAtZero:true}}, plugins:{legend:{display:false}} } });
  }

  document.getElementById('btn-apply').addEventListener('click', applyFilters);
  // โหลดครั้งแรก
  applyFilters();
</script>
</body>
</html>
