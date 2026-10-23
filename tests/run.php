<?php
/*
 * DotComSA payment tests. No internet or real PayFast needed.
 * Run from the repo folder:  php tests/run.php
 * Exit code 0 = all passed.
 */
declare(strict_types=1);
require __DIR__ . '/../api/lib/core.php';

$passed = 0;
$failed = 0;
function check(string $name, bool $ok, string $why = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "  ok   $name\n"; }
    else { $failed++; echo "  FAIL $name" . ($why ? " ($why)" : '') . "\n"; }
}
function throwsDcs(callable $fn): ?DcsError
{
    try { $fn(); } catch (DcsError $e) { return $e; }
    return null;
}

$cfg = [
    'site_url' => 'https://shop.example.co.za',
    'db' => ['dsn' => 'sqlite::memory:'],
    'payfast' => ['sandbox' => true, 'merchant_id' => '10000100', 'merchant_key' => '46f0cd694581a', 'passphrase' => 'test pass phrase', 'split_merchant_id' => '10000105', 'setup_in_signature' => false],
    'commission_bp' => 500, // example rate for tests only (5%)
    'delivery_fee_cents' => 10000,
    'free_delivery_from_cents' => 100000,
    'max_orders_per_ip_10min' => 3,
];
dcs_config($cfg);
// Default: throwaway SQLite database. To test against MySQL as used on Xneelo, set
// DCS_TEST_DSN, DCS_TEST_USER and DCS_TEST_PASS to an EMPTY test database (its tables are dropped).
$dsn = getenv('DCS_TEST_DSN') ?: 'sqlite::memory:';
$pdo = new PDO($dsn, getenv('DCS_TEST_USER') ?: null, getenv('DCS_TEST_PASS') ?: null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
if ($dsn !== 'sqlite::memory:') {
    foreach (['orders', 'order_items', 'payment_events', 'refunds', 'login_attempts'] as $t) $pdo->exec("DROP TABLE IF EXISTS $t");
}
echo 'Database: ' . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n";
dcs_migrate($pdo);
dcs_migrate($pdo); // must be safe to run twice
dcs_db($pdo);
$cat = dcs_catalogue();

echo "Commission\n";
check('5% of R1,000 = R50.00', dcs_commission_cents(100000, 500) === 5000);
check('5% of R350 = R17.50', dcs_commission_cents(35000, 500) === 1750);
check('2.5% of R120 = R3.00', dcs_commission_cents(12000, 250) === 300);
check('half cent rounds up (5% of 10c -> 1c)', dcs_commission_cents(10, 500) === 1);
check('below half rounds down (5% of 9c -> 0c)', dcs_commission_cents(9, 500) === 0);
check('zero amount', dcs_commission_cents(0, 500) === 0);
check('money format', dcs_money(129900) === 'R1,299.00' && dcs_amount(129900) === '1299.00');

echo "Catalogue\n";
check('server reads the shop product list', count($cat) >= 1 && isset(array_values($cat)[0]['price']));
$p = $cat['strap-platform-sneakers'] ?? array_values($cat)[0];
$item = ['id' => $p['id'], 'size' => $p['sizes'][0], 'colour' => $p['colours'][0], 'qty' => 2];

echo "Pricing\n";
$priced = dcs_price_order([$item + ['price' => 1]], $cfg, $cat);
$unit = dcs_cents($p['price']);
check('price comes from server, not the browser', $priced['subtotal'] === $unit * 2);
check('delivery charged under free threshold', $priced['delivery'] === ($unit * 2 >= 100000 ? 0 : 10000));
check('commission on clothing only', $priced['commission'] === dcs_commission_cents($unit * 2, 500));
$big = dcs_price_order([array_merge($item, ['qty' => 99])], $cfg, $cat);
check('free delivery over R1,000', $big['delivery'] === 0 && $big['total'] === $big['subtotal']);
check('unknown product rejected', throwsDcs(fn() => dcs_price_order([['id' => 'nope', 'size' => 'M', 'colour' => 'Black', 'qty' => 1]], $cfg, $cat)) !== null);
check('bad size rejected', throwsDcs(fn() => dcs_price_order([array_merge($item, ['size' => 'XXXL'])], $cfg, $cat)) !== null);
check('bad colour rejected', throwsDcs(fn() => dcs_price_order([array_merge($item, ['colour' => 'Gold'])], $cfg, $cat)) !== null);
check('quantity 0 rejected', throwsDcs(fn() => dcs_price_order([array_merge($item, ['qty' => 0])], $cfg, $cat)) !== null);
check('quantity 100 rejected', throwsDcs(fn() => dcs_price_order([array_merge($item, ['qty' => 100])], $cfg, $cat)) !== null);
check('empty cart rejected', throwsDcs(fn() => dcs_price_order([], $cfg, $cat)) !== null);
$soldOut = $cat;
$soldOut[$p['id']]['inStock'] = false;
check('sold-out item rejected', throwsDcs(fn() => dcs_price_order([$item], $cfg, $soldOut)) !== null);

echo "Customer details\n";
$cust = ['name' => 'Thandi  Nkosi', 'phone' => '082 123 4567', 'email' => 'thandi@example.com', 'address' => '1 Main Rd', 'city' => 'Umlazi, Durban', 'province' => 'KwaZulu-Natal', 'postal' => '4031', 'notes' => ''];
$clean = dcs_clean_customer($cust);
check('valid details accepted, spaces tidied', $clean['name'] === 'Thandi Nkosi');
$e = throwsDcs(fn() => dcs_clean_customer(array_merge($cust, ['email' => 'nope', 'postal' => '12'])));
check('bad email and postal code rejected with field errors', $e !== null && isset($e->fields['email'], $e->fields['postal']));
check('5-digit postal code rejected, not cut short', throwsDcs(fn() => dcs_clean_customer(array_merge($cust, ['postal' => '40311']))) !== null);
check('unknown province rejected', throwsDcs(fn() => dcs_clean_customer(array_merge($cust, ['province' => 'Narnia']))) !== null);

echo "PayFast form\n";
$order = dcs_create_order($pdo, $priced, $clean, $cfg, '10.0.0.1');
$form = pf_payment_form($order, $cfg);
$f = $form['fields'];
check('sandbox URL used in test mode', $form['action'] === 'https://sandbox.payfast.co.za/eng/process');
check('amount is server total', $f['amount'] === dcs_amount($priced['total']));
check('m_payment_id is the order ref', $f['m_payment_id'] === $order['ref']);
$setup = json_decode($f['setup'], true);
check('split sends fixed commission in cents to TLC', $setup['split_payment']['merchant_id'] === 10000105 && $setup['split_payment']['amount'] === $priced['commission']);
check('split has no percentage', !isset($setup['split_payment']['percentage']));
check('notify and return URLs are absolute https', str_starts_with($f['notify_url'], 'https://shop.example.co.za/api/notify.php') && str_contains($f['return_url'], $order['token']));
check('cell number normalised', $f['cell_number'] === '0821234567');
// independent signature recomputation
$parts = [];
foreach ($f as $k => $v) {
    if ($k === 'signature' || $k === 'setup') continue;
    $parts[] = $k . '=' . urlencode(trim($v));
}
$expected = md5(implode('&', $parts) . '&passphrase=' . urlencode('test pass phrase'));
check('signature matches PayFast rules (setup excluded)', $f['signature'] === $expected);
$cfg2 = $cfg; $cfg2['payfast']['setup_in_signature'] = true;
$f2 = pf_payment_form($order, $cfg2)['fields'];
$parts2 = [];
foreach ($f2 as $k => $v) { if ($k !== 'signature') $parts2[] = $k . '=' . urlencode(trim($v)); }
check('setup_in_signature switch works', $f2['signature'] === md5(implode('&', $parts2) . '&passphrase=' . urlencode('test pass phrase')));
$cfg3 = $cfg; $cfg3['payfast']['split_merchant_id'] = '';
$o3 = dcs_create_order($pdo, $priced, $clean, $cfg3, null);
check('no split when TLC merchant ID is empty', !isset(pf_payment_form($o3, $cfg3)['fields']['setup']) && (int) $o3['commission_cents'] === 0);
$cfgLive = $cfg; $cfgLive['payfast']['sandbox'] = false;
check('live URL when sandbox is off', pf_payment_form($order, $cfgLive)['action'] === 'https://www.payfast.co.za/eng/process');

echo "Payment notices (ITN)\n";
function itn(array $order, array $over = [], string $pass = 'test pass phrase'): array
{
    $d = array_merge([
        'm_payment_id' => $order['ref'], 'pf_payment_id' => '1089250', 'payment_status' => 'COMPLETE',
        'item_name' => 'DotComSA order ' . $order['ref'], 'item_description' => '',
        'amount_gross' => dcs_amount((int) $order['total_cents']), 'amount_fee' => '-11.85', 'amount_net' => dcs_amount((int) $order['total_cents'] - 1185),
        'custom_str1' => '', 'name_first' => 'Thandi', 'name_last' => "O'Neil & Co", 'email_address' => 'thandi@example.com', 'merchant_id' => '10000100',
    ], $over);
    $parts = [];
    foreach ($d as $k => $v) $parts[] = $k . '=' . urlencode($v);
    $d['signature'] = md5(implode('&', $parts) . ($pass !== '' ? '&passphrase=' . urlencode($pass) : ''));
    return $d;
}
$yes = fn() => true;
$no = fn() => false;
$status = fn(string $ref) => dcs_find_order($pdo, $ref)['status'];
$events = fn(string $ref) => (int) $pdo->query('SELECT COUNT(*) FROM payment_events WHERE order_ref = ' . $pdo->quote($ref))->fetchColumn();

check('bad signature rejected', pf_handle_itn(itn($order, [], 'wrong'), '1.2.3.4', $cfg, $pdo, $yes, $yes) === 'bad_signature' && $status($order['ref']) === 'pending');
$tampered = itn($order); $tampered['amount_gross'] = '1.00';
check('tampered notice rejected', pf_handle_itn($tampered, '1.2.3.4', $cfg, $pdo, $yes, $yes) === 'bad_signature');
check('notice not from PayFast rejected', pf_handle_itn(itn($order), '6.6.6.6', $cfg, $pdo, $no, $yes) === 'bad_source' && $status($order['ref']) === 'pending');
check('wrong merchant rejected', pf_handle_itn(itn($order, ['merchant_id' => '999']), '1.2.3.4', $cfg, $pdo, $yes, $yes) === 'wrong_merchant');
check('unknown order logged', pf_handle_itn(itn(['ref' => 'DCS-000000-XXXXXX', 'total_cents' => 100]), '1.2.3.4', $cfg, $pdo, $yes, $yes) === 'unknown_order');
check('not confirmed by PayFast rejected', pf_handle_itn(itn($order), '1.2.3.4', $cfg, $pdo, $yes, $no) === 'not_confirmed' && $status($order['ref']) === 'pending');
check('valid COMPLETE marks paid', pf_handle_itn(itn($order), '1.2.3.4', $cfg, $pdo, $yes, $yes) === 'paid' && $status($order['ref']) === 'paid');
$paid = dcs_find_order($pdo, $order['ref']);
check('PayFast fee and net recorded', (int) $paid['amount_fee_cents'] === -1185 && $paid['pf_payment_id'] === '1089250');
$before = $events($order['ref']);
check('duplicate notice changes nothing', pf_handle_itn(itn($order), '1.2.3.4', $cfg, $pdo, $yes, $yes) === 'duplicate' && $status($order['ref']) === 'paid' && $events($order['ref']) === $before + 1);
check('late FAILED after paid does not undo payment', pf_handle_itn(itn($order, ['payment_status' => 'FAILED']), '1.2.3.4', $cfg, $pdo, $yes, $yes) === 'duplicate' && $status($order['ref']) === 'paid');

$o4 = dcs_create_order($pdo, $priced, $clean, $cfg, null);
check('wrong amount flagged for checking', pf_handle_itn(itn($o4, ['amount_gross' => '5.00']), '1.2.3.4', $cfg, $pdo, $yes, $yes) === 'amount_mismatch' && $status($o4['ref']) === 'needs_checking');
$o5 = dcs_create_order($pdo, $priced, $clean, ['max_orders_per_ip_10min' => 0] + $cfg, null);
check('FAILED marks failed', pf_handle_itn(itn($o5, ['payment_status' => 'FAILED']), '1.2.3.4', $cfg, $pdo, $yes, $yes) === 'failed' && $status($o5['ref']) === 'failed');
$o6 = dcs_create_order($pdo, $priced, $clean, ['max_orders_per_ip_10min' => 0] + $cfg, null);
check('PENDING notice ignored', pf_handle_itn(itn($o6, ['payment_status' => 'PENDING']), '1.2.3.4', $cfg, $pdo, $yes, $yes) === 'ignored' && $status($o6['ref']) === 'pending');
$cfgNoPass = $cfg; $cfgNoPass['payfast']['passphrase'] = '';
check('works without a passphrase', pf_handle_itn(itn($o6, [], ''), '1.2.3.4', $cfgNoPass, $pdo, $yes, $yes) === 'paid');

echo "Abuse protection\n";
dcs_create_order($pdo, $priced, $clean, $cfg, '10.0.0.9');
dcs_create_order($pdo, $priced, $clean, $cfg, '10.0.0.9');
dcs_create_order($pdo, $priced, $clean, $cfg, '10.0.0.9');
$e = throwsDcs(fn() => dcs_create_order($pdo, $priced, $clean, $cfg, '10.0.0.9'));
check('too many orders from one IP blocked', $e !== null && $e->httpStatus === 429);

echo "Refunds\n";
$id = (int) $paid['id'];
$part = 5000;
dcs_record_refund($pdo, $id, $part, 'Wrong size', 'tester');
$r = dcs_find_order($pdo, $order['ref']);
check('partial refund recorded', $r['status'] === 'partially_refunded' && (int) $r['refunded_cents'] === $part);
check('commission adjusted by the rate on the refund', (int) $r['commission_adjust_cents'] === dcs_commission_cents($part, 500));
check('refund above remaining rejected', throwsDcs(fn() => dcs_record_refund($pdo, $id, (int) $r['total_cents'], '', 't')) !== null);
dcs_record_refund($pdo, $id, (int) $r['total_cents'] - $part, 'Rest', 'tester');
$r = dcs_find_order($pdo, $order['ref']);
check('full refund marks refunded', $r['status'] === 'refunded' && (int) $r['refunded_cents'] === (int) $r['total_cents']);
check('commission adjustment never exceeds commission', (int) $r['commission_adjust_cents'] <= (int) $r['commission_cents'] + 1);
check('refund on unpaid order rejected', throwsDcs(fn() => dcs_record_refund($pdo, (int) $o5['id'], 100, '', 't')) !== null);

echo "\n$passed passed, $failed failed\n";
echo $failed ? "RESULT: FAIL\n" : "RESULT: PASS\n";
exit($failed ? 1 : 0);
