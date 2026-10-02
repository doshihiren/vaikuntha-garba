<?php if (!function_exists('h')) exit; $isAdmin = role() === 'admin'; ?>
<header><b><?= h(EVENT_NAME) ?><?= $isAdmin ? '' : ' · Gate' ?></b>
<?php foreach (($isAdmin ? ['admin.php' => 'Bookings', 'scan.php' => 'Scan QR', 'report.php' => 'Report'] : ['scan.php' => 'Scan QR']) as $f => $t): ?>
<a href="<?= $f ?>" class="<?= basename($_SERVER['SCRIPT_NAME']) === $f ? 'on' : '' ?>"><?= $t ?></a>
<?php endforeach; ?><a href="admin.php?logout=1">Logout</a></header>
