<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_message('./budget-setup.php', 'error', 'Invalid request method.');
}

function safe_len(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value);
    }
    return strlen($value);
}

$financialYearId = (int)($_POST['financial_year_id'] ?? 0);
$mainLineName = trim((string)($_POST['main_line_name'] ?? ''));

if ($financialYearId <= 0 || safe_len($mainLineName) < 3) {
    redirect_with_message('./budget-setup.php?fy=' . max(0, $financialYearId), 'error', 'Please provide a valid financial year and main line name.');
}

try {
    $conn = db_connect();

    $sql = 'INSERT INTO budget_main_lines (financial_year_id, main_line_name)
            VALUES (?, ?)';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $financialYearId, $mainLineName);
    $stmt->execute();

    redirect_with_message('./budget-setup.php?fy=' . $financialYearId, 'success', 'Main budget line added successfully.');
} catch (Throwable $e) {
    error_log('Add main line error: ' . $e->getMessage());
    redirect_with_message('./budget-setup.php?fy=' . $financialYearId, 'error', 'Failed to add main budget line. Try again.');
}

