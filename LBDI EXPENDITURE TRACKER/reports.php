<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/app_shell.php';
require_once __DIR__ . '/includes/budget_helpers.php';

$user = require_auth();

$financialYears = [];
$fyId = null;
$filterMainId = isset($_GET['main']) ? (int)$_GET['main'] : 0;
$detailRows = [];
$mainSummaries = [];
$dbError = '';
$flashStatus = (string)($_GET['status'] ?? '');
$flashMessage = (string)($_GET['message'] ?? '');

try {
    $conn = db_connect();
    $res = $conn->query('SELECT id, fy_name FROM financial_years ORDER BY start_date DESC');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $financialYears[] = $row;
        }
    }

    $validFyIds = array_map(static fn ($r) => (int)$r['id'], $financialYears);
    $reqFy = isset($_GET['fy']) ? (int)$_GET['fy'] : 0;
    if ($reqFy > 0 && in_array($reqFy, $validFyIds, true)) {
        $fyId = $reqFy;
    } else {
        $fyId = budget_get_active_financial_year_id($conn);
        if ($fyId !== null && !in_array($fyId, $validFyIds, true)) {
            $fyId = null;
        }
    }
    if ($fyId === null && $validFyIds !== []) {
        $fyId = $validFyIds[0];
    }

    if ($fyId !== null) {
        $mainWhere = 'b.financial_year_id = ' . (int)$fyId;
        if ($filterMainId > 0) {
            $mainWhere .= ' AND b.id = ' . $filterMainId;
        }

        $sql = 'SELECT b.id AS main_id, b.main_line_name, s.id AS sub_id, s.sub_line_name, s.budget_amount,
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
                $detailRows[] = [
                    'main_name' => (string)$r['main_line_name'],
                    'sub_name' => (string)$r['sub_line_name'],
                    'budget' => $b,
                    'spent' => $s,
                    'balance' => $bal,
                    'status' => $st,
                ];
            }
        }

        $sumSql = 'SELECT b.id, b.main_line_name,
                  (SELECT COALESCE(SUM(bs.budget_amount), 0) FROM budget_sub_lines bs WHERE bs.budget_main_line_id = b.id) AS budget_amt,
                  (SELECT COALESCE(SUM(e.amount), 0) FROM expenditures e
                   INNER JOIN budget_sub_lines s2 ON s2.id = e.sub_line_id
                   WHERE s2.budget_main_line_id = b.id) AS spent_amt
                  FROM budget_main_lines b
                  WHERE b.financial_year_id = ' . (int)$fyId . '
                  ORDER BY b.main_line_name ASC';
        $sr = $conn->query($sumSql);
        if ($sr) {
            while ($r = $sr->fetch_assoc()) {
                $mainSummaries[] = [
                    'name' => (string)$r['main_line_name'],
                    'budget' => (float)$r['budget_amt'],
                    'spent' => (float)$r['spent_amt'],
                ];
            }
        }
    }
} catch (Throwable $e) {
    error_log('reports: ' . $e->getMessage());
    $dbError = 'Import budget_schema.sql and expenditures_schema.sql if tables are missing.';
}

$chartLabels = array_column($mainSummaries, 'name');
$chartBudget = array_map(static fn ($r) => round($r['budget'], 2), $mainSummaries);
$chartSpent = array_map(static fn ($r) => round($r['spent'], 2), $mainSummaries);
$pieLabels = $chartLabels;
$pieData = array_map(static fn ($r) => round(max(0, $r['spent']), 2), $mainSummaries);

$footerScripts = '';
if ($fyId !== null && !empty($mainSummaries)) {
    $footerScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(() => {
  const barCfg = {
    type: "bar",
    data: {
      labels: ' . json_encode($chartLabels) . ',
      datasets: [
        { label: "Budget", data: ' . json_encode($chartBudget) . ', backgroundColor: "rgba(96,165,250,0.55)" },
        { label: "Actual", data: ' . json_encode($chartSpent) . ', backgroundColor: "rgba(34,197,94,0.55)" }
      ]
    },
    options: {
      responsive: true,
      plugins: { legend: { labels: { color: "#e5e7eb" } } },
      scales: {
        x: { ticks: { color: "#a7b0c0" }, grid: { color: "rgba(255,255,255,0.06)" } },
        y: { ticks: { color: "#a7b0c0" }, grid: { color: "rgba(255,255,255,0.06)" } }
      }
    }
  };
  const pieCfg = {
    type: "pie",
    data: {
      labels: ' . json_encode($pieLabels) . ',
      datasets: [{ data: ' . json_encode($pieData) . ', backgroundColor: [
        "rgba(96,165,250,0.75)","rgba(34,197,94,0.75)","rgba(251,191,36,0.75)","rgba(167,139,250,0.75)",
        "rgba(248,113,113,0.75)","rgba(52,211,153,0.75)","rgba(125,211,252,0.75)","rgba(251,146,60,0.75)"
      ]}]
    },
    options: {
      responsive: true,
      plugins: { legend: { position: "bottom", labels: { color: "#e5e7eb" } } }
    }
  };
  const b = document.getElementById("chartBudgetVsActual");
  const p = document.getElementById("chartSpendPie");
  if (b) new Chart(b, barCfg);
  if (p) new Chart(p, pieCfg);
})();
</script>';
}

app_shell_begin(
    'Reports & Analysis',
    'reports',
    $user,
    'Reports & monitoring',
    'Budget vs actual by sub-line, status signals, and charts.',
    null
);
?>

<?php if ($dbError !== ''): ?>
  <div class="alert alert--error" style="margin-bottom:16px"><?php echo htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<?php if ($flashMessage !== ''): ?>
  <div class="alert <?php echo $flashStatus === 'success' ? 'alert--success' : 'alert--error'; ?>" style="margin-bottom:16px"><?php echo htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<?php if (empty($financialYears)): ?>
  <article class="dashPanel"><p class="fineprint">Create a financial year in <a class="link" href="./settings.php">Settings</a>.</p></article>
<?php else: ?>
  <div class="reportToolbar dashFormBlock" style="margin-top:0">
    <form class="dashForm" method="get" action="./reports.php" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
      <label class="field" style="margin:0">
        <span class="label" style="margin:0 0 8px">Financial year</span>
        <select class="input" name="fy" style="min-width:200px">
          <?php foreach ($financialYears as $fy): ?>
            <option value="<?php echo (int)$fy['id']; ?>" <?php echo (int)$fy['id'] === (int)$fyId ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars((string)$fy['fy_name'], ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <?php if ($filterMainId > 0): ?>
        <input type="hidden" name="main" value="<?php echo (int)$filterMainId; ?>" />
      <?php endif; ?>
      <button class="btn" type="submit" style="width:auto"><span class="btn__text">Apply</span></button>
      <?php if ($fyId !== null): ?>
        <a class="btn dashBtn dashBtn--muted" style="width:auto;text-decoration:none" href="./export-report-csv.php?fy=<?php echo (int)$fyId; ?><?php echo $filterMainId > 0 ? '&main=' . (int)$filterMainId : ''; ?>">Export CSV</a>
        <button type="button" class="btn btn--ghost" style="width:auto;opacity:0.7" disabled title="Coming soon">Export PDF</button>
        <button type="button" class="btn btn--ghost" style="width:auto;opacity:0.7" disabled title="Use CSV in Excel">Export Excel</button>
      <?php endif; ?>
    </form>
    <?php if ($filterMainId > 0): ?>
      <p class="fineprint" style="margin-top:10px"><a class="link" href="./reports.php?fy=<?php echo (int)$fyId; ?>">Clear main-line filter</a></p>
    <?php endif; ?>
  </div>

  <?php if ($fyId === null): ?>
    <p class="fineprint">Create a financial year in <a class="link" href="./settings.php">Settings</a>, then return here.</p>
  <?php else: ?>
    <div class="reportCharts" style="display:grid;grid-template-columns:1fr;gap:16px;margin:20px 0">
      <?php if (!empty($mainSummaries)): ?>
        <div class="dashPanel">
          <h3 class="dashPanel__title">Budget vs actual (by main line)</h3>
          <div class="chartWrap"><canvas id="chartBudgetVsActual" height="120"></canvas></div>
        </div>
        <div class="dashPanel">
          <h3 class="dashPanel__title">Spending distribution</h3>
          <div class="chartWrap chartWrap--pie"><canvas id="chartSpendPie"></canvas></div>
        </div>
      <?php endif; ?>
    </div>

    <article class="dashPanel">
      <h2 class="dashPanel__title">Sub-line performance</h2>
      <?php if (empty($detailRows)): ?>
        <p class="fineprint">No sub-lines for this view. Use <a class="link" href="./budget-setup.php">Budget Setup</a>.</p>
      <?php else: ?>
        <div class="reportTableWrap">
          <table class="reportTable">
            <thead>
              <tr>
                <th>Main line</th>
                <th>Sub-line</th>
                <th style="text-align:right">Budget</th>
                <th style="text-align:right">Spent</th>
                <th style="text-align:right">Balance</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($detailRows as $dr): ?>
                <tr>
                  <td><?php echo htmlspecialchars($dr['main_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars($dr['sub_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td style="text-align:right"><?php echo htmlspecialchars(number_format($dr['budget'], 2), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td style="text-align:right"><?php echo htmlspecialchars(number_format($dr['spent'], 2), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td style="text-align:right"><?php echo htmlspecialchars(number_format($dr['balance'], 2), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>
                    <?php if ($dr['status'] === 'over'): ?>
                      <span class="statusPill statusPill--over">Over</span>
                    <?php elseif ($dr['status'] === 'warn'): ?>
                      <span class="statusPill statusPill--warn">Near limit</span>
                    <?php else: ?>
                      <span class="statusPill statusPill--ok">OK</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </article>
  <?php endif; ?>
<?php endif; ?>

<?php app_shell_end($footerScripts); ?>
