<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/app_shell.php';
require_once __DIR__ . '/includes/budget_helpers.php';

$user = require_auth();

$status = (string)($_GET['status'] ?? '');
$message = (string)($_GET['message'] ?? '');
$alertClass = $status === 'success' ? 'alert--success' : ($status === 'error' ? 'alert--error' : 'alert--warn');

$financialYears = [];
$selectedFyId = null;
$selectedFyName = '';
$mains = [];

try {
    $conn = db_connect();
    $activeId = budget_get_active_financial_year_id($conn);

    $res = $conn->query('SELECT id, fy_name, start_date, end_date FROM financial_years ORDER BY start_date DESC');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $financialYears[] = $row;
        }
    }

    $reqFy = isset($_GET['fy']) ? (int)$_GET['fy'] : 0;
    if ($reqFy > 0) {
        $selectedFyId = $reqFy;
    } elseif ($activeId !== null) {
        $selectedFyId = $activeId;
    } elseif (!empty($financialYears)) {
        $selectedFyId = (int)$financialYears[0]['id'];
    }

    if ($selectedFyId !== null) {
        foreach ($financialYears as $fy) {
            if ((int)$fy['id'] === $selectedFyId) {
                $selectedFyName = (string)$fy['fy_name'];
                break;
            }
        }

        $fyInt = (int)$selectedFyId;
        $mainRes = $conn->query(
            'SELECT id, main_line_name, total_budget_amount
             FROM budget_main_lines
             WHERE financial_year_id = ' . $fyInt . '
             ORDER BY main_line_name ASC'
        );
        if ($mainRes) {
            while ($main = $mainRes->fetch_assoc()) {
                $mid = (int)$main['id'];
                $subs = [];
                $sres = $conn->query(
                    'SELECT id, sub_line_name, budget_amount FROM budget_sub_lines WHERE budget_main_line_id = ' . $mid . ' ORDER BY sub_line_name ASC'
                );
                if ($sres) {
                    while ($s = $sres->fetch_assoc()) {
                        $subs[] = $s;
                    }
                }
                $main['sub_lines'] = $subs;
                $mains[] = $main;
            }
        }
    }
} catch (Throwable $e) {
    error_log('budget-setup: ' . $e->getMessage());
}

app_shell_begin(
    'Budget Setup',
    'budget_setup',
    $user,
    'Budget Setup',
    'Define main lines and sub-lines for a financial year. Use Settings to create years and set the active year.'
);
?>

<?php if ($message !== ''): ?>
  <div class="alerts" style="margin-bottom:16px" aria-live="polite">
    <div class="alert <?php echo $alertClass === 'alert--warn' ? 'alert--warn' : $alertClass; ?>">
      <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
  </div>
<?php endif; ?>

<?php if (empty($financialYears)): ?>
  <article class="dashPanel">
    <p class="fineprint">No financial years yet. Go to <a class="link" href="./settings.php">Settings</a> to create one.</p>
  </article>
<?php else: ?>
  <div class="dashFormBlock" style="margin-top:0">
    <form class="dashForm" method="get" action="./budget-setup.php">
      <label class="field">
        <span class="label" style="margin:0 0 8px">Financial year</span>
        <select class="input" name="fy" onchange="this.form.submit()" style="max-width:360px">
          <?php foreach ($financialYears as $fy): ?>
            <option value="<?php echo (int)$fy['id']; ?>" <?php echo (int)$fy['id'] === (int)$selectedFyId ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars((string)$fy['fy_name'], ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
    </form>
  </div>

  <?php if ($selectedFyId === null): ?>
    <p class="fineprint">Select a financial year.</p>
  <?php else: ?>
    <article class="dashPanel">
      <h2 class="dashPanel__title">Add main line</h2>
      <form class="dashForm" action="./add-budget-main-line.php" method="post">
        <input type="hidden" name="financial_year_id" value="<?php echo (int)$selectedFyId; ?>" />
        <div class="dashFormRow">
          <label class="field">
            <span class="label" style="margin:0 0 8px">Main line name</span>
            <input class="input" name="main_line_name" type="text" required minlength="3" placeholder="e.g. Board Expenses" />
          </label>
          <div class="field" style="display:flex;align-items:flex-end">
            <button class="btn" type="submit" style="width:auto;min-width:160px"><span class="btn__text">+ Add main line</span></button>
          </div>
        </div>
      </form>
    </article>

    <?php if (empty($mains)): ?>
      <article class="dashPanel">
        <p class="fineprint">No main lines for this year. Add one above.</p>
      </article>
    <?php else: ?>
      <?php foreach ($mains as $main): ?>
        <?php
        $mid = (int)$main['id'];
        $subTotal = 0.0;
        foreach ($main['sub_lines'] as $sl) {
            $subTotal += (float)$sl['budget_amount'];
        }
        ?>
        <details class="budgetSetupBlock" open>
          <summary class="budgetSetupBlock__summary">
            <span class="budgetSetupBlock__title"><?php echo htmlspecialchars((string)$main['main_line_name'], ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="budgetSetupBlock__total">Total: <?php echo htmlspecialchars(number_format($subTotal, 2), ENT_QUOTES, 'UTF-8'); ?></span>
          </summary>
          <div class="budgetSetupBlock__body">
            <div class="reportTableWrap">
              <table class="reportTable">
                <thead>
                  <tr>
                    <th>Sub-line</th>
                    <th style="text-align:right">Budget</th>
                    <th style="width:100px"></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($main['sub_lines'] as $sl): ?>
                    <tr>
                      <td><?php echo htmlspecialchars((string)$sl['sub_line_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <td style="text-align:right"><?php echo htmlspecialchars(number_format((float)$sl['budget_amount'], 2), ENT_QUOTES, 'UTF-8'); ?></td>
                      <td>
                        <form action="./delete-budget-sub-line.php" method="post" style="display:inline" onsubmit="return confirm('Delete this sub-line?');">
                          <input type="hidden" name="sub_line_id" value="<?php echo (int)$sl['id']; ?>" />
                          <input type="hidden" name="budget_main_line_id" value="<?php echo $mid; ?>" />
                          <input type="hidden" name="financial_year_id" value="<?php echo (int)$selectedFyId; ?>" />
                          <button type="submit" class="btn btn--small btn--ghost">Delete</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <form class="dashForm budgetSetupAddSub" action="./add-budget-sub-line.php" method="post">
              <input type="hidden" name="budget_main_line_id" value="<?php echo $mid; ?>" />
              <div class="dashFormRow">
                <label class="field">
                  <span class="label" style="margin:0 0 8px">New sub-line</span>
                  <input class="input" name="sub_line_name" type="text" required minlength="3" placeholder="Sub-line name" />
                </label>
                <label class="field">
                  <span class="label" style="margin:0 0 8px">Budget amount</span>
                  <input class="input" name="budget_amount" type="number" step="0.01" min="0" required />
                </label>
                <div class="field" style="display:flex;align-items:flex-end">
                  <button class="btn" type="submit" style="width:auto"><span class="btn__text">Add sub-line</span></button>
                </div>
              </div>
            </form>

            <form action="./delete-budget-main-line.php" method="post" style="margin-top:12px" onsubmit="return confirm('Delete this entire main line and all sub-lines?');">
              <input type="hidden" name="main_line_id" value="<?php echo $mid; ?>" />
              <input type="hidden" name="financial_year_id" value="<?php echo (int)$selectedFyId; ?>" />
              <button type="submit" class="btn btn--small btn--dangerGhost">Delete main line</button>
            </form>
          </div>
        </details>
      <?php endforeach; ?>
    <?php endif; ?>
  <?php endif; ?>
<?php endif; ?>

<?php app_shell_end(); ?>
