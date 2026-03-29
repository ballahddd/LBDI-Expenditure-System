<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/budget_helpers.php';

$user = require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_message('./record-expenditure.php', 'error', 'Invalid request method.');
}

$subLineId = (int)($_POST['sub_line_id'] ?? 0);
$expenseDate = trim((string)($_POST['expense_date'] ?? ''));
$amountRaw = trim((string)($_POST['amount'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$confirmOverspend = isset($_POST['confirm_overspend']) && $_POST['confirm_overspend'] === '1';

if ($subLineId <= 0 || $description === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expenseDate)) {
    redirect_with_message('./record-expenditure.php', 'error', 'Please fill date, sub-line, amount, and description.');
}

if (!preg_match('/^\d+(\.\d{1,2})?$/', $amountRaw)) {
    redirect_with_message('./record-expenditure.php', 'error', 'Amount must be a valid number.');
}

$amount = (float)$amountRaw;
if ($amount <= 0) {
    redirect_with_message('./record-expenditure.php', 'error', 'Amount must be greater than zero.');
}

try {
    $conn = db_connect();

    $stmt = $conn->prepare('SELECT budget_amount FROM budget_sub_lines WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $subLineId);
    $stmt->execute();
    $stmt->bind_result($budgetAmt);
    if (!$stmt->fetch()) {
        redirect_with_message('./record-expenditure.php', 'error', 'Sub-line not found.');
    }
    $budget = (float)$budgetAmt;

    $spentBefore = budget_spent_for_sub_line($conn, $subLineId);
    $after = $spentBefore + $amount;

    if ($after > $budget && !$confirmOverspend) {
        $q = http_build_query([
            'prefill_date' => $expenseDate,
            'prefill_sub' => (string)$subLineId,
            'prefill_amount' => $amountRaw,
            'prefill_desc' => $description,
            'overspend' => '1',
        ]);
        header('Location: ./record-expenditure.php?' . $q);
        exit;
    }

    $ins = $conn->prepare(
        'INSERT INTO expenditures (sub_line_id, expense_date, amount, description)
         VALUES (?, ?, ?, ?)'
    );
    $ins->bind_param('isds', $subLineId, $expenseDate, $amount, $description);
    $ins->execute();

    redirect_with_message('./record-expenditure.php', 'success', 'Expense recorded successfully.');
} catch (Throwable $e) {
    error_log('save-expenditure: ' . $e->getMessage());
    redirect_with_message('./record-expenditure.php', 'error', 'Could not save. Ensure expenditures table exists (import expenditures_schema.sql).');
}
