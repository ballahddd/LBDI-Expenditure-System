<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/budget_helpers.php';

require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_message('./budget-setup.php', 'error', 'Invalid request method.');
}

$subId = (int)($_POST['sub_line_id'] ?? 0);
$mainId = (int)($_POST['budget_main_line_id'] ?? 0);
$fyId = (int)($_POST['financial_year_id'] ?? 0);
if ($subId <= 0 || $mainId <= 0) {
    redirect_with_message('./budget-setup.php' . ($fyId > 0 ? '?fy=' . $fyId : ''), 'error', 'Invalid sub-line.');
}

try {
    $conn = db_connect();

    $chk = $conn->prepare('SELECT COUNT(*) AS c FROM expenditures WHERE sub_line_id = ?');
    $chk->bind_param('i', $subId);
    $chk->execute();
    $chk->bind_result($cnt);
    $chk->fetch();
    if ((int)$cnt > 0) {
        redirect_with_message('./budget-setup.php' . ($fyId > 0 ? '?fy=' . $fyId : ''), 'error', 'Cannot delete a sub-line that has recorded expenditures.');
    }

    $del = $conn->prepare('DELETE FROM budget_sub_lines WHERE id = ? AND budget_main_line_id = ?');
    $del->bind_param('ii', $subId, $mainId);
    $del->execute();

    budget_refresh_main_line_total($conn, $mainId);

    redirect_with_message('./budget-setup.php' . ($fyId > 0 ? '?fy=' . $fyId : ''), 'success', 'Sub-line deleted.');
} catch (Throwable $e) {
    error_log('delete sub line: ' . $e->getMessage());
    redirect_with_message('./budget-setup.php' . ($fyId > 0 ? '?fy=' . $fyId : ''), 'error', 'Delete failed.');
}
