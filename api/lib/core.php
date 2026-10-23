<?php
/*
 * DotComSA payment server: shared code.
 * Blocked from direct web access by api/.htaccess. Included by the api/*.php endpoints and admin pages.
 *
 * Money is always handled in whole cents (integers) to avoid rounding errors.
 */
declare(strict_types=1);

date_default_timezone_set('Africa/Johannesburg');

final class DcsError extends RuntimeException
{
    /** @var array<string,string> */
    public array $fields;
    public int $httpStatus;

    /** @param array<string,string> $fields */
    public function __construct(string $message, array $fields = [], int $httpStatus = 400)
    {
        parent::__construct($message);
        $this->fields = $fields;
        $this->httpStatus = $httpStatus;
    }
}

/* ---------------------------------------------------------------- config */

function dcs_config(?array $set = null): array
{
    static $cfg = null;
    if ($set !== null) {
        $cfg = $set;
    }
    if ($cfg === null) {
        $path = getenv('DCS_CONFIG') ?: __DIR__ . '/../config.local.php';
        if (!is_file($path)) {
            throw new DcsError('Payments are not set up on this server yet.', [], 503);
        }
        $cfg = require $path;
    }
    return $cfg;
}

/* ---------------------------------------------------------------- database */

function dcs_db(?PDO $set = null): PDO
{
    static $pdo = null;
    if ($set !== null) {
        $pdo = $set;
    }
    if ($pdo === null) {
        $db = dcs_config()['db'];
        $pdo = new PDO($db['dsn'], $db['user'] ?? null, $db['pass'] ?? null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        dcs_migrate($pdo);
    }
    return $pdo;
}

function dcs_migrate(PDO $pdo): void
{
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $id = $mysql ? 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $tail = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
    $s = $mysql ? 'VARCHAR(255)' : 'TEXT';
    $t = 'TEXT';
    $dt = $mysql ? 'DATETIME' : 'TEXT';
    $i = $mysql ? 'BIGINT' : 'INTEGER';

    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id $id,
        ref VARCHAR(32) NOT NULL UNIQUE,
        token VARCHAR(64) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        fulfilment VARCHAR(20) NOT NULL DEFAULT 'not_shipped',
        created_at $dt NOT NULL,
        updated_at $dt NOT NULL,
        paid_at $dt NULL,
        customer_name $s NOT NULL,
        phone VARCHAR(30) NOT NULL,
        email $s NOT NULL,
        address $s NOT NULL,
        city $s NOT NULL,
        province VARCHAR(40) NOT NULL,
        postal VARCHAR(10) NOT NULL,
        notes $t NULL,
        subtotal_cents $i NOT NULL,
        delivery_cents $i NOT NULL,
        total_cents $i NOT NULL,
        eligible_cents $i NOT NULL,
        commission_bp INT NOT NULL,
        commission_cents $i NOT NULL,
        split_merchant_id VARCHAR(20) NULL,
        pf_payment_id VARCHAR(40) NULL,
        amount_gross_cents $i NULL,
        amount_fee_cents $i NULL,
        amount_net_cents $i NULL,
        refunded_cents $i NOT NULL DEFAULT 0,
        commission_adjust_cents $i NOT NULL DEFAULT 0,
        ip VARCHAR(45) NULL
    )$tail");
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
        id $id,
        order_id INT NOT NULL,
        product_id VARCHAR(80) NOT NULL,
        name $s NOT NULL,
        size VARCHAR(40) NOT NULL,
        colour VARCHAR(40) NOT NULL,
        qty INT NOT NULL,
        unit_cents $i NOT NULL,
        line_cents $i NOT NULL
    )$tail");
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_events (
        id $id,
        order_ref VARCHAR(32) NULL,
        received_at $dt NOT NULL,
        ip VARCHAR(45) NULL,
        payment_status VARCHAR(20) NULL,
        pf_payment_id VARCHAR(40) NULL,
        amount_gross_cents $i NULL,
        result VARCHAR(30) NOT NULL,
        detail $t NULL
    )$tail");
    $pdo->exec("CREATE TABLE IF NOT EXISTS refunds (
        id $id,
        order_id INT NOT NULL,
        amount_cents $i NOT NULL,
        commission_adjust_cents $i NOT NULL,
        note $t NULL,
        created_by VARCHAR(40) NOT NULL,
        created_at $dt NOT NULL
    )$tail");
    if (!$mysql) {
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_orders_created ON orders(created_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_items_order ON order_items(order_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_events_ref ON payment_events(order_ref)');
    } else {
        foreach ([
            ['orders', 'idx_orders_status', 'status'],
            ['orders', 'idx_orders_created', 'created_at'],
            ['order_items', 'idx_items_order', 'order_id'],
            ['payment_events', 'idx_events_ref', 'order_ref'],
        ] as [$table, $name, $col]) {
            $exists = $pdo->query("SHOW INDEX FROM $table WHERE Key_name = " . $pdo->quote($name))->fetch();
            if (!$exists) {
                $pdo->exec("CREATE INDEX $name ON $table($col)");
            }
        }
    }
}

function dcs_now(): string
{
    return date('Y-m-d H:i:s');
}

/* ---------------------------------------------------------------- money */

/** Rand amount (e.g. 350 or 349.99) to whole cents. */
function dcs_cents($rand): int
{
    return (int) round(((float) $rand) * 100);
}

/** Cents to the "0.00" format PayFast expects. */
function dcs_amount(int $cents): string
{
    return number_format($cents / 100, 2, '.', '');
}

/** Cents to "R1,299.00" for display. */
function dcs_money(int $cents): string
{
    $sign = $cents < 0 ? '-' : '';
    return $sign . 'R' . number_format(abs($cents) / 100, 2, '.', ',');
}

/** Commission in cents: eligible amount x basis points, rounded to the nearest cent (half up). */
function dcs_commission_cents(int $eligibleCents, int $basisPoints): int
{
    if ($eligibleCents <= 0 || $basisPoints <= 0) {
        return 0;
    }
    return intdiv($eligibleCents * $basisPoints + 5000, 10000);
}

/* ---------------------------------------------------------------- catalogue */

/**
 * Reads the same product list the shop front uses (assets/js/products.js),
 * so prices are always taken from the server, never from the customer's browser.
 *
 * @return array<string,array>
 */
function dcs_catalogue(?string $path = null): array
{
    static $cache = [];
    $path = $path ?? __DIR__ . '/../../assets/js/products.js';
    if (isset($cache[$path])) {
        return $cache[$path];
    }
    $src = file_get_contents($path);
    $marker = 'window.DOTCOMSA_PRODUCTS =';
    $start = $src === false ? false : strpos($src, $marker);
    $end = $src === false ? false : strrpos($src, ';');
    if ($start === false || $end === false) {
        throw new DcsError('Product list could not be read.', [], 500);
    }
    $json = trim(substr($src, $start + strlen($marker), $end - $start - strlen($marker)));
    $list = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    $byId = [];
    foreach ($list as $p) {
        $byId[$p['id']] = $p;
    }
    return $cache[$path] = $byId;
}

/* ---------------------------------------------------------------- order pricing */

/**
 * Checks the cart against the catalogue and works out every amount on the server.
 *
 * @param array<int,array> $items  [{id, size, colour, qty}]
 * @return array{lines: array, subtotal: int, delivery: int, total: int, eligible: int, commission: int, commission_bp: int}
 */
function dcs_price_order(array $items, array $cfg, array $catalogue): array
{
    if (count($items) === 0) {
        throw new DcsError('Your cart is empty.');
    }
    if (count($items) > 50) {
        throw new DcsError('Too many different items in one order.');
    }
    $lines = [];
    $subtotal = 0;
    foreach ($items as $n => $it) {
        $id = is_array($it) ? (string) ($it['id'] ?? '') : '';
        $p = $catalogue[$id] ?? null;
        if ($p === null) {
            throw new DcsError('An item in your cart is no longer available. Please remove it and try again.');
        }
        if (empty($p['inStock'])) {
            throw new DcsError($p['name'] . ' is sold out. Please remove it from your cart.');
        }
        $size = (string) ($it['size'] ?? '');
        $colour = (string) ($it['colour'] ?? '');
        if (!in_array($size, $p['sizes'], true) || !in_array($colour, $p['colours'], true)) {
            throw new DcsError('Please re-choose the size and colour for ' . $p['name'] . '.');
        }
        $qty = filter_var($it['qty'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
        if ($qty === false) {
            throw new DcsError('Quantity must be between 1 and 99.');
        }
        $unit = dcs_cents($p['price']);
        $line = $unit * $qty;
        $subtotal += $line;
        $lines[] = ['product_id' => $id, 'name' => $p['name'], 'size' => $size, 'colour' => $colour, 'qty' => $qty, 'unit_cents' => $unit, 'line_cents' => $line];
    }
    $free = ($cfg['free_delivery_from_cents'] ?? 0) > 0 && $subtotal >= $cfg['free_delivery_from_cents'];
    $delivery = $free ? 0 : (int) $cfg['delivery_fee_cents'];
    $bp = (int) $cfg['commission_bp'];
    $eligible = $subtotal; // agreed: clothing subtotal, delivery excluded (no discounts exist yet)
    return [
        'lines' => $lines,
        'subtotal' => $subtotal,
        'delivery' => $delivery,
        'total' => $subtotal + $delivery,
        'eligible' => $eligible,
        'commission_bp' => $bp,
        'commission' => dcs_commission_cents($eligible, $bp),
    ];
}

const DCS_PROVINCES = ['Eastern Cape', 'Free State', 'Gauteng', 'KwaZulu-Natal', 'Limpopo', 'Mpumalanga', 'North West', 'Northern Cape', 'Western Cape'];

/** @return array<string,string> cleaned customer details */
function dcs_clean_customer($c): array
{
    $c = is_array($c) ? $c : [];
    $get = static fn(string $k, int $max) => mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($c[$k] ?? '')) ?? ''), 0, $max);
    $out = [
        'name' => $get('name', 120),
        'phone' => $get('phone', 20),
        'email' => $get('email', 160),
        'address' => $get('address', 200),
        'city' => $get('city', 120),
        'province' => $get('province', 40),
        'postal' => $get('postal', 10),
        'notes' => mb_substr(trim((string) ($c['notes'] ?? '')), 0, 500),
    ];
    $err = [];
    if ($out['name'] === '') $err['name'] = 'Please enter your name.';
    if (!preg_match('/^[0-9+ ()-]{9,15}$/', $out['phone'])) $err['phone'] = 'Please enter a valid cellphone number.';
    if (!filter_var($out['email'], FILTER_VALIDATE_EMAIL)) $err['email'] = 'Please enter a valid email address.';
    if ($out['address'] === '') $err['address'] = 'Please enter your street address.';
    if ($out['city'] === '') $err['city'] = 'Please enter your suburb and town.';
    if (!in_array($out['province'], DCS_PROVINCES, true)) $err['province'] = 'Please choose your province.';
    if (!preg_match('/^[0-9]{4}$/', $out['postal'])) $err['postal'] = 'Please enter a 4-digit postal code.';
    if ($err) {
        throw new DcsError('Please check your delivery details.', $err);
    }
    return $out;
}

/* ---------------------------------------------------------------- orders */

function dcs_new_ref(): string
{
    return 'DCS-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/** Creates a pending order and returns it with its id, ref and token. */
function dcs_create_order(PDO $pdo, array $priced, array $customer, array $cfg, ?string $ip): array
{
    $limit = (int) ($cfg['max_orders_per_ip_10min'] ?? 15);
    if ($ip !== null && $limit > 0) {
        $q = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE ip = ? AND created_at >= ?');
        $q->execute([$ip, date('Y-m-d H:i:s', time() - 600)]);
        if ((int) $q->fetchColumn() >= $limit) {
            throw new DcsError('Too many orders from your connection. Please wait a few minutes and try again.', [], 429);
        }
    }
    $split = trim((string) ($cfg['payfast']['split_merchant_id'] ?? ''));
    $now = dcs_now();
    $order = [
        'ref' => dcs_new_ref(),
        'token' => bin2hex(random_bytes(16)),
        'status' => 'pending',
        'fulfilment' => 'not_shipped',
        'created_at' => $now,
        'updated_at' => $now,
        'customer_name' => $customer['name'],
        'phone' => $customer['phone'],
        'email' => $customer['email'],
        'address' => $customer['address'],
        'city' => $customer['city'],
        'province' => $customer['province'],
        'postal' => $customer['postal'],
        'notes' => $customer['notes'],
        'subtotal_cents' => $priced['subtotal'],
        'delivery_cents' => $priced['delivery'],
        'total_cents' => $priced['total'],
        'eligible_cents' => $priced['eligible'],
        'commission_bp' => $priced['commission_bp'],
        'commission_cents' => $split === '' ? 0 : $priced['commission'],
        'split_merchant_id' => $split === '' ? null : $split,
        'ip' => $ip,
    ];
    $pdo->beginTransaction();
    try {
        $cols = array_keys($order);
        $pdo->prepare('INSERT INTO orders (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')
            ->execute(array_values($order));
        $order['id'] = (int) $pdo->lastInsertId();
        $ins = $pdo->prepare('INSERT INTO order_items (order_id, product_id, name, size, colour, qty, unit_cents, line_cents) VALUES (?,?,?,?,?,?,?,?)');
        foreach ($priced['lines'] as $l) {
            $ins->execute([$order['id'], $l['product_id'], $l['name'], $l['size'], $l['colour'], $l['qty'], $l['unit_cents'], $l['line_cents']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    $order['lines'] = $priced['lines'];
    return $order;
}

function dcs_find_order(PDO $pdo, string $ref): ?array
{
    $q = $pdo->prepare('SELECT * FROM orders WHERE ref = ?');
    $q->execute([$ref]);
    $o = $q->fetch();
    return $o ?: null;
}

function dcs_order_items(PDO $pdo, int $orderId): array
{
    $q = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
    $q->execute([$orderId]);
    return $q->fetchAll();
}

/* ---------------------------------------------------------------- PayFast */

function pf_host(array $cfg): string
{
    return !empty($cfg['payfast']['sandbox']) ? 'sandbox.payfast.co.za' : 'www.payfast.co.za';
}

/** PayFast's encoding: PHP urlencode of the trimmed value (spaces become +, upper-case hex). */
function pf_encode($value): string
{
    return urlencode(trim((string) $value));
}

/**
 * MD5 signature over the fields in the order given, skipping empty values and the listed keys,
 * with the passphrase appended when one is set.
 */
function pf_signature(array $fields, string $passphrase = '', array $skip = ['signature']): string
{
    $parts = [];
    foreach ($fields as $k => $v) {
        if (in_array($k, $skip, true) || trim((string) $v) === '') {
            continue;
        }
        $parts[] = $k . '=' . pf_encode($v);
    }
    $str = implode('&', $parts);
    if ($passphrase !== '') {
        $str .= '&passphrase=' . pf_encode($passphrase);
    }
    return md5($str);
}

/**
 * Builds the PayFast payment form for an order: the action URL and the fields to post, in PayFast's order.
 *
 * @return array{action: string, fields: array<string,string>}
 */
function pf_payment_form(array $order, array $cfg): array
{
    $pf = $cfg['payfast'];
    $site = rtrim($cfg['site_url'], '/');
    $names = preg_split('/\s+/', trim($order['customer_name']), 2) ?: [''];
    $phone = preg_replace('/\D/', '', $order['phone']) ?? '';
    if (str_starts_with($phone, '27') && strlen($phone) === 11) {
        $phone = '0' . substr($phone, 2);
    }
    $fields = [
        'merchant_id' => (string) $pf['merchant_id'],
        'merchant_key' => (string) $pf['merchant_key'],
        'return_url' => $site . '/thankyou.html?ref=' . rawurlencode($order['ref']) . '&t=' . $order['token'],
        'cancel_url' => $site . '/cart.html?cancelled=1',
        'notify_url' => $site . '/api/notify.php',
        'name_first' => mb_substr($names[0], 0, 100),
        'name_last' => mb_substr($names[1] ?? '', 0, 100),
        'email_address' => $order['email'],
        'cell_number' => preg_match('/^0[0-9]{9}$/', $phone) ? $phone : '',
        'm_payment_id' => $order['ref'],
        'amount' => dcs_amount((int) $order['total_cents']),
        'item_name' => 'DotComSA order ' . $order['ref'],
        'item_description' => mb_substr(implode(', ', array_map(static fn($l) => $l['qty'] . ' x ' . $l['name'], $order['lines'])), 0, 255),
    ];
    $fields = array_filter($fields, static fn($v) => trim((string) $v) !== '');
    $setup = null;
    if (!empty($order['split_merchant_id']) && (int) $order['commission_cents'] > 0) {
        $setup = json_encode(['split_payment' => [
            'merchant_id' => (int) $order['split_merchant_id'],
            'amount' => (int) $order['commission_cents'],
        ]]);
        $fields['setup'] = $setup;
    }
    $skip = ['signature'];
    if ($setup !== null && empty($pf['setup_in_signature'])) {
        $skip[] = 'setup';
    }
    $fields['signature'] = pf_signature($fields, (string) ($pf['passphrase'] ?? ''), $skip);
    return ['action' => 'https://' . pf_host($cfg) . '/eng/process', 'fields' => $fields];
}

/** The parameter string PayFast's ITN check uses: every posted field except the signature, in the order received. */
function pf_itn_param_string(array $post): string
{
    $parts = [];
    foreach ($post as $k => $v) {
        if ($k === 'signature') {
            continue;
        }
        $parts[] = $k . '=' . urlencode(stripslashes((string) $v));
    }
    return implode('&', $parts);
}

/** True when the request came from one of PayFast's servers. */
function pf_ip_is_payfast(string $ip): bool
{
    $hosts = ['www.payfast.co.za', 'sandbox.payfast.co.za', 'w1w.payfast.co.za', 'w2w.payfast.co.za'];
    $ips = [];
    foreach ($hosts as $h) {
        $ips = array_merge($ips, gethostbynamel($h) ?: []);
    }
    return in_array($ip, array_unique($ips), true);
}

/** Asks PayFast to confirm the notice is genuine. */
function pf_server_confirms(string $paramString, array $cfg): bool
{
    $ch = curl_init('https://' . pf_host($cfg) . '/eng/query/validate');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $paramString,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'DotComSA ITN',
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return is_string($res) && trim($res) === 'VALID';
}

/**
 * Handles one PayFast Instant Transaction Notification (ITN).
 * The order changes only after every check passes. Repeated notices change nothing.
 *
 * @param callable(string):bool $isPayfastIp     source check (real: pf_ip_is_payfast)
 * @param callable(string,array):bool $confirms  server check (real: pf_server_confirms)
 * @return string result code, also written to payment_events
 */
function pf_handle_itn(array $post, string $ip, array $cfg, PDO $pdo, callable $isPayfastIp, callable $confirms): string
{
    $ref = (string) ($post['m_payment_id'] ?? '');
    $status = strtoupper((string) ($post['payment_status'] ?? ''));
    $pfId = (string) ($post['pf_payment_id'] ?? '');
    $gross = isset($post['amount_gross']) ? dcs_cents($post['amount_gross']) : null;

    $log = static function (string $result, string $detail = '') use ($pdo, $ref, $ip, $status, $pfId, $gross): string {
        $pdo->prepare('INSERT INTO payment_events (order_ref, received_at, ip, payment_status, pf_payment_id, amount_gross_cents, result, detail) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$ref === '' ? null : mb_substr($ref, 0, 32), dcs_now(), $ip, mb_substr($status, 0, 20), mb_substr($pfId, 0, 40), $gross, $result, $detail]);
        return $result;
    };

    $paramString = pf_itn_param_string($post);
    $sigString = $paramString;
    $pass = (string) ($cfg['payfast']['passphrase'] ?? '');
    if ($pass !== '') {
        $sigString .= '&passphrase=' . urlencode($pass);
    }
    if (!hash_equals(md5($sigString), (string) ($post['signature'] ?? ''))) {
        return $log('bad_signature');
    }
    if (!$isPayfastIp($ip)) {
        return $log('bad_source', 'Notice did not come from a PayFast server.');
    }
    if ((string) ($post['merchant_id'] ?? '') !== (string) $cfg['payfast']['merchant_id']) {
        return $log('wrong_merchant');
    }
    $order = $ref === '' ? null : dcs_find_order($pdo, $ref);
    if ($order === null) {
        return $log('unknown_order');
    }
    if (!$confirms($paramString, $cfg)) {
        return $log('not_confirmed', 'PayFast did not confirm this notice.');
    }
    if ($order['status'] !== 'pending') {
        return $log('duplicate', 'Order already ' . $order['status'] . '.');
    }

    $now = dcs_now();
    if ($status === 'COMPLETE') {
        if ($gross === null || abs($gross - (int) $order['total_cents']) > 1) {
            $pdo->prepare("UPDATE orders SET status = 'needs_checking', pf_payment_id = ?, amount_gross_cents = ?, updated_at = ? WHERE id = ? AND status = 'pending'")
                ->execute([$pfId, $gross, $now, $order['id']]);
            return $log('amount_mismatch', 'Expected ' . dcs_money((int) $order['total_cents']) . ', PayFast reported ' . ($gross === null ? 'nothing' : dcs_money($gross)) . '.');
        }
        $q = $pdo->prepare("UPDATE orders SET status = 'paid', paid_at = ?, pf_payment_id = ?, amount_gross_cents = ?, amount_fee_cents = ?, amount_net_cents = ?, updated_at = ? WHERE id = ? AND status = 'pending'");
        $q->execute([
            $now, $pfId, $gross,
            isset($post['amount_fee']) ? dcs_cents($post['amount_fee']) : null,
            isset($post['amount_net']) ? dcs_cents($post['amount_net']) : null,
            $now, $order['id'],
        ]);
        return $log($q->rowCount() === 1 ? 'paid' : 'duplicate');
    }
    if ($status === 'FAILED' || $status === 'CANCELLED') {
        $new = $status === 'FAILED' ? 'failed' : 'cancelled';
        $pdo->prepare("UPDATE orders SET status = ?, pf_payment_id = ?, updated_at = ? WHERE id = ? AND status = 'pending'")
            ->execute([$new, $pfId, $now, $order['id']]);
        return $log($new);
    }
    return $log('ignored', 'Payment status ' . $status . '.');
}

/* ---------------------------------------------------------------- refunds */

/** Records a refund already made in the PayFast dashboard, and the matching commission adjustment. */
function dcs_record_refund(PDO $pdo, int $orderId, int $amountCents, string $note, string $by): void
{
    $q = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $q->execute([$orderId]);
    $o = $q->fetch();
    if (!$o || !in_array($o['status'], ['paid', 'partially_refunded', 'needs_checking'], true)) {
        throw new DcsError('Only paid orders can be refunded.');
    }
    $left = (int) $o['total_cents'] - (int) $o['refunded_cents'];
    if ($amountCents <= 0 || $amountCents > $left) {
        throw new DcsError('Refund must be between R0.01 and ' . dcs_money($left) . '.');
    }
    // Commission adjustment in proportion to the clothing part refunded (delivery refunds carry no commission).
    $clothingLeft = max(0, (int) $o['subtotal_cents'] - (int) $o['refunded_cents']);
    $clothingRefund = min($amountCents, $clothingLeft);
    $adjust = (int) $o['commission_cents'] > 0 ? dcs_commission_cents($clothingRefund, (int) $o['commission_bp']) : 0;
    $refunded = (int) $o['refunded_cents'] + $amountCents;
    $status = $refunded >= (int) $o['total_cents'] ? 'refunded' : 'partially_refunded';
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO refunds (order_id, amount_cents, commission_adjust_cents, note, created_by, created_at) VALUES (?,?,?,?,?,?)')
            ->execute([$orderId, $amountCents, $adjust, mb_substr($note, 0, 500), $by, dcs_now()]);
        $pdo->prepare('UPDATE orders SET refunded_cents = ?, commission_adjust_cents = commission_adjust_cents + ?, status = ?, updated_at = ? WHERE id = ?')
            ->execute([$refunded, $adjust, $status, dcs_now(), $orderId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/* ---------------------------------------------------------------- HTTP helpers */

function dcs_json(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function dcs_client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

/** Writes an error to the server log without customer details or secrets. */
function dcs_log_error(Throwable $e): void
{
    error_log('[DotComSA] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine());
}
