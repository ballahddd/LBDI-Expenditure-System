<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/budget_helpers.php';

require_auth();

$fyId = (int)($_GET['fy'] ?? 0);
$filterMainId = (int)($_GET['main'] ?? 0);
if ($fyId <= 0) {
    header('Location: ./reports.php');
    exit;
}

$rows = [];
try {
    $conn = db_connect();
    $chk = $conn->prepare('SELECT fy_name FROM financial_years WHERE id = ? LIMIT 1');
    $chk->bind_param('i', $fyId);
    $chk->execute();
    $chk->bind_result($fyName);
    if (!$chk->fetch()) {
        header('Location: ./reports.php?status=error&message=' . rawurlencode('Financial year not found.'));
        exit;
    }
    $fyLabel = (string)$fyName;

    $mainWhere = 'b.financial_year_id = ' . (int)$fyId;
    if ($filterMainId > 0) {
        $mainWhere .= ' AND b.id = ' . (int)$filterMainId;
    }

    $sql = 'SELECT b.main_line_name, s.sub_line_name, s.budget_amount,
            (SELECT COALESCE(SUM(e.amount), 0) FROM expenditures e WHERE e.sub_line_id = s.id) AS spent_amt
            FROM budget_main_lines b
            INNER JOIN budget_sub_lines s ON s.budget_main_line_id = b.id
            WHERE ' . $mainWhere . '
            ORDER BY b.main_line_name ASC, s.sub_line_name ASC';
    $qr = $conn->query($sql);
    if ($qr) {
        while ($r = $qr->fetch_assoc()) {
            $b = (float)$r['budget_amount'];
            $s = (float)$r['spent_amt'];
            $bal = $b - $s;
            $st = budget_status_for_amounts($b, $s);
            $statusLabel = $st === 'over' ? 'Over' : ($st === 'warn' ? 'Near limit' : 'OK');
            $rows[] = [
                (string)$r['main_line_name'],
                (string)$r['sub_line_name'],
                $b,
                $s,
                $bal,
                $statusLabel,
            ];
        }
    }
} catch (Throwable $e) {
    error_log('export-report-csv: ' . $e->getMessage());
    header('Location: ./reports.php?status=error&message=' . rawurlencode('Could not export. Check database tables.'));
    exit;
}

$safeName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $fyLabel) ?: 'fy-' . $fyId;
$filename = 'budget-report-' . $safeName . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
if ($out === false) {
    exit;
}

// UTF-8 BOM for Excel
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['Financial year', $fyLabel]);
fputcsv($out, ['Main line', 'Sub-line', 'Budget', 'Spent', 'Balance', 'Status']);
foreach ($rows as $row) {
    fputcsv($out, [
        $row[0],
        $row[1],
        number_format($row[2], 2, '.', ''),
        number_format($row[3], 2, '.', ''),
        number_format($row[4], 2, '.', ''),
        $row[5],
    ]);
}
fclose($out);
exit;
