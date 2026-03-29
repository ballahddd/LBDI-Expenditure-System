<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

function safe_len(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value);
    }
    return strlen($value);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_message('./index.html', 'error', 'Invalid request method.');
}

$identifier = trim((string)($_POST['identifier'] ?? ''));
$password = (string)($_POST['password'] ?? '');

if (safe_len($identifier) < 3 || safe_len($password) < 6) {
    redirect_with_message('./index.html', 'error', 'Invalid username/email or password.');
}

try {
    $conn = db_connect();
    $sql = 'SELECT id, username, email, password_hash, first_name, last_name, is_active
            FROM users
            WHERE username = ? OR email = ?
            LIMIT 1';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $identifier, $identifier);
    $stmt->execute();
    $stmt->bind_result($id, $username, $email, $passwordHash, $firstName, $lastName, $isActive);
    $hasRow = $stmt->fetch();

    if (!$hasRow) {
        redirect_with_message('./index.html', 'error', 'Account not found.');
    }

    if ((int)$isActive !== 1) {
        redirect_with_message('./index.html', 'error', 'Your account is inactive.');
    }

    if (!password_verify($password, (string)$passwordHash)) {
        redirect_with_message('./index.html', 'error', 'Incorrect password.');
    }

    start_session_if_needed();
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int)$id,
        'username' => (string)$username,
        'email' => (string)$email,
        'first_name' => (string)$firstName,
        'last_name' => (string)$lastName,
    ];

    header('Location: ./dashboard.php');
    exit;
} catch (Throwable $e) {
    redirect_with_message('./index.html', 'error', 'Login failed. Please try again.');
}
