<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/budget_helpers.php';

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

$budgetMainLineId = (int)($_POST['budget_main_line_id'] ?? 0);
$subLineName = trim((string)($_POST['sub_line_name'] ?? ''));
$budgetAmountRaw = trim((string)($_POST['budget_amount'] ?? ''));

if ($budgetMainLineId <= 0 || safe_len($subLineName) < 3) {
    redirect_with_message('./budget-setup.php', 'error', 'Please provide a valid main line and sub-line name.');
}

if (!preg_match('/^\d+(\.\d{1,2})?$/', $budgetAmountRaw)) {
    redirect_with_message('./budget-setup.php', 'error', 'Budget amount must be a valid non-negative number.');
}

$budgetAmount = (float)$budgetAmountRaw;

try {
    $conn = db_connect();

    $fyStmt = $conn->prepare('SELECT financial_year_id FROM budget_main_lines WHERE id = ? LIMIT 1');
    $fyStmt->bind_param('i', $budgetMainLineId);
    $fyStmt->execute();
    $fyStmt->bind_result($fyId);
    if (!$fyStmt->fetch()) {
        redirect_with_message('./budget-setup.php', 'error', 'Main line not found.');
    }
    $financialYearId = (int)$fyId;

    $sql = 'INSERT INTO budget_sub_lines (budget_main_line_id, sub_line_name, budget_amount)
            VALUES (?, ?, ?)';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('isd', $budgetMainLineId, $subLineName, $budgetAmount);
    $stmt->execute();

    budget_refresh_main_line_total($conn, $budgetMainLineId);

    redirect_with_message('./budget-setup.php?fy=' . $financialYearId, 'success', 'Sub-line added successfully.');
} catch (Throwable $e) {
    error_log('Add sub-line error: ' . $e->getMessage());
    redirect_with_message('./budget-setup.php', 'error', 'Failed to add sub-line. Try again.');
}

