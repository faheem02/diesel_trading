<?php
/**
 * Single-tank workflow helper.
 *
 * The whole app assumes one default tank: tank pickers are hidden inputs and
 * every stock movement (purchase, sale, opening stock, adjustment) posts to
 * that tank. Previously pages fell back to a hardcoded tank_id = 1 when no
 * tank existed, which silently skipped stock updates or crashed on the
 * stock_ledger foreign key after a data reset.
 *
 * resolve_default_tank() returns all tanks ordered by id. If the tanks table
 * is empty it creates a default "Main Tank" first, so a valid tank always
 * exists for stock postings. Use the first element as the default tank.
 *
 * Do NOT use this on read-only report pages — reports should show their
 * "no tank" empty state instead of creating data.
 */
function resolve_default_tank(mysqli $conn): array {
    $res = $conn->query("SELECT id, tank_name FROM tanks ORDER BY id ASC");
    $tanks = [];
    while ($row = $res->fetch_assoc()) {
        $tanks[] = $row;
    }

    if (empty($tanks)) {
        $conn->query("INSERT INTO tanks (tank_name, capacity, opening_stock, location, current_stock) VALUES ('Main Tank', 0, 0, '', 0)");
        $tanks[] = ['id' => intval($conn->insert_id), 'tank_name' => 'Main Tank'];
    }

    return $tanks;
}
