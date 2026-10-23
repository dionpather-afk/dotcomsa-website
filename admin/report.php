<?php
/* Admin: monthly sales and commission report, with CSV download. */
declare(strict_types=1);
require __DIR__ . '/inc/admin.php';
$user = require_login();
$pdo = dcs_db();

$month = (string) ($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');
$from = "$month-01 00:00:00";
$to = date('Y-m-01 00:00:00', strtotime("$month-01 +1 month"));

$paidStatuses = "('paid','partially_refunded','refunded')";
$q = $pdo->prepare("SELECT * FROM orders WHERE status IN $paidStatuses AND paid_at >= ? AND paid_at < ? ORDER BY paid_at");
$q->execute([$from, $to]);
$orders = $q->fetchAll();

$sum = static fn(string $k) => array_sum(array_map(static fn($o) => (int) $o[$k], $orders));
$r = $pdo->prepare('SELECT COALESCE(SUM(amount_cents),0) a, COALESCE(SUM(commission_adjust_cents),0) c, COUNT(*) n FROM refunds WHERE created_at >= ? AND created_at < ?');
$r->execute([$from, $to]);
$ref = $r->fetch();
$check = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status = 'needs_checking' AND created_at >= ? AND created_at < ?");
$check->execute([$from, $to]);
$needsChecking = (int) $check->fetchColumn();
$commission = $sum('commission_cents');
$netCommission = $commission - (int) $ref['c'];

if (($_GET['format'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="dotcomsa-' . $month . '.csv"');
    $out = fopen('php://output', 'w');
    // Stop spreadsheet formulas sneaking in through customer-typed text.
    $safe = static fn($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
    fputcsv($out, ['Order', 'Paid at', 'Customer', 'Clothing (R)', 'Delivery (R)', 'Total (R)', 'Commission rate %', 'Commission (R)', 'PayFast payment ID', 'PayFast fee (R)', 'Refunded (R)', 'Commission adjustment (R)', 'Status']);
    foreach ($orders as $o) {
        fputcsv($out, [$o['ref'], $o['paid_at'], $safe($o['customer_name']), dcs_amount((int) $o['subtotal_cents']), dcs_amount((int) $o['delivery_cents']), dcs_amount((int) $o['total_cents']), $o['commission_bp'] / 100, dcs_amount((int) $o['commission_cents']), $o['pf_payment_id'], $o['amount_fee_cents'] === null ? '' : dcs_amount((int) $o['amount_fee_cents']), dcs_amount((int) $o['refunded_cents']), dcs_amount((int) $o['commission_adjust_cents']), $o['status']]);
    }
    fclose($out);
    exit;
}

page_start('Monthly report', $user);
echo '<h1 style="font-size:1.6rem;margin-top:16px">Monthly report</h1>';
echo '<form class="inline" method="get"><div class="field"><label>Month</label><input type="month" name="month" value="' . h($month) . '"></div><button class="btn btn--blue" type="submit">Show</button> <a class="btn btn--outline" href="?month=' . h($month) . '&amp;format=csv">Download CSV</a></form>';
if ($needsChecking) echo '<p class="notice notice--error">' . $needsChecking . ' order(s) this month need checking against PayFast.</p>';
echo '<div class="cards">';
echo '<div>Paid orders<b>' . count($orders) . '</b></div>';
echo '<div>Total paid<b>' . h(dcs_money($sum('total_cents'))) . '</b></div>';
echo '<div>Clothing sales<b>' . h(dcs_money($sum('subtotal_cents'))) . '</b></div>';
echo '<div>Delivery charged<b>' . h(dcs_money($sum('delivery_cents'))) . '</b></div>';
echo '<div>PayFast fees<b>' . h(dcs_money($sum('amount_fee_cents'))) . '</b></div>';
echo '<div>Refunds (' . (int) $ref['n'] . ')<b>' . h(dcs_money((int) $ref['a'])) . '</b></div>';
echo '<div>TLC commission (expected)<b>' . h(dcs_money($commission)) . '</b></div>';
echo '<div>Commission after refunds<b>' . h(dcs_money($netCommission)) . '</b></div>';
echo '</div><p style="color:var(--muted)">Expected figures from this website. Compare with the PayFast statements of DotComSA and TLC; PayFast\'s records are final.</p>';
echo '<div class="scroll"><table><thead><tr><th>Order</th><th>Paid</th><th>Clothing</th><th>Delivery</th><th>Total</th><th>Commission</th><th>Status</th></tr></thead><tbody>';
foreach ($orders as $o) echo '<tr><td><a href="order.php?id=' . (int) $o['id'] . '">' . h($o['ref']) . '</a></td><td>' . h(substr((string) $o['paid_at'], 0, 16)) . '</td><td>' . h(dcs_money((int) $o['subtotal_cents'])) . '</td><td>' . h(dcs_money((int) $o['delivery_cents'])) . '</td><td>' . h(dcs_money((int) $o['total_cents'])) . '</td><td>' . h(dcs_money((int) $o['commission_cents'])) . '</td><td>' . status_badge($o['status']) . '</td></tr>';
if (!$orders) echo '<tr><td colspan="7">No paid orders this month.</td></tr>';
echo '</tbody></table></div>';
page_end();
