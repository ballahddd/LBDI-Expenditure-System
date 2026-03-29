<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/app_shell.php';
require_once __DIR__ . '/includes/budget_helpers.php';

$user = require_auth();

$fyId = null;
$fyName = '';
$totalBudget = 0.0;
$totalSpent = 0.0;
$mainRows = [];
$dbError = '';

try {
    $conn = db_connect();
    $fyId = budget_get_active_financial_year_id($conn);
    if ($fyId !== null) {
        $stmt = $conn->prepare('SELECT fy_name FROM financial_years WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $fyId);
        $stmt->execute();
        $stmt->bind_result($fn);
        if ($stmt->fetch()) {
            $fyName = (string)$fn;
        }

        $sql = 'SELECT b.id, b.main_line_name,
                (SELECT COALESCE(SUM(bs.budget_amount), 0) FROM budget_sub_lines bs WHERE bs.budget_main_line_id = b.id) AS budget_amt,
                (SELECT COALESCE(SUM(e.amount), 0) FROM expenditures e
                 INNER JOIN budget_sub_lines s2 ON s2.id = e.sub_line_id
                 WHERE s2.budget_main_line_id = b.id) AS spent_amt
                FROM budget_main_lines b
                WHERE b.financial_year_id = ?
                ORDER BY b.main_line_name ASC';
        $q = $conn->prepare($sql);
        $q->bind_param('i', $fyId);
        $q->execute();
        $q->bind_result($mid, $mname, $bamt, $samt);
        while ($q->fetch()) {
            $b = (float)$bamt;
            $s = (float)$samt;
            $bal = $b - $s;
            $st = budget_status_for_amounts($b, $s);
            $mainRows[] = [
                'id' => (int)$mid,
                'name' => (string)$mname,
                'budget' => $b,
                'spent' => $s,
                'balance' => $bal,
                'status' => $st,
            ];
            $totalBudget += $b;
            $totalSpent += $s;
        }
    }
} catch (Throwable $e) {
    error_log('dashboard: ' . $e->getMessage());
    $dbError = 'Database tables may be missing. Import budget_schema.sql and expenditures_schema.sql.';
}

$remaining = $totalBudget - $totalSpent;

app_shell_begin(
    'Dashboard',
    'dashboard',
    $user,
    'Dashboard',
    'Home — budget vs spending for the active financial year.'
);
?>

<?php if ($dbError !== ''): ?>
  <div class="alert alert--error" style="margin-bottom:16px"><?php echo htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<?php if ($fyId === null): ?>
  <article class="dashPanel">
    <p class="fineprint">No financial year configured. Open <a class="link" href="./settings.php">Settings</a> to create one and set it active, then use <a class="link" href="./budget-setup.php">Budget Setup</a> for lines.</p>
  </article>
<?php else: ?>
  <div class="fyBanner">
    <span class="fyBanner__label">Financial year</span>
    <span class="fyBanner__value"><?php echo htmlspecialchars($fyName, ENT_QUOTES, 'UTF-8'); ?></span>
  </div>

  <section class="summaryCards" aria-label="Summary">
    <article class="summaryCard summaryCard--neutral">
      <p class="summaryCard__label">Total budget</p>
      <p class="summaryCard__value"><?php echo htmlspecialchars(number_format($totalBudget, 2), ENT_QUOTES, 'UTF-8'); ?></p>
    </article>
    <article class="summaryCard summaryCard--spent">
      <p class="summaryCard__label">Total spent</p>
      <p class="summaryCard__value"><?php echo htmlspecialchars(number_format($totalSpent, 2), ENT_QUOTES, 'UTF-8'); ?></p>
    </article>
    <article class="summaryCard <?php echo $remaining >= 0 ? 'summaryCard--ok' : 'summaryCard--over'; ?>">
      <p class="summaryCard__label">Remaining balance</p>
      <p class="summaryCard__value"><?php echo htmlspecialchars(number_format($remaining, 2), ENT_QUOTES, 'UTF-8'); ?></p>
    </article>
  </section>

  <article class="dashPanel" style="margin-top:20px">
    <h2 class="dashPanel__title">Budget overview</h2>
    <?php if (empty($mainRows)): ?>
      <p class="fineprint">No main lines for this year. Go to <a class="link" href="./budget-setup.php">Budget Setup</a>.</p>
    <?php else: ?>
      <div class="reportTableWrap">
        <table class="reportTable reportTable--dashboard">
          <thead>
            <tr>
              <th>Main line</th>
              <th style="text-align:right">Budget</th>
              <th style="text-align:right">Spent</th>
              <th style="text-align:right">Balance</th>
              <th style="text-align:center;width:72px">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($mainRows as $row): ?>
              <tr class="reportTable__clickRow" onclick="window.location.href='./reports.php?fy=<?php echo (int)$fyId; ?>&main=<?php echo (int)$row['id']; ?>'">
                <td><?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td style="text-align:right"><?php echo htmlspecialchars(number_format($row['budget'], 2), ENT_QUOTES, 'UTF-8'); ?></td>
                <td style="text-align:right"><?php echo htmlspecialchars(number_format($row['spent'], 2), ENT_QUOTES, 'UTF-8'); ?></td>
                <td style="text-align:right"><?php echo htmlspecialchars(number_format($row['balance'], 2), ENT_QUOTES, 'UTF-8'); ?></td>
                <td style="text-align:center">
                  <?php if ($row['status'] === 'over'): ?>
                    <span class="statusDot statusDot--over" title="Over budget">●</span>
                  <?php elseif ($row['status'] === 'warn'): ?>
                    <span class="statusDot statusDot--warn" title="Near budget">●</span>
                  <?php else: ?>
                    <span class="statusDot statusDot--ok" title="Within budget">●</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="fineprint" style="margin-top:10px">Green = within budget · Yellow = near limit (85%+) · Red = over budget. Click a row for detail in Reports.</p>
    <?php endif; ?>
  </article>

  <div class="dashActions" style="margin-top:16px;grid-template-columns:repeat(auto-fill,minmax(200px,1fr))">
    <a class="btn dashBtn" href="./record-expenditure.php">Record expenditure</a>
    <a class="btn dashBtn dashBtn--muted" href="./budget-setup.php">Budget setup</a>
    <a class="btn dashBtn dashBtn--muted" href="./reports.php">Reports &amp; analysis</a>
  </div>
<?php endif; ?>

<?php app_shell_end(); ?>
