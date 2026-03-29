<?php
declare(strict_types=1);

/**
 * Active FY, main-line totals, budget vs spent status helpers.
 */

function budget_refresh_main_line_total(mysqli $conn, int $mainLineId): void
{
    $sql = 'UPDATE budget_main_lines
            SET total_budget_amount = (
              SELECT COALESCE(SUM(budget_amount), 0)
              FROM budget_sub_lines
              WHERE budget_main_line_id = ?
            )
            WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $mainLineId, $mainLineId);
    $stmt->execute();
}

function budget_get_active_financial_year_id(mysqli $conn): ?int
{
    $res = $conn->query("SELECT setting_value FROM app_settings WHERE setting_key = 'active_financial_year_id' LIMIT 1");
    if ($res && ($row = $res->fetch_assoc())) {
        $id = (int)$row['setting_value'];
        if ($id > 0) {
            return $id;
        }
    }
    $res2 = $conn->query('SELECT id FROM financial_years ORDER BY start_date DESC LIMIT 1');
    if ($res2 && ($row2 = $res2->fetch_assoc())) {
        return (int)$row2['id'];
    }
    return null;
}

/**
 * @return 'over'|'warn'|'ok'
 */
function budget_status_for_amounts(float $budget, float $spent): string
{
    if ($budget <= 0) {
        return $spent > 0 ? 'over' : 'ok';
    }
    if ($spent > $budget) {
        return 'over';
    }
    if ($spent >= $budget * 0.85) {
        return 'warn';
    }
    return 'ok';
}

function budget_spent_for_sub_line(mysqli $conn, int $subLineId): float
{
    $sql = 'SELECT COALESCE(SUM(amount), 0) AS s FROM expenditures WHERE sub_line_id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $subLineId);
    $stmt->execute();
    $stmt->bind_result($sum);
    $stmt->fetch();
    return (float)$sum;
}
