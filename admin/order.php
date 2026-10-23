<?php
/* Admin: one order, with delivery status and refund recording. */
declare(strict_types=1);
require __DIR__ . '/inc/admin.php';
$user = require_login();
$pdo = dcs_db();
$id = (int) ($_GET['id'] ?? 0);

$msg = null;
$err = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    try {
        if (isset($_POST['fulfilment']) && isset(FULFIL_LABELS[$_POST['fulfilment']])) {
            $pdo->prepare("UPDATE orders SET fulfilment = ?, updated_at = ? WHERE id = ? AND status IN ('paid','partially_refunded')")
                ->execute([$_POST['fulfilment'], dcs_now(), $id]);
            $msg = 'Delivery status updated.';
        } elseif (isset($_POST['refund_amount'])) {
            $amount = (string) $_POST['refund_amount'];
            if (!preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
                throw new DcsError('Enter the refund amount in rand, e.g. 120 or 120.50.');
            }
            dcs_record_refund($pdo, $id, dcs_cents($amount), (string) ($_POST['note'] ?? ''), $user);
            $msg = 'Refund recorded.';
        }
    } catch (DcsError $e) {
        $err = $e->getMessage();
    }
}

$q = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
$q->execute([$id]);
$o = $q->fetch();
if (!$o) {
    http_response_code(404);
    page_start('Not found', $user);
    echo '<p>Order not found. <a href="index.php">Back to orders</a></p>';
    page_end();
    exit;
}
$items = dcs_order_items($pdo, $id);
$ev = $pdo->prepare('SELECT * FROM payment_events WHERE order_ref = ? ORDER BY id');
$ev->execute([$o['ref']]);
$events = $ev->fetchAll();
$rf = $pdo->prepare('SELECT * FROM refunds WHERE order_id = ? ORDER BY id');
$rf->execute([$id]);
$refunds = $rf->fetchAll();

page_start($o['ref'], $user);
echo '<p><a href="index.php">&larr; All orders</a></p><h1 style="font-size:1.6rem">' . h($o['ref']) . ' ' . status_badge($o['status']) . '</h1>';
if ($msg) echo '<p class="notice notice--ok">' . h($msg) . '</p>';
if ($err) echo '<p class="notice notice--error">' . h($err) . '</p>';
if ($o['status'] === 'needs_checking') echo '<p class="notice notice--error">The amount PayFast reported does not match this order. Check the payment in the PayFast dashboard before sending anything.</p>';

echo '<div class="cards"><div>Order total<b>' . h(dcs_money((int) $o['total_cents'])) . '</b></div><div>Clothing<b>' . h(dcs_money((int) $o['subtotal_cents'])) . '</b></div><div>Delivery<b>' . h(dcs_money((int) $o['delivery_cents'])) . '</b></div><div>TLC commission (' . h($o['commission_bp'] / 100) . '%)<b>' . h(dcs_money((int) $o['commission_cents'])) . '</b></div></div>';

echo '<h2 style="font-size:1.2rem">Items</h2><div class="scroll"><table><thead><tr><th>Item</th><th>Size</th><th>Colour</th><th>Qty</th><th>Price</th><th>Line</th></tr></thead><tbody>';
foreach ($items as $i) echo '<tr><td>' . h($i['name']) . '</td><td>' . h($i['size']) . '</td><td>' . h($i['colour']) . '</td><td>' . (int) $i['qty'] . '</td><td>' . h(dcs_money((int) $i['unit_cents'])) . '</td><td>' . h(dcs_money((int) $i['line_cents'])) . '</td></tr>';
echo '</tbody></table></div>';

echo '<h2 style="font-size:1.2rem;margin-top:24px">Customer and delivery</h2><p><b>' . h($o['customer_name']) . '</b><br>' . h($o['phone']) . ' &middot; ' . h($o['email']) . '<br>' . h($o['address']) . ', ' . h($o['city']) . ', ' . h($o['province']) . ', ' . h($o['postal']) . '</p>';
if ($o['notes']) echo '<p><b>Notes:</b> ' . nl2br(h($o['notes'])) . '</p>';
echo '<p>Placed ' . h($o['created_at']) . ($o['paid_at'] ? ' &middot; Paid ' . h($o['paid_at']) : '') . '</p>';

if (in_array($o['status'], ['paid', 'partially_refunded'], true)) {
    echo '<form method="post" class="inline" style="margin:16px 0">' . csrf_field() . '<div class="field"><label>Delivery status</label><select class="select" name="fulfilment">';
    foreach (FULFIL_LABELS as $k => $l) echo '<option value="' . h($k) . '"' . ($k === $o['fulfilment'] ? ' selected' : '') . '>' . h($l) . '</option>';
    echo '</select></div><button class="btn btn--blue" type="submit">Save</button></form>';
} elseif ($o['status'] === 'pending') {
    echo '<p class="notice">Not paid yet. Do not send this order until it shows <b>Paid</b>.</p>';
}

echo '<h2 style="font-size:1.2rem;margin-top:24px">Payment</h2><table><tbody>';
echo '<tr><th>PayFast payment ID</th><td>' . h($o['pf_payment_id'] ?: '-') . '</td></tr>';
echo '<tr><th>Amount paid</th><td>' . ($o['amount_gross_cents'] !== null ? h(dcs_money((int) $o['amount_gross_cents'])) : '-') . '</td></tr>';
echo '<tr><th>PayFast fee</th><td>' . ($o['amount_fee_cents'] !== null ? h(dcs_money((int) $o['amount_fee_cents'])) : '-') . '</td></tr>';
echo '<tr><th>Net after fee</th><td>' . ($o['amount_net_cents'] !== null ? h(dcs_money((int) $o['amount_net_cents'])) : '-') . '</td></tr>';
echo '<tr><th>Split to</th><td>' . h($o['split_merchant_id'] ?: 'No split set up') . '</td></tr>';
echo '<tr><th>Refunded</th><td>' . h(dcs_money((int) $o['refunded_cents'])) . ' (commission adjustment ' . h(dcs_money((int) $o['commission_adjust_cents'])) . ')</td></tr></tbody></table>';

if (in_array($o['status'], ['paid', 'partially_refunded', 'needs_checking'], true)) {
    echo '<h3 style="margin-top:20px">Record a refund</h3><p style="color:var(--muted)">First do the refund in the PayFast dashboard, then record it here so the reports stay correct.</p>';
    echo '<form method="post" class="inline">' . csrf_field() . '<div class="field"><label>Amount (R)</label><input name="refund_amount" inputmode="decimal" required></div><div class="field"><label>Reason</label><input name="note" maxlength="500"></div><button class="btn btn--red" type="submit">Record refund</button></form>';
}
if ($refunds) {
    echo '<h3 style="margin-top:20px">Refunds</h3><table><thead><tr><th>Date</th><th>Amount</th><th>Commission adj.</th><th>By</th><th>Reason</th></tr></thead><tbody>';
    foreach ($refunds as $r) echo '<tr><td>' . h($r['created_at']) . '</td><td>' . h(dcs_money((int) $r['amount_cents'])) . '</td><td>' . h(dcs_money((int) $r['commission_adjust_cents'])) . '</td><td>' . h($r['created_by']) . '</td><td>' . h($r['note']) . '</td></tr>';
    echo '</tbody></table>';
}
echo '<h3 style="margin-top:20px">PayFast notices</h3><table><thead><tr><th>Received</th><th>Status</th><th>Amount</th><th>Result</th><th>Detail</th></tr></thead><tbody>';
foreach ($events as $e) echo '<tr><td>' . h($e['received_at']) . '</td><td>' . h($e['payment_status']) . '</td><td>' . ($e['amount_gross_cents'] !== null ? h(dcs_money((int) $e['amount_gross_cents'])) : '-') . '</td><td>' . h($e['result']) . '</td><td>' . h($e['detail']) . '</td></tr>';
if (!$events) echo '<tr><td colspan="5">None yet.</td></tr>';
echo '</tbody></table>';
page_end();
