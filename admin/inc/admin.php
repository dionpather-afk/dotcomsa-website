<?php
/* DotComSA admin area: login, security and page layout. Blocked from direct web access. */
declare(strict_types=1);
require __DIR__ . '/../../api/lib/core.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name('dcs_admin');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
session_start();

const DCS_SESSION_MAX = 8 * 3600;

function h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function admin_user(): ?string
{
    $u = $_SESSION['user'] ?? null;
    if ($u && (time() - ($_SESSION['login_at'] ?? 0)) < DCS_SESSION_MAX) {
        return $u;
    }
    return null;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (!hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Your session expired. Please go back and try again.');
    }
}

function login_attempts_table(PDO $pdo): void
{
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $pdo->exec('CREATE TABLE IF NOT EXISTS login_attempts (ip VARCHAR(45) NOT NULL, at ' . ($mysql ? 'DATETIME' : 'TEXT') . ' NOT NULL)' . ($mysql ? ' ENGINE=InnoDB' : ''));
}

/** Returns an error message, or null on success. Locks an IP out after 5 failed tries in 15 minutes. */
function admin_login(string $user, string $pass): ?string
{
    $pdo = dcs_db();
    login_attempts_table($pdo);
    $ip = dcs_client_ip();
    $since = date('Y-m-d H:i:s', time() - 900);
    $q = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND at >= ?');
    $q->execute([$ip, $since]);
    if ((int) $q->fetchColumn() >= 5) {
        return 'Too many failed attempts. Please wait 15 minutes.';
    }
    $users = dcs_config()['admin_users'] ?? [];
    $hash = $users[$user] ?? null;
    if ($hash === null || !password_verify($pass, $hash)) {
        $pdo->prepare('INSERT INTO login_attempts (ip, at) VALUES (?, ?)')->execute([$ip, dcs_now()]);
        usleep(400000);
        return 'Wrong username or password.';
    }
    session_regenerate_id(true);
    $_SESSION['user'] = $user;
    $_SESSION['login_at'] = time();
    return null;
}

function require_login(): string
{
    $u = admin_user();
    if ($u === null) {
        header('Location: index.php');
        exit;
    }
    return $u;
}

const STATUS_LABELS = [
    'pending' => 'Waiting for payment',
    'paid' => 'Paid',
    'failed' => 'Payment failed',
    'cancelled' => 'Cancelled',
    'refunded' => 'Refunded',
    'partially_refunded' => 'Partly refunded',
    'needs_checking' => 'Needs checking',
];
const FULFIL_LABELS = ['not_shipped' => 'Not sent', 'shipped' => 'Sent', 'delivered' => 'Delivered'];

function status_badge(string $s): string
{
    $colour = ['paid' => '#166534;background:#DCFCE7', 'pending' => '#92400E;background:#FEF3C7', 'needs_checking' => '#fff;background:#DC2626'][$s] ?? '#374151;background:#E5E7EB';
    return '<span class="badge" style="color:' . $colour . '">' . h(STATUS_LABELS[$s] ?? $s) . '</span>';
}

function page_start(string $title, ?string $user): void
{
    $sandbox = !empty(dcs_config()['payfast']['sandbox']);
    echo '<!doctype html><html lang="en-ZA"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="robots" content="noindex"><title>' . h($title) . ' | DotComSA admin</title><link rel="stylesheet" href="../assets/css/styles.css"><style>
    .adm{max-width:1180px;margin:0 auto;padding:20px 16px 60px}.adm table{width:100%;border-collapse:collapse;font-size:.95rem}
    .adm th,.adm td{text-align:left;padding:9px 8px;border-bottom:1px solid var(--border);vertical-align:top}.adm th{background:var(--bg-soft)}
    .badge{display:inline-block;padding:2px 10px;border-radius:999px;font-size:.8rem;font-weight:700;white-space:nowrap}
    .adm nav a{margin-right:14px;font-weight:700}.adm .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin:16px 0}
    .adm .cards div{background:var(--bg-soft);border-radius:12px;padding:14px}.adm .cards b{display:block;font-size:1.3rem;color:var(--blue)}
    .adm form.inline{display:flex;gap:8px;flex-wrap:wrap;align-items:end}.adm .scroll{overflow-x:auto}.adm .btn{min-height:40px;padding:8px 16px}
    </style></head><body>';
    echo '<header class="header"><div class="container header__inner"><a class="logo" href="index.php">DotCom<span>SA</span> <small style="font-size:.9rem;color:#CBD5E1">admin</small></a>';
    if ($user) {
        echo '<span style="color:#fff">Signed in as <b>' . h($user) . '</b> &middot; <a style="color:#FCA5A5" href="logout.php">Sign out</a></span>';
    }
    echo '</div></header>';
    if ($sandbox) {
        echo '<div class="preview-bar">PayFast TEST MODE: no real money is taken.</div>';
    }
    echo '<main class="adm">';
    if ($user) {
        echo '<nav><a href="index.php">Orders</a><a href="report.php">Monthly report</a></nav>';
    }
}

function page_end(): void
{
    echo '</main></body></html>';
}
