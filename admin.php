<?php
require __DIR__ . '/lib.php';
require_staff('admin');
$rows = array_map('admin_row', db()->query('SELECT * FROM bookings ORDER BY id DESC')->fetchAll());
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Bookings · <?= h(EVENT_NAME) ?></title><?php include 'admin_style.php'; ?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Yatra+One&family=Mukta:wght@400;600;700;800&display=swap">
<style>
.tools{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}.tools input{flex:1;min-width:180px;font:inherit;padding:10px 12px;border-radius:10px;border:1px solid var(--line)}
.tabs{display:flex;gap:6px;overflow-x:auto;margin-bottom:12px;padding-bottom:2px}
.tabs button{font:inherit;font-weight:600;border:1px solid var(--line);background:var(--card);border-radius:999px;padding:6px 12px;cursor:pointer;white-space:nowrap;color:var(--fg)}
.tabs button.on{background:var(--fg);color:#fff;border-color:var(--fg)}
.list{display:flex;flex-direction:column;gap:10px}
.bk{background:var(--card);border:1px solid var(--line);border-left:5px solid #E89A12;border-radius:12px;padding:12px 14px;display:flex;flex-direction:column;gap:10px}
.bk.Approved{border-left-color:var(--ok)}.bk.Rejected{border-left-color:var(--bad)}
.top{display:flex;justify-content:space-between;gap:10px}.top .nm{font-weight:700;font-size:1.05rem}
.amt{font-weight:800;font-size:1.1rem;font-variant-numeric:tabular-nums;text-align:right;white-space:nowrap}
.meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:4px 14px;font-size:.88rem;overflow-wrap:anywhere}.meta span.k{color:var(--muted)}
.utr{font-family:ui-monospace,Menlo,monospace;font-weight:800;font-size:1rem;letter-spacing:.04em;background:#FFF5D6;padding:1px 6px;border-radius:6px}
.seg{display:inline-flex;border:1px solid var(--line);border-radius:10px;overflow:hidden}
.seg button{font:inherit;font-weight:700;border:0;background:var(--card);padding:9px 12px;cursor:pointer;color:var(--muted)}
.seg button+button{border-left:1px solid var(--line)}
.seg button.on.Approved{background:var(--ok);color:#fff}.seg button.on.Pending{background:#E89A12;color:#fff}.seg button.on.Rejected{background:var(--bad);color:#fff}
.seg button:disabled{cursor:not-allowed;opacity:.6}
.acts{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.badge{font-size:.78rem;font-weight:700;border-radius:999px;padding:3px 9px}.badge.in{background:var(--okbg);color:var(--ok)}.badge.sent{background:#E3F1FF;color:#1559A8}.badge.warn{background:var(--warnbg);color:var(--warn)}
.btn.wa.rej{background:#6B4E3D}.btn.sm{padding:6px 10px;font-size:.82rem}
.toast{position:fixed;left:50%;bottom:20px;transform:translateX(-50%);background:var(--fg);color:#fff;padding:10px 16px;border-radius:10px;font-weight:600;display:none;z-index:9;max-width:90vw}
.btn.img{background:#128C7E;color:#fff}.btn .n{display:inline-grid;place-items:center;width:20px;height:20px;border-radius:50%;background:#fff3;font-size:.8rem}
.empty{text-align:center;padding:30px;color:var(--muted)}.help{font-size:.85rem;background:#FFF5D6;border-radius:10px;padding:8px 12px;margin-bottom:12px}
</style></head><body>
<?php include 'admin_nav.php'; ?>
<main>
  <div class="stats" id="stats"></div>
  <div class="help">Match the <b>UTR</b> and amount in your bank / UPI app → <b>✓ Approve</b> → <b>① Message</b> (opens the chat, press Send) → come back → <b>② Ticket image</b> → choose the same chat at the top → Send.</div>
  <div class="tools"><input type="search" id="q" placeholder="Search name, mobile, UTR, booking ID…"><a class="btn g" href="report.php?csv=1">⬇ Excel</a></div>
  <div class="tabs" id="tabs"></div>
  <div class="list" id="list"></div>
</main>
<div class="toast" id="toast"></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
const EV = <?= json_encode(['name' => EVENT_NAME, 'tag' => EVENT_TAG, 'date' => EVENT_DATE, 'time' => EVENT_TIME, 'venue' => EVENT_VENUE], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
const CSRF = <?= json_encode($_SESSION['csrf']) ?>;
let rows = <?= json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
let tab = "Pending", q = "", busy = false;
const $ = s => document.querySelector(s);
const inr = n => "₹" + Number(n).toLocaleString("en-IN");
const esc = s => String(s ?? "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
const t12 = s => s ? new Date(s.replace(" ", "T")).toLocaleString("en-IN", {day:"numeric", month:"short", hour:"numeric", minute:"2-digit"}) : "";
function toast(m){const t=$("#toast");t.textContent=m;t.style.display="block";clearTimeout(t._h);t._h=setTimeout(()=>t.style.display="none",2800)}
async function api(body){
  const r = await fetch("api.php",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF":CSRF},body:JSON.stringify(body)});
  const j = await r.json().catch(()=>({error:"No internet / server error — try again"}));
  if (r.status===401 || r.status===403 && /reload/.test(j.error||"")) { alert(j.error); location.reload(); }
  if (!r.ok) throw new Error(j.error||"Error");
  return j;
}
const put = row => { const i = rows.findIndex(r => r.booking_id === row.booking_id); if (i >= 0) rows[i] = row; else rows.unshift(row); };
const TABS = [["Pending","Pending"],["Approved","Approved"],["NotSent","Ticket not sent"],["Rejected","Rejected"],["In","Entered"],["All","All"]];
const match = (r,k) => k==="All" || (k==="In" ? !!r.checked_in_at : k==="NotSent" ? r.status==="Approved" && !r.wa_sent_at : r.status===k);
function render(){
  const pax = f => rows.filter(f).reduce((a,r)=>a+r.pax,0), ap = rows.filter(r=>r.status==="Approved");
  $("#stats").innerHTML = [
    ["To verify", rows.filter(r=>r.status==="Pending").length],
    ["Approved", ap.length + " · " + pax(r=>r.status==="Approved") + " pax"],
    ["Approved amount", inr(ap.reduce((a,r)=>a+r.amount,0))],
    ["Entered", pax(r=>r.checked_in_at) + " / " + pax(r=>r.status==="Approved") + " pax"],
  ].map(([k,v])=>`<div class="stat"><small>${k}</small><b>${v}</b></div>`).join("");
  $("#tabs").innerHTML = TABS.map(([k,l])=>`<button data-tab="${k}" class="${k===tab?"on":""}">${l} (${rows.filter(r=>match(r,k)).length})</button>`).join("");
  const mob = {}; rows.forEach(r => { if (r.status !== "Rejected") mob[r.mobile] = (mob[r.mobile]||0) + 1; });
  const ql = q.toLowerCase().trim();
  const list = rows.filter(r => match(r,tab)).filter(r => !ql || [r.name,r.mobile,r.utr,r.booking_id,r.email].join(" ").toLowerCase().includes(ql));
  $("#list").innerHTML = list.length ? list.map(r => card(r, mob[r.mobile] > 1)).join("") : `<div class="empty">Nothing here.</div>`;
}
function card(r, dupMob){
  const lock = !!r.checked_in_at;
  const seg = ["Approved","Pending","Rejected"].map(s=>`<button data-st="${s}" class="${r.status===s?"on "+s:""}" ${lock&&s!=="Approved"?"disabled":""}>${s==="Approved"?"✓ Approve":s==="Rejected"?"✕ Reject":"Pending"}</button>`).join("");
  const wa = r.status==="Approved" ? `<a class="btn wa" href="${esc(r.wa)}" target="_blank" rel="noopener" data-wa><span class="n">1</span> Message</a><button class="btn img" data-img><span class="n">2</span> Ticket image</button><a class="btn g" href="${esc(r.ticket)}" target="_blank" rel="noopener">View ticket</a>`
          : r.status==="Rejected" ? `<a class="btn wa rej" href="${esc(r.wa)}" target="_blank" rel="noopener" data-wa>WhatsApp "payment not found"</a>` : "";
  return `<div class="bk ${r.status}" data-b="${esc(r.booking_id)}">
    <div class="top"><div><div class="nm">${esc(r.name)}</div><div class="muted">${esc(r.booking_id)} · booked ${t12(r.created_at)}</div></div>
      <div class="amt">${inr(r.amount)}<div class="muted">${r.pax} ${r.pax>1?"persons":"person"}</div></div></div>
    <div class="meta"><div><span class="k">UTR </span><span class="utr">${esc(r.utr)}</span></div>
      <div><span class="k">Mobile </span><a href="tel:${esc(r.mobile)}">${esc(r.mobile)}</a></div><div><span class="k">Email </span>${esc(r.email)}</div>
      ${r.pay_note?`<div><span class="k">UPI note </span>${esc(r.pay_note)}</div>`:""}</div>
    <div class="acts"><div class="seg">${seg}</div>${wa}</div>
    ${(dupMob||r.wa_sent_at||r.checked_in_at)?`<div class="acts">
      ${dupMob?`<span class="badge warn">⚠ Same mobile has more bookings</span>`:""}
      ${r.wa_sent_at?`<span class="badge sent">${r.status==="Approved"?"Ticket image sent":"WhatsApp sent"} ${t12(r.wa_sent_at)}</span>`:""}
      ${r.checked_in_at?`<span class="badge in">Entered ${t12(r.checked_in_at)}</span><button class="btn g sm" data-undo>Undo entry</button>`:""}</div>`:""}
  </div>`;
}
document.addEventListener("click", async e => {
  const tb = e.target.closest("[data-tab]"); if (tb) { tab = tb.dataset.tab; render(); return; }
  const el = e.target.closest(".bk"); if (!el) return;
  const bid = el.dataset.b, r = rows.find(x => x.booking_id === bid);
  const st = e.target.closest("[data-st]");
  if (st && !st.disabled && st.dataset.st !== r.status) {
    const to = st.dataset.st;
    if (to==="Rejected" && !confirm(`Reject ${r.name} (${inr(r.amount)}, UTR ${r.utr})?`)) return;
    if (r.status==="Approved" && !confirm(`${r.name} is approved${r.wa_sent_at?" and the ticket was already sent":""}. Change to ${to}? Their QR will stop working.`)) return;
    try { busy = true; const j = await api({action:"status", booking_id: bid, status: to}); put(j.row); render();
      toast(to==="Approved" ? "Approved ✓ — now tap ① Message, then ② Ticket image" : "Marked " + to); }
    catch(err){ toast(err.message); } finally { busy = false; }
  }
  if (e.target.closest("[data-wa]")) { api({action:"wa_sent", booking_id: bid, kind:"text"}).then(j=>{ if(j.row){put(j.row); render();} }).catch(()=>{}); if (r.status==="Approved") ticketFile(r).catch(()=>{}); }
  if (e.target.closest("[data-img]")) sendTicketImage(r, e.target.closest("[data-img]"));
  if (e.target.closest("[data-undo]") && confirm(`Undo entry for ${r.name}? Their QR will work again.`)) {
    try { const j = await api({action:"undo_entry", booking_id: bid}); put(j.row); render(); toast("Entry undone"); } catch(err){ toast(err.message); }
  }
});
// ---------- Ticket image (PNG) for WhatsApp ----------
const fileCache = {};
function qrCanvas(text, size){
  if (!window.QRCode) throw new Error("QR maker did not load — check internet and reload the page");
  const d = document.createElement("div"); new QRCode(d, {text, width:size, height:size, correctLevel:QRCode.CorrectLevel.M});
  const c = d.querySelector("canvas"); if (!c) throw new Error("QR could not be drawn on this phone"); return c;
}
function wrap(cx, text, x, y, maxW, lh, maxLines=3){
  const words = String(text).split(" "); let line = "", n = 0;
  for (const w of words) { const t = line ? line + " " + w : w;
    if (cx.measureText(t).width > maxW && line) { cx.fillText(line, x, y + n*lh); n++; line = w; if (n === maxLines-1) { line = words.slice(words.indexOf(w)).join(" "); break; } } else line = t; }
  if (line) { while (cx.measureText(line).width > maxW && line.length > 3) line = line.slice(0,-2); cx.fillText(line, x, y + n*lh); n++; }
  return n;
}
function rr(cx,x,y,w,h,r){ cx.beginPath(); cx.moveTo(x+r,y); cx.arcTo(x+w,y,x+w,y+h,r); cx.arcTo(x+w,y+h,x,y+h,r); cx.arcTo(x,y+h,x,y,r); cx.arcTo(x,y,x+w,y,r); cx.closePath(); }
async function ticketFile(r){
  if (fileCache[r.booking_id]) return fileCache[r.booking_id];
  await Promise.all([document.fonts.load('64px "Yatra One"'), document.fonts.load('700 40px Mukta'), document.fonts.load('800 40px Mukta')]).catch(()=>{});
  const W = 1080, H = 1740, cv = document.createElement("canvas"); cv.width = W; cv.height = H; const cx = cv.getContext("2d");
  const DISP = '"Yatra One", Georgia, serif', BODY = 'Mukta, system-ui, sans-serif', MAROON = "#9E1B32", INK = "#3A1A12", MUTED = "#7A5A4C";
  cx.fillStyle = "#FBF3E4"; cx.fillRect(0,0,W,H);
  // header: poster strip + dark overlay + event name
  const HH = 360;
  const g = cx.createLinearGradient(0,0,0,HH); g.addColorStop(0,"#B5223C"); g.addColorStop(1,"#7A1226"); cx.fillStyle = g; cx.fillRect(0,0,W,HH);
  cx.fillStyle = "rgba(242,181,68,.18)"; for (let i = 0; i < 9; i++) { cx.beginPath(); cx.arc(60 + i*120, 30, 26, 0, Math.PI*2); cx.fill(); }   // marigold dots
  cx.fillStyle = "#F2B544"; cx.font = `700 34px ${BODY}`; cx.textAlign = "center"; cx.fillText(EV.tag.toUpperCase(), W/2, 150);
  cx.fillStyle = "#FFF7EC"; cx.font = `80px ${DISP}`; wrap(cx, EV.name, W/2, 250, W-120, 88, 2);
  // ticket card
  const X = 60, Y = HH - 50, CW = W - 120, CH = H - Y - 60;
  cx.save(); cx.shadowColor = "rgba(58,26,18,.18)"; cx.shadowBlur = 40; cx.shadowOffsetY = 10; cx.fillStyle = "#FFFBF3"; rr(cx,X,Y,CW,CH,36); cx.fill(); cx.restore();
  cx.fillStyle = MAROON; cx.font = `800 30px ${BODY}`; cx.fillText("ENTRY PASS  ·  SHOW AT GATE", W/2, Y + 70);
  // QR
  const QS = 560, qx = (W-QS)/2, qy = Y + 100;
  cx.fillStyle = "#fff"; rr(cx, qx-24, qy-24, QS+48, QS+48, 28); cx.fill(); cx.strokeStyle = "#E8D6B8"; cx.lineWidth = 3; cx.stroke();
  cx.imageSmoothingEnabled = false; cx.drawImage(qrCanvas(r.ticket, QS), qx, qy, QS, QS); cx.imageSmoothingEnabled = true;
  // booking id
  let y = qy + QS + 92;
  cx.fillStyle = "#1E7A4A"; cx.font = `800 64px ui-monospace, Menlo, Consolas, monospace`; cx.fillText(r.booking_id, W/2, y);
  // perforation
  y += 50; cx.strokeStyle = "#E8D6B8"; cx.setLineDash([14,12]); cx.lineWidth = 4; cx.beginPath(); cx.moveTo(X+30,y); cx.lineTo(X+CW-30,y); cx.stroke(); cx.setLineDash([]);
  cx.fillStyle = "#FBF3E4"; cx.beginPath(); cx.arc(X, y, 28, 0, Math.PI*2); cx.arc(X+CW, y, 28, 0, Math.PI*2); cx.fill();
  // details
  const L = X + 60, R = X + CW - 60; y += 80; cx.textAlign = "left";
  cx.fillStyle = MUTED; cx.font = `600 30px ${BODY}`; cx.fillText("NAME", L, y);
  cx.textAlign = "right"; cx.fillText("PERSONS", R, y);
  cx.textAlign = "left"; cx.fillStyle = INK; cx.font = `800 52px ${BODY}`; wrap(cx, r.name, L, y + 62, CW - 300, 56, 1);
  cx.textAlign = "right"; cx.fillStyle = MAROON; cx.font = `800 84px ${BODY}`; cx.fillText(String(r.pax), R, y + 78);
  y += 150; cx.textAlign = "left"; cx.fillStyle = MUTED; cx.font = `600 30px ${BODY}`; cx.fillText("WHEN", L, y);
  cx.fillStyle = INK; cx.font = `700 40px ${BODY}`; cx.fillText(EV.date + "  ·  " + EV.time, L, y + 52);
  y += 120; cx.fillStyle = MUTED; cx.font = `600 30px ${BODY}`; cx.fillText("VENUE", L, y);
  cx.fillStyle = INK; cx.font = `700 38px ${BODY}`; wrap(cx, EV.venue, L, y + 50, CW - 120, 48, 2);
  // footer
  cx.textAlign = "center"; cx.fillStyle = MUTED; cx.font = `600 26px ${BODY}`;
  cx.fillText(`Valid for ${r.pax} ${r.pax>1?"persons":"person"} · one entry only · please enter together`, W/2, Y + CH - 40);
  const blob = await new Promise(ok => cv.toBlob(ok, "image/png"));
  if (!blob) throw new Error("Could not create image");
  return (fileCache[r.booking_id] = new File([blob], `Ticket-${r.booking_id}.png`, {type:"image/png"}));
}
async function sendTicketImage(r, btn){
  const old = btn.innerHTML; btn.disabled = true; btn.innerHTML = "Preparing…"; busy = true;
  try {
    const file = await ticketFile(r);
    const caption = `🎟 ${EV.name} — Entry ticket\n${r.name} · ${r.pax} ${r.pax>1?"persons":"person"} · ${r.booking_id}\nShow this QR at the gate.`;
    if (navigator.canShare && navigator.canShare({files:[file]})) {
      try { await navigator.share({files:[file], text: caption}); }
      catch (err) { if (err.name === "AbortError") { toast("Not sent — tap ② Ticket image again"); return; } throw err; }
    } else {                                           // computer / old phone: save image, attach it in WhatsApp
      const a = document.createElement("a"); a.href = URL.createObjectURL(file); a.download = file.name; document.body.appendChild(a); a.click(); a.remove();
      setTimeout(() => URL.revokeObjectURL(a.href), 30000);
      toast("Ticket image downloaded — attach it in the WhatsApp chat");
    }
    const j = await api({action:"wa_sent", booking_id: r.booking_id, kind:"image"}); if (j.row) { put(j.row); render(); }
  } catch (err) { toast(err.message || "Could not share image"); }
  finally { busy = false; if (btn.isConnected) { btn.disabled = false; btn.innerHTML = old; } }
}
$("#q").addEventListener("input", e => { q = e.target.value; render(); });
// Auto-refresh every 20 s so new bookings & gate entries appear (pauses while you act)
setInterval(async () => {
  if (busy || document.hidden) return;
  try { const j = await api({action:"list"}); const before = rows.length; rows = j.rows; render(); if (rows.length > before) toast((rows.length-before) + " new booking(s)"); } catch(_){}
}, 20000);
render();
</script></body></html>
