<?php
/* Admin: sign in, then the list of orders. */
declare(strict_types=1);
require __DIR__ . '/inc/admin.php';

$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['username'])) {
    csrf_check();
    $error = admin_login(trim((string) $_POST['username']), (string) ($_POST['password'] ?? ''));
    if ($error === null) {
        header('Location: index.php');
        exit;
    }
}

$user = admin_user();
if ($user === null) {
    page_start('Sign in', null);
    echo '<div style="max-width:380px;margin:40px auto"><h1 style="font-size:1.6rem">Admin sign in</h1>';
    if ($error) echo '<p class="notice notice--error">' . h($error) . '</p>';
    echo '<form method="post" class="form">' . csrf_field() .
        '<div class="field"><label for="u">Username</label><input id="u" name="username" autocomplete="username" required></div>' .
        '<div class="field"><label for="p">Password</label><input id="p" name="password" type="password" autocomplete="current-password" required></div>' .
        '<button class="btn btn--blue btn--block" type="submit">Sign in</button></form></div>';
    page_end();
    exit;
}

$pdo = dcs_db();
$status = (string) ($_GET['status'] ?? '');
$month = (string) ($_GET['month'] ?? '');
$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$where = [];
$args = [];
if ($status !== '' && isset(STATUS_LABELS[$status])) { $where[] = 'status = ?'; $args[] = $status; }
if (preg_match('/^\d{4}-\d{2}$/', $month)) { $where[] = 'created_at >= ? AND created_at < ?'; $args[] = "$month-01 00:00:00"; $args[] = date('Y-m-01 00:00:00', strtotime("$month-01 +1 month")); }
if ($search !== '') { $where[] = '(ref LIKE ? OR customer_name LIKE ? OR phone LIKE ?)'; $like = '%' . $search . '%'; array_push($args, $like, $like, $like); }
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$count = $pdo->prepare("SELECT COUNT(*) FROM orders $sqlWhere");
$count->execute($args);
$total = (int) $count->fetchColumn();
$per = 50;
$q = $pdo->prepare("SELECT * FROM orders $sqlWhere ORDER BY id DESC LIMIT $per OFFSET " . (($page - 1) * $per));
$q->execute($args);
$orders = $q->fetchAll();
$attention = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'needs_checking' OR (status = 'paid' AND fulfilment = 'not_shipped')")->fetchColumn();

page_start('Orders', $user);
echo '<h1 style="font-size:1.6rem;margin-top:16px">Orders</h1>';
if ($attention) echo '<p class="notice">' . $attention . ' order(s) need attention: paid and not yet sent, or flagged for checking.</p>';
echo '<form class="inline" method="get" style="margin:14px 0"><div class="field"><label>Status</label><select class="select" name="status"><option value="">All</option>';
foreach (STATUS_LABELS as $k => $l) echo '<option value="' . h($k) . '"' . ($k === $status ? ' selected' : '') . '>' . h($l) . '</option>';
echo '</select></div><div class="field"><label>Month</label><input type="month" name="month" value="' . h($month) . '"></div>';
echo '<div class="field"><label>Search</label><input name="q" value="' . h($search) . '" placeholder="Order no., name or phone"></div><button class="btn btn--blue" type="submit">Filter</button></form>';
echo '<p style="color:var(--muted)">' . $total . ' order(s)</p><div class="scroll"><table><thead><tr><th>Order</th><th>Date</th><th>Customer</th><th>Total</th><th>Payment</th><th>Delivery</th></tr></thead><tbody>';
foreach ($orders as $o) {
    echo '<tr><td><a href="order.php?id=' . (int) $o['id'] . '"><b>' . h($o['ref']) . '</b></a></td><td>' . h(substr($o['created_at'], 0, 16)) . '</td><td>' . h($o['customer_name']) . '<br><small>' . h($o['city']) . ', ' . h($o['province']) . '</small></td><td>' . h(dcs_money((int) $o['total_cents'])) . '</td><td>' . status_badge($o['status']) . '</td><td>' . h(FULFIL_LABELS[$o['fulfilment']] ?? $o['fulfilment']) . '</td></tr>';
}
if (!$orders) echo '<tr><td colspan="6">No orders found.</td></tr>';
echo '</tbody></table></div>';
if ($total > $per) {
    $qs = static fn(int $p) => h('?' . http_build_query(['status' => $status, 'month' => $month, 'q' => $search, 'page' => $p]));
    echo '<p>';
    if ($page > 1) echo '<a href="' . $qs($page - 1) . '">&larr; Newer</a> ';
    if ($page * $per < $total) echo '<a href="' . $qs($page + 1) . '">Older &rarr;</a>';
    echo '</p>';
}
page_end();
