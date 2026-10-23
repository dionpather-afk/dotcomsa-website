<?php
declare(strict_types=1);
require __DIR__ . '/inc/admin.php';
$_SESSION = [];
session_destroy();
header('Location: index.php');
