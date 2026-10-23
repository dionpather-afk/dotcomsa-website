<?php
/* Lets the thank-you page check whether an order has been paid. Needs the order's secret token. */
declare(strict_types=1);
require __DIR__ . '/lib/core.php';

try {
    $ref = (string) ($_GET['ref'] ?? '');
    $token = (string) ($_GET['t'] ?? '');
    $order = $ref === '' ? null : dcs_find_order(dcs_db(), $ref);
    if ($order === null || $token === '' || !hash_equals($order['token'], $token)) {
        dcs_json(['error' => 'Order not found.'], 404);
        exit;
    }
    dcs_json(['ref' => $order['ref'], 'status' => $order['status'], 'total' => dcs_money((int) $order['total_cents'])]);
} catch (Throwable $e) {
    dcs_log_error($e);
    dcs_json(['error' => 'Could not check the order right now.'], 500);
}
