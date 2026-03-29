<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_message('./settings.php', 'error', 'Invalid request method.');
}

$fyId = (int)($_POST['financial_year_id'] ?? 0);
if ($fyId <= 0) {
    redirect_with_message('./settings.php', 'error', 'Select a financial year.');
}

try {
    $conn = db_connect();
    $stmt = $conn->prepare('SELECT id FROM financial_years WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $fyId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
        redirect_with_message('./settings.php', 'error', 'Financial year not found.');
    }

    $upsert = $conn->prepare(
        'INSERT INTO app_settings (setting_key, setting_value) VALUES (\'active_financial_year_id\', ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $fyIdStr = (string)$fyId;
    $upsert->bind_param('s', $fyIdStr);
    $upsert->execute();

    redirect_with_message('./settings.php', 'success', 'Active financial year updated.');
} catch (Throwable $e) {
    error_log('set-active-fy: ' . $e->getMessage());
    redirect_with_message('./settings.php', 'error', 'Could not update active year. Import expenditures_schema.sql if missing tables.');
}
