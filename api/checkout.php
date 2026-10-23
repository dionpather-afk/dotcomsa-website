<?php
/*
 * Creates a pending order from the cart and returns the PayFast payment form.
 * Prices, delivery and TLC's commission are all worked out here, on the server.
 */
declare(strict_types=1);
require __DIR__ . '/lib/core.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    dcs_json(['error' => 'Method not allowed.'], 405);
    exit;
}
try {
    $raw = file_get_contents('php://input', false, null, 0, 65536);
    $in = json_decode((string) $raw, true);
    if (!is_array($in)) {
        throw new DcsError('Invalid request.');
    }
    $cfg = dcs_config();
    $priced = dcs_price_order(is_array($in['items'] ?? null) ? $in['items'] : [], $cfg, dcs_catalogue());
    $customer = dcs_clean_customer($in['customer'] ?? []);
    $order = dcs_create_order(dcs_db(), $priced, $customer, $cfg, dcs_client_ip());
    $form = pf_payment_form($order, $cfg);
    dcs_json(['ref' => $order['ref'], 'action' => $form['action'], 'fields' => $form['fields']]);
} catch (DcsError $e) {
    dcs_json(['error' => $e->getMessage(), 'fields' => $e->fields], $e->httpStatus);
} catch (Throwable $e) {
    dcs_log_error($e);
    dcs_json(['error' => 'Sorry, something went wrong. Please try again, or contact us on WhatsApp.'], 500);
}
