<?php
// Customer's QR entry ticket (link sent on WhatsApp after approval).
require __DIR__ . '/lib.php';
header('Cache-Control: no-store'); header('X-Robots-Tag: noindex');
$t = strtolower(preg_replace('/[^a-f0-9]/i', '', $_GET['t'] ?? ''));
$row = null;
if (strlen($t) === 24) { $q = db()->prepare('SELECT * FROM bookings WHERE token=?'); $q->execute([$t]); $row = $q->fetch() ?: null; }
$valid = $row && $row['status'] === 'Approved';
$used  = $valid && $row['checked_in_at'];
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Entry ticket · <?= h(EVENT_NAME) ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Yatra+One&family=Mukta:wght@400;600;700&display=swap">
<style>
body{margin:0;background:#FBF3E4;color:#3A1A12;font-family:Mukta,system-ui,sans-serif;padding:16px;display:flex;justify-content:center}
.t{background:#FFFBF3;border:1px solid #E8D6B8;border-radius:18px;max-width:380px;width:100%;overflow:hidden;box-shadow:0 4px 20px #3A1A1214}
.hd{background:#9E1B32;color:#FFF7EC;padding:16px 18px}.hd h1{font-family:"Yatra One",serif;font-weight:400;margin:0;font-size:1.6rem;line-height:1.2}
.hd div{opacity:.9;font-size:.9rem}
.qr{display:flex;flex-direction:column;align-items:center;gap:8px;padding:20px 18px;border-bottom:2px dashed #E8D6B8}
#qr{background:#fff;padding:12px;border-radius:12px;line-height:0;min-width:244px;min-height:244px;display:grid;place-items:center}#qr.used{opacity:.12}
.st{font-weight:700;border-radius:999px;padding:4px 14px;text-align:center}.ok{background:#D8F0E2;color:#1E7A4A}.bad{background:#F9DEDC;color:#B3261E}
dl{display:grid;grid-template-columns:auto 1fr;gap:6px 14px;margin:0;padding:16px 18px}dt{color:#7A5A4C;font-size:.85rem}dd{margin:0;font-weight:600}
.big{font-family:ui-monospace,Menlo,monospace;font-size:1.25rem;letter-spacing:.06em}
.f{padding:0 18px 18px;font-size:.85rem;color:#7A5A4C}a{color:#9E1B32;font-weight:700}.msg{padding:30px 20px;text-align:center}
</style></head><body><div class="t">
<div class="hd"><h1><?= h(EVENT_NAME) ?></h1><div>Entry pass · <?= h(EVENT_DATE) ?></div></div>
<?php if (!$valid): ?>
  <div class="msg"><p class="st bad" style="display:inline-block">Ticket not valid</p><p>This ticket is not active. Please contact the organiser on WhatsApp.</p>
  <p><a href="https://wa.me/<?= h(ORGANISER_WHATSAPP) ?>">WhatsApp organiser</a></p></div>
<?php else: ?>
  <div class="qr">
    <div id="qr" class="<?= $used ? 'used' : '' ?>"><?php if (!$used): ?><span style="line-height:1.4;color:#7A5A4C">Loading QR…<br>Show Booking ID below if it doesn't load</span><?php endif; ?></div>
    <?php if ($used): ?><span class="st bad">Already used · entered <?= h(date('j M, g:i A', strtotime($row['checked_in_at']))) ?></span>
    <?php else: ?><span class="st ok">Valid · show this QR at the gate</span><?php endif; ?>
  </div>
  <dl>
    <dt>Booking ID</dt><dd class="big"><?= h($row['booking_id']) ?></dd>
    <dt>Name</dt><dd><?= h($row['name']) ?></dd>
    <dt>Persons</dt><dd><?= (int)$row['pax'] ?></dd>
    <dt>When</dt><dd><?= h(EVENT_DATE) ?> · <?= h(EVENT_TIME) ?></dd>
    <dt>Venue</dt><dd><?= h(EVENT_VENUE) ?><br><a href="<?= h(EVENT_MAP) ?>" target="_blank" rel="noopener">Open in Maps</a></dd>
  </dl>
  <div class="f">One QR for all <?= (int)$row['pax'] ?> <?= $row['pax'] > 1 ? 'persons' : 'person' ?> · works for one entry only · please enter together. Tip: take a screenshot of this ticket.</div>
  <?php if (!$used): ?>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <script>if(window.QRCode){const e=document.getElementById("qr");e.innerHTML="";new QRCode(e,{text:<?= json_encode(ticket_url($row)) ?>,width:220,height:220,correctLevel:QRCode.CorrectLevel.M});}</script>
  <?php endif; ?>
<?php endif; ?>
</div></body></html>
