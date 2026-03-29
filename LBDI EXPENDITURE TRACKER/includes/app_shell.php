<?php
declare(strict_types=1);

/**
 * Shared application chrome: sidebar, top bar, scrollable workspace.
 * Pages call app_shell_begin() then output content, then app_shell_end().
 */

function app_user_display_name(array $user): string
{
    $full = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    return $full !== '' ? $full : ($user['username'] ?? 'User');
}

/**
 * @return array<string, array{label:string,href:string,icon:string,danger?:bool}>
 */
function app_shell_nav_items(): array
{
    return [
        'dashboard' => ['label' => 'Dashboard', 'href' => './dashboard.php', 'icon' => '▦'],
        'budget_setup' => ['label' => 'Budget Setup', 'href' => './budget-setup.php', 'icon' => '≡'],
        'record' => ['label' => 'Record Expenditure', 'href' => './record-expenditure.php', 'icon' => '＋'],
        'reports' => ['label' => 'Reports & Analysis', 'href' => './reports.php', 'icon' => '📊'],
        'settings' => ['label' => 'Settings', 'href' => './settings.php', 'icon' => '⚙'],
        'logout' => ['label' => 'Logout', 'href' => './logout.php', 'icon' => '⟲', 'danger' => true],
    ];
}

function app_shell_begin(
    string $htmlTitle,
    string $activeNavKey,
    array $user,
    string $pageTitle,
    string $pageSubtitle,
    ?string $headExtra = null
): void {
    $displayName = app_user_display_name($user);
    $nameH = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
    $emailH = htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8');
    $titleH = htmlspecialchars($htmlTitle, ENT_QUOTES, 'UTF-8');
    $pageTitleH = htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8');
    $pageSubH = htmlspecialchars($pageSubtitle, ENT_QUOTES, 'UTF-8');
    $navItems = app_shell_nav_items();
    ?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo $titleH; ?> — LBDI Expenditure Tracker</title>
    <link rel="stylesheet" href="./assets/css/styles.css" />
    <?php echo $headExtra ?? ''; ?>
  </head>
  <body class="appBody">
    <div class="appFrame dashLayout" id="appShell" data-app-shell>
      <aside class="dashSidebar appSidebar" aria-label="Application navigation">
        <div class="appSidebar__brandBlock">
          <div class="brand">
            <div class="brand__mark" aria-hidden="true">
              <span class="brand__markInner"></span>
            </div>
            <div class="brand__text">
              <p class="brand__org">Liberian Bank for Development &amp; Investment</p>
              <h1 class="brand__app">Budget Expenditure Tracker</h1>
            </div>
          </div>
          <p class="fineprint dashSidebar__user"><?php echo $nameH; ?></p>
        </div>

        <nav class="dashSidebarNav appSidebar__nav" aria-label="Primary">
          <?php foreach ($navItems as $key => $item): ?>
            <?php
            $isActive = $key === $activeNavKey;
            $classes = 'dashNav__item' . ($isActive ? ' is-active' : '') . (!empty($item['danger']) ? ' dashNav__item--danger' : '');
            ?>
            <a class="<?php echo $classes; ?>" href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>">
              <span class="dashNav__icon" aria-hidden="true"><?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?></span>
              <span class="dashNav__label"><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
          <?php endforeach; ?>
        </nav>

        <div class="appSidebar__footer fineprint" aria-hidden="true">Internal use</div>
      </aside>

      <div class="appMainColumn">
        <header class="appTopbar">
          <div class="appTopbar__left">
            <button type="button" class="iconBtn appSidebarToggle" aria-label="Toggle navigation menu">☰</button>
            <nav class="appBreadcrumb" aria-label="Breadcrumb">
              <span class="appBreadcrumb__root">LBDI Budget Tracker</span>
              <span class="appBreadcrumb__sep" aria-hidden="true">/</span>
              <span class="appBreadcrumb__current"><?php echo $pageTitleH; ?></span>
            </nav>
          </div>
          <div class="appTopbar__right">
            <div class="appUserChip" title="<?php echo $emailH; ?>">
              <span class="appUserChip__name"><?php echo $nameH; ?></span>
              <span class="appUserChip__email"><?php echo $emailH; ?></span>
            </div>
          </div>
        </header>

        <main class="appWorkspace" id="appWorkspace">
          <header class="appPageHeader">
            <h1 class="appPageHeader__title"><?php echo $pageTitleH; ?></h1>
            <p class="appPageHeader__subtitle"><?php echo $pageSubH; ?></p>
          </header>
    <?php
}

function app_shell_end(?string $footerScripts = null): void
{
    ?>
        </main>
      </div>
    </div>
    <?php echo $footerScripts ?? ''; ?>
    <script>
      (() => {
        const frame = document.getElementById("appShell");
        const btn = document.querySelector(".appSidebarToggle");
        if (!frame || !btn) return;
        btn.addEventListener("click", () => frame.classList.toggle("sidebar-collapsed"));
      })();
    </script>
  </body>
</html>
    <?php
}
