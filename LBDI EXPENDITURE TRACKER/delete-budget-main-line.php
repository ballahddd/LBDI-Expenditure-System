<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_message('./budget-setup.php', 'error', 'Invalid request method.');
}

$mainId = (int)($_POST['main_line_id'] ?? 0);
$fyId = (int)($_POST['financial_year_id'] ?? 0);
if ($mainId <= 0) {
    redirect_with_message('./budget-setup.php' . ($fyId > 0 ? '?fy=' . $fyId : ''), 'error', 'Invalid main line.');
}

try {
    $conn = db_connect();

    $chk = $conn->prepare(
        'SELECT COUNT(*) AS c FROM expenditures e
         INNER JOIN budget_sub_lines s ON s.id = e.sub_line_id
         WHERE s.budget_main_line_id = ?'
    );
    $chk->bind_param('i', $mainId);
    $chk->execute();
    $chk->bind_result($cnt);
    $chk->fetch();
    if ((int)$cnt > 0) {
        redirect_with_message('./budget-setup.php' . ($fyId > 0 ? '?fy=' . $fyId : ''), 'error', 'Remove or reclassify expenditures under this main line before deleting.');
    }

    $del = $conn->prepare('DELETE FROM budget_main_lines WHERE id = ?');
    $del->bind_param('i', $mainId);
    $del->execute();

    redirect_with_message('./budget-setup.php' . ($fyId > 0 ? '?fy=' . $fyId : ''), 'success', 'Main budget line deleted.');
} catch (Throwable $e) {
    error_log('delete main line: ' . $e->getMessage());
    redirect_with_message('./budget-setup.php' . ($fyId > 0 ? '?fy=' . $fyId : ''), 'error', 'Delete failed.');
}
