<?php
require __DIR__ . '/lib.php';
db();
$closed = booking_closed_reason();
$left = seats_left();
if (!$closed && $left !== null && $left <= 0) $closed = 'All passes are sold out. 🙏';
$cfg = ['price' => PRICE, 'maxPax' => $left !== null ? min(MAX_PAX, $left) : MAX_PAX, 'upi' => UPI_ID, 'upiName' => UPI_NAME ?: EVENT_NAME,
        'wa' => ORGANISER_WHATSAPP, 'event' => EVENT_NAME];
header('Cache-Control: no-store');
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= h(EVENT_NAME) ?></title>
<meta name="description" content="<?= h(EVENT_NAME . ' · ' . EVENT_DATE . ' · ' . EVENT_VENUE . ' · Entry ' . inr(PRICE)) ?>">
<meta property="og:title" content="<?= h(EVENT_NAME) ?>"><meta property="og:image" content="<?= h(base_url()) ?>/poster.jpg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Yatra+One&family=Mukta:wght@400;600;700&display=swap">
<style>
/* Layout: single narrow column like a pass counter — poster, event facts, then a 3-step booking card */
:root{
  --bg:#FBF3E4; --surface:#FFFBF3; --fg:#3A1A12; --muted:#7A5A4C;
  --accent:#9E1B32; --accent-fg:#FFF7EC; --marigold:#E89A12; --line:#E8D6B8; --ok:#1E7A4A;
  --display:"Yatra One","Mukta",Georgia,serif; --body:"Mukta",system-ui,-apple-system,"Segoe UI",sans-serif;
}
@media (prefers-color-scheme: dark){:root:not([data-theme="light"]){
  --bg:#1C1210; --surface:#2A1B16; --fg:#F6E7D4; --muted:#C2A58F;
  --accent:#E0506A; --accent-fg:#1C1210; --marigold:#F2B544; --line:#4A3128; --ok:#5CC08A; color-scheme:dark}}
:root[data-theme="dark"]{
  --bg:#1C1210; --surface:#2A1B16; --fg:#F6E7D4; --muted:#C2A58F;
  --accent:#E0506A; --accent-fg:#1C1210; --marigold:#F2B544; --line:#4A3128; --ok:#5CC08A; color-scheme:dark}
*{box-sizing:border-box}
[hidden]{display:none!important}
body{background:var(--bg);color:var(--fg);font-family:var(--body);font-size:16px;line-height:1.5;padding:0 16px;margin:0}
.wrap{max-width:520px;margin:0 auto;padding-block:20px 48px;display:flex;flex-direction:column;gap:20px}
h1{font-family:var(--display);font-weight:400;font-size:2.1rem;line-height:1.15;margin:0;color:var(--accent);text-wrap:balance}
h2{font-family:var(--display);font-weight:400;font-size:1.3rem;margin:0;color:var(--accent)}
.poster{width:100%;max-width:100%;border-radius:14px;display:block;border:1px solid var(--line)}
.tag{font-size:.78rem;letter-spacing:.12em;text-transform:uppercase;color:var(--marigold);font-weight:700}
.facts{display:grid;grid-template-columns:1fr 1fr;gap:12px 16px;margin:0}
.facts div{min-width:0}
.facts dt{font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;color:var(--muted)}
.facts dd{margin:0;font-weight:600}
.card{background:var(--surface);border:1px solid var(--line);border-radius:16px;padding:20px 18px;display:flex;flex-direction:column;gap:16px}
.step{display:flex;align-items:center;gap:10px}
.step b{width:28px;height:28px;border-radius:50%;background:var(--accent);color:var(--accent-fg);display:grid;place-items:center;font-size:.9rem;flex:none}
label{display:flex;flex-direction:column;gap:4px;font-weight:600;font-size:.92rem}
input{font:inherit;font-weight:400;padding:11px 12px;border-radius:10px;border:1px solid var(--line);background:var(--bg);color:var(--fg);width:100%}
input:focus-visible,button:focus-visible,a:focus-visible{outline:3px solid var(--marigold);outline-offset:2px}
.pax{display:flex;align-items:center;gap:12px}
.pax button{width:44px;height:44px;border-radius:10px;border:1px solid var(--line);background:var(--bg);color:var(--fg);font-size:1.4rem;cursor:pointer}
.pax output{min-width:2ch;text-align:center;font-size:1.4rem;font-weight:700;font-variant-numeric:tabular-nums}
.total{display:flex;justify-content:space-between;align-items:baseline;border-top:1px dashed var(--line);padding-top:12px}
.total strong{font-size:1.6rem;color:var(--accent);font-variant-numeric:tabular-nums}
.btn{font:inherit;font-weight:700;border:0;border-radius:12px;padding:14px 16px;background:var(--accent);color:var(--accent-fg);cursor:pointer;text-align:center;text-decoration:none;display:block;width:100%}
.btn.alt{background:transparent;color:var(--accent);border:2px solid var(--accent)}
.btn.wa{background:#1E8E4E;color:#fff}
.btn.gpay{background:#1A73E8;color:#fff}
.btn:disabled{opacity:.45;cursor:not-allowed}
.qrbox{display:flex;flex-direction:column;align-items:center;gap:10px;text-align:center}
#qr{background:#fff;padding:12px;border-radius:12px;line-height:0}
.upi{display:flex;gap:8px;align-items:center;justify-content:center;flex-wrap:wrap;font-family:ui-monospace,Menlo,monospace;font-size:.95rem}
.upi button{font:inherit;font-family:var(--body);font-size:.8rem;padding:4px 10px;border-radius:8px;border:1px solid var(--line);background:var(--bg);color:var(--fg);cursor:pointer}
.note{font-size:.85rem;color:var(--muted)}
.err{color:var(--accent);font-size:.85rem;font-weight:600}
.done{text-align:center;align-items:center}
.bid{font-family:ui-monospace,Menlo,monospace;font-size:1.3rem;font-weight:700;letter-spacing:.06em;color:var(--ok)}
.pending{background:var(--bg);border:1px solid var(--line);border-radius:10px;padding:10px 12px;font-size:.9rem}
.lnk{text-align:center;font-size:.9rem}.lnk a{color:var(--accent);font-weight:700}
.closed{background:var(--surface);border:1px solid var(--line);border-radius:16px;padding:20px;text-align:center;font-weight:600}
footer{text-align:center;font-size:.8rem;color:var(--muted)}
@media (prefers-reduced-motion:no-preference){.card{transition:opacity .2s}}
</style>
</head><body>
<div class="wrap">
  <img class="poster" src="poster.jpg" alt="<?= h(EVENT_NAME) ?> poster">
  <section><div class="tag"><?= h(EVENT_TAG) ?></div><h1><?= h(EVENT_NAME) ?></h1></section>
  <dl class="facts">
    <div><dt>Date</dt><dd><?= h(EVENT_DATE) ?></dd></div>
    <div><dt>Time</dt><dd><?= h(EVENT_TIME) ?></dd></div>
    <div><dt>Venue</dt><dd><?= h(EVENT_VENUE) ?></dd></div>
    <div><dt>Entry pass</dt><dd><?= inr(PRICE) ?> per person<?= EVENT_NOTE ? ' · ' . h(EVENT_NOTE) : '' ?></dd></div>
  </dl>
  <a class="btn alt" href="<?= h(EVENT_MAP) ?>" target="_blank" rel="noopener">Open location in Maps</a>

<?php if ($closed): ?>
  <div class="closed"><?= h($closed) ?></div>
<?php else: ?>
  <!-- Step 1: details -->
  <form class="card" id="f1" novalidate>
    <div class="step"><b>1</b><h2>Book your passes</h2></div>
    <label for="nm">Full name<input id="nm" autocomplete="name" maxlength="60" placeholder="e.g. Riya Shah"></label>
    <label for="mo">Mobile (WhatsApp) — ticket comes here<input id="mo" inputmode="numeric" maxlength="10" autocomplete="tel-national" placeholder="10-digit number"></label>
    <label for="em">Email<input id="em" type="email" autocomplete="email" maxlength="100" placeholder="you@example.com"></label>
    <div>
      <div style="font-weight:600;font-size:.92rem">Total persons (pax)</div>
      <div class="pax">
        <button type="button" id="minus" aria-label="One less person">−</button>
        <output id="pax">1</output>
        <button type="button" id="plus" aria-label="One more person">+</button>
        <span class="note">× <?= inr(PRICE) ?></span>
      </div>
      <?php if ($left !== null): ?><div class="note">Only <?= (int)$left ?> passes left</div><?php endif; ?>
    </div>
    <div class="total"><span>Total to pay</span><strong id="tot"><?= inr(PRICE) ?></strong></div>
    <p class="err" id="e1" hidden></p>
    <button class="btn" type="submit">Continue to payment</button>
  </form>

  <!-- Step 2: pay -->
  <section class="card" id="f2" hidden>
    <div class="step"><b>2</b><h2>Pay <span id="amt2"></span> by UPI</h2></div>
    <div class="qrbox">
      <div id="qr" aria-label="UPI payment QR code"></div>
      <div class="note">Scan with GPay, PhonePe, Paytm or any UPI app</div>
      <div class="pending"><b>Paying on this same phone?</b> Tap the button below, or take a screenshot of this QR → open GPay → <b>Scan any QR</b> → choose the screenshot from gallery → pay.</div>
      <?php if (UPI_NAME): ?><div class="note">Payee name in your UPI app: <strong id="payee"></strong></div><?php endif; ?>
      <div class="upi"><span id="upiid"></span><button type="button" id="copy">Copy UPI ID</button></div>
    </div>
    <a class="btn gpay" id="gpaylink" href="#">Pay with Google Pay</a>
    <a class="btn alt" id="paylink" href="#">Pay with other UPI app (PhonePe, Paytm…)</a>
    <p class="err" id="e3" hidden></p>
    <label for="utr">UPI transaction ID / UTR (12 digits)<input id="utr" inputmode="numeric" maxlength="12" placeholder="Shown in your UPI app after payment"></label>
    <p class="note">GPay: open the payment → <b>UPI transaction ID</b>. PhonePe: <b>UTR</b>. Paytm: <b>UPI Ref No</b>.</p>
    <p class="err" id="e2" hidden></p>
    <button class="btn" id="paid" type="button">Payment done — submit booking</button>
    <button class="btn alt" id="back" type="button">Change details</button>
  </section>

  <!-- Step 3: submitted -->
  <section class="card done" id="f3" hidden>
    <div class="step"><b>3</b><h2>Booking received 🙏</h2></div>
    <div class="note">Your booking ID</div>
    <div class="bid" id="bid"></div>
    <div class="pending">We will verify your payment and send your <b>QR entry ticket on WhatsApp</b> to <b id="momask"></b>. Please save this booking ID.</div>
    <a class="btn alt" id="chk" href="status.php">Check booking status</a>
    <a class="btn wa" id="wa" href="#" target="_blank" rel="noopener">Questions? WhatsApp the organiser</a>
  </section>
<?php endif; ?>
  <p class="lnk">Already booked? <a href="status.php">Check status / get your ticket</a></p>
  <footer>Organised by <?= h(EVENT_NAME) ?> team · Please carry your ticket QR at entry</footer>
</div>
<?php if (!$closed): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
const CFG = <?= json_encode($cfg, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
const $ = id => document.getElementById(id);
let pax = 1, booking = {}, sending = false;
const fmt = n => "₹" + n.toLocaleString("en-IN");
function setPax(n){pax=Math.max(1,Math.min(CFG.maxPax,n));$("pax").textContent=pax;$("tot").textContent=fmt(pax*CFG.price)}
$("minus").onclick=()=>setPax(pax-1); $("plus").onclick=()=>setPax(pax+1);
function show(id){["f1","f2","f3"].forEach(s=>$(s).hidden=s!==id);$(id).scrollIntoView({behavior:"smooth",block:"start"})}
function err(id,msg){$(id).textContent=msg;$(id).hidden=!msg}
function newRef(){const a="ABCDEFGHJKMNPQRSTUVWXYZ23456789",r=crypto.getRandomValues(new Uint32Array(5));return "VGN-"+[...r].map(x=>a[x%a.length]).join("")}
function gpayUri(uri){const q=uri.split("?")[1],ua=navigator.userAgent;
  if(/Android/i.test(ua)) return `intent://pay?${q}#Intent;scheme=upi;package=com.google.android.apps.nbu.paisa.user;end`;
  if(/iPhone|iPad|iPod/i.test(ua)) return `gpay://upi/pay?${q}`; return uri;}

$("f1").addEventListener("submit",e=>{
  e.preventDefault();
  const nm=$("nm").value.trim().replace(/\s+/g," "), mo=$("mo").value.replace(/\D/g,"").slice(-10), em=$("em").value.trim();
  if(nm.length<2) return err("e1","Enter your full name.");
  if(!/^[6-9]\d{9}$/.test(mo)) return err("e1","Enter a valid 10-digit mobile number.");
  if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) return err("e1","Enter a valid email address.");
  err("e1","");
  const amt=pax*CFG.price;
  if(!booking.ref || booking.pax!==pax) booking.ref=newRef();       // keep same ref if they come back
  Object.assign(booking,{nm,mo,em,pax,amt});
  const uri=`upi://pay?pa=${encodeURIComponent(CFG.upi)}&pn=${encodeURIComponent(CFG.upiName)}&am=${amt}.00&cu=INR&tn=${encodeURIComponent("Garba "+booking.ref)}`;
  $("amt2").textContent=fmt(amt); $("upiid").textContent=CFG.upi; if($("payee")) $("payee").textContent=CFG.upiName;
  $("paylink").href=uri; $("gpaylink").href=gpayUri(uri);
  $("qr").innerHTML=""; if(window.QRCode) new QRCode($("qr"),{text:uri,width:200,height:200,correctLevel:QRCode.CorrectLevel.M});
  show("f2");
});
$("copy").onclick=()=>{navigator.clipboard?.writeText(CFG.upi).then(()=>$("copy").textContent="Copied",()=>{})};
$("back").onclick=()=>show("f1");
["paylink","gpaylink"].forEach(id=>$(id).addEventListener("click",e=>{
  e.preventDefault(); const uri=$(id).href; if(!/^(upi|intent|gpay):/.test(uri)) return;
  err("e3",""); let left=false; const onHide=()=>{left=true};
  document.addEventListener("visibilitychange",onHide,{once:true}); window.addEventListener("blur",onHide,{once:true});
  location.href=uri;
  setTimeout(()=>{ if(!left && !document.hidden) err("e3","Your UPI app didn't open. Tap Copy UPI ID and pay "+fmt(booking.amt)+" in GPay / PhonePe / Paytm, or use the QR screenshot method above."); },1800);
}));
$("paid").onclick=async()=>{
  if(sending) return;
  const utr=$("utr").value.replace(/\D/g,"");
  if(utr.length!==12) return err("e2","Enter the 12-digit UTR / transaction ID from your UPI app.");
  err("e2",""); sending=true; const btn=$("paid"); btn.disabled=true; btn.textContent="Submitting…";
  try{
    const res=await fetch("save.php",{method:"POST",headers:{"Content-Type":"application/json"},
      body:JSON.stringify({nm:booking.nm,mo:booking.mo,em:booking.em,pax:booking.pax,ref:booking.ref,utr})});
    const j=await res.json().catch(()=>({}));
    if(!res.ok || !j.booking_id) throw new Error(j.error||"Could not submit. Check your internet and tap again — you will NOT be charged again.");
    $("bid").textContent=j.booking_id; $("momask").textContent="+91 "+booking.mo.slice(0,2)+"xxxxxx"+booking.mo.slice(-2);
    $("chk").href="status.php?b="+encodeURIComponent(j.booking_id);
    $("wa").href=`https://wa.me/${CFG.wa}?text=${encodeURIComponent(`${CFG.event} booking\nBooking ID: ${j.booking_id}\nName: ${booking.nm}\nUTR: ${utr}`)}`;
    show("f3");
  }catch(ex){ err("e2",ex.message); }
  finally{ sending=false; btn.disabled=false; btn.textContent="Payment done — submit booking"; }
};
</script>
<?php endif; ?>
</body></html>
