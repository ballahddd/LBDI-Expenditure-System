<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/app_shell.php';
require_once __DIR__ . '/includes/budget_helpers.php';

$user = require_auth();

$status = (string)($_GET['status'] ?? '');
$message = (string)($_GET['message'] ?? '');
$prefillDate = (string)($_GET['prefill_date'] ?? date('Y-m-d'));
$prefillSub = (string)($_GET['prefill_sub'] ?? '');
$prefillAmount = (string)($_GET['prefill_amount'] ?? '');
$prefillDesc = (string)($_GET['prefill_desc'] ?? '');
$overspend = isset($_GET['overspend']) && $_GET['overspend'] === '1';

$fyId = null;
$mainsOptions = [];
$subsByMain = [];
$recent = [];
$dbError = '';

try {
    $conn = db_connect();
    $fyId = budget_get_active_financial_year_id($conn);
    if ($fyId !== null) {
        $res = $conn->query(
            'SELECT id, main_line_name FROM budget_main_lines WHERE financial_year_id = ' . (int)$fyId . ' ORDER BY main_line_name ASC'
        );
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $mainsOptions[] = $r;
                $mid = (int)$r['id'];
                $subsByMain[$mid] = [];
                $sr = $conn->query(
                    'SELECT id, sub_line_name FROM budget_sub_lines WHERE budget_main_line_id = ' . $mid . ' ORDER BY sub_line_name ASC'
                );
                if ($sr) {
                    while ($s = $sr->fetch_assoc()) {
                        $subsByMain[$mid][] = $s;
                    }
                }
            }
        }
    }

    $recentSql = 'SELECT e.expense_date, e.amount, e.description, s.sub_line_name, b.main_line_name
                  FROM expenditures e
                  INNER JOIN budget_sub_lines s ON s.id = e.sub_line_id
                  INNER JOIN budget_main_lines b ON b.id = s.budget_main_line_id
                  ORDER BY e.expense_date DESC, e.id DESC
                  LIMIT 15';
    $rr = $conn->query($recentSql);
    if ($rr) {
        while ($row = $rr->fetch_assoc()) {
            $recent[] = $row;
        }
    }
} catch (Throwable $e) {
    error_log('record-expenditure: ' . $e->getMessage());
    $dbError = 'Ensure expenditures_schema.sql is imported so expenses can be saved.';
}

$subsJson = htmlspecialchars(json_encode($subsByMain), ENT_QUOTES, 'UTF-8');

app_shell_begin(
    'Record expenditure',
    'record',
    $user,
    'Record expenditure',
    'Post spending against a budget sub-line. Amounts update balances immediately.'
);
?>

<?php if ($dbError !== ''): ?>
  <div class="alert alert--error" style="margin-bottom:16px"><?php echo htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<?php if ($message !== ''): ?>
  <div class="alerts" style="margin-bottom:16px" aria-live="polite">
    <div class="alert <?php echo $status === 'success' ? 'alert--success' : 'alert--error'; ?>">
      <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($overspend): ?>
  <div class="alert alert--warn" style="margin-bottom:16px" role="alert">
    This expense exceeds the allocated budget for the selected sub-line. Check the box below to confirm, then save again.
  </div>
<?php endif; ?>

<?php if ($fyId === null || empty($mainsOptions)): ?>
  <article class="dashPanel">
    <p class="fineprint">No budget lines available. Set an active year in <a class="link" href="./settings.php">Settings</a> and add lines in <a class="link" href="./budget-setup.php">Budget Setup</a>.</p>
  </article>
<?php else: ?>
  <article class="dashPanel">
    <h2 class="dashPanel__title">Record new expense</h2>
    <form class="dashForm expenseForm" action="./save-expenditure.php" method="post" id="expenseForm">
      <div class="dashFormRow">
        <label class="field">
          <span class="label" style="margin:0 0 8px">Date</span>
          <input class="input" type="date" name="expense_date" required value="<?php echo htmlspecialchars($prefillDate, ENT_QUOTES, 'UTF-8'); ?>" />
        </label>
        <label class="field">
          <span class="label" style="margin:0 0 8px">Amount</span>
          <input class="input" type="number" name="amount" step="0.01" min="0.01" required value="<?php echo htmlspecialchars($prefillAmount, ENT_QUOTES, 'UTF-8'); ?>" id="expAmount" />
        </label>
      </div>
      <label class="field">
        <span class="label" style="margin:0 0 8px">Main line</span>
        <select class="input" id="expMain" required>
          <option value="">Select main line</option>
          <?php foreach ($mainsOptions as $m): ?>
            <option value="<?php echo (int)$m['id']; ?>"><?php echo htmlspecialchars((string)$m['main_line_name'], ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field">
        <span class="label" style="margin:0 0 8px">Sub-line</span>
        <select class="input" name="sub_line_id" id="expSub" required>
          <option value="">Select sub-line</option>
        </select>
      </label>
      <label class="field">
        <span class="label" style="margin:0 0 8px">Description</span>
        <input class="input" type="text" name="description" required maxlength="500" value="<?php echo htmlspecialchars($prefillDesc, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Brief description of the expense" />
      </label>
      <?php if ($overspend): ?>
        <label class="check" style="margin-top:8px">
          <input class="check__input" type="checkbox" name="confirm_overspend" value="1" required />
          <span class="check__box" aria-hidden="true"></span>
          <span class="check__label">I confirm this expense exceeds the sub-line budget</span>
        </label>
      <?php endif; ?>
      <button class="btn" type="submit" style="width:auto;min-width:200px;margin-top:12px">
        <span class="btn__text">Save expense</span>
      </button>
    </form>
  </article>

  <script>
    (() => {
      const subsByMain = <?php echo json_encode($subsByMain); ?>;
      const mainEl = document.getElementById("expMain");
      const subEl = document.getElementById("expSub");
      const prefillSub = <?php echo json_encode($prefillSub); ?>;
      function fillSubs() {
        const mid = mainEl.value;
        subEl.innerHTML = '<option value="">Select sub-line</option>';
        if (!mid || !subsByMain[mid]) return;
        subsByMain[mid].forEach((s) => {
          const o = document.createElement("option");
          o.value = s.id;
          o.textContent = s.sub_line_name;
          subEl.appendChild(o);
        });
        if (prefillSub) {
          subEl.value = String(prefillSub);
        }
      }
      mainEl.addEventListener("change", fillSubs);
      if (mainEl.value) fillSubs();
      else if (prefillSub) {
        for (const mid of Object.keys(subsByMain)) {
          if (subsByMain[mid].some((s) => String(s.id) === String(prefillSub))) {
            mainEl.value = mid;
            fillSubs();
            break;
          }
        }
      }
    })();
  </script>
<?php endif; ?>

<article class="dashPanel" style="margin-top:20px">
  <h2 class="dashPanel__title">Recent entries</h2>
  <?php if (empty($recent)): ?>
    <p class="fineprint">No expenditures recorded yet.</p>
  <?php else: ?>
    <div class="reportTableWrap">
      <table class="reportTable">
        <thead>
          <tr>
            <th>Date</th>
            <th>Category</th>
            <th style="text-align:right">Amount</th>
            <th>Description</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars((string)$r['expense_date'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($r['main_line_name'] . ' — ' . $r['sub_line_name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td style="text-align:right"><?php echo htmlspecialchars(number_format((float)$r['amount'], 2), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$r['description'], ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</article>

<?php app_shell_end(); ?>
