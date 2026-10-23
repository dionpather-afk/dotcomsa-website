<?php
/*
 * Makes a password hash for the admin area.
 * Run:  php tools/make-password-hash.php
 * Type the new password, then paste the printed hash into api/config.local.php under admin_users.
 * The password itself is never stored anywhere.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
fwrite(STDOUT, "New admin password (at least 12 characters): ");
if (stripos(PHP_OS, 'WIN') !== 0) { shell_exec('stty -echo'); }
$pass = trim((string) fgets(STDIN));
if (stripos(PHP_OS, 'WIN') !== 0) { shell_exec('stty echo'); }
fwrite(STDOUT, PHP_EOL);
if (strlen($pass) < 12) { fwrite(STDERR, "Too short. Use at least 12 characters.\n"); exit(1); }
fwrite(STDOUT, password_hash($pass, PASSWORD_DEFAULT) . PHP_EOL);
