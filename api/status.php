<?php
/* Tells the shop front whether card payments are switched on, and the delivery settings to show. */
declare(strict_types=1);
require __DIR__ . '/lib/core.php';

try {
    $cfg = dcs_config();
    dcs_json([
        'payfast' => true,
        'sandbox' => !empty($cfg['payfast']['sandbox']),
        'allowWhatsappOrders' => !empty($cfg['allow_whatsapp_orders']),
        'deliveryFee' => $cfg['delivery_fee_cents'] / 100,
        'freeDeliveryFrom' => ($cfg['free_delivery_from_cents'] ?? 0) / 100,
        'deliveryTime' => (string) ($cfg['delivery_time'] ?? ''),
    ]);
} catch (Throwable $e) {
    dcs_json(['payfast' => false]);
}
