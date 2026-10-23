<?php
/*
 * PayFast's server-to-server payment notice (ITN).
 * PayFast only needs a 200 response; every check happens before an order changes.
 */
declare(strict_types=1);
require __DIR__ . '/lib/core.php';

http_response_code(200);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    exit;
}
try {
    pf_handle_itn($_POST, dcs_client_ip(), dcs_config(), dcs_db(), 'pf_ip_is_payfast', 'pf_server_confirms');
} catch (Throwable $e) {
    dcs_log_error($e);
}
