<?php
require __DIR__ . '/lib.php';
require_staff('gate');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Scan QR · <?= h(EVENT_NAME) ?></title><?php include 'admin_style.php'; ?>
<style>
main{display:flex;flex-direction:column;gap:12px;max-width:520px}
.cam{position:relative;background:#000;border-radius:16px;overflow:hidden;aspect-ratio:1/1}
video{width:100%;height:100%;object-fit:cover;display:block}
.frame{position:absolute;inset:16%;border:3px solid #fff9;border-radius:18px;pointer-events:none}
.startbox{position:absolute;inset:0;display:grid;place-items:center;text-align:center;color:#fff;padding:20px}
.count{display:flex;justify-content:space-between;align-items:center;background:var(--card);border:1px solid var(--line);border-radius:12px;padding:10px 14px}
.count b{font-size:1.3rem;font-variant-numeric:tabular-nums}
.man{display:flex;gap:8px}.man input{flex:1;font:inherit;padding:11px 12px;border-radius:10px;border:1px solid var(--line);text-transform:uppercase}
#res{position:fixed;inset:0;z-index:20;display:none;flex-direction:column;align-items:center;justify-content:center;gap:10px;padding:24px;text-align:center;color:#fff}
#res.ok{background:#1E7A4A}#res.used{background:#B3261E}#res.invalid{background:#5A1A1A}#res.not_approved{background:#9A5B00}
#res .big{font-size:2.2rem;font-weight:800;line-height:1.1}#res .nm{font-size:1.5rem;font-weight:700}#res .pax{font-size:3.5rem;font-weight:800;line-height:1}
#res button{margin-top:18px;font:inherit;font-weight:800;font-size:1.1rem;border:0;border-radius:12px;padding:14px 30px;background:#fff;color:#000}
</style></head><body>
<?php include 'admin_nav.php'; ?>
<main>
  <div class="count"><span>Entered</span><b id="cnt">–</b></div>
  <div class="cam"><video id="v" playsinline muted></video><div class="frame"></div>
    <div class="startbox" id="sb"><div><button class="btn p" id="go">📷 Start camera</button><p class="muted" style="color:#ddd" id="camerr"></p></div></div></div>
  <div class="man"><input id="mid" placeholder="Or type Booking ID, e.g. VGN-AB12C"><button class="btn p" id="mgo">Check in</button></div>
  <p class="muted">Green = let them in. Red = already used / not valid. Each QR works only once.</p>
</main>
<div id="res"></div>
<script>
const CSRF = <?= json_encode($_SESSION['csrf']) ?>;
const $ = s => document.querySelector(s);
const esc = s => String(s ?? "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
const tm = s => s ? new Date(s.replace(" ","T")).toLocaleString("en-IN",{day:"numeric",month:"short",hour:"numeric",minute:"2-digit"}) : "";
let lastCode = "", lastAt = 0, busy = false, detector = null, jsQRfn = null, stream = null;
const v = $("#v"), cv = document.createElement("canvas"), cx = cv.getContext("2d", {willReadFrequently: true});

function beep(ok){ try{ const a=new AudioContext(), o=a.createOscillator(), g=a.createGain(); o.frequency.value=ok?880:220; o.connect(g); g.connect(a.destination); g.gain.value=.2; o.start(); o.stop(a.currentTime+(ok?.15:.5)); }catch(_){} navigator.vibrate?.(ok?120:[200,100,200]); }

async function checkin(code, manual=false){
  if (busy) return; busy = true;
  let j;
  try {
    const r = await fetch("api.php",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF":CSRF},body:JSON.stringify({action:"checkin",code,manual})});
    if (r.status === 401 || r.status === 403) { alert("Login expired — please login again."); return location.reload(); }
    j = await r.json();
  } catch(_) { j = {result:"invalid", neterr:true}; }
  if (j.counts) $("#cnt").textContent = j.counts.in + " / " + j.counts.total + " persons";
  const r = j.row || {}, res = $("#res");
  const html = {
    ok: `<div class="big">✓ ENTRY OK</div><div class="nm">${esc(r.name)}</div><div class="pax">${r.pax}</div><div>${r.pax>1?"persons":"person"} · ${esc(r.booking_id)}</div>${j.manual?`<div style="background:#0003;padding:6px 10px;border-radius:8px">Typed ID — please confirm the name with the guest</div>`:""}`,
    used: `<div class="big">✕ ALREADY USED</div><div class="nm">${esc(r.name)}</div><div>${r.pax} persons · ${esc(r.booking_id)}</div><div>Entered at ${tm(r.checked_in_at)} · ${esc(r.checked_in_by||"")}</div><div>Do NOT allow entry again.</div>`,
    not_approved: `<div class="big">⚠ NOT APPROVED</div><div class="nm">${esc(r.name)}</div><div>Status: ${esc(r.status)} · ${esc(r.booking_id)}</div><div>Send them to the organiser.</div>`,
    invalid: `<div class="big">✕ INVALID QR</div><div>${j.neterr ? "No internet — try again" : "This ticket is not in the booking list."}</div>`
  }[j.result] || "";
  res.className = j.result; res.innerHTML = html + `<button id="nx">Scan next</button>`; res.style.display = "flex";
  beep(j.result === "ok");
  $("#nx").onclick = () => { res.style.display = "none"; busy = false; };
}

async function startCam(){
  $("#camerr").textContent = "";
  try {
    stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:"environment"}});
  } catch(e) { $("#camerr").textContent = location.protocol !== "https:" ? "Camera needs https:// — open this page with https." : "Camera blocked. Allow camera permission for this site and try again."; return; }
  v.srcObject = stream; await v.play(); $("#sb").style.display = "none";
  if ("BarcodeDetector" in window) { try { if ((await BarcodeDetector.getSupportedFormats()).includes("qr_code")) detector = new BarcodeDetector({formats:["qr_code"]}); } catch(_){} }
  if (!detector) await new Promise(ok => { const s=document.createElement("script"); s.src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"; s.onload=()=>{jsQRfn=window.jsQR;ok()}; s.onerror=ok; document.head.appendChild(s); });
  if (!detector && !jsQRfn) { $("#camerr").textContent = "QR reader could not load. Use Booking ID box below."; $("#sb").style.display = "grid"; return; }
  loop();
}
async function loop(){
  if (!busy && v.readyState >= 2) {
    try {
      let txt = null;
      if (detector) { const c = await detector.detect(v); if (c.length) txt = c[0].rawValue; }
      else { cv.width = v.videoWidth; cv.height = v.videoHeight; cx.drawImage(v,0,0); const d = cx.getImageData(0,0,cv.width,cv.height); const c = jsQRfn(d.data,d.width,d.height,{inversionAttempts:"dontInvert"}); if (c) txt = c.data; }
      if (txt && !(txt === lastCode && Date.now() - lastAt < 4000)) { lastCode = txt; lastAt = Date.now(); await checkin(txt); }
    } catch(_){}
  }
  setTimeout(loop, 180);
}
$("#go").onclick = startCam;
fetch("api.php",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF":CSRF},body:JSON.stringify({action:"counts"})}).then(r=>r.json()).then(j=>{ if(j.counts) $("#cnt").textContent = j.counts.in + " / " + j.counts.total + " persons"; }).catch(()=>{});
$("#mgo").onclick = () => { const x = $("#mid").value.trim(); if (x) { checkin(x, true); $("#mid").value = ""; } };
$("#mid").addEventListener("keydown", e => { if (e.key === "Enter") $("#mgo").click(); });
</script></body></html>
