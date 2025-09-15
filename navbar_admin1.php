<?php 
// navbar_admin1.php — Topbar + กระดิ่งแจ้งเตือน + เสียง + ช่องค้นหา (วันนี้เท่านั้น)

/* ---------- CSS/JS lib ---------- */
if (!defined('APP_BOOTSTRAP_CSS')) {
  define('APP_BOOTSTRAP_CSS', true);
  echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
  echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">';
  echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>';
}

/* ---------- Home link ---------- */
$homeHref = defined('NAV_HOME_HREF') ? NAV_HOME_HREF : 'desk_status.php';
?>
<nav class="app-topbar navbar navbar-expand-lg navbar-dark px-4">
  <a class="brand" href="<?= htmlspecialchars($homeHref) ?>">
    <i class="bi bi-house-door-fill"></i><span>หน้าหลัก</span>
  </a>

  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topbarNav">
    <span class="navbar-toggler-icon"></span>
  </button>

  <div class="collapse navbar-collapse" id="topbarNav">
    <div class="ms-auto d-flex align-items-center gap-2">
      <!-- กระดิ่ง -->
      <div class="nav-item dropdown me-1">
        <a id="notifBell" class="nav-link p-0 position-relative notif-bell" href="#" role="button"
           data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
          <i class="bi bi-bell-fill fs-5"></i>
          <span id="notifBadge" class="notif-badge" style="display:none;">0</span>
        </a>

        <div class="dropdown-menu dropdown-menu-end dropdown-menu-notif shadow" style="min-width:360px;max-width:420px;">
          <!-- ใช้ .notif-tabs แทน .nav-tabs เพื่อตัดชนกับ selector ในหน้าแผนผัง -->
          <ul class="nav notif-tabs px-3 pt-2" role="tablist" style="border-bottom:1px solid #e9ecef;" data-scope="notif-tabs">
            <li class="nav-item">
              <button class="nav-link active" id="tab-slip" data-bs-toggle="tab" data-bs-target="#pane-slip" type="button">
                การชำระเงิน
                <span id="badgeSlip" class="badge rounded-pill bg-danger ms-2" style="display:none;">0</span>
              </button>
            </li>
            <li class="nav-item">
              <button class="nav-link" id="tab-expire" data-bs-toggle="tab" data-bs-target="#pane-expire" type="button">
                หมดเวลา
                <span id="badgeExpire" class="badge rounded-pill bg-danger ms-2" style="display:none;">0</span>
              </button>
            </li>
          </ul>

          <div class="tab-content p-2">
            <!-- การชำระเงิน -->
            <div class="tab-pane fade show active" id="pane-slip">
              <div id="listSlip" class="list-group list-group-flush small"></div>
              <div class="d-flex justify-content-end align-items-center mt-2 px-2">
                <button id="btnSlipDeleteAll" class="btn btn-sm btn-outline-danger">
                  <i class="bi bi-trash3 me-1"></i>ลบการแจ้งเตือนทั้งหมด
                </button>
              </div>
            </div>

            <!-- หมดเวลา -->
            <div class="tab-pane fade" id="pane-expire">
              <div id="listExpire" class="list-group list-group-flush small"></div>
              <div class="d-flex justify-content-end align-items-center mt-2 px-2">
                <button id="btnExpireDeleteAll" class="btn btn-sm btn-outline-danger">
                  <i class="bi bi-trash3 me-1"></i>ลบการแจ้งเตือนทั้งหมด
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ช่องค้นหา -->
      <form class="my-2 my-lg-0 d-flex search-group" role="search" action="search.php" method="get" onsubmit="return !!this.q.value.trim();">
        <input class="form-control me-2 search-input" type="search" name="q" placeholder="ค้นหา..." aria-label="Search">
        <button class="btn btn-outline-light" type="submit"><i class="bi bi-search"></i></button>
      </form>

      <!-- โปรไฟล์ -->
      <div class="nav-item dropdown ms-2">
        <?php
          $adminName = isset($admin['fullname']) && $admin['fullname'] !== '' ? $admin['fullname']
                      : (isset($_SESSION['fullname']) && $_SESSION['fullname'] !== '' ? $_SESSION['fullname'] : 'แอดมิน');
          $adminPic  = isset($admin['profile_pic']) && $admin['profile_pic'] !== '' ? $admin['profile_pic'] : null;
        ?>
        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown">
          <?php if ($adminPic): ?>
            <img src="<?= htmlspecialchars($adminPic) ?>" alt="<?= htmlspecialchars($adminName) ?>" class="rounded-circle" width="28" height="28" style="object-fit:cover;">
          <?php else: ?>
            <i class="bi bi-person-circle fs-5"></i>
          <?php endif; ?>
          <span><?= htmlspecialchars($adminName) ?></span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end shadow">
          <li><a class="dropdown-item" href="admin_profile.php"><i class="bi bi-gear me-2"></i>โปรไฟล์</a></li>
          <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>ออกจากระบบ</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>

<!-- เสียงแจ้งเตือน -->
<audio id="notifSound" preload="auto">
  <source src="/coworking/assets/elegant.mp3" type="audio/mpeg">
  <source src="/coworking/assets/elegant.ogg" type="audio/ogg">
  Your browser does not support the audio element.
</audio>

<!-- Toast container -->
<div id="notifToastWrap" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:1080;"></div>

<style>
  .notif-badge{
    position:absolute; top:-6px; right:-6px; min-width:20px; height:20px;
    background:#dc3545; color:#fff; border-radius:10px; font-size:12px;
    display:flex; align-items:center; justify-content:center; padding:0 6px;
  }
  .dropdown-menu-notif .notif-item.unread{ background:#fff7f8; }
  .dropdown-menu-notif .notif-time{ font-size:12px; opacity:0.8; }

  /* --- เลียนแบบ nav-tabs ให้ .notif-tabs (กันชน selector ของหน้าแผนผัง) --- */
  .notif-tabs {
    --bs-nav-link-padding-x: 0.75rem;
    --bs-nav-link-padding-y: 0.5rem;
    --bs-nav-link-color: #0d6efd;
    --bs-nav-link-hover-color: #0a58ca;
    --bs-nav-tabs-link-active-color: #495057;
    --bs-nav-tabs-link-active-bg: #fff;
    --bs-nav-tabs-border-color: #dee2e6;
    --bs-nav-tabs-link-hover-border-color: #e9ecef #e9ecef #dee2e6;
    --bs-nav-tabs-link-active-border-color: #dee2e6 #dee2e6 #fff;
    display: flex;
    flex-wrap: wrap;
    margin-bottom: 0;
    border-bottom: 1px solid var(--bs-nav-tabs-border-color);
  }
  .notif-tabs .nav-link {
    margin-bottom: -1px;
    background: none;
    border: 1px solid transparent;
    border-top-left-radius: 0.375rem;
    border-top-right-radius: 0.375rem;
    color: var(--bs-nav-link-color);
    padding: var(--bs-nav-link-padding-y) var(--bs-nav-link-padding-x);
  }
  .notif-tabs .nav-link:hover,
  .notif-tabs .nav-link:focus {
    border-color: var(--bs-nav-tabs-link-hover-border-color);
    isolation: isolate;
    color: var(--bs-nav-link-hover-color);
  }
  .notif-tabs .nav-link.active,
  .notif-tabs .nav-item.show .nav-link {
    color: var(--bs-nav-tabs-link-active-color);
    background-color: var(--bs-nav-tabs-link-active-bg);
    border-color: var(--bs-nav-tabs-link-active-border-color);
  }
</style>

<script>
/* =========================================================
   IIFE: แยก scope ไม่ให้ชนกับหน้า desk_status.php
   ========================================================= */
(function(){
  'use strict';

  /* ----- Config / Endpoints ----- */
  const API_BASE = (function(){
    const fromDefine = <?php echo json_encode(defined('NAV_API_BASE') ? rtrim(NAV_API_BASE,'/').'/' : null); ?>;
    return fromDefine || location.pathname.replace(/[^\/]+$/, '');
  })();
  const ENDPOINT = {
    serverTime:  API_BASE + 'server_time_ms.php',
    counts:      API_BASE + 'notify_counts1.php',
    list:        API_BASE + 'notify_list.php',
    markViewed:  API_BASE + 'notify_mark_viewed.php',
    expMark:     API_BASE + 'notify_expire_mark_viewed.php',
    delAll:      API_BASE + 'notify_delete_all.php'
  };

  const POLL_MS = 3000;
  const WARN_MIN = 10;
  let serverOffsetMs = 0;
  let lastCounts = { unread_slips: 0, about_to_expire: 0 };
  let initialized = false;

  /* ----- Local cache ----- */
  const CACHE_KEYS = { slips: 'notifCache_slips', expire: 'notifCache_expire' };
  let cacheSlips = [], cacheExpire = [];
  const seenSlipIds = new Set(), seenExpireIds = new Set();

  function loadCache(){
    try{cacheSlips=JSON.parse(localStorage.getItem(CACHE_KEYS.slips)||'[]')||[]}catch(_){cacheSlips=[]}
    try{cacheExpire=JSON.parse(localStorage.getItem(CACHE_KEYS.expire)||'[]')||[]}catch(_){cacheExpire=[]}
  }
  function saveCache(){
    try{localStorage.setItem(CACHE_KEYS.slips, JSON.stringify(cacheSlips))}catch(_){}
    try{localStorage.setItem(CACHE_KEYS.expire, JSON.stringify(cacheExpire))}catch(_){}
  }

  /* ----- Time helpers ----- */
  function nowMs(){ return Date.now() + serverOffsetMs; }
  function startOfTodayMs(){ const d=new Date(nowMs()); d.setHours(0,0,0,0); return d.getTime(); }

  /* ----- Utils ----- */
  function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }
  const parseISOLocal = s => Date.parse(String(s||'').replace(' ', 'T'));
  function parseDateLoose(s){ let t=parseISOLocal(s); if(!Number.isNaN(t))return t; t=Date.parse(s); if(!Number.isNaN(t))return t; return NaN; }
  function parseEndMs(it){ if(Number.isFinite(it.end_ms))return it.end_ms; const t=parseDateLoose(it.end_text); return Number.isNaN(t)?null:t; }
  function parseCreatedMs(it){ if(Number.isFinite(it.created_ms))return it.created_ms; const t=parseDateLoose(it.time_text); return Number.isNaN(t)?null:t; }

  /* ----- Sound unlock ----- */
  let soundArmed=false;
  function armSound(){ if(soundArmed)return; const snd=document.getElementById('notifSound'); if(!snd)return;
    try{ const p=snd.play(); if(p?.then){ p.then(()=>{snd.pause(); snd.currentTime=0; soundArmed=true;}).catch(()=>{});} else {soundArmed=true;} }catch(_){}
  }
  ['click','keydown','pointerdown','touchstart'].forEach(ev=>document.addEventListener(ev,armSound,{once:true,capture:true}));
  document.addEventListener('DOMContentLoaded', ()=>{ document.getElementById('notifBell')?.addEventListener('click', armSound, {capture:true}); });
  function playDing(){ const snd=document.getElementById('notifSound'); if(!snd)return; try{ snd.currentTime=0; snd.play()?.catch(()=>{});}catch(_){} }

  /* ----- Badges ----- */
  function fmt9(n){ return n>9?'9+':String(n); }
  function setNotifBadge(n){ const b=document.getElementById('notifBadge'); const bell=document.getElementById('notifBell'); if(!b)return;
    if(n>0){ b.textContent=fmt9(n); b.style.display='flex'; bell?.setAttribute('aria-label',`การแจ้งเตือน ${b.textContent}`);} else { b.style.display='none'; bell?.setAttribute('aria-label','ไม่มีการแจ้งเตือน'); } }
  function setTabBadge(id,n){ const el=document.getElementById(id); if(!el)return; if(n>0){ el.textContent=fmt9(n); el.style.display='inline-flex'; } else el.style.display='none'; }

  /* ----- Fetch helper ----- */
  async function safeFetch(url, opt) {
    try {
      const res = await fetch(
        url + (url.includes('?') ? '&' : '?') + 'ts=' + Date.now(),
        { cache:'no-store', credentials:'include', ...opt }
      );
      const text = await res.text();
      try { return JSON.parse(text); }
      catch(e){ console.error('JSON parse error from', url, text); return null; }
    } catch(e){ console.error('Fetch error', url, e); return null; }
  }
  async function syncServerTime(){ const j=await safeFetch(ENDPOINT.serverTime); if(j && typeof j.time_ms==='number') serverOffsetMs=j.time_ms-Date.now(); }

  /* ----- Toast ----- */
  function showToast(title, body, href, onRead){
    const wrap=document.getElementById('notifToastWrap'); if(!wrap)return;
    const el=document.createElement('div'); el.className='toast align-items-center text-bg-dark border-0';
    el.setAttribute('role','alert'); el.dataset.bsAutohide='true'; el.dataset.bsDelay='8000';
    el.innerHTML=`<div class="d-flex"><div class="toast-body"><strong class="me-2">${esc(title)}</strong>${body?esc(body):''}</div>
    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>`;
    el.addEventListener('click',async(e)=>{ if(e.target.closest('button'))return; try{await onRead?.();}catch(_){} if(href) window.location.href=href; });
    wrap.appendChild(el); new bootstrap.Toast(el).show();
  }

  /* ----- Cache ops ----- */
  function itemKey(it,type){ return it.id||it.key||(type+':'+(it.desk_id||it.desk_name||it.title||'')+':'+(it.end_ms||it.created_ms||it.time_text||'')); }
  function upsertCache(list,cache,type){ const m=new Map(cache.map(x=>[itemKey(x,type),x])); list.forEach(it=>{ const k=itemKey(it,type); m.set(k,{...(m.get(k)||{}),...it}); }); return [...m.values()]; }

  /* ----- Normalize list API ----- */
  function normalizeNotifLists(raw){
    const out={slips:[],expiring:[]};
    if(Array.isArray(raw)){
      raw.forEach(item=>{
        const t=(item.notif_type||'').toLowerCase();
        if(t==='payment_today'){
          out.slips.push({ id:item.ref_id||item.payment_id||'', title:'การชำระเงินใหม่', message:item.message||'', created_ms:parseISOLocal(item.ref_time)||undefined, time_text:item.ref_time||'', viewed:item.viewed?1:0 });
        }else if(t==='booking_expiring_10m'){
          out.expiring.push({ id:item.ref_id||item.booking_id||'', booking_id:item.ref_id||item.booking_id||'', desk_id:item.desk_id||'', desk_name:item.desk_name||'', end_ms:parseISOLocal(item.ref_time)||undefined, end_text:item.ref_time||'', viewed:item.viewed?1:0 });
        }
      });
    }else if(raw && typeof raw==='object'){
      const slips=Array.isArray(raw.slips)?raw.slips:[], exp=Array.isArray(raw.expiring)?raw.expiring:[];
      out.slips=slips.map(it=>({ id:it.id||it.ref_id||it.payment_id||'', title:it.title||'การชำระเงินใหม่', message:it.message||'', created_ms:typeof it.created_ms==='number'?it.created_ms:parseISOLocal(it.ref_time||it.time_text||'')||undefined, time_text:it.time_text||it.ref_time||'', viewed:it.viewed?1:0 }));
      out.expiring=exp.map(it=>({ id:it.id||it.ref_id||it.booking_id||'', booking_id:it.booking_id||it.ref_id||'', desk_id:it.desk_id||'', desk_name:it.desk_name||'', end_ms:typeof it.end_ms==='number'?it.end_ms:parseISOLocal(it.ref_time||it.end_text||'')||undefined, end_text:it.end_text||it.ref_time||'', viewed:it.viewed?1:0 }));
    }
    return out;
  }

  /* ----- Load counts + lists ----- */
  async function loadCountsAndLists(){
    const counts=await safeFetch(`${ENDPOINT.counts}?warn_min=${encodeURIComponent(WARN_MIN)}`); if(!counts)return;
    const unread=counts.unread_slips||0, expSoon=counts.about_to_expire||0;
    setNotifBadge(unread+expSoon); setTabBadge('badgeSlip',unread); setTabBadge('badgeExpire',expSoon); lastCounts={unread_slips:unread,about_to_expire:expSoon};

    const listsRaw=await safeFetch(`${ENDPOINT.list}?warn_min=${encodeURIComponent(WARN_MIN)}`); if(!listsRaw)return;
    const {slips:slipsSrv0, expiring:expSrv0}=normalizeNotifLists(listsRaw);
    let slipsSrv=Array.isArray(slipsSrv0)?slipsSrv0:[], expSrv=Array.isArray(expSrv0)?expSrv0:[];

    // “วันนี้เท่านั้น”
    let slipsToday=slipsSrv.filter(it=>{ const t=parseCreatedMs(it); return Number.isFinite(t)&&t>=startOfTodayMs(); });
    let expToday  =expSrv  .filter(it=>{ const t=parseEndMs(it);     return Number.isFinite(t)&&t>=startOfTodayMs(); });

    if(unread>0 && !slipsToday.length && slipsSrv.length) slipsToday=slipsSrv.slice();
    if(expSoon>0 && !expToday.length && expSrv.length)    expToday  =expSrv.slice();

    if(!initialized){
      loadCache();
      cacheSlips =(cacheSlips ||[]).filter(it=>{ const t=parseCreatedMs(it); return Number.isFinite(t)&&t>=startOfTodayMs(); });
      cacheExpire=(cacheExpire||[]).filter(it=>{ const t=parseEndMs(it);     return Number.isFinite(t)&&t>=startOfTodayMs(); });
      cacheSlips =upsertCache(slipsToday, cacheSlips,'slip');
      cacheExpire=upsertCache(expToday,   cacheExpire,'expire');
      cacheSlips.forEach(it=>seenSlipIds.add(itemKey(it,'slip'))); cacheExpire.forEach(it=>seenExpireIds.add(itemKey(it,'expire')));
      saveCache();

      const newExp=expToday.filter(it=>!it.viewed);
      if(newExp.length){ playDing(); newExp.forEach(it=>{ const id=it.id||it.booking_id||''; const href='desk_status.php#desk-'+encodeURIComponent(it.desk_id||''); const endMs=parseEndMs(it);
        showToast(`ใกล้หมดเวลา: โต๊ะ ${esc(it.desk_name||it.desk_id||'')}`, `${Number.isFinite(endMs)?`เหลือ ~ ${Math.max(0,Math.ceil((endMs-nowMs())/60000))} นาที`:''}`, href,
        async()=>{ const fd=new FormData(); if(id)fd.append('id',id); await safeFetch(ENDPOINT.expMark+`?warn_min=${encodeURIComponent(WARN_MIN)}`,{method:'POST',body:fd});
          if(lastCounts.about_to_expire>0) lastCounts.about_to_expire--; setTabBadge('badgeExpire',lastCounts.about_to_expire); setNotifBadge((lastCounts.unread_slips||0)+(lastCounts.about_to_expire||0)); setTimeout(loadCountsAndLists,200); }); }); }

      const newSlips=slipsToday.filter(it=>!it.viewed);
      if(newSlips.length){ playDing(); newSlips.forEach(it=>{ const id=it.id||''; showToast(it.title||'อัพโหลดสลิปใหม่', it.message||'', 'dashboard_admin.php',
        async()=>{ const fd=new FormData(); if(id)fd.append('id',id); await safeFetch(ENDPOINT.markViewed,{method:'POST',body:fd});
          if(lastCounts.unread_slips>0) lastCounts.unread_slips--; setTabBadge('badgeSlip',lastCounts.unread_slips); setNotifBadge((lastCounts.unread_slips||0)+(lastCounts.about_to_expire||0)); setTimeout(loadCountsAndLists,200); }); }); }
      initialized=true;
    }else{
      const newSlips=slipsToday.filter(it=>!seenSlipIds.has(itemKey(it,'slip')));
      const newExp  =expToday  .filter(it=>!seenExpireIds.has(itemKey(it,'expire')));
      newSlips.forEach(it=>seenSlipIds.add(itemKey(it,'slip'))); newExp.forEach(it=>seenExpireIds.add(itemKey(it,'expire')));
      cacheSlips =upsertCache(slipsToday, cacheSlips,'slip'); cacheExpire=upsertCache(expToday, cacheExpire,'expire'); saveCache();

      if(newSlips.length||newExp.length){ playDing();
        newSlips.forEach(it=>{ const id=it.id||''; showToast(it.title||'อัพโหลดสลิปใหม่', it.message||'', 'dashboard_admin.php',
          async()=>{ const fd=new FormData(); if(id)fd.append('id',id); await safeFetch(ENDPOINT.markViewed,{method:'POST',body:fd});
            if(lastCounts.unread_slips>0) lastCounts.unread_slips--; setTabBadge('badgeSlip',lastCounts.unread_slips); setNotifBadge((lastCounts.unread_slips||0)+(lastCounts.about_to_expire||0)); setTimeout(loadCountsAndLists,200); }); });
        newExp.forEach(it=>{ const id=it.id||it.booking_id||''; const href='desk_status.php#desk-'+encodeURIComponent(it.desk_id||''); const endMs=parseEndMs(it);
          showToast(`ใกล้หมดเวลา: โต๊ะ ${esc(it.desk_name||it.desk_id||'')}`, `${Number.isFinite(endMs)?`เหลือ ~ ${Math.max(0,Math.ceil((endMs-nowMs())/60000))} นาที`:''}`, href,
          async()=>{ const fd=new FormData(); if(id)fd.append('id',id); await safeFetch(ENDPOINT.expMark+`?warn_min=${encodeURIComponent(WARN_MIN)}`,{method:'POST',body:fd});
            if(lastCounts.about_to_expire>0) lastCounts.about_to_expire--; setTabBadge('badgeExpire',lastCounts.about_to_expire); setNotifBadge((lastCounts.unread_slips||0)+(lastCounts.about_to_expire||0)); setTimeout(loadCountsAndLists,200); }); }); }
    }

    renderSlipList(cacheSlips); renderExpireList(cacheExpire);
    const expireUnread=cacheExpire.reduce((n,it)=>n+(it.viewed?0:1),0), slipUnread=cacheSlips.reduce((n,it)=>n+(it.viewed?0:1),0);
    setTabBadge('badgeSlip',Math.min(unread,slipUnread)); setTabBadge('badgeExpire',Math.min(expSoon,expireUnread)); setNotifBadge(unread+expSoon);
  }

  /* ----- Renderers ----- */
  function renderSlipList(items){
    const wrap=document.getElementById('listSlip'); if(!wrap)return; const cutoff=startOfTodayMs();
    items=(items||[]).filter(it=>{ const ms=parseCreatedMs(it); return Number.isFinite(ms)&&ms>=cutoff; });
    wrap.innerHTML=''; if(!items.length){ wrap.innerHTML='<div class="text-center text-muted py-3">ไม่มีการแจ้งเตือนการชำระเงิน</div>'; return; }
    items.slice().sort((a,b)=>(parseCreatedMs(b)||0)-(parseCreatedMs(a)||0)).forEach(it=>{
      const row=document.createElement('a'); row.className='list-group-item list-group-item-action d-flex justify-content-between align-items-start notif-item '+(it.viewed?'':'unread');
      row.href='#'; row.setAttribute('role','button');
      row.dataset.id=it.id||''; row.dataset.type='slip';
      const dot=it.viewed?'':'<span class="dot-unread" style="width:8px;height:8px;border-radius:50%;background:#dc3545;display:inline-block;"></span>';
      row.innerHTML=`<div><div class="d-flex align-items-center gap-2">${dot}<strong>${esc(it.title||'การชำระเงินใหม่')}</strong></div>
        <div class="text-muted">${esc(it.message||'')}</div><div class="notif-time">${esc(it.time_text||'')}</div></div>
        <div class="d-flex align-items-center"><i class="bi bi-chevron-right"></i></div>`;
      wrap.appendChild(row);
    });
  }
  function renderExpireList(items){
    const wrap=document.getElementById('listExpire'); if(!wrap)return; const cutoff=startOfTodayMs();
    items=(items||[]).filter(it=>{ const ms=parseEndMs(it); return Number.isFinite(ms)&&ms>=cutoff; });
    wrap.innerHTML=''; if(!items.length){ wrap.innerHTML='<div class="text-center text-muted py-3">ยังไม่มีโต๊ะที่ใกล้หมดเวลา</div>'; return; }
    items.slice().sort((a,b)=>(parseEndMs(a)||0)-(parseEndMs(b)||0)).forEach(it=>{
      const endMs=parseEndMs(it); const mins=Number.isFinite(endMs)?Math.max(0,Math.ceil((endMs-nowMs())/60000)):'';
      const row=document.createElement('a'); row.className='list-group-item list-group-item-action d-flex justify-content-between align-items-start notif-item '+(it.viewed?'':'unread');
      row.href='#'; row.setAttribute('role','button');
      row.dataset.id=it.id||it.booking_id||''; row.dataset.type='expire';
      const dot=it.viewed?'':'<span class="dot-unread" style="width:8px;height:8px;border-radius:50%;background:#dc3545;display:inline-block;"></span>';
      row.innerHTML=`<div><div class="d-flex align-items-center gap-2">${dot}<strong>โต๊ะ ${esc(it.desk_name||it.desk_id||'')}</strong></div>
        <div class="text-muted">${mins!==''?`เหลือเวลา ~ ${mins} นาที`:''}${it.end_text?` (สิ้นสุด ${esc(it.end_text)})`:''}</div></div>
        <div class="d-flex align-items-center"><i class="bi bi-chevron-right"></i></div>`;
      wrap.appendChild(row);
    });
  }

  /* ----- Dropdown open: refresh ----- */
  (function(){
    function onDropdownShow(){
      try { loadCountsAndLists?.(); } catch(e){ console.error(e); }
    }
    document.addEventListener('DOMContentLoaded', function(){
      const bell = document.getElementById('notifBell');
      if (bell && typeof bell.addEventListener === 'function') {
        bell.addEventListener('show.bs.dropdown', onDropdownShow);
      }
    });
  })();

  /* ----- กันชนขั้นเด็ดขาด: เอา .active ออกจากแท็บกระดิ่งเมื่อปิด dropdown ----- */
  document.addEventListener('DOMContentLoaded', function(){
    const bell = document.getElementById('notifBell');
    const menu = bell?.closest('.nav-item')?.querySelector('.dropdown-menu-notif');
    if (!bell || !menu) return;

    const btnSlip   = document.getElementById('tab-slip');
    const btnExpire = document.getElementById('tab-expire');
    const paneSlip  = document.getElementById('pane-slip');
    const paneExp   = document.getElementById('pane-expire');

    function deactivateAll(){
      [btnSlip, btnExpire].forEach(b=>b?.classList.remove('active','show','focus'));
      [paneSlip, paneExp].forEach(p=>{
        if (!p) return;
        p.classList.remove('active','show');
        p.setAttribute('aria-hidden','true');
        p.style.display = 'none';
      });
    }
    function activateDefault(){
      if (btnSlip) btnSlip.classList.add('active');
      if (paneSlip){
        paneSlip.classList.add('active','show');
        paneSlip.removeAttribute('aria-hidden');
        paneSlip.style.display = '';
      }
    }

    // เริ่มต้นปิด active ในกระดิ่ง เพื่อไม่ให้ selector กว้าง ๆ ไปเจอ
    deactivateAll();

    bell.addEventListener('show.bs.dropdown', ()=>{ activateDefault(); });
    bell.addEventListener('hide.bs.dropdown', ()=>{ deactivateAll(); });

    // กัน bubbling เผื่อหน้าอื่นมี listener กว้าง ๆ
    menu.addEventListener('click', (e)=>{
      if (e.target.closest('.notif-tabs .nav-link')) {
        e.stopPropagation();
        e.stopImmediatePropagation?.();
      }
    }, true);
  });

  /* ----- ลบทั้งหมด ----- */
  document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('btnSlipDeleteAll')?.addEventListener('click', async ()=>{
      const btn = document.getElementById('btnSlipDeleteAll');
      btn.disabled = true;

      await safeFetch(`${ENDPOINT.delAll}?type=slip`, {method:'POST'});

      cacheSlips = []; saveCache();
      lastCounts.unread_slips = 0;
      setTabBadge('badgeSlip', 0);
      setNotifBadge((lastCounts.unread_slips||0) + (lastCounts.about_to_expire||0));

      const wrap = document.getElementById('listSlip');
      if (wrap) wrap.innerHTML = '<div class="text-center text-muted py-3">ไม่มีการแจ้งเตือนการชำระเงิน</div>';

      await loadCountsAndLists();
      btn.disabled = false;
    });

    document.getElementById('btnExpireDeleteAll')?.addEventListener('click', async ()=>{
      const btn = document.getElementById('btnExpireDeleteAll');
      btn.disabled = true;

      await safeFetch(`${ENDPOINT.delAll}?type=expire&warn_min=${encodeURIComponent(WARN_MIN)}`, {method:'POST'});

      cacheExpire = []; saveCache();
      lastCounts.about_to_expire = 0;
      setTabBadge('badgeExpire', 0);
      setNotifBadge((lastCounts.unread_slips||0) + (lastCounts.about_to_expire||0));

      const wrap = document.getElementById('listExpire');
      if (wrap) wrap.innerHTML = '<div class="text-center text-muted py-3">ยังไม่มีโต๊ะที่ใกล้หมดเวลา</div>';

      await loadCountsAndLists();
      btn.disabled = false;
    });
  });

  /* ----- Init ----- */
  (async function initNotif(){
    await syncServerTime();
    loadCache();
    cacheSlips =(cacheSlips ||[]).filter(it=>{ const t=parseCreatedMs(it); return Number.isFinite(t)&&t>=startOfTodayMs(); });
    cacheExpire=(cacheExpire||[]).filter(it=>{ const t=parseEndMs(it);     return Number.isFinite(t)&&t>=startOfTodayMs(); });
    saveCache();
    renderSlipList(cacheSlips); renderExpireList(cacheExpire);
    await loadCountsAndLists();
    attachClickToMarkRead('listSlip'); attachClickToMarkRead('listExpire');
    setInterval(loadCountsAndLists, POLL_MS);
  })();

})(); // END IIFE
</script>
