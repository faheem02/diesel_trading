<?php
require_once 'includes/db.php';
header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';
$db     = getDB();

// ── helpers ──────────────────────────────────────────────────────────────────
function sanitize($v) { return htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8'); }

function jsonOut($ok, $msg = '', $data = []) {
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $data));
    exit;
}

// ── ROUTER ────────────────────────────────────────────────────────────────────
switch ($action) {

    // ── LIST ──────────────────────────────────────────────────────────────────
    case 'list':
        $book    = in_array($_GET['book'] ?? '', ['cash','bank']) ? $_GET['book'] : 'cash';
        $type    = in_array($_GET['type'] ?? '', ['in','out'])    ? $_GET['type'] : '';
        $cat     = sanitize($_GET['cat'] ?? '');
        $from    = $_GET['from'] ?? '';
        $to      = $_GET['to']   ?? '';

        $where = ['book = ?'];
        $params = [$book];
        $types  = 's';

        if ($type)         { $where[] = 'type = ?';      $params[] = $type; $types .= 's'; }
        if ($cat)          { $where[] = 'category = ?';  $params[] = $cat;  $types .= 's'; }
        if ($from)         { $where[] = 'txn_date >= ?'; $params[] = $from; $types .= 's'; }
        if ($to)           { $where[] = 'txn_date <= ?'; $params[] = $to;   $types .= 's'; }

        $sql  = 'SELECT * FROM transactions WHERE ' . implode(' AND ', $where) . ' ORDER BY txn_date DESC, id DESC';
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Summary (un-filtered except book)
        $sum = $db->prepare('SELECT
            SUM(CASE WHEN type="in"  THEN amount ELSE 0 END) AS total_in,
            SUM(CASE WHEN type="out" THEN amount ELSE 0 END) AS total_out
            FROM transactions WHERE book = ?');
        $sum->bind_param('s', $book);
        $sum->execute();
        $summary = $sum->get_result()->fetch_assoc();

        // Categories for filter dropdown
        $cq   = $db->prepare('SELECT DISTINCT category FROM transactions WHERE book = ? ORDER BY category');
        $cq->bind_param('s', $book);
        $cq->execute();
        $cats = array_column($cq->get_result()->fetch_all(MYSQLI_ASSOC), 'category');

        jsonOut(true, '', ['rows' => $rows, 'summary' => $summary, 'categories' => $cats]);

    // ── CREATE ────────────────────────────────────────────────────────────────
    case 'create':
        $data = json_decode(file_get_contents('php://input'), true);
        $book = in_array($data['book'] ?? '', ['cash','bank']) ? $data['book'] : null;
        $type = in_array($data['type'] ?? '', ['in','out'])    ? $data['type'] : null;
        $date = $data['txn_date']    ?? '';
        $desc = sanitize($data['description'] ?? '');
        $cat  = sanitize($data['category']    ?? '');
        $amt  = floatval($data['amount']      ?? 0);
        $ref  = sanitize($data['reference']   ?? '');
        $notes= sanitize($data['notes']       ?? '');

        if (!$book || !$type || !$date || !$desc || !$cat || $amt <= 0)
            jsonOut(false, 'Missing or invalid fields.');

        $stmt = $db->prepare('INSERT INTO transactions (book,type,txn_date,description,category,amount,reference,notes) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->bind_param('sssssdss', $book, $type, $date, $desc, $cat, $amt, $ref, $notes);
        $stmt->execute();
        $id = $db->insert_id;
        $row = $db->query("SELECT * FROM transactions WHERE id=$id")->fetch_assoc();
        jsonOut(true, 'Transaction saved.', ['row' => $row]);

    // ── UPDATE ────────────────────────────────────────────────────────────────
    case 'update':
        $data = json_decode(file_get_contents('php://input'), true);
        $id   = intval($data['id'] ?? 0);
        $book = in_array($data['book'] ?? '', ['cash','bank']) ? $data['book'] : null;
        $type = in_array($data['type'] ?? '', ['in','out'])    ? $data['type'] : null;
        $date = $data['txn_date']    ?? '';
        $desc = sanitize($data['description'] ?? '');
        $cat  = sanitize($data['category']    ?? '');
        $amt  = floatval($data['amount']      ?? 0);
        $ref  = sanitize($data['reference']   ?? '');
        $notes= sanitize($data['notes']       ?? '');

        if (!$id || !$book || !$type || !$date || !$desc || !$cat || $amt <= 0)
            jsonOut(false, 'Missing or invalid fields.');

        $stmt = $db->prepare('UPDATE transactions SET book=?,type=?,txn_date=?,description=?,category=?,amount=?,reference=?,notes=? WHERE id=?');
        $stmt->bind_param('sssssdssі', $book, $type, $date, $desc, $cat, $amt, $ref, $notes, $id);
        // fix: bind_param type for int
        $stmt = $db->prepare('UPDATE transactions SET book=?,type=?,txn_date=?,description=?,category=?,amount=?,reference=?,notes=? WHERE id=?');
        $stmt->bind_param('sssssdssi', $book, $type, $date, $desc, $cat, $amt, $ref, $notes, $id);
        $stmt->execute();
        $row = $db->query("SELECT * FROM transactions WHERE id=$id")->fetch_assoc();
        jsonOut(true, 'Transaction updated.', ['row' => $row]);

    // ── DELETE ────────────────────────────────────────────────────────────────
    case 'delete':
        $data = json_decode(file_get_contents('php://input'), true);
        $id   = intval($data['id'] ?? 0);
        if (!$id) jsonOut(false, 'Invalid ID.');
        $db->prepare('DELETE FROM transactions WHERE id=?')->bind_param('i', $id) && true;
        $stmt = $db->prepare('DELETE FROM transactions WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        jsonOut(true, 'Transaction deleted.');

    // ── GET SINGLE ────────────────────────────────────────────────────────────
    case 'get':
        $id   = intval($_GET['id'] ?? 0);
        $stmt = $db->prepare('SELECT * FROM transactions WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row  = $stmt->get_result()->fetch_assoc();
        if (!$row) jsonOut(false, 'Not found.');
        jsonOut(true, '', ['row' => $row]);

    default:
        jsonOut(false, 'Unknown action.');
}
