<?php
/*
 * TEST TOOL ONLY: pretends to be PayFast and sends a signed "payment complete" notice
 * for an order, skipping the network checks. Never deployed (tests/ is excluded and blocked).
 * Usage: DCS_CONFIG=path/to/config.php php tests/simulate_itn.php ORDER-REF [COMPLETE|FAILED]
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../api/lib/core.php';
$cfg = dcs_config();
$order = dcs_find_order(dcs_db(), $argv[1] ?? '');
if (!$order) { fwrite(STDERR, "Order not found\n"); exit(1); }
$d = [
    'm_payment_id' => $order['ref'], 'pf_payment_id' => (string) random_int(1000000, 9999999),
    'payment_status' => $argv[2] ?? 'COMPLETE', 'item_name' => 'DotComSA order ' . $order['ref'],
    'amount_gross' => dcs_amount((int) $order['total_cents']), 'amount_fee' => '-' . dcs_amount(intdiv((int) $order['total_cents'] * 32, 1000) + 200),
    'amount_net' => dcs_amount((int) $order['total_cents'] - intdiv((int) $order['total_cents'] * 32, 1000) - 200),
    'merchant_id' => (string) $cfg['payfast']['merchant_id'],
];
$sig = pf_itn_param_string($d);
$pass = (string) ($cfg['payfast']['passphrase'] ?? '');
$d['signature'] = md5($sig . ($pass !== '' ? '&passphrase=' . urlencode($pass) : ''));
echo pf_handle_itn($d, '127.0.0.1', $cfg, dcs_db(), fn() => true, fn() => true), "\n";
