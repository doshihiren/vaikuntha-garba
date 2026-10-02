<?php
require __DIR__ . '/config.php';
date_default_timezone_set('Asia/Kolkata');
const DB_FILE = __DIR__ . '/data/.ht-garba.sqlite';   // ".ht" prefix + data/.htaccess = never downloadable
const DATA_JSON_OLD = __DIR__ . '/data/bookings.json';
const STATUSES = ['Pending', 'Approved', 'Rejected'];

function now() { return date('Y-m-d H:i:s'); }
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES); }
function inr($n) { return '₹' . number_format((float)$n); }

function db() {
  static $db = null; if ($db) return $db;
  if (!extension_loaded('pdo_sqlite')) { http_response_code(500); exit('Server setup: please enable the PHP "pdo_sqlite" extension in Hostinger → Advanced → PHP Configuration.'); }
  if (!is_dir(dirname(DB_FILE))) mkdir(dirname(DB_FILE), 0755, true);
  $db = new PDO('sqlite:' . DB_FILE, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
  $db->exec('PRAGMA busy_timeout = 8000; PRAGMA journal_mode = WAL; PRAGMA foreign_keys = ON; PRAGMA synchronous = NORMAL;');
  $db->exec("CREATE TABLE IF NOT EXISTS bookings (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      booking_id TEXT NOT NULL UNIQUE,
      created_at TEXT NOT NULL,
      name TEXT NOT NULL, mobile TEXT NOT NULL, email TEXT NOT NULL,
      pax INTEGER NOT NULL CHECK (pax BETWEEN 1 AND 100),
      amount INTEGER NOT NULL,
      utr TEXT NOT NULL UNIQUE,
      pay_note TEXT,
      status TEXT NOT NULL DEFAULT 'Pending' CHECK (status IN ('Pending','Approved','Rejected')),
      token TEXT UNIQUE,
      approved_at TEXT, rejected_at TEXT, wa_sent_at TEXT,
      checked_in_at TEXT, checked_in_by TEXT,
      ip TEXT);
    CREATE INDEX IF NOT EXISTS ix_status ON bookings(status);
    CREATE INDEX IF NOT EXISTS ix_mobile ON bookings(mobile);
    CREATE TABLE IF NOT EXISTS activity (
      id INTEGER PRIMARY KEY AUTOINCREMENT, at TEXT NOT NULL, booking_id TEXT, action TEXT NOT NULL, detail TEXT, by_role TEXT, ip TEXT);
    CREATE TABLE IF NOT EXISTS attempts (ip TEXT NOT NULL, kind TEXT NOT NULL, at INTEGER NOT NULL);
    CREATE INDEX IF NOT EXISTS ix_att ON attempts(ip, kind, at);");
  try { migrate_json($db); } catch (Throwable $e) { error_log('migrate: ' . $e->getMessage()); }
  return $db;
}
// One-time import from the earlier JSON version (if it was ever used)
function migrate_json($db) {
  if (!is_file(DATA_JSON_OLD)) return;
  $txt = trim(file_get_contents(DATA_JSON_OLD)); $j = json_decode($txt, true);
  if (is_array($j) && isset($j['utr'])) $j = [$j];          // single booking saved as one line
  $rows = is_array($j) ? $j : array_filter(array_map(fn($l) => json_decode($l, true), explode("\n", $txt)));
  $ins = $db->prepare('INSERT OR IGNORE INTO bookings (booking_id,created_at,name,mobile,email,pax,amount,utr,status,token,approved_at,wa_sent_at,checked_in_at)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
  foreach ($rows as $r) {
    if (!is_array($r) || empty($r['utr']) || empty($r['id'])) continue;
    $st = ($r['status'] ?? '') === 'Confirmed' ? 'Approved' : ($r['status'] ?? 'Pending');
    if (!in_array($st, STATUSES, true)) $st = 'Pending';
    $tok = $st === 'Approved' ? ($r['token'] ?? null ?: bin2hex(random_bytes(12))) : ($r['token'] ?? null);
    $ins->execute([$r['id'], $r['time'], $r['name'], $r['mobile'], $r['email'], (int)$r['pax'], (int)$r['amount'], $r['utr'], $st, $tok,
                   $r['approved_at'] ?? null, $r['wa_sent_at'] ?? null, $r['checked_in_at'] ?? null]);
  }
  rename(DATA_JSON_OLD, DATA_JSON_OLD . '.imported');
}

function client_ip() { return $_SERVER['REMOTE_ADDR'] ?? '0'; }
function log_act($bid, $action, $detail = '', $role = null) {
  db()->prepare('INSERT INTO activity (at,booking_id,action,detail,by_role,ip) VALUES (?,?,?,?,?,?)')
      ->execute([now(), $bid, $action, $detail, $role ?? (role() ?: 'customer'), client_ip()]);
}
// Simple per-IP limiter: true = allowed
function rate_ok($kind, $max, $windowSec) {
  $db = db(); $t = time();
  $db->prepare('DELETE FROM attempts WHERE at < ?')->execute([$t - 86400]);
  $q = $db->prepare('SELECT COUNT(*) FROM attempts WHERE ip=? AND kind=? AND at>=?'); $q->execute([client_ip(), $kind, $t - $windowSec]);
  return (int)$q->fetchColumn() < $max;
}
function rate_hit($kind) { db()->prepare('INSERT INTO attempts (ip,kind,at) VALUES (?,?,?)')->execute([client_ip(), $kind, time()]); }

function booking_closed_reason() {
  if (BOOKING_CLOSES && time() >= strtotime(BOOKING_CLOSES)) return 'Online booking is closed.';
  return null;
}
function seats_left() {
  if (!CAPACITY) return null;
  $used = (int)db()->query("SELECT COALESCE(SUM(pax),0) FROM bookings WHERE status IN ('Pending','Approved')")->fetchColumn();
  return max(0, CAPACITY - $used);
}
function new_booking_id() { $a = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; $s = ''; for ($i = 0; $i < 5; $i++) $s .= $a[random_int(0, strlen($a) - 1)]; return 'VGN-' . $s; }

function base_url() {
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
  return ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
}
function ticket_url($r) { return base_url() . '/ticket.php?t=' . $r['token']; }
function wa_approved($r) {
  $p = (int)$r['pax'];
  return "🙏 Jai Mataji {$r['name']}!\n\nYour booking for *" . EVENT_NAME . "* is CONFIRMED ✅\n\n"
    . "🎟 Booking ID: {$r['booking_id']}\n👥 Persons: $p\n📅 " . EVENT_DATE . ", " . EVENT_TIME . "\n📍 " . EVENT_VENUE . "\n🗺 Map: " . EVENT_MAP . "\n\n"
    . "👉 Your entry QR ticket (open & show at the gate):\n" . ticket_url($r) . "\n\n"
    . "This QR is valid for all $p " . ($p > 1 ? 'persons' : 'person') . " and works only once — please enter together. Do not share this link.";
}
function wa_rejected($r) {
  return "Hello {$r['name']},\n\nWe could not find your payment for *" . EVENT_NAME . "* booking {$r['booking_id']} (UTR {$r['utr']}).\n"
    . "Please reply here with a screenshot of the payment so we can check again. 🙏";
}
function wa_link($r) {
  $msg = $r['status'] === 'Approved' ? wa_approved($r) : ($r['status'] === 'Rejected' ? wa_rejected($r) : null);
  return $msg ? 'https://wa.me/91' . $r['mobile'] . '?text=' . rawurlencode($msg) : null;
}
function admin_row($r) {
  $r['pax'] = (int)$r['pax']; $r['amount'] = (int)$r['amount'];
  $r['wa'] = wa_link($r); $r['ticket'] = $r['status'] === 'Approved' ? ticket_url($r) : null;
  unset($r['ip']); return $r;
}
function get_booking($bid) { $q = db()->prepare('SELECT * FROM bookings WHERE booking_id=?'); $q->execute([$bid]); return $q->fetch() ?: null; }

// ---------------- staff login (two roles) ----------------
function start_session() {
  if (session_status() === PHP_SESSION_ACTIVE) return;
  session_name('garba_staff');
  ini_set('session.gc_maxlifetime', (string)(7 * 86400));
  session_set_cookie_params(['lifetime' => 7 * 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')]);
  session_start();
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
function role() { if (session_status() !== PHP_SESSION_ACTIVE) return null; return $_SESSION['role'] ?? null; }
function login_page($msg = '') { header('Cache-Control: no-store'); ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Staff login</title>
<style>body{font-family:system-ui,sans-serif;background:#FBF3E4;color:#3A1A12;display:grid;place-items:center;min-height:100vh;margin:0;padding:16px}
form{background:#fff;padding:24px;border-radius:16px;display:flex;flex-direction:column;gap:12px;width:100%;max-width:320px;box-shadow:0 2px 12px #0001}
input,button{font:inherit;padding:12px;border-radius:10px;border:1px solid #ddd}button{background:#9E1B32;color:#fff;border:0;font-weight:700}
b{color:#9E1B32}.e{color:#B3261E;font-size:.9rem;margin:0}small{color:#7A5A4C}</style></head><body>
<form method="post"><b><?= h(EVENT_NAME) ?> · Staff</b><?php if ($msg): ?><p class="e"><?= h($msg) ?></p><?php endif; ?>
<input type="password" name="pw" placeholder="Password" autofocus required autocomplete="current-password"><button>Login</button>
<small>Organiser password = full access · Gate password = scan only</small></form></body></html>
<?php exit; }
// $need: 'admin' or 'gate' (gate pages also allow admin)
function require_staff($need = 'admin') {
  start_session();
  if (isset($_GET['logout'])) { $_SESSION = []; session_destroy(); header('Location: admin.php'); exit; }
  if (strlen(ADMIN_PASSWORD) < 6) login_page('Setup needed: create data/secrets.php on the server with ADMIN_PASSWORD (at least 6 characters).');
  if (isset($_POST['pw'])) {
    if (!rate_ok('login', 6, 900)) login_page('Too many wrong tries. Wait 15 minutes.');
    $pw = (string)$_POST['pw']; $r = null;
    if (hash_equals(ADMIN_PASSWORD, $pw)) $r = 'admin'; elseif (GATE_PASSWORD !== '' && hash_equals(GATE_PASSWORD, $pw)) $r = 'gate';
    if (!$r) { rate_hit('login'); login_page('Wrong password.'); }
    session_regenerate_id(true); $_SESSION['role'] = $r; $_SESSION['csrf'] = bin2hex(random_bytes(16));
    log_act(null, 'login', '', $r);
    header('Location: ' . ($r === 'gate' && $need === 'admin' ? 'scan.php' : strtok($_SERVER['REQUEST_URI'], '#'))); exit;
  }
  $r = role();
  if (!$r) login_page();
  if ($need === 'admin' && $r !== 'admin') { header('Location: scan.php'); exit; }
  header('Cache-Control: no-store');
  return $r;
}
