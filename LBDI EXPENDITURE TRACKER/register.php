<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

function safe_len(string $value): int
{
    // Some shared hosts may not have mbstring enabled.
    if (function_exists('mb_strlen')) {
        return mb_strlen($value);
    }
    return strlen($value);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_message('./index.html', 'error', 'Invalid request method.');
}

$username = trim((string)($_POST['username'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$firstName = trim((string)($_POST['first_name'] ?? ''));
$lastName = trim((string)($_POST['last_name'] ?? ''));

if (
    safe_len($username) < 3 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    safe_len($password) < 6 ||
    safe_len($firstName) < 1 ||
    safe_len($lastName) < 1
) {
    redirect_with_message('./index.html', 'error', 'Please provide valid registration details.');
}

try {
    $conn = db_connect();

    $checkSql = 'SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1';
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('ss', $username, $email);
    $checkStmt->execute();
    $checkStmt->store_result();
    if ($checkStmt->num_rows > 0) {
        redirect_with_message('./index.html', 'error', 'Username or email already exists.');
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    if ($passwordHash === false) {
        throw new RuntimeException('Password hashing failed.');
    }
    $insertSql = 'INSERT INTO users
        (username, email, password_hash, first_name, last_name, created_at, updated_at, is_active)
        VALUES (?, ?, ?, ?, ?, NOW(), NOW(), 1)';
    $insertStmt = $conn->prepare($insertSql);
    $insertStmt->bind_param('sssss', $username, $email, $passwordHash, $firstName, $lastName);
    $insertStmt->execute();

    redirect_with_message('./index.html', 'success', 'Account created. Please sign in.');
} catch (Throwable $e) {
    error_log('Register error: ' . $e->getMessage());
    redirect_with_message('./index.html', 'error', 'Registration failed. Please try again.');
}
