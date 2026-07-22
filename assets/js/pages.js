/* ── STATIC PAGES ─────────────────────────────── */
async function pgAbout(){
  const res = await fetch('pages/about.html');
  if(!res.ok){setPage(errPage('Failed to load','go("dashboard")'));return;}
  const html = await res.text();
  setPage(html);
}
async function pgPrivacy(){
  const res = await fetch('pages/privacy.html');
  if(!res.ok){setPage(errPage('Failed to load','go("dashboard")'));return;}
  const html = await res.text();
  setPage(html);
}
async function pgTerms(){
  const res = await fetch('pages/terms.html');
  if(!res.ok){setPage(errPage('Failed to load','go("dashboard")'));return;}
  const html = await res.text();
  setPage(html);
}
