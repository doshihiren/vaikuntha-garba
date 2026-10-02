<?php
// Customer submits booking after paying. Saved as "Pending" until organiser approves.
require __DIR__ . '/lib.php';
header('Content-Type: application/json'); header('Cache-Control: no-store');
function out($code, $arr) { http_response_code($code); echo json_encode($arr); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['error' => 'POST only']);
$b = json_decode(file_get_contents('php://input'), true);
if (!is_array($b)) out(400, ['error' => 'Bad request']);

$nm  = trim(preg_replace('/\s+/u', ' ', strip_tags((string)($b['nm'] ?? ''))));
$nm  = mb_substr($nm, 0, 60);
$mo  = substr(preg_replace('/\D/', '', (string)($b['mo'] ?? '')), -10);
$em  = mb_substr(trim((string)($b['em'] ?? '')), 0, 100);
$pax = (int)($b['pax'] ?? 0);
$utr = preg_replace('/\D/', '', (string)($b['utr'] ?? ''));
$ref = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', (string)($b['ref'] ?? '')));

if (mb_strlen($nm) < 2) out(422, ['error' => 'Enter your full name.']);
if (!preg_match('/^[6-9]\d{9}$/', $mo)) out(422, ['error' => 'Enter a valid 10-digit mobile number.']);
if (!filter_var($em, FILTER_VALIDATE_EMAIL)) out(422, ['error' => 'Enter a valid email address.']);
if ($pax < 1 || $pax > MAX_PAX) out(422, ['error' => 'Persons must be between 1 and ' . MAX_PAX . '.']);
if (strlen($utr) !== 12) out(422, ['error' => 'Enter the 12-digit UTR / transaction ID.']);

$db = db();
// Same UTR + same mobile again (e.g. network retry) → return the same booking, never a duplicate
$q = $db->prepare('SELECT booking_id, mobile FROM bookings WHERE utr=?'); $q->execute([$utr]);
if ($ex = $q->fetch()) {
  if ($ex['mobile'] === $mo) out(200, ['booking_id' => $ex['booking_id'], 'repeat' => true]);
  out(409, ['error' => 'This UTR is already used for another booking. Please check the number.']);
}
if (!rate_ok('book', 15, 3600)) out(429, ['error' => 'Too many bookings from this network. Please try after some time or WhatsApp the organiser.']);
if ($why = booking_closed_reason()) out(403, ['error' => $why]);

try {
  $db->exec('BEGIN IMMEDIATE');                           // one booking at a time → capacity can never be oversold
  if (CAPACITY) {
    $left = seats_left();
    if ($pax > $left) { $db->exec('ROLLBACK'); out(409, ['error' => $left > 0 ? "Only $left passes left. Please reduce persons." : 'All passes are sold out.']); }
  }
  $bid = preg_match('/^VGN-[A-HJ-NP-Z2-9]{5}$/', $ref) && !get_booking($ref) ? $ref : null;
  for ($i = 0; !$bid && $i < 20; $i++) { $c = new_booking_id(); if (!get_booking($c)) $bid = $c; }
  $db->prepare('INSERT INTO bookings (booking_id,created_at,name,mobile,email,pax,amount,utr,pay_note,status,ip) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
     ->execute([$bid, now(), $nm, $mo, $em, $pax, $pax * PRICE, $utr, $ref ? "Garba $ref" : null, 'Pending', client_ip()]);
  log_act($bid, 'booked', "$pax pax · UTR $utr", 'customer');
  $db->exec('COMMIT');
} catch (PDOException $e) {
  if ($db->inTransaction()) $db->exec('ROLLBACK');
  if (str_contains($e->getMessage(), 'UNIQUE') && str_contains($e->getMessage(), 'utr')) out(409, ['error' => 'This UTR is already used for a booking.']);
  error_log('save.php: ' . $e->getMessage());
  out(500, ['error' => 'Server busy. Please tap submit again — you will not be charged again.']);
}
rate_hit('book');
out(200, ['booking_id' => $bid]);
