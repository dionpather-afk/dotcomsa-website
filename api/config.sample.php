<?php
/*
 * DotComSA payment server settings: TEMPLATE.
 *
 * On the server, copy this file to  api/config.local.php  and fill it in.
 * config.local.php holds secrets. It is NEVER committed to GitHub
 * (it is listed in .gitignore) and is blocked from the web by api/.htaccess.
 *
 * Merchant keys and passphrases are typed in by the account owner only.
 * Never send them by WhatsApp or email.
 */
return [
    // Public address of the site, no trailing slash. Used for PayFast return and notify links.
    'site_url' => 'https://www.example.co.za',

    // Database. Production on Xneelo: MySQL.
    'db' => [
        'dsn'  => 'mysql:host=localhost;dbname=CHANGE_ME;charset=utf8mb4',
        'user' => 'CHANGE_ME',
        'pass' => 'CHANGE_ME',
    ],

    'payfast' => [
        // true = PayFast test mode (no real money). Set to false only at go-live, with Dion's written approval.
        'sandbox'      => true,

        // DotComSA's PayFast account (primary merchant).
        // Sandbox test details shown; replace with DotComSA's live details at go-live.
        'merchant_id'  => '10000100',
        'merchant_key' => '46f0cd694581a',
        'passphrase'   => '',            // must match the passphrase set in PayFast > Settings > Integration

        // TLC's PayFast merchant ID (receives the commission through Split Payments).
        // Leave empty to switch the split off.
        'split_merchant_id' => '',

        // Whether the split "setup" field is included in the signature.
        // PayFast's published examples are unclear. Confirm in sandbox: if PayFast shows a
        // signature mismatch, flip this value.
        'setup_in_signature' => false,
    ],

    // TLC commission in basis points (100 = 1%, 250 = 2.5%), as per the signed agreement.
    // Charged on the clothing subtotal (delivery excluded). Kept here, not in GitHub.
    'commission_bp' => 0,

    // Delivery, in cents. Must match what the shop front shows (it reads these from api/status.php).
    'delivery_fee_cents'         => 10000,   // R100.00
    'free_delivery_from_cents'   => 100000,  // R1,000.00 (0 = no free delivery)
    'delivery_time'              => '3 to 5 working days',

    // false = website orders must be paid by PayFast (recommended: TLC's commission is automatic).
    // true  = also show "Send order on WhatsApp / email" on the cart page.
    'allow_whatsapp_orders' => false,

    // Admin area logins. Make a hash with:  php tools/make-password-hash.php
    'admin_users' => [
        // 'dotcomsa' => '$2y$10$...',
        // 'tlc'      => '$2y$10$...',
    ],

    // Abuse protection: maximum new orders per IP address per 10 minutes.
    'max_orders_per_ip_10min' => 15,
];
