<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/app_shell.php';
require_once __DIR__ . '/includes/budget_helpers.php';

$user = require_auth();
$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$status = (string)($_GET['status'] ?? '');
$message = (string)($_GET['message'] ?? '');
$alertClass = $status === 'success' ? 'alert--success' : 'alert--error';

$financialYears = [];
$activeFyId = null;
try {
    $conn = db_connect();
    $activeFyId = budget_get_active_financial_year_id($conn);
    $fyRes = $conn->query('SELECT id, fy_name, start_date, end_date FROM financial_years ORDER BY start_date DESC');
    if ($fyRes) {
        while ($row = $fyRes->fetch_assoc()) {
            $financialYears[] = $row;
        }
    }
} catch (Throwable $e) {
    error_log('Settings load error: ' . $e->getMessage());
}

app_shell_begin(
    'Settings',
    'settings',
    $user,
    'Settings',
    'Profile, financial years, and which year is active. Use Budget Setup for main lines and sub-lines.'
);
?>

<section class="dashPanels" aria-label="Settings sections">
  <article class="dashPanel">
    <h2 class="dashPanel__title">Profile</h2>
    <ul class="dashList">
      <li>Name: <?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></li>
      <li>Username: <?php echo htmlspecialchars((string)$user['username'], ENT_QUOTES, 'UTF-8'); ?></li>
      <li>Email: <?php echo htmlspecialchars((string)$user['email'], ENT_QUOTES, 'UTF-8'); ?></li>
    </ul>
  </article>

  <article class="dashPanel">
    <h2 class="dashPanel__title">Financial years</h2>

    <?php if ($message !== ''): ?>
      <div class="alerts" aria-live="polite" aria-atomic="true">
        <div class="alert <?php echo $alertClass; ?>">
          <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="dashFormBlock">
      <h3 class="dashFormBlock__title">Create new financial year</h3>
      <p class="fineprint" style="margin:0 0 12px">Use at the start of each cycle. Then open <a class="link" href="./budget-setup.php">Budget Setup</a> to add main and sub-lines.</p>
      <form class="dashForm" action="./create-financial-year.php" method="post">
        <div class="dashFormRow">
          <label class="field">
            <span class="label" style="margin:0 0 8px">Financial year name</span>
            <input class="input" name="fy_name" type="text" required minlength="3" />
          </label>
          <label class="field">
            <span class="label" style="margin:0 0 8px">Start date</span>
            <input class="input" name="start_date" type="date" required />
          </label>
        </div>
        <div class="dashFormRow" style="margin-top:12px">
          <label class="field">
            <span class="label" style="margin:0 0 8px">End date</span>
            <input class="input" name="end_date" type="date" required />
          </label>
          <div class="field" style="display:flex; align-items:flex-end">
            <button class="btn" type="submit" style="width: auto; min-width: 200px">
              <span class="btn__text">Create financial year</span>
            </button>
          </div>
        </div>
      </form>
    </div>

    <div class="dashFormBlock">
      <h3 class="dashFormBlock__title">Active financial year</h3>
      <p class="fineprint" style="margin:0 0 12px">Dashboard and record expenditure use the active year.</p>
      <?php if (empty($financialYears)): ?>
        <p class="fineprint">No years yet. Create one above.</p>
      <?php else: ?>
        <form class="dashForm" action="./set-active-fy.php" method="post">
          <label class="field">
            <span class="label" style="margin:0 0 8px">Switch active year</span>
            <select class="input" name="financial_year_id" required>
              <?php foreach ($financialYears as $fy): ?>
                <option value="<?php echo (int)$fy['id']; ?>" <?php echo $activeFyId !== null && (int)$fy['id'] === (int)$activeFyId ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars((string)$fy['fy_name'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <button class="btn" type="submit" style="width: auto; min-width: 200px; margin-top: 12px">
            <span class="btn__text">Set as active</span>
          </button>
        </form>
      <?php endif; ?>
    </div>
  </article>
</section>

<?php app_shell_end(); ?>
