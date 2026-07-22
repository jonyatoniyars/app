/* PalliCare — app.js */

/* ── API ──────────────────────────────────────── */
async function api(url, opts={}) {
  const ctrl = new AbortController();
  const tid  = setTimeout(()=>ctrl.abort(), 20000);
  try {
    const res  = await fetch(url, { credentials:'same-origin', signal:ctrl.signal, headers:{'Content-Type':'application/json',...(opts.headers||{})}, ...opts });
    clearTimeout(tid);
    const text = await res.text();
    try { return JSON.parse(text); }
    catch { return {success:false,error:{message:'Server error. Check PHP error logs.'}}; }
  } catch(e) {
    clearTimeout(tid);
    return {success:false,error:{message:e.name==='AbortError'?'Request timed out':'Network error: '+e.message}};
  }
}
const em = j => j?.error?.message || 'Something went wrong';

/* ── Toast ────────────────────────────────────── */
!function(){const el=document.createElement('div');el.id='toasts';el.style.cssText='position:fixed;top:16px;right:16px;z-index:9999;display:flex;flex-direction:column;gap:8px;pointer-events:none';document.body.appendChild(el)}();
function toast(msg,type='success'){
  const t=document.createElement('div');
  t.style.cssText=`background:${type==='success'?'#16a34a':type==='error'?'#dc2626':'#2998ab'};color:#fff;padding:10px 16px;border-radius:10px;font-size:13px;font-weight:500;box-shadow:0 4px 12px rgba(0,0,0,.15);max-width:320px;pointer-events:auto;opacity:1;transition:opacity .3s`;
  t.textContent=msg;
  document.getElementById('toasts').appendChild(t);
  setTimeout(()=>{t.style.opacity='0';setTimeout(()=>t.remove(),300)},3500);
}

/* ── SVG Icons ────────────────────────────────── */
const I={
  dashboard:`<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>`,
  file:`<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>`,
  pill:`<path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"/><line x1="8.5" y1="8.5" x2="15.5" y2="15.5"/>`,
  users:`<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>`,
  link:`<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>`,
  video:`<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/>`,
  plus:`<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>`,
  search:`<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>`,
  check:`<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>`,
  clock:`<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>`,
  back:`<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>`,
  print:`<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>`,
  trash:`<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>`,
  phone:`<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.64 3.42 2 2 0 0 1 3.6 1.24h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.9a16 16 0 0 0 6 6l.92-.92a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>`,
  phoneOff:`<line x1="1" y1="1" x2="23" y2="23"/><path d="M16.5 16.5L12 21l-9-9 4.5-4.5"/><path d="M21 12l-4.5 4.5"/>`,
  unlink:`<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/><line x1="2" y1="2" x2="22" y2="22"/>`,
  shield:`<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>`,
  userPlus:`<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>`,
  send:`<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>`,
};
function svg(n){return`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${I[n]||''}</svg>`}

/* ── Helpers ──────────────────────────────────── */
function badge(s){
  const m={ACTIVE:'badge-green',REVIEWED:'badge-green',ACCEPTED:'badge-green',PENDING:'badge-yellow',SUBMITTED:'badge-blue',DRAFT:'badge-slate',SUSPENDED:'badge-red',DECLINED:'badge-red'};
  return `<span class="badge ${m[s]||'badge-slate'}">${s.charAt(0)+s.slice(1).toLowerCase()}</span>`;
}
function fd(s){if(!s)return'—';return new Date(s).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'});}
function setPage(h){const c=document.getElementById('page-wrap');if(c)c.innerHTML=h;buildNav();}
function loading(){setPage('<div class="loading-center"><div class="spinner"></div></div>');}
function errPage(msg,fn){return`<div class="err-page"><div class="err-icon">⚠️</div><h3>Failed to load</h3><p>${msg}</p>${fn?`<button class="btn btn-primary btn-sm" onclick="${fn}">Retry</button>`:''}</div>`;}

/* ── Nav ──────────────────────────────────────── */
const NAVS=[
  {id:'dashboard',    label:'Dashboard',    icon:'dashboard',roles:['ADMIN','DOCTOR','HEALTH_WORKER']},
  {id:'prescriptions',label:'Prescriptions',icon:'file',     roles:['ADMIN','DOCTOR','HEALTH_WORKER']},
  {id:'medicines',    label:'Medicines',    icon:'pill',     roles:['ADMIN']},
  {id:'users',        label:'Users',        icon:'users',    roles:['ADMIN']},
  {id:'assignments',  label:'Assignments',  icon:'link',     roles:['ADMIN']},
  {id:'video-calls',  label:'Video Calls',  icon:'video',    roles:['ADMIN','DOCTOR','HEALTH_WORKER']},
];
let curPage='dashboard';
function buildNav(){
  const nav=document.getElementById('sb-nav');
  if(!nav||!window.ROLE)return;
  nav.innerHTML=NAVS.filter(n=>n.roles.includes(ROLE)).map(n=>`
    <div class="nav-item${n.id===curPage?' active':''}" onclick="go('${n.id}')">
      ${svg(n.icon)} ${n.label}
    </div>`).join('');
}
async function go(page, opts={push:true}){
  try{
    if(!page) page='dashboard';
    if(opts.push) history.pushState({page:page}, '', '#'+page);
    curPage=page; buildNav(); loading();
    if(page==='dashboard')    return await pgDashboard();
    if(page==='prescriptions')return await pgRxList();
    if(page==='new-rx')       return await pgNewRx();
    if(page==='medicines')    return await pgMedicines();
    if(page==='users')        return await pgUsers();
    if(page==='assignments')  return await pgAssignments();
    if(page==='video-calls')  return await pgVideoCalls();
    if(page.startsWith('rx-'))return await pgRxDetail(page.slice(3));
    // Unknown page -> dashboard
    return await pgDashboard();
  }catch(e){setPage(errPage('Unexpected error: '+e.message,`go('${page}')`))}
}

/* ── AUTH ─────────────────────────────────────── */
function showCard(id){document.querySelectorAll('.auth-card').forEach(c=>c.style.display='none');document.getElementById(id).style.display='block';document.getElementById('login-error')?.style && (document.getElementById('login-error').style.display='none');document.getElementById('reg-error')?.style && (document.getElementById('reg-error').style.display='none');}

async function doLogin(){
  const btn=document.getElementById('login-btn'),err=document.getElementById('login-error');
  btn.disabled=true;btn.textContent='Signing in…';err.style.display='none';
  const res=await api('api/auth/login.php',{method:'POST',body:JSON.stringify({identifier:document.getElementById('login-id').value.trim(),password:document.getElementById('login-pw').value})});
  btn.disabled=false;btn.textContent='Sign In';
  if(res.success){location.reload();}
  else{err.textContent=em(res);err.style.display='block';}
}

async function doRegister(){
  const btn=document.getElementById('reg-btn'),err=document.getElementById('reg-error'),suc=document.getElementById('reg-success');
  btn.disabled=true;btn.textContent='Registering…';err.style.display='none';suc.style.display='none';
  const res=await api('api/auth/register.php',{method:'POST',body:JSON.stringify({name:document.getElementById('reg-name').value.trim(),role:document.getElementById('reg-role').value,email:document.getElementById('reg-email').value.trim(),phone:document.getElementById('reg-phone').value.trim(),password:document.getElementById('reg-pw').value})});
  btn.disabled=false;btn.textContent='Register';
  if(res.success){suc.style.display='block';document.querySelectorAll('#register-card input,#register-card select').forEach(e=>e.value='');}
  else{err.textContent=em(res);err.style.display='block';}
}

async function doLogout(){await api('api/auth/logout.php',{method:'POST'});location.reload();}

/* ── DASHBOARD ────────────────────────────────── */
async function pgDashboard(){
  const res=await api('api/dashboard.php');
  if(!res.success){setPage(errPage(em(res),"go('dashboard')"));return;}
  const d=res.data||{};
  let stats='';
  if(ROLE==='ADMIN'){
    stats=sc(d.totalUsers||0,'Total Users','users','brand')+sc(d.pendingApprovals||0,'Pending Approvals','clock','yellow')+sc(d.totalPrescriptions||0,'Prescriptions','file','brand')+sc(d.activeMedicines||0,'Active Medicines','pill','green');
  }else if(ROLE==='DOCTOR'){
    stats=sc(d.assignedWorkers||0,'Assigned Workers','users','brand')+sc(d.pendingReviews||0,'Pending Reviews','clock','yellow')+sc(d.totalReviewed||0,'Total Reviewed','check','green')+sc(d.pendingVideoCalls||0,'Pending Calls','video','red');
  }else{
    stats=sc(d.totalPrescriptions||0,'Total Prescriptions','file','brand')+sc(d.submittedPrescriptions||0,'Submitted','clock','yellow')+sc(d.reviewedPrescriptions||0,'Reviewed','check','green')+sc(d.pendingVideoCalls||0,'Pending Calls','video','red');
  }
  const qi=ROLE==='ADMIN'
    ?[['users','users','Pending Approvals',`${d.pendingApprovals||0} waiting`],['medicines','pill','Medicine List','Manage approved list'],['assignments','link','Assignments','Assign workers to doctors']]
    :ROLE==='DOCTOR'
    ?[['prescriptions','file','Review Prescriptions',`${d.pendingReviews||0} pending`],['video-calls','video','Video Calls',`${d.pendingVideoCalls||0} pending`],['assignments','link','My Workers',`${d.assignedWorkers||0} assigned`]]
    :[['new-rx','plus','New Prescription','Write a prescription'],['prescriptions','file','My Prescriptions','View history'],['video-calls','video','Call Doctor','Request consultation']];
  setPage(`
    <div class="page-header"><h1>Dashboard</h1><p>Welcome back, ${USER.name}</p></div>
    <div class="stats-grid">${stats}</div>
    <div class="card card-p">
      <div class="card-header"><span class="card-title">Quick Actions</span></div>
      <div class="quick-grid">${qi.map(([p,i,t,desc])=>`<button class="quick-card" onclick="go('${p}')"><div class="quick-ic">${svg(i)}</div><div><div class="quick-title">${t}</div><div class="quick-desc">${desc}</div></div></button>`).join('')}</div>
    </div>`);
}
function sc(v,l,i,c){return`<div class="stat-card"><div class="stat-icon ${c}">${svg(i)}</div><div><div class="stat-val">${v}</div><div class="stat-lbl">${l}</div></div></div>`;}

/* ── PRESCRIPTIONS LIST ───────────────────────── */
async function pgRxList(page=1,search='',status='',type=''){
  const url=`api/prescriptions.php?page=${page}&limit=20${search?'&search='+encodeURIComponent(search):''}${status?'&status='+status:''}${type?'&type='+type:''}`;
  const res=await api(url);
  if(!res.success){setPage(errPage(em(res),"pgRxList()"));return;}
  const rows=res.data||[],meta=res.meta||{};
  setPage(`
    <div class="page-header-row">
      <div class="page-header"><h1>Prescriptions</h1><p>${meta.total||rows.length} total</p></div>
      ${ROLE==='HEALTH_WORKER'?`<button class="btn btn-primary" onclick="go('new-rx')">${svg('plus')} New Prescription</button>`:''}
    </div>
    <div class="filter-bar">
      <div class="search-wrap"><svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${I.search}</svg>
        <input class="search-input" id="rx-s" placeholder="Search patient or complaints…" value="${search}" onkeydown="if(event.key==='Enter')pgRxList(1,this.value,'${status}','${type}')"/>
      </div>
      <div class="pill-row">${['','DRAFT','SUBMITTED','REVIEWED'].map((f,i)=>`<button class="pill${status===f?' active':''}" onclick="pgRxList(1,document.getElementById('rx-s').value,'${f}','${type}')">${['All','Draft','Submitted','Reviewed'][i]}</button>`).join('')}</div>
      <div class="pill-row">${['','GENERAL','DENTAL'].map((f,i)=>`<button class="pill${type===f?' active':''}" style="margin-left:8px" onclick="pgRxList(1,document.getElementById('rx-s').value,'${status}','${f}')">${[f?f:'All Types',f].includes(f)?f:'All Types'}</button>`).join('')}</div>
    </div>
    <div class="table-wrap">
      ${rows.length===0?`<div class="empty-state">${svg('file')}<h3>No prescriptions found</h3><p>None match your filters</p></div>`:`
      <table><thead><tr><th>Type</th><th>Patient</th><th>Age</th><th>Chief Complaints</th><th>Status</th><th>Health Worker</th><th>Date</th><th></th></tr></thead>
      <tbody>${rows.map(r=>`<tr>
        <td><span style="display:inline-block;padding:2px 8px;border-radius:4px;background:${r.prescriptionType==='DENTAL'?'#fef3c7':'#dbeafe'};color:${r.prescriptionType==='DENTAL'?'#92400e':'#1e40af'};font-size:11px;font-weight:600">${r.prescriptionType==='DENTAL'?'🦷 Dental':'General'}</span></td>
        <td class="fw">${r.patientName}</td><td>${r.patientAge} y</td>
        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#475569">${r.chiefComplaints}</td>
        <td>${badge(r.status)}</td><td class="text-muted text-sm">${r.healthWorker?.name||'—'}</td>
        <td class="text-muted text-sm" style="white-space:nowrap">${fd(r.createdAt)}</td>
        <td><a href="#" onclick="go('rx-${r.id}')" style="color:#2998ab;font-size:12px;font-weight:600;text-decoration:none">View →</a></td>
      </tr>`).join('')}</tbody></table>`}
    </div>
    ${(meta.totalPages||1)>1?`<div class="flex-between" style="margin-top:14px"><span class="text-muted text-sm">Page ${meta.page} of ${meta.totalPages}</span><div style="display:flex;gap:6px"><button class="btn btn-secondary btn-sm" ${page<=1?'disabled':''} onclick="pgRxList(${page-1},'${search}','${status}','${type}')">← Prev</button><button class="btn btn-secondary btn-sm" ${page>=(meta.totalPages||1)?'disabled':''} onclick="pgRxList(${page+1},'${search}','${status}','${type}')">Next →</button></div></div>`:''}`);
}

/* ── PRESCRIPTION DETAIL ──────────────────────── */
async function pgRxDetail(id){
  const res=await api(`api/prescriptions.php?id=${id}`);
  if(!res.success){setPage(errPage(em(res),"go('prescriptions')"));return;}
  const rx=res.data;
  setPage(`
    <div class="flex-between no-print" style="margin-bottom:20px">
      <div style="display:flex;align-items:center;gap:10px">
        <button class="btn btn-secondary btn-sm" onclick="go('prescriptions')">${svg('back')}</button>
        <div><div style="display:flex;align-items:center;gap:8px"><span style="font-size:18px;font-weight:700">Prescription</span>${badge(rx.status)}</div><div class="text-muted text-sm">${fd(rx.createdAt)}</div></div>
      </div>
      <div style="display:flex;gap:8px">
        ${(ROLE==='DOCTOR'||ROLE==='ADMIN')&&rx.status==='SUBMITTED'?`<button class="btn btn-secondary btn-sm" onclick="reviewRx('${rx.id}')">${svg('check')} Mark Reviewed</button>`:''}
        <button class="btn btn-secondary btn-sm" onclick="window.print()">${svg('print')} Print</button>
      </div>
    </div>
    <div class="card card-p">
      ${rx.reviewedBy?`<div class="rx-reviewed">${svg('check')}<span>Reviewed by <strong>${rx.reviewedBy.name}</strong> on ${fd(rx.reviewedAt)}${rx.reviewNotes?` — "${rx.reviewNotes}"`:''}</span></div>`:''}
      <div class="rx-header">
        <div><div class="rx-clinic-name">PalliCare Community Clinic</div><div class="rx-clinic-sub">Community Health Programme</div></div>
        <div class="rx-meta">Rx: <strong>${rx.id.slice(-8).toUpperCase()}</strong><br>${fd(rx.createdAt)}</div>
      </div>
      <div class="rx-patient-row">
        <div><div class="rx-field-label">Type</div><div class="rx-field-val">${rx.prescriptionType==='DENTAL'?'🦷 Dental Prescription':'General Practice'}</div></div>
        <div><div class="rx-field-label">Patient</div><div class="rx-field-val">${rx.patientName}</div></div>
        <div><div class="rx-field-label">Age / Gender</div><div class="rx-field-val">${rx.patientAge} yrs / ${rx.patientGender}</div></div>
        <div><div class="rx-field-label">Health Worker</div><div class="rx-field-val">${rx.healthWorker?.name}</div></div>
      </div>
      <div style="margin-bottom:12px"><div class="rx-section-lbl">C/C — Chief Complaints</div><div style="font-size:13px">${rx.chiefComplaints}</div></div>
      ${rx.onExamination?`<div style="margin-bottom:12px"><div class="rx-section-lbl">O/E — On Examination</div><div style="font-size:13px">${rx.onExamination}</div></div>`:''}
      <div style="margin-top:16px;padding-top:14px;border-top:1px solid #e2e8f0">
        <div class="rx-symbol">℞</div>
        <table class="rx-table"><thead><tr><th>#</th><th>Medicine</th><th>Dose</th><th>Frequency</th><th>Duration</th><th>Notes</th></tr></thead>
        <tbody>${(rx.items||[]).map((x,i)=>`<tr><td style="color:#94a3b8">${i+1}.</td><td class="med-name">${x.medicineName}</td><td>${x.dose}</td><td>${x.frequency}</td><td>${x.duration}</td><td style="font-style:italic;color:#94a3b8">${x.instructions||'—'}</td></tr>`).join('')}</tbody></table>
      </div>
      ${rx.advice?`<div style="margin-top:12px;padding-top:10px;border-top:1px solid #e2e8f0"><div class="rx-section-lbl">Advice</div><div style="font-size:13px">${rx.advice}</div></div>`:''}
      <div class="rx-sig"><div class="rx-sig-block"><div class="rx-sig-line"></div>Health Worker Signature</div><div class="rx-sig-block"><div class="rx-sig-line"></div>Doctor Seal / Sign</div></div>
    </div>`);
}
async function reviewRx(id){
  const notes=prompt('Review notes (optional):')||'';
  const res=await api(`api/prescriptions.php?id=${id}`,{method:'PATCH',body:JSON.stringify({reviewNotes:notes})});
  if(res.success){toast('Reviewed!');go(`rx-${id}`);}else toast(em(res),'error');
}

/* ── NEW PRESCRIPTION ─────────────────────────── */
let rxStep=0,rxMeds=[],rxItems=[{medicineId:'',dose:'1 tablet',frequency:'Once daily',duration:'5 days',instructions:''}],_rxd={prescriptionType:'GENERAL'};
async function pgNewRx(){
  if(!rxMeds.length){const r=await api('api/admin/medicines.php?limit=200');if(r.success)rxMeds=r.data;}
  const steps=['Patient Info','Clinical Notes','Medicines','Review & Submit'];
  const bar=steps.map((_,i)=>`<div class="step-seg${i<=rxStep?' done':''}"></div>`).join('');
  let body='';
  if(rxStep===0){
    body=`<div class="form-group"><label class="form-label">Prescription Type *</label><select class="form-input" id="pt" onchange="_rxd.prescriptionType=this.value"><option value="GENERAL" ${_rxd.prescriptionType==='GENERAL'?'selected':''}>General Practice</option><option value="DENTAL" ${_rxd.prescriptionType==='DENTAL'?'selected':''}>Dental</option></select></div>
    <div class="form-group"><label class="form-label">Patient Name *</label><input class="form-input" id="pn" value="${_rxd.patientName||''}" placeholder="Full name"/></div>
    <div class="grid-2"><div class="form-group"><label class="form-label">Age *</label><input class="form-input" id="pa" type="number" min="0" max="150" value="${_rxd.patientAge||''}"/></div>
    <div class="form-group"><label class="form-label">Gender</label><select class="form-input" id="pg"><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option></select></div></div>`;
  }else if(rxStep===1){
    body=`<div class="form-group"><label class="form-label">Chief Complaints (C/C) *</label><textarea class="form-input" id="cc" rows="3" placeholder="e.g. Fever 3 days, headache…">${_rxd.chiefComplaints||''}</textarea></div>
    <div class="form-group"><label class="form-label">On Examination (O/E)</label><textarea class="form-input" id="oe" rows="3" placeholder="e.g. Temp 38°C, BP 120/80…">${_rxd.onExamination||''}</textarea></div>
    <div class="form-group"><label class="form-label">Advice</label><textarea class="form-input" id="adv" rows="2" placeholder="e.g. Rest, drink fluids…">${_rxd.advice||''}</textarea></div>`;
  }else if(rxStep===2){
    const mo=`<option value="">— Select medicine —</option>`+rxMeds.map(m=>`<option value="${m.id}">${m.name}${m.genericName?` (${m.genericName})`:''}</option>`).join('');
    const fo=['Once daily','Twice daily','Three times daily','Four times daily','At bedtime','As needed'].map(v=>`<option>${v}</option>`).join('');
    const do2=['½ tablet','1 tablet','2 tablets','1 capsule','5ml','10ml','1 sachet','2 puffs'].map(v=>`<option>${v}</option>`).join('');
    const du=['1 day','3 days','5 days','7 days','10 days','14 days','30 days','Until finished'].map(v=>`<option>${v}</option>`).join('');
    body=rxItems.map((item,i)=>`<div class="rx-item">
      <div class="flex-between" style="margin-bottom:10px"><span style="font-size:11px;font-weight:600;color:#94a3b8">Medicine ${i+1}</span>${rxItems.length>1?`<button onclick="delRxItem(${i})" style="color:#ef4444;border:none;background:none;cursor:pointer">${svg('trash')}</button>`:''}</div>
      <div class="form-group"><label class="form-label">Medicine *</label><select class="form-input" onchange="rxItems[${i}].medicineId=this.value">${mo.replace(`value="${item.medicineId}"`,`value="${item.medicineId}" selected`)}</select></div>
      <div class="grid-3">
        <div class="form-group"><label class="form-label">Dose</label><select class="form-input" onchange="rxItems[${i}].dose=this.value">${do2}</select></div>
        <div class="form-group"><label class="form-label">Frequency</label><select class="form-input" onchange="rxItems[${i}].frequency=this.value">${fo}</select></div>
        <div class="form-group"><label class="form-label">Duration</label><select class="form-input" onchange="rxItems[${i}].duration=this.value">${du}</select></div>
      </div>
      <div class="form-group"><label class="form-label">Instructions</label><input class="form-input" value="${item.instructions}" placeholder="e.g. After meals" onchange="rxItems[${i}].instructions=this.value"/></div>
    </div>`).join('')+`<button class="btn btn-secondary btn-sm" onclick="addRxItem()">${svg('plus')} Add Medicine</button>`;
  }else{
    body=`<div style="display:flex;flex-direction:column;gap:14px;font-size:13px">
     <div><div style="font-size:10px;font-weight:600;text-transform:uppercase;color:#94a3b8;margin-bottom:3px">Type</div><div>${_rxd.prescriptionType==='DENTAL'?'🦷 Dental':'General Practice'}</div></div>
     <div><div style="font-size:10px;font-weight:600;text-transform:uppercase;color:#94a3b8;margin-bottom:3px">Patient</div><div>${_rxd.patientName} — ${_rxd.patientAge} y — ${_rxd.patientGender}</div></div>
     <div><div style="font-size:10px;font-weight:600;text-transform:uppercase;color:#94a3b8;margin-bottom:3px">C/C</div><div>${_rxd.chiefComplaints}</div></div>
     ${_rxd.onExamination?`<div><div style="font-size:10px;font-weight:600;text-transform:uppercase;color:#94a3b8;margin-bottom:3px">O/E</div><div>${_rxd.onExamination}</div></div>`:''}
     <div><div style="font-size:10px;font-weight:600;text-transform:uppercase;color:#94a3b8;margin-bottom:3px">Rx</div>${rxItems.map((x,i)=>{const m=rxMeds.find(m=>m.id===x.medicineId);return`<div>${i+1}. ${m?.name||'—'} — ${x.dose}, ${x.frequency}, ${x.duration}${x.instructions?` (${x.instructions})`:''}</div>`;}).join('')}</div>
     ${_rxd.advice?`<div><div style="font-size:10px;font-weight:600;text-transform:uppercase;color:#94a3b8;margin-bottom:3px">Advice</div><div>${_rxd.advice}</div></div>`:''}
   </div>`;
  }
  setPage(`
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
      <button class="btn btn-secondary btn-sm" onclick="go('prescriptions')">${svg('back')}</button>
      <div><div style="font-size:18px;font-weight:700">New Prescription</div><div class="text-muted text-sm">Step ${rxStep+1}/4: ${steps[rxStep]}</div></div>
    </div>
    <div style="max-width:600px"><div class="step-bar">${bar}</div>
    <div class="card card-p" style="margin-bottom:12px">${body}</div>
    <div class="flex-between">
      <button class="btn btn-secondary" onclick="rxPrev()" ${rxStep===0?'disabled':''}>${svg('back')} Back</button>
      ${rxStep<3?`<button class="btn btn-primary" onclick="rxNext()">Next ${svg('send')}</button>`:`<div style="display:flex;gap:8px"><button class="btn btn-secondary" onclick="submitRx('DRAFT')">${svg('file')} Save Draft</button><button class="btn btn-primary" onclick="submitRx('SUBMITTED')">${svg('send')} Submit</button></div>`}
    </div></div>`);
}
function addRxItem(){rxItems.push({medicineId:'',dose:'1 tablet',frequency:'Once daily',duration:'5 days',instructions:''});pgNewRx();}
function delRxItem(i){rxItems.splice(i,1);pgNewRx();}
function rxPrev(){if(rxStep>0){rxStep--;pgNewRx();}}
function rxNext(){
  if(rxStep===0){const n=document.getElementById('pn')?.value.trim(),a=document.getElementById('pa')?.value;if(!n){toast('Patient name required','error');return;}if(!a){toast('Age required','error');return;}_rxd={..._rxd,prescriptionType:document.getElementById('pt')?.value||'GENERAL',patientName:n,patientAge:parseInt(a),patientGender:document.getElementById('pg').value};}
  else if(rxStep===1){const cc=document.getElementById('cc')?.value.trim();if(!cc){toast('Chief complaints required','error');return;}_rxd={..._rxd,chiefComplaints:cc,onExamination:document.getElementById('oe')?.value.trim()||'',advice:document.getElementById('adv')?.value.trim()||''};}
  else if(rxStep===2){if(rxItems.some(x=>!x.medicineId)){toast('Select a medicine for each entry','error');return;}}
  rxStep++;pgNewRx();
}
async function submitRx(status){
  const res=await api('api/prescriptions.php',{method:'POST',body:JSON.stringify({..._rxd,status,items:rxItems})});
  if(res.success){toast(status==='SUBMITTED'?'Submitted!':'Draft saved!');rxStep=0;rxItems=[{medicineId:'',dose:'1 tablet',frequency:'Once daily',duration:'5 days',instructions:''}];_rxd={prescriptionType:'GENERAL'};go(`rx-${res.data.id}`);}
  else toast(em(res),'error');
}

/* ── MEDICINES ────────────────────────────────── */
async function pgMedicines(search=''){
  if(ROLE!=='ADMIN'){go('dashboard');return;}
  const res=await api(`api/admin/medicines.php?limit=200${search?'&search='+encodeURIComponent(search):''}`);
  if(!res.success){setPage(errPage(em(res),"pgMedicines()"));return;}
  const meds=res.data||[];
  setPage(`
    <div class="page-header-row">
      <div class="page-header"><h1>Medicine List</h1><p>${meds.filter(m=>m.isActive).length} active medicines</p></div>
      <button class="btn btn-primary" onclick="showMedForm()">${svg('plus')} Add Medicine</button>
    </div>
    <div class="card card-p" id="med-form" style="display:none;margin-bottom:16px">
      <div class="card-header"><span class="card-title">Add New Medicine</span></div>
      <div class="grid-3">
        <div class="form-group"><label class="form-label">Medicine Name *</label><input class="form-input" id="med-n" placeholder="e.g. Paracetamol 500mg"/></div>
        <div class="form-group"><label class="form-label">Generic Name</label><input class="form-input" id="med-g" placeholder="e.g. Paracetamol"/></div>
        <div class="form-group"><label class="form-label">Form</label><select class="form-input" id="med-f">${['TABLET','CAPSULE','SYRUP','INJECTION','OINTMENT','DROPS','INHALER','OTHER'].map(f=>`<option value="${f}">${f.charAt(0)+f.slice(1).toLowerCase()}</option>`).join('')}</select></div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:8px"><button class="btn btn-secondary btn-sm" onclick="document.getElementById('med-form').style.display='none'">Cancel</button><button class="btn btn-primary btn-sm" onclick="addMed()">Add</button></div>
    </div>
    <div class="filter-bar"><div class="search-wrap"><svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${I.search}</svg><input class="search-input" placeholder="Search…" value="${search}" onkeydown="if(event.key==='Enter')pgMedicines(this.value)"/></div></div>
    <div class="table-wrap">
      ${meds.length===0?`<div class="empty-state">${svg('pill')}<h3>No medicines yet</h3></div>`:`
      <table><thead><tr><th>Name</th><th>Generic</th><th>Form</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>${meds.map(m=>`<tr style="${m.isActive?'':'opacity:.5'}">
        <td class="fw">${m.name}</td><td class="text-muted">${m.genericName||'—'}</td>
        <td class="text-muted">${m.form.charAt(0)+m.form.slice(1).toLowerCase()}</td>
        <td><span class="badge ${m.isActive?'badge-green':'badge-red'}">${m.isActive?'Active':'Inactive'}</span></td>
        <td><button onclick="toggleMed('${m.id}',${m.isActive})" style="font-size:12px;font-weight:600;color:${m.isActive?'#dc2626':'#16a34a'};border:none;background:none;cursor:pointer">${m.isActive?'Deactivate':'Reactivate'}</button></td>
      </tr>`).join('')}</tbody></table>`}
    </div>`);
}
function showMedForm(){const f=document.getElementById('med-form');f.style.display=f.style.display==='none'?'block':'none';}
async function addMed(){
  if(ROLE!=='ADMIN')return;
  const n=document.getElementById('med-n')?.value.trim();if(!n){toast('Name required','error');return;}
  const res=await api('api/admin/medicines.php',{method:'POST',body:JSON.stringify({name:n,genericName:document.getElementById('med-g').value.trim(),form:document.getElementById('med-f').value})});
  if(res.success){toast('Medicine added!');pgMedicines();}else toast(em(res),'error');
}
async function toggleMed(id,active){
  if(ROLE!=='ADMIN')return;
  const res=await api(`api/admin/medicines.php?id=${id}`,{method:active?'DELETE':'PATCH',body:active?null:JSON.stringify({isActive:true})});
  if(res.success){toast(active?'Deactivated':'Reactivated');pgMedicines();}else toast(em(res),'error');
}

/* ── USERS ────────────────────────────────────── */
async function pgUsers(search='',status='',role=''){
  if(ROLE!=='ADMIN'){go('dashboard');return;}
  const url=`api/admin/users.php?limit=100${search?'&search='+encodeURIComponent(search):''}${status?'&status='+status:''}${role?'&role='+role:''}`;
  const res=await api(url);
  if(!res.success){setPage(errPage(em(res),"pgUsers()"));return;}
  const users=res.data||[];
  setPage(`
    <div class="page-header"><h1>User Management</h1><p>Approve, suspend, and manage permissions</p></div>
    ${ROLE==='ADMIN' && USER.superAdminId?`<div class="card note" style="margin-bottom:12px;padding:10px;background:#f8fafc;border-left:4px solid #3b82f6"><strong>Super Admin ID:</strong> <code>${USER.superAdminId}</code> — Only this user may reset other ADMIN passwords.</div>`:''}
    <div class="filter-bar">
      <div class="search-wrap"><svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${I.search}</svg><input class="search-input" id="usr-s" placeholder="Name, email or phone…" value="${search}" onkeydown="if(event.key==='Enter')pgUsers(this.value,'${status}','${role}')"/></div>
      <div class="pill-row">${['','PENDING','ACTIVE','SUSPENDED'].map((s,i)=>`<button class="pill${status===s?' active':''}" onclick="pgUsers(document.getElementById('usr-s').value,'${s}','${role}')">${['All','Pending','Active','Suspended'][i]}</button>`).join('')}</div>
      <select class="form-input" style="width:auto;font-size:12px" onchange="pgUsers(document.getElementById('usr-s').value,'${status}',this.value)">
        <option value="">All Roles</option><option value="DOCTOR" ${role==='DOCTOR'?'selected':''}>Doctor</option><option value="HEALTH_WORKER" ${role==='HEALTH_WORKER'?'selected':''}>Health Worker</option>
      </select>
    </div>
    <div class="table-wrap">
      ${users.length===0?`<div class="empty-state">${svg('users')}<h3>No users found</h3></div>`:`
      <table><thead><tr><th>Name</th><th>Contact</th><th>Role</th><th>Status</th><th>Rx Permission</th><th>Joined</th><th>Actions</th></tr></thead>
      <tbody>${users.map(u=>`<tr>
        <td class="fw">${u.name}</td>
        <td class="text-muted text-sm">${u.email||''}<br>${u.phone||''}</td>
        <td><span class="badge ${u.role==='DOCTOR'?'badge-blue':'badge-green'}" style="font-size:10px">${u.role==='HEALTH_WORKER'?'HW':u.role}</span></td>
        <td>${badge(u.status)}</td>
        <td>${u.role==='HEALTH_WORKER'?`<button onclick="togglePerm('${u.id}',${u.canWritePrescription})" style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:${u.canWritePrescription?'#16a34a':'#dc2626'};border:none;background:none;cursor:pointer">${svg('shield')} ${u.canWritePrescription?'Granted':'Revoked'}</button>`:'<span class="text-muted text-sm">N/A</span>'}</td>
        <td class="text-muted text-sm">${fd(u.createdAt)}</td>
        <td style="white-space:nowrap">
          ${u.status==='PENDING'?`<button class="btn btn-sm" style="background:#f0fdf4;color:#16a34a;border:1px solid #dcfce7" onclick="updateUser('${u.id}','ACTIVE')">✓ Approve</button>`:''}
          ${u.status==='ACTIVE'?`<button class="btn btn-sm" style="background:#fef2f2;color:#dc2626;border:1px solid #fee2e2" onclick="updateUser('${u.id}','SUSPENDED')">Suspend</button>`:''}
          ${u.status==='SUSPENDED'?`<button class="btn btn-sm" style="background:#f0fdf4;color:#16a34a;border:1px solid #dcfce7" onclick="updateUser('${u.id}','ACTIVE')">Reactivate</button>`:''}
          <button class="btn btn-sm" style="margin-left:6px;background:#eef2ff;color:#3730a3;border:1px solid #e0e7ff" onclick="adminResetPassword('${u.id}')">Reset Password</button>
        </td>
      </tr>`).join('')}</tbody></table>`}
    </div>`);
}
async function updateUser(id,status){
  if(ROLE!=='ADMIN')return;
  const res=await api(`api/admin/users.php?id=${id}`,{method:'PATCH',body:JSON.stringify({status})});if(res.success){toast('Updated!');pgUsers();}else toast(em(res),'error');}
async function togglePerm(id,cur){
  if(ROLE!=='ADMIN')return;
  const res=await api(`api/admin/users.php?id=${id}`,{method:'PATCH',body:JSON.stringify({canWritePrescription:!cur})});if(res.success){toast('Permission updated!');pgUsers();}else toast(em(res),'error');}

async function adminResetPassword(userId){
  if(ROLE!=='ADMIN')return; 
  const custom = prompt('Enter a new password for this user (leave blank to generate a random one):');
  let pw = custom && custom.trim() ? custom.trim() : null;
  if(pw && pw.length<6){alert('Password must be at least 6 characters');return;}
  if(!pw){pw = Array.from(window.crypto.getRandomValues(new Uint8Array(8))).map(b=>b.toString(16).padStart(2,'0')).join('').slice(0,12);}
  if(!confirm('Reset password for user? This will set their password to: '+pw)) return;
  const res = await api('api/admin/reset_password.php',{method:'POST',body:JSON.stringify({userId, password: pw})});
  if(res.success){toast('Password reset. Share the temporary password securely.');pgUsers();}else{toast(em(res),'error');}
}

/* ── ASSIGNMENTS ──────────────────────────────── */
async function pgAssignments(){
  let ar,dr,wr;
  if(ROLE==='ADMIN'){
    [ar,dr,wr]=await Promise.all([api('api/admin/assignments.php'),api('api/admin/users.php?role=DOCTOR&status=ACTIVE&limit=100'),api('api/admin/users.php?role=HEALTH_WORKER&status=ACTIVE&limit=100')]);
  }else{
    ar=await api('api/doctor/my_workers.php');
    dr={success:true,data:[]};wr={success:true,data:[]};
  }
  if(!ar.success){setPage(errPage(em(ar),"pgAssignments()"));return;}
  const asgns=ar.data||[],docs=dr.data||[],wks=wr.data||[];
  const grps={};asgns.forEach(a=>{if(!grps[a.doctor.id])grps[a.doctor.id]={doctor:a.doctor,workers:[]};grps[a.doctor.id].workers.push(a.healthWorker);});
  setPage(`
    <div class="page-header"><h1>Assignments</h1><p>Assign health workers to doctors</p></div>
    <div class="card card-p" style="margin-bottom:16px">
      <div class="card-header"><span class="card-title">New Assignment</span></div>
      <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:flex-end">
        <div class="form-group" style="margin-bottom:0"><label class="form-label">Doctor</label><select class="form-input" id="asgn-d"><option value="">— Select Doctor —</option>${docs.map(d=>`<option value="${d.id}">${d.name}</option>`).join('')}</select></div>
        <div class="form-group" style="margin-bottom:0"><label class="form-label">Health Worker</label><select class="form-input" id="asgn-w"><option value="">— Select Worker —</option>${wks.map(w=>`<option value="${w.id}">${w.name}</option>`).join('')}</select></div>
        <button class="btn btn-primary" onclick="createAssignment()">${svg('userPlus')} Assign</button>
      </div>
    </div>
    ${Object.values(grps).length===0?`<div class="empty-state" style="margin-top:20px">${svg('link')}<h3>No assignments yet</h3><p>Use the form above to assign health workers</p></div>`
      :Object.values(grps).map(g=>`<div class="assign-group">
        <div class="assign-group-hdr"><div><div style="font-weight:600">${g.doctor.name}</div><div class="text-muted text-sm">${g.doctor.email||''}</div></div><span class="badge badge-blue">${g.workers.length} worker${g.workers.length!==1?'s':''}</span></div>
        ${g.workers.map(w=>`<div class="assign-row"><div><div style="font-size:13px;font-weight:500">${w.name}</div><div class="text-muted text-sm">${w.phone||w.email||'—'}</div></div><button onclick="removeAssignment('${w.id}')" style="display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:600;color:#dc2626;border:none;background:none;cursor:pointer">${svg('unlink')} Remove</button></div>`).join('')}
      </div>`).join('')}`);
}
async function createAssignment(){
  const d=document.getElementById('asgn-d')?.value,w=document.getElementById('asgn-w')?.value;
  if(!d||!w){toast('Select both a doctor and health worker','error');return;}
  const res=await api('api/admin/assignments.php',{method:'POST',body:JSON.stringify({doctorId:d,healthWorkerId:w})});
  if(res.success){toast('Assigned!');pgAssignments();}else toast(em(res),'error');
}
async function removeAssignment(hwId){
  if(!confirm('Remove this assignment?'))return;
  const res=await api(`api/admin/assignments.php?healthWorkerId=${hwId}`,{method:'DELETE'});
  if(res.success){toast('Removed');pgAssignments();}else toast(em(res),'error');
}

/* ── VIDEO CALLS ──────────────────────────────── */
async function pgVideoCalls(){
  const res=await api('api/video-calls.php');
  if(!res.success){setPage(errPage(em(res),"pgVideoCalls()"));return;}
  const calls=res.data||[],pend=calls.filter(c=>c.status==='PENDING'),hist=calls.filter(c=>c.status!=='PENDING');
  setPage(`
    <div class="page-header"><h1>Video Calls</h1><p>Consultation requests</p></div>
    ${ROLE==='HEALTH_WORKER'?`<div class="card card-p" style="margin-bottom:16px"><div class="card-header"><span class="card-title">Request Consultation</span></div><div class="form-group"><label class="form-label">Describe your query</label><textarea class="form-input" id="call-note" rows="3" placeholder="e.g. Patient has persistent fever, need guidance…"></textarea></div><button class="btn btn-primary btn-sm" onclick="requestCall()">${svg('send')} Send Request</button></div>`:''}
    ${pend.length?`<div style="margin-bottom:20px"><div style="font-size:13px;font-weight:600;color:#334155;margin-bottom:10px">⏳ Pending (${pend.length})</div>${pend.map(c=>`<div class="call-pending"><div><div style="font-size:13px;font-weight:600">${c.requester.name}${c.requester.phone?' · '+c.requester.phone:''}</div>${c.note?`<div style="font-size:12px;color:#475569;margin-top:3px">${c.note}</div>`:''}<div class="text-muted text-sm" style="margin-top:4px">${fd(c.createdAt)}</div></div>${ROLE==='DOCTOR'?`<div style="display:flex;gap:6px;flex-shrink:0"><button class="btn btn-primary btn-sm" onclick="respondCall('${c.id}','ACCEPTED')">${svg('phone')} Accept</button><button class="btn btn-danger btn-sm" onclick="respondCall('${c.id}','DECLINED')">${svg('phoneOff')} Decline</button></div>`:''}</div>`).join('')}</div>`:''}
    <div class="card card-p"><div class="card-header"><span class="card-title">Call History</span></div>${hist.length===0?`<div class="empty-state" style="padding:28px 20px">${svg('video')}<h3>No history yet</h3></div>`:hist.map(c=>`<div class="call-hist-row"><div><div style="font-size:13px;font-weight:600">${c.requester.name} → ${c.receiver.name}</div>${c.note?`<div class="text-muted text-sm" style="max-width:400px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${c.note}</div>`:''}<div class="text-muted text-sm">${fd(c.createdAt)}</div></div>${badge(c.status)}</div>`).join('')}</div>`);
}
async function requestCall(){const note=document.getElementById('call-note')?.value.trim();const res=await api('api/video-calls.php',{method:'POST',body:JSON.stringify({note})});if(res.success){toast('Call request sent!');pgVideoCalls();}else toast(em(res),'error');}
async function respondCall(id,status){const res=await api(`api/video-calls.php?id=${id}`,{method:'PATCH',body:JSON.stringify({status})});if(res.success){toast(status==='ACCEPTED'?'Accepted!':'Declined');pgVideoCalls();}else toast(em(res),'error');}

/* ── BOOT ─────────────────────────────────────── */
document.addEventListener('DOMContentLoaded',()=>{
  const loginId=document.getElementById('login-id');
  if(loginId){
    loginId.addEventListener('keydown',e=>e.key==='Enter'&&doLogin());
    document.getElementById('login-pw')?.addEventListener('keydown',e=>e.key==='Enter'&&doLogin());
    document.getElementById('reg-pw')?.addEventListener('keydown',e=>e.key==='Enter'&&doRegister());
    return;
  }
  if(window.ROLE){
    buildNav();
    // Close mobile nav on item click (ensure newly created items are wired)
    setTimeout(()=>{
      document.querySelectorAll('.nav-item').forEach(item=>item.addEventListener('click',()=>{document.getElementById('sb-nav')?.classList.remove('mobile-open');document.querySelector('.mobile-menu-btn')?.classList.remove('active');}));
    },50);

    // Handle browser back/forward to stay inside the SPA
    window.addEventListener('popstate', function(e){
      const p = (e.state && e.state.page) ? e.state.page : (location.hash?location.hash.slice(1):'dashboard');
      go(p, {push:false});
    });

    const initial = location.hash ? location.hash.slice(1) : 'dashboard';
    go(initial, {push:false});
  }
});
