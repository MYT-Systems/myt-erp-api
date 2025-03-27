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
    se_bank_slip.bank_to AS bank_to_name, 
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

    SELECT se_gcash_slip.id, 
    se_gcash_entry.se_id,
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
    se_gcash_slip.reference_no
    FROM se_gcash_slip
    LEFT JOIN (
        SELECT se_gcash_entry.se_id, se_gcash_entry.se_gcash_slip_id
        FROM se_gcash_entry
    ) se_gcash_entry ON se_gcash_entry.se_gcash_slip_id = se_gcash_slip.id

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
        if ($status) {
            $sql .= ' AND supplies_payments.status = ?';
            $binds[] = $status;
        }

        if ($start_date) {
            $sql .= ' AND CAST(supplies_payments.date AS DATE) >= ?';
            $binds[] = $start_date;
        }

        if ($end_date) {
            $sql .= ' AND CAST(supplies_payments.date AS DATE) <= ?';
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

}