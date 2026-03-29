<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

start_session_if_needed();
$_SESSION = [];
session_destroy();

header('Location: ./index.html?status=success&message=' . urlencode('Signed out successfully.'));
exit;
