<?php
// Customer self-service: check booking status / reopen QR ticket using Booking ID + mobile.
require __DIR__ . '/lib.php';
header('Cache-Control: no-store');
$bid = strtoupper(trim(preg_replace('/\s/', '', $_REQUEST['b'] ?? '')));
$mo  = substr(preg_replace('/\D/', '', $_POST['m'] ?? ''), -10);
$row = null; $msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!rate_ok('status', 20, 3600)) $msg = 'Too many tries. Please try later.';
  else {
    rate_hit('status');
    $r = get_booking($bid);
    if ($r && hash_equals($r['mobile'], $mo)) $row = $r; else $msg = 'No booking found with this Booking ID and mobile number.';
  }
}
$label = ['Pending' => ['⏳ Payment being verified', 'We are checking your payment. Your QR ticket will come on WhatsApp once approved.', '#9A5B00', '#FDEBC8'],
          'Approved' => ['✅ Confirmed', 'Your entry ticket is ready.', '#1E7A4A', '#D8F0E2'],
          'Rejected' => ['✕ Payment not found', 'We could not match your payment. Please WhatsApp the organiser with your payment screenshot.', '#B3261E', '#F9DEDC']];
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Booking status · <?= h(EVENT_NAME) ?></title>
<style>body{margin:0;background:#FBF3E4;color:#3A1A12;font-family:system-ui,sans-serif;padding:16px;display:flex;justify-content:center}
.c{background:#FFFBF3;border:1px solid #E8D6B8;border-radius:16px;padding:20px;max-width:400px;width:100%;display:flex;flex-direction:column;gap:12px}
h1{margin:0;font-size:1.3rem;color:#9E1B32}label{display:flex;flex-direction:column;gap:4px;font-weight:600;font-size:.92rem}
input{font:inherit;padding:11px 12px;border-radius:10px;border:1px solid #E8D6B8}button,.b{font:inherit;font-weight:700;border:0;border-radius:12px;padding:13px;background:#9E1B32;color:#fff;text-align:center;text-decoration:none}
.wa{background:#1E8E4E}.e{color:#B3261E;font-weight:600;margin:0}.st{border-radius:12px;padding:12px}.st b{font-size:1.1rem}a.k{color:#9E1B32;font-weight:700;text-align:center}</style></head><body>
<div class="c"><h1><?= h(EVENT_NAME) ?> · Booking status</h1>
<?php if ($row): [$t, $d, $fg, $bg] = $label[$row['status']]; ?>
  <div class="st" style="color:<?= $fg ?>;background:<?= $bg ?>"><b><?= $t ?></b><br><?= h($d) ?></div>
  <div><?= h($row['name']) ?> · <?= (int)$row['pax'] ?> persons · <?= inr($row['amount']) ?><br><small>Booking ID <?= h($row['booking_id']) ?></small></div>
  <?php if ($row['status'] === 'Approved'): ?><a class="b" href="ticket.php?t=<?= h($row['token']) ?>">Open my QR ticket</a><?php endif; ?>
  <?php if ($row['status'] !== 'Approved'): ?><a class="b wa" href="https://wa.me/<?= h(ORGANISER_WHATSAPP) ?>?text=<?= rawurlencode('Booking ' . $row['booking_id'] . ' · UTR ' . $row['utr']) ?>" target="_blank" rel="noopener">WhatsApp organiser</a><?php endif; ?>
  <a class="k" href="status.php">Check another booking</a>
<?php else: ?>
  <form method="post" style="display:contents">
    <?php if ($msg): ?><p class="e"><?= h($msg) ?></p><?php endif; ?>
    <label>Booking ID<input name="b" value="<?= h($bid) ?>" placeholder="VGN-XXXXX" required autocapitalize="characters"></label>
    <label>Mobile number used while booking<input name="m" inputmode="numeric" maxlength="10" placeholder="10-digit number" required></label>
    <button>Check status</button>
  </form>
  <a class="k" href="./">← Back to booking page</a>
<?php endif; ?>
</div></body></html>
