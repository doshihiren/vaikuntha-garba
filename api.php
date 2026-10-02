<?php
// Staff actions (JSON). Every change is written to the activity log.
require __DIR__ . '/lib.php';
header('Content-Type: application/json'); header('Cache-Control: no-store');
function out($code, $arr) { http_response_code($code); echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }
start_session();
$role = role();
if (!$role) out(401, ['error' => 'Login expired. Please login again.']);
if (!hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF'] ?? '')) out(403, ['error' => 'Session changed. Please reload the page.']);
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$act = $in['action'] ?? '';
$db = db();
if (!in_array($act, ['checkin', 'counts'], true) && $role !== 'admin') out(403, ['error' => 'Gate login can only scan tickets.']);

function entry_counts() {
  $r = db()->query("SELECT COALESCE(SUM(pax),0) total, COALESCE(SUM(CASE WHEN checked_in_at IS NOT NULL THEN pax END),0) inside FROM bookings WHERE status='Approved'")->fetch();
  return ['in' => (int)$r['inside'], 'total' => (int)$r['total']];
}

switch ($act) {
case 'counts':
  out(200, ['counts' => entry_counts()]);

case 'list':
  $rows = $db->query('SELECT * FROM bookings ORDER BY id DESC')->fetchAll();
  out(200, ['rows' => array_map('admin_row', $rows)]);

case 'status':
  $st = $in['status'] ?? ''; $bid = (string)($in['booking_id'] ?? '');
  if (!in_array($st, STATUSES, true)) out(422, ['error' => 'Bad status']);
  $db->exec('BEGIN IMMEDIATE');
  $r = get_booking($bid);
  if (!$r) { $db->exec('ROLLBACK'); out(404, ['error' => 'Booking not found']); }
  if ($r['checked_in_at'] && $st !== 'Approved') { $db->exec('ROLLBACK'); out(409, ['error' => 'Already entered the event — undo the entry first.']); }
  if ($r['status'] !== $st) {
    $token = $r['token'] ?: bin2hex(random_bytes(12));
    $db->prepare("UPDATE bookings SET status=?, token=?,
        approved_at = CASE WHEN ?='Approved' THEN COALESCE(approved_at, ?) ELSE approved_at END,
        rejected_at = CASE WHEN ?='Rejected' THEN ? ELSE NULL END WHERE booking_id=?")
       ->execute([$st, $st === 'Approved' ? $token : $r['token'], $st, now(), $st, now(), $bid]);
    log_act($bid, 'status', $r['status'] . ' → ' . $st);
  }
  $db->exec('COMMIT');
  out(200, ['row' => admin_row(get_booking($bid))]);

case 'wa_sent':
  // kind: 'text' = message opened in WhatsApp, 'image' = ticket image shared. "Ticket sent" is set only by the image
  // (or by the message for a rejected booking, which has no ticket).
  $bid = (string)($in['booking_id'] ?? ''); $kind = ($in['kind'] ?? 'text') === 'image' ? 'image' : 'text';
  $r = get_booking($bid);
  if ($r) {
    if ($kind === 'image' || $r['status'] !== 'Approved') $db->prepare('UPDATE bookings SET wa_sent_at=? WHERE booking_id=?')->execute([now(), $bid]);
    log_act($bid, 'whatsapp', $kind === 'image' ? 'ticket image shared' : ($r['status'] === 'Approved' ? 'ticket message opened' : 'rejection message opened'));
    $r = get_booking($bid);
  }
  out(200, ['row' => $r ? admin_row($r) : null]);

case 'undo_entry':
  $bid = (string)($in['booking_id'] ?? '');
  $db->prepare('UPDATE bookings SET checked_in_at=NULL, checked_in_by=NULL WHERE booking_id=?')->execute([$bid]);
  log_act($bid, 'entry undone');
  out(200, ['row' => admin_row(get_booking($bid))]);

case 'checkin':
  $code = trim((string)($in['code'] ?? '')); $manual = !empty($in['manual']);
  $r = null;
  if (preg_match('/(?:^|[?&]t=)([a-f0-9]{24})(?:$|&)/i', $code, $m)) {
    $q = $db->prepare('SELECT * FROM bookings WHERE token=?'); $q->execute([strtolower($m[1])]); $r = $q->fetch() ?: null;
  } elseif ($manual) {
    $r = get_booking(strtoupper(preg_replace('/\s/', '', $code)));
  }
  if (!$r) { log_act(null, 'scan invalid', mb_substr($code, 0, 80)); out(200, ['result' => 'invalid', 'counts' => entry_counts()]); }
  // Atomic: only ONE scan can ever win, even if two gate phones scan the same QR at the same second
  $u = $db->prepare("UPDATE bookings SET checked_in_at=?, checked_in_by=? WHERE booking_id=? AND status='Approved' AND checked_in_at IS NULL");
  $u->execute([now(), $role . ($manual ? ' (manual ID)' : ' (QR)'), $r['booking_id']]);
  $res = $u->rowCount() === 1 ? 'ok' : ($r['status'] !== 'Approved' ? 'not_approved' : 'used');
  $r = get_booking($r['booking_id']);
  log_act($r['booking_id'], 'scan ' . $res, ($manual ? 'manual ID · ' : 'QR · ') . $r['pax'] . ' pax');
  out(200, ['result' => $res, 'manual' => $manual, 'counts' => entry_counts(),
    'row' => ['booking_id' => $r['booking_id'], 'name' => $r['name'], 'pax' => (int)$r['pax'], 'status' => $r['status'], 'checked_in_at' => $r['checked_in_at'], 'checked_in_by' => $r['checked_in_by']]]);

default: out(400, ['error' => 'Unknown action']);
}
