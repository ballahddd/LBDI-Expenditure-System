<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_message('./settings.php', 'error', 'Invalid request method.');
}

$fyName = trim((string)($_POST['fy_name'] ?? ''));
$startDate = trim((string)($_POST['start_date'] ?? ''));
$endDate = trim((string)($_POST['end_date'] ?? ''));

function safe_len(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value);
    }
    return strlen($value);
}

if (safe_len($fyName) < 3) {
    redirect_with_message('./settings.php', 'error', 'Financial year name is too short.');
}

try {
    $start = new DateTimeImmutable($startDate);
    $end = new DateTimeImmutable($endDate);
} catch (Throwable $e) {
    redirect_with_message('./settings.php', 'error', 'Please provide valid start and end dates.');
}

if ($start >= $end) {
    redirect_with_message('./settings.php', 'error', 'End date must be after start date.');
}

try {
    $conn = db_connect();
    $sql = 'INSERT INTO financial_years (fy_name, start_date, end_date)
            VALUES (?, ?, ?)';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $fyName, $startDate, $endDate);
    $stmt->execute();

    redirect_with_message('./settings.php', 'success', 'Financial year created successfully.');
} catch (Throwable $e) {
    error_log('Create FY error: ' . $e->getMessage());
    redirect_with_message('./settings.php', 'error', 'Failed to create financial year. Try again.');
}

