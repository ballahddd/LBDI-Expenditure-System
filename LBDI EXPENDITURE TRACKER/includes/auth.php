<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function start_session_if_needed(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function redirect_with_message(string $path, string $status, string $message): never
{
    $query = http_build_query([
        'status' => $status,
        'message' => $message,
    ]);

    header('Location: ' . $path . '?' . $query);
    exit;
}

function current_user(): ?array
{
    start_session_if_needed();
    return $_SESSION['user'] ?? null;
}

function require_auth(): array
{
    $user = current_user();
    if ($user === null) {
        header('Location: ./index.html?status=error&message=' . urlencode('Please sign in first.'));
        exit;
    }
    return $user;
}

