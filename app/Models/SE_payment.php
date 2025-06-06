<?php

namespace App\Models;

class SE_payment extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'payment_date',
        'amount',
        'supplier_id',
        'payee',
        'particulars',
        'approved_by',
        'approved_on',
        'disapproved_by',
        'disapproved_on',
        'completed_by',
        'completed_on',
        'printed_by',
        'printed_on',
        'status',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    /**
     * Get all cash payments details
     */
    public function get_all_payment($start_date, $end_date, $status, $supplier_id, $vendor_id, $payment_mode, $doc_no)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM
(
    SELECT se_cash_slip.id, 
        se_cash_entry.se_id,  
        se_cash_entry.type, 
        null AS from_account_name, 
        null AS to_account_name, 
        se_cash_slip.particulars, 
        se_cash_slip.added_on AS date, 
        null AS check_no,
        se_cash_slip.payment_date AS issued_date, 
        CONCAT('CASH-', se_cash_slip.id, '-', REPLACE(CAST(se_cash_slip.added_on AS DATE), '-', '')) AS doc_no, 
        'cash' AS payment_mode, 
        NULL AS bank_from_name, NULL as bank_to_name, 
        (SELECT supplier.trade_name FROM supplier WHERE supplier.id = se_cash_slip.supplier_id) AS supplier, 
        (SELECT vendor.trade_name FROM vendor WHERE vendor.id = se_cash_slip.vendor_id) AS vendor,
        se_cash_slip.payee AS payee, 
        se_cash_slip.amount AS amount, 
        se_cash_slip.status, 
        se_cash_slip.is_deleted, 
        se_cash_slip.supplier_id,
        se_cash_slip.vendor_id,
        Null as reference_no,
        (SELECT supplies_expense.grand_total FROM supplies_expense WHERE supplies_expense.id = se_cash_entry.se_id) AS grand_total
    FROM se_cash_slip
    LEFT JOIN (
        SELECT se_cash_entry.se_id, se_cash_entry.se_cash_slip_id, se_cash_entry.type
        FROM se_cash_entry
    ) se_cash_entry ON se_cash_entry.se_cash_slip_id = se_cash_slip.id

    UNION ALL

    SELECT se_bank_slip.id, 
    se_bank_entry.se_id, 
    se_bank_entry.type, 
    (SELECT bank.name FROM bank WHERE bank.id = se_bank_slip.bank_from) AS from_account_name, 
    (SELECT bank.name FROM bank WHERE bank.id = se_bank_slip.bank_to) AS to_account_name, 
    se_bank_slip.particulars,
    se_bank_slip.added_on AS date, 
    null AS check_no,
    se_bank_slip.payment_date AS issued_date, 
    CONCAT('BANK-', se_bank_slip.id, '-', REPLACE(CAST(se_bank_slip.added_on AS DATE), '-', '')) AS doc_no, 
    'bank' AS payment_mode, 
    (SELECT bank.name FROM bank WHERE bank.id = se_bank_slip.bank_from) AS bank_from_name, 
    se_bank_slip.bank_to AS bank_to_name, 
    (SELECT supplier.trade_name FROM supplier WHERE supplier.id = se_bank_slip.supplier_id) AS supplier, 
    (SELECT vendor.trade_name FROM vendor WHERE vendor.id = se_bank_slip.vendor_id) AS vendor,
    se_bank_slip.payee AS payee, 
    se_bank_slip.amount AS amount, 
    se_bank_slip.status, 
    se_bank_slip.is_deleted, 
    se_bank_slip.supplier_id,
    se_bank_slip.vendor_id,
    se_bank_slip.reference_no,
    (SELECT supplies_expense.grand_total FROM supplies_expense WHERE supplies_expense.id = se_bank_entry.se_id) AS grand_total
    FROM se_bank_slip
    LEFT JOIN (
        SELECT se_bank_entry.se_id, se_bank_entry.se_bank_slip_id, se_bank_entry.type
        FROM se_bank_entry
    ) se_bank_entry ON se_bank_entry.se_bank_slip_id = se_bank_slip.id

    UNION ALL

    SELECT se_gcash_slip.id, 
    se_gcash_entry.se_id,
    se_gcash_entry.type, 
    null AS from_account_name, 
    null AS to_account_name, 
    se_gcash_slip.particulars,
    se_gcash_slip.added_on AS date, 
    null AS check_no,
    se_gcash_slip.payment_date AS issued_date, 
    CONCAT('GCASH-', se_gcash_slip.id, '-', REPLACE(CAST(se_gcash_slip.added_on AS DATE), '-', '')) AS doc_no, 
    'gcash' AS payment_mode, 
    null AS bank_from_name, 
    null AS bank_to_name, 
    (SELECT supplier.trade_name FROM supplier WHERE supplier.id = se_gcash_slip.supplier_id) AS supplier, 
    (SELECT vendor.trade_name FROM vendor WHERE vendor.id = se_gcash_slip.vendor_id) AS vendor,
    se_gcash_slip.payee AS payee, 
    se_gcash_slip.amount AS amount, 
    se_gcash_slip.status, 
    se_gcash_slip.is_deleted, 
    se_gcash_slip.supplier_id,
    se_gcash_slip.vendor_id,
    se_gcash_slip.reference_no,
    (SELECT supplies_expense.grand_total FROM supplies_expense WHERE supplies_expense.id = se_gcash_entry.se_id) AS grand_total
    FROM se_gcash_slip
    LEFT JOIN (
        SELECT se_gcash_entry.se_id, se_gcash_entry.se_gcash_slip_id, se_gcash_entry.type
        FROM se_gcash_entry
    ) se_gcash_entry ON se_gcash_entry.se_gcash_slip_id = se_gcash_slip.id

    UNION ALL
    
    SELECT se_check_slip.id, 
    se_check_entry.se_id, 
    se_check_entry.type,
    (SELECT bank.name FROM bank WHERE bank.id = se_check_slip.bank_id) AS from_account_name, 
    Null AS to_account_name, 
    se_check_slip.particulars, 
    se_check_slip.added_on AS date, 
    se_check_slip.check_no,
    se_check_slip.issued_date AS issued_date, 
    CONCAT('CHECK-', se_check_slip.id, '-', REPLACE(CAST(se_check_slip.added_on AS DATE), '-', '')) AS doc_no, 
    'check' AS payment_mode, 
    (SELECT bank.name FROM bank WHERE bank.id = se_check_slip.bank_id) AS bank_from_name, 
    Null AS bank_to_name, 
    (SELECT supplier.trade_name FROM supplier WHERE supplier.id = se_check_slip.supplier_id) AS supplier, 
    (SELECT vendor.trade_name FROM vendor WHERE vendor.id = se_check_slip.vendor_id) AS vendor,
    se_check_slip.payee AS payee, se_check_slip.amount AS amount, 
    se_check_slip.status, 
    se_check_slip.is_deleted, 
    se_check_slip.supplier_id,
    se_check_slip.vendor_id,
    Null as reference_no,
    (SELECT supplies_expense.grand_total FROM supplies_expense WHERE supplies_expense.id = se_check_entry.se_id) AS grand_total
    FROM se_check_slip
    LEFT JOIN (
        SELECT se_check_entry.se_id, se_check_entry.se_check_slip_id, se_check_entry.type
        FROM se_check_entry
    ) se_check_entry ON se_check_entry.se_check_slip_id = se_check_slip.id
) supplies_payments
WHERE supplies_payments.is_deleted = 0
EOT;
        $binds = [];
        if ($status) {
            $sql .= ' AND supplies_payments.status = ?';
            $binds[] = $status;
        }

        if ($start_date) {
            $sql .= ' AND CAST(supplies_payments.issued_date AS DATE) >= ?';
            $binds[] = $start_date;
        }

        if ($end_date) {
            $sql .= ' AND CAST(supplies_payments.issued_date AS DATE) <= ?';
            $binds[] = $end_date;
        }

        if ($supplier_id) {
            $sql .= ' AND supplies_payments.supplier_id = ?';
            $binds[] = $supplier_id;
        }

        if ($vendor_id) {
            $sql .= ' AND supplies_payments.vendor_id = ?';
            $binds[] = $vendor_id;
        }

        if ($payment_mode) {
            $sql .= ' AND supplies_payments.payment_mode = ?';
            $binds[] = $payment_mode;
        }

        if ($doc_no) {
            $sql .= ' AND supplies_payments.doc_no LIKE ?';
            $binds[] = "%" . $doc_no . "%";
        }

        $sql .= ' ORDER BY date';

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all payments for a specific supplies_expense
     */
    public function get_payment_details_by_se($supplies_expense_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
(
    SELECT 
        'bank' AS payment_type,
        se_bank_slip.id,
        NULL AS check_date,
        se_bank_slip.payment_date,
        se_bank_slip.bank_from,
        se_bank_slip.from_account_no,
        se_bank_slip.from_account_name,
        se_bank_slip.bank_to,
        se_bank_slip.to_account_no,
        se_bank_slip.to_account_name,
        se_bank_slip.transaction_fee,
        se_bank_slip.reference_no AS payment_ref_no,
        se_bank_slip.amount,
        se_bank_slip.supplier_id,
        se_bank_slip.vendor_id,
        se_bank_slip.payee,
        se_bank_slip.particulars,
        se_bank_slip.acknowledged_by,
        se_bank_slip.approved_by,
        se_bank_slip.approved_on,
        se_bank_slip.disapproved_by,
        se_bank_slip.disapproved_on,
        se_bank_slip.completed_by,
        se_bank_slip.completed_on,
        se_bank_slip.printed_by,
        se_bank_slip.printed_on,
        se_bank_slip.status
    FROM se_bank_slip
    LEFT JOIN se_bank_entry ON se_bank_slip.id = se_bank_entry.se_bank_slip_id
    WHERE se_bank_slip.is_deleted = 0 
      AND se_bank_entry.se_id = ?
)
UNION
(
    SELECT 
        'gcash' AS payment_type,
        se_gcash_slip.id,
        NULL AS check_date,
        se_gcash_slip.payment_date,
        NULL AS bank_from,
        NULL AS from_account_no,
        se_gcash_slip.account_name AS from_account_name,
        NULL AS bank_to,
        se_gcash_slip.account_no AS to_account_no,
        se_gcash_slip.account_name AS to_account_name,
        0.00 AS transaction_fee,
        se_gcash_slip.reference_no AS payment_ref_no,
        se_gcash_slip.amount,
        se_gcash_slip.supplier_id,
        se_gcash_slip.vendor_id,
        se_gcash_slip.payee,
        se_gcash_slip.particulars,
        se_gcash_slip.acknowledged_by,
        se_gcash_slip.approved_by,
        se_gcash_slip.approved_on,
        se_gcash_slip.disapproved_by,
        se_gcash_slip.disapproved_on,
        se_gcash_slip.completed_by,
        se_gcash_slip.completed_on,
        se_gcash_slip.printed_by,
        se_gcash_slip.printed_on,
        se_gcash_slip.status
    FROM se_gcash_slip
    LEFT JOIN se_gcash_entry ON se_gcash_slip.id = se_gcash_entry.se_gcash_slip_id
    WHERE se_gcash_slip.is_deleted = 0
      AND se_gcash_entry.se_id = ?
)
UNION
(
    SELECT 
        'check' AS payment_type,
        se_check_slip.id,
        se_check_slip.check_date,
        se_check_slip.issued_date AS payment_date,
        se_check_slip.bank_id AS bank_from,
        NULL AS from_account_no,
        NULL AS from_account_name,
        NULL AS bank_to,
        NULL AS to_account_no,
        NULL AS to_account_name,
        0.00 AS transaction_fee,
        se_check_slip.check_no AS payment_ref_no,
        se_check_slip.amount,
        se_check_slip.supplier_id,
        se_check_slip.vendor_id,
        se_check_slip.payee,
        se_check_slip.particulars,
        se_check_slip.acknowledged_by,
        se_check_slip.approved_by,
        se_check_slip.approved_on,
        se_check_slip.disapproved_by,
        se_check_slip.disapproved_on,
        se_check_slip.completed_by,
        se_check_slip.completed_on,
        se_check_slip.printed_by,
        se_check_slip.printed_on,
        se_check_slip.status
    FROM se_check_slip
    LEFT JOIN se_check_entry ON se_check_slip.id = se_check_entry.se_check_slip_id
    WHERE se_check_slip.is_deleted = 0
      AND se_check_entry.se_id = ?
)
EOT;

        $binds = [$supplies_expense_id, $supplies_expense_id, $supplies_expense_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Search
     */
    public function search($payment_date, $amount, $supplier_id, $payee, $particulars)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM se_cash_slip
WHERE se_cash_slip.is_deleted = 0
EOT;
        $binds = [];

        if ($payment_date) {
            $sql .= ' AND se_cash_slip.payment_date = ?';
            $binds[] = $payment_date;
        }

        if ($amount) {
            $sql .= ' AND se_cash_slip.amount = ?';
            $binds[] = $amount;
        }

        if ($supplier_id) {
            $sql .= ' AND se_cash_slip.supplier_id = ?';
            $binds[] = $supplier_id;
        }

        if ($payee) {
            $sql .= " AND se_cash_slip.particulars LIKE ?";
            $binds[] = "%$payee%";
        }

        if ($particulars) {
            $sql .= " AND se_cash_slip.particulars LIKE ?";
            $binds[] = "%$particulars%";
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all cash payments details
     */
    public function get_all_payment_by_se($se_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM
(
    SELECT se_cash_slip.id, 
        se_cash_entry.se_id, 
        null AS from_account_name, 
        null AS to_account_name, 
        se_cash_slip.particulars, 
        se_cash_slip.added_on AS date, 
        null AS check_no,
        se_cash_slip.payment_date AS issued_date, 
        CONCAT('CASH-', se_cash_slip.id, '-', REPLACE(CAST(se_cash_slip.added_on AS DATE), '-', '')) AS doc_no, 
        'cash' AS payment_mode, 
        NULL AS bank_from_name, NULL as bank_to_name, 
        (SELECT supplier.trade_name FROM supplier WHERE supplier.id = se_cash_slip.supplier_id) AS supplier, 
        (SELECT vendor.trade_name FROM vendor WHERE vendor.id = se_cash_slip.vendor_id) AS vendor,
        se_cash_slip.payee AS payee, 
        se_cash_slip.amount AS amount, 
        se_cash_slip.status, 
        se_cash_slip.is_deleted, 
        se_cash_slip.supplier_id,
        se_cash_slip.vendor_id,
    	Null as reference_no
    FROM se_cash_slip
    LEFT JOIN (
        SELECT se_cash_entry.se_id, se_cash_entry.se_cash_slip_id
        FROM se_cash_entry
    ) se_cash_entry ON se_cash_entry.se_cash_slip_id = se_cash_slip.id

    UNION ALL

    SELECT se_bank_slip.id, 
    se_bank_entry.se_id, 
    (SELECT bank.name FROM bank WHERE bank.id = se_bank_slip.bank_from) AS from_account_name, 
    (SELECT bank.name FROM bank WHERE bank.id = se_bank_slip.bank_to) AS to_account_name, 
    se_bank_slip.particulars,
    se_bank_slip.added_on AS date, 
    null AS check_no,
    se_bank_slip.payment_date AS issued_date, 
    CONCAT('BANK-', se_bank_slip.id, '-', REPLACE(CAST(se_bank_slip.added_on AS DATE), '-', '')) AS doc_no, 
    'bank' AS payment_mode, 
    (SELECT bank.name FROM bank WHERE bank.id = se_bank_slip.bank_from) AS bank_from_name, 
    (SELECT bank.name FROM bank WHERE bank.id = se_bank_slip.bank_to) AS bank_to_name, 
    (SELECT supplier.trade_name FROM supplier WHERE supplier.id = se_bank_slip.supplier_id) AS supplier, 
    (SELECT vendor.trade_name FROM vendor WHERE vendor.id = se_bank_slip.vendor_id) AS vendor,
    se_bank_slip.payee AS payee, 
    se_bank_slip.amount AS amount, 
    se_bank_slip.status, 
    se_bank_slip.is_deleted, 
    se_bank_slip.supplier_id,
    se_bank_slip.vendor_id,
    se_bank_slip.reference_no
    FROM se_bank_slip
    LEFT JOIN (
        SELECT se_bank_entry.se_id, se_bank_entry.se_bank_slip_id
        FROM se_bank_entry
    ) se_bank_entry ON se_bank_entry.se_bank_slip_id = se_bank_slip.id

    UNION ALL
    
    SELECT se_check_slip.id, 
    se_check_entry.se_id, 
    (SELECT bank.name FROM bank WHERE bank.id = se_check_slip.bank_id) AS from_account_name, 
    Null AS to_account_name, 
    se_check_slip.particulars, 
    se_check_slip.added_on AS date, 
    se_check_slip.check_no,
    se_check_slip.issued_date AS issued_date, 
    CONCAT('CHECK-', se_check_slip.id, '-', REPLACE(CAST(se_check_slip.added_on AS DATE), '-', '')) AS doc_no, 
    'check' AS payment_mode, 
    (SELECT bank.name FROM bank WHERE bank.id = se_check_slip.bank_id) AS bank_from_name, 
    Null AS bank_to_name, 
    (SELECT supplier.trade_name FROM supplier WHERE supplier.id = se_check_slip.supplier_id) AS supplier, 
    (SELECT vendor.trade_name FROM vendor WHERE vendor.id = se_check_slip.vendor_id) AS vendor,
    se_check_slip.payee AS payee, se_check_slip.amount AS amount, 
    se_check_slip.status, 
    se_check_slip.is_deleted, 
    se_check_slip.supplier_id,
    se_check_slip.vendor_id,
    Null as reference_no
    FROM se_check_slip
    LEFT JOIN (
        SELECT se_check_entry.se_id, se_check_entry.se_check_slip_id
        FROM se_check_entry
    ) se_check_entry ON se_check_entry.se_check_slip_id = se_check_slip.id
) supplies_payments
WHERE supplies_payments.is_deleted = 0
EOT;
        $binds = [];

        if ($se_id) {
            $sql .= ' AND supplies_payments.se_id = ?';
            $binds[] = $se_id;
        }

      

        $sql .= ' ORDER BY date DESC';

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all payments for a specific supplies_expense
     */
    public function get_payment_by_expense($start_date = null, $end_date = null, $expense_type_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT 
    supplies_expense.id AS se_id,
    supplies_expense.supplies_expense_date,
    supplies_expense.type,
    supplier.trade_name AS supplier,
    supplies_expense.grand_total,
    payment_info.issued_date,
    payment_info.payment_mode,
    payment_info.bank_name
FROM supplies_expense
LEFT JOIN supplier ON supplier.id = supplies_expense.supplier_id
LEFT JOIN (
    SELECT 
        se_cash_entry.se_id,
        se_cash_slip.payment_date AS issued_date,
        'cash' AS payment_mode,
        NULL AS bank_name
    FROM se_cash_entry
    INNER JOIN se_cash_slip ON se_cash_slip.id = se_cash_entry.se_cash_slip_id 
    AND se_cash_slip.is_deleted = 0

    UNION ALL

    SELECT 
        se_bank_entry.se_id,
        se_bank_slip.payment_date,
        'bank',
        bank.name AS bank_name
    FROM se_bank_entry
    INNER JOIN se_bank_slip ON se_bank_slip.id = se_bank_entry.se_bank_slip_id 
    AND se_bank_slip.is_deleted = 0
    LEFT JOIN bank ON bank.id = se_bank_slip.bank_from

    UNION ALL

    SELECT 
        se_gcash_entry.se_id,
        se_gcash_slip.payment_date,
        'gcash',
        NULL AS bank_name
    FROM se_gcash_entry
    INNER JOIN se_gcash_slip ON se_gcash_slip.id = se_gcash_entry.se_gcash_slip_id 
    AND se_gcash_slip.is_deleted = 0

    UNION ALL

    SELECT 
        se_check_entry.se_id,
        se_check_slip.issued_date,
        'check',
        NULL AS bank_name
    FROM se_check_entry
    INNER JOIN se_check_slip ON se_check_slip.id = se_check_entry.se_check_slip_id 
    AND se_check_slip.is_deleted = 0
) AS payment_info ON payment_info.se_id = supplies_expense.id
WHERE supplies_expense.is_deleted = 0
AND (
    (supplies_expense.status = 'approved' AND supplies_expense.order_status IN ('complete', 'pending', 'incomplete'))
    OR
    (supplies_expense.status = 'sent' AND supplies_expense.order_status IN ('complete', 'pending', 'incomplete'))
)
EOT;

        $binds = [];

        if ($start_date) {
            $sql .= ' AND DATE(supplies_expense.supplies_expense_date) >= ?';
            $binds[] = $start_date;
        }

        if ($end_date) {
            $sql .= ' AND DATE(supplies_expense.supplies_expense_date) <= ?';
            $binds[] = $end_date;
        }

        if ($expense_type_id) {
            $sql .= ' AND supplies_expense.type = ?';
            $binds[] = $expense_type_id;
        }

        $sql .= ' ORDER BY supplies_expense.supplies_expense_date';

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}