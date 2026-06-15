<?php

function postToLedger($conn, $supplier_id, $transaction_date, $description, $debit, $credit, $reference_type, $reference_id = null)
{
    $stmt = $conn->prepare("
        INSERT INTO supplier_ledger (supplier_id, transaction_date, description, debit, credit, balance, reference_type, reference_id)
        VALUES (?, ?, ?, ?, ?, 0, ?, ?)
    ");
    $stmt->bind_param("issddsi", $supplier_id, $transaction_date, $description, $debit, $credit, $reference_type, $reference_id);
    $stmt->execute();
    $stmt->close();

    $bal = $conn->query("SELECT COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) AS bal FROM supplier_ledger WHERE supplier_id = $supplier_id")->fetch_assoc();
    $running = $bal['bal'];

    $id = $conn->insert_id;
    $conn->query("UPDATE supplier_ledger SET balance = $running WHERE id = $id");

    $conn->query("UPDATE suppliers SET balance = $running WHERE id = $supplier_id");

    return $id;
}
