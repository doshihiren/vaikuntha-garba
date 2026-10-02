<?php
require __DIR__ . '/lib.php';
require_staff('admin');
$db = db();

if (isset($_GET['csv'])) {                       // Excel-friendly CSV of all bookings
  $rows = $db->query('SELECT * FROM bookings ORDER BY id')->fetchAll();
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="garba-bookings-' . date('Y-m-d-Hi') . '.csv"');
  $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
  fputcsv($o, ['#', 'Booked at', 'Booking ID', 'Name', 'Mobile', 'Email', 'Persons', 'Amount', 'UTR', 'UPI note', 'Status', 'Approved at', 'Rejected at', 'WhatsApp opened at', 'Entered at', 'Entered via']);
  foreach ($rows as $i => $r) fputcsv($o, [$i + 1, $r['created_at'], $r['booking_id'], $r['name'], $r['mobile'], $r['email'], $r['pax'], $r['amount'],
    '="' . $r['utr'] . '"', $r['pay_note'], $r['status'], $r['approved_at'], $r['rejected_at'], $r['wa_sent_at'], $r['checked_in_at'], $r['checked_in_by']]);
  exit;
}
if (isset($_GET['logcsv'])) {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="garba-activity-' . date('Y-m-d-Hi') . '.csv"');
  $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF"); fputcsv($o, ['Time', 'Booking ID', 'Action', 'Detail', 'By', 'IP']);
  foreach ($db->query('SELECT at,booking_id,action,detail,by_role,ip FROM activity ORDER BY id') as $r) fputcsv($o, array_values($r));
  exit;
}
if (isset($_GET['backup'])) {                    // full database copy (keep it safe)
  $tmp = sys_get_temp_dir() . '/garba-' . bin2hex(random_bytes(4)) . '.sqlite';
  try { $db->exec('VACUUM INTO ' . $db->quote($tmp)); } catch (Throwable $e) { $db->exec('PRAGMA wal_checkpoint(FULL)'); copy(DB_FILE, $tmp); }
  log_act(null, 'backup downloaded');
  header('Content-Type: application/octet-stream');
  header('Content-Disposition: attachment; filename="garba-backup-' . date('Y-m-d-Hi') . '.sqlite"');
  header('Content-Length: ' . filesize($tmp)); readfile($tmp); unlink($tmp); exit;
}

$rows = $db->query('SELECT * FROM bookings ORDER BY id')->fetchAll();
$g = fn($st) => array_values(array_filter($rows, fn($r) => $r['status'] === $st));
$sum = fn($a, $k) => array_sum(array_column($a, $k));
$ap = $g('Approved'); $pe = $g('Pending'); $rj = $g('Rejected');
$in = array_values(array_filter($ap, fn($r) => $r['checked_in_at']));
$notSent = array_values(array_filter($ap, fn($r) => !$r['wa_sent_at']));
$dt = fn($s) => $s ? date('j M, g:i A', strtotime($s)) : '—';
$byDay = $db->query("SELECT substr(created_at,1,10) d, COUNT(*) n, SUM(pax) p, SUM(CASE WHEN status='Approved' THEN amount ELSE 0 END) a FROM bookings GROUP BY d ORDER BY d")->fetchAll();
$log = $db->query('SELECT * FROM activity ORDER BY id DESC LIMIT 60')->fetchAll();
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Report · <?= h(EVENT_NAME) ?></title><?php include 'admin_style.php'; ?>
<style>
h1{font-size:1.4rem;margin:0}h2{font-size:1.05rem;margin:22px 0 8px;color:var(--accent)}
table{width:100%;border-collapse:collapse;font-size:.88rem}th,td{padding:7px 9px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}
th{background:#F3E6CF;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em}td.n,th.n{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
tfoot td{font-weight:800;background:#F8EFDD}.wrap{overflow-x:auto;background:var(--card);border:1px solid var(--line);border-radius:12px}
.bar{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 4px}.mono{font-family:ui-monospace,Menlo,monospace}
@media print{header,.bar,.noprint{display:none!important}body{background:#fff;font-size:11px}main{max-width:none;padding:0}.wrap{border:0;overflow:visible}h2{break-after:avoid}tr{break-inside:avoid}}
</style></head><body>
<?php include 'admin_nav.php'; ?>
<main>
<h1><?= h(EVENT_NAME) ?> — Booking report</h1>
<div class="muted"><?= h(EVENT_DATE) ?> · <?= h(EVENT_VENUE) ?> · Generated <?= date('j M Y, g:i A') ?></div>
<div class="bar"><button class="btn p" onclick="print()">🖨 Print / Save PDF</button><a class="btn g" href="?csv=1">⬇ Bookings Excel</a><a class="btn g" href="?logcsv=1">⬇ Activity log</a><a class="btn g" href="?backup=1">💾 Backup database</a></div>

<h2>Summary</h2>
<div class="wrap"><table>
<thead><tr><th>Status</th><th class="n">Bookings</th><th class="n">Persons</th><th class="n">Amount</th></tr></thead>
<tbody>
<tr><td>✓ Approved (money received)</td><td class="n"><?= count($ap) ?></td><td class="n"><?= $sum($ap, 'pax') ?></td><td class="n"><?= inr($sum($ap, 'amount')) ?></td></tr>
<tr><td>⏳ Pending (to verify)</td><td class="n"><?= count($pe) ?></td><td class="n"><?= $sum($pe, 'pax') ?></td><td class="n"><?= inr($sum($pe, 'amount')) ?></td></tr>
<tr><td>✕ Rejected</td><td class="n"><?= count($rj) ?></td><td class="n"><?= $sum($rj, 'pax') ?></td><td class="n"><?= inr($sum($rj, 'amount')) ?></td></tr>
</tbody>
<tfoot><tr><td>Total submitted</td><td class="n"><?= count($rows) ?></td><td class="n"><?= $sum($rows, 'pax') ?></td><td class="n"><?= inr($sum($rows, 'amount')) ?></td></tr></tfoot>
</table></div>

<h2>Gate / entry</h2>
<div class="wrap"><table><tbody>
<tr><td>Entered</td><td class="n"><?= count($in) ?> bookings · <?= $sum($in, 'pax') ?> persons</td></tr>
<tr><td>Yet to arrive</td><td class="n"><?= count($ap) - count($in) ?> bookings · <?= $sum($ap, 'pax') - $sum($in, 'pax') ?> persons</td></tr>
<tr><td>Approved but WhatsApp ticket not sent</td><td class="n"><?= count($notSent) ?> bookings</td></tr>
<?php if (CAPACITY): ?><tr><td>Passes left (capacity <?= CAPACITY ?>)</td><td class="n"><?= seats_left() ?></td></tr><?php endif; ?>
</tbody></table></div>

<?php if ($byDay): ?>
<h2>Bookings by day</h2>
<div class="wrap"><table><thead><tr><th>Date</th><th class="n">Bookings</th><th class="n">Persons</th><th class="n">Approved ₹</th></tr></thead><tbody>
<?php foreach ($byDay as $d): ?><tr><td><?= h(date('D, j M', strtotime($d['d']))) ?></td><td class="n"><?= $d['n'] ?></td><td class="n"><?= $d['p'] ?></td><td class="n"><?= inr($d['a']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>

<?php foreach ([['Approved bookings', $ap, 'ap'], ['Pending — verify UTR in bank', $pe, 'pe'], ['Rejected', $rj, 'rj']] as [$title, $list, $k]): if (!$list) continue; ?>
<h2><?= $title ?> (<?= count($list) ?>)</h2>
<div class="wrap"><table>
<thead><tr><th>#</th><th>Booking</th><th>Name / Mobile</th><th class="n">Pax</th><th class="n">Amount</th><th>UTR</th><?php if ($k === 'ap'): ?><th>Ticket sent</th><th>Entered</th><?php else: ?><th>Booked</th><?php endif; ?></tr></thead>
<tbody>
<?php foreach ($list as $i => $r): ?>
<tr><td><?= $i + 1 ?></td><td class="mono"><?= h($r['booking_id']) ?></td><td><?= h($r['name']) ?><br><span class="muted"><?= h($r['mobile']) ?></span></td>
<td class="n"><?= (int)$r['pax'] ?></td><td class="n"><?= inr($r['amount']) ?></td><td class="mono"><?= h($r['utr']) ?></td>
<?php if ($k === 'ap'): ?><td><?= $dt($r['wa_sent_at']) ?></td><td><?= $dt($r['checked_in_at']) ?></td><?php else: ?><td><?= $dt($r['created_at']) ?></td><?php endif; ?></tr>
<?php endforeach; ?>
</tbody>
<tfoot><tr><td colspan="3">Total</td><td class="n"><?= $sum($list, 'pax') ?></td><td class="n"><?= inr($sum($list, 'amount')) ?></td><td colspan="<?= $k === 'ap' ? 3 : 2 ?>"></td></tr></tfoot>
</table></div>
<?php endforeach; if (!$rows) echo '<p class="muted">No bookings yet.</p>'; ?>

<div class="noprint">
<h2>Recent activity (who did what)</h2>
<div class="wrap"><table><thead><tr><th>Time</th><th>Booking</th><th>Action</th><th>By</th></tr></thead><tbody>
<?php foreach ($log as $l): ?><tr><td><?= $dt($l['at']) ?></td><td class="mono"><?= h($l['booking_id'] ?: '—') ?></td><td><?= h($l['action']) ?><?= $l['detail'] ? '<br><span class="muted">' . h($l['detail']) . '</span>' : '' ?></td><td><?= h($l['by_role']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
</div>
</main></body></html>
