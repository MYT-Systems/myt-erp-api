<?php

namespace App\Models;

class Supplies_payment extends MYTModel
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

    public function __construct()
    {
        $this->table = 'cash_slip';
    }

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
    (
    SELECT cash_slip.id, 
        cash_slip.added_on AS date, 
        cash_slip.payment_date AS issue_date, 
        NULL AS check_no,
        NULL AS reference_no,
        CONCAT('CASH-', cash_slip.id, '-', REPLACE(CAST(cash_slip.added_on AS DATE), '-', '')) AS doc_no, 
        'cash' AS payment_mode, 
        NULL AS bank_from_name,
        NULL as bank_to_name, 
        (SELECT supplier.trade_name FROM supplier WHERE supplier.id = cash_slip.supplier_id) AS supplier,
        (SELECT vendor.trade_name FROM vendor WHERE vendor.id = cash_slip.vendor_id) AS vendor,
        cash_slip.payee AS payee, cash_slip.amount AS amount, 
        cash_slip.status, 
        cash_slip.is_deleted, 
        cash_slip.supplier_id,
        cash_slip.vendor_id
    FROM cash_slip
    )

    UNION ALL

    (
    SELECT bank_slip.id, 
        bank_slip.added_on AS date, 
        bank_slip.payment_date AS issue_date, 
        NULL AS check_no,
        bank_slip.reference_no AS reference_no,
        CONCAT('BANK-',bank_slip.id, '-', REPLACE(CAST(bank_slip.added_on AS DATE), '-', '')) AS doc_no, 
        'bank' AS payment_mode, 
        (SELECT bank.name FROM bank WHERE bank.id = bank_slip.bank_from) AS bank_from_name, 
        bank_slip.bank_to AS bank_to_name, 
        (SELECT supplier.trade_name FROM supplier WHERE supplier.id = bank_slip.supplier_id) AS supplier,
        (SELECT vendor.trade_name FROM vendor WHERE vendor.id = bank_slip.vendor_id) AS vendor,
        bank_slip.payee AS payee, bank_slip.amount AS amount, 
        bank_slip.status, 
        bank_slip.is_deleted, 
        bank_slip.supplier_id,
        bank_slip.vendor_id
    FROM bank_slip
    )

    UNION ALL
    
    (
    SELECT check_slip.id, 
        check_slip.added_on AS date, 
        check_slip.check_date AS issue_date, 
        check_slip.check_no AS check_no,
        NULL AS reference_no,
        CONCAT('CHECK-', '-', check_slip.id, REPLACE(CAST(check_slip.added_on AS DATE), '-', '')) AS doc_no, 
        'check' AS payment_mode, 
        (SELECT bank.name FROM bank WHERE bank.id = check_slip.bank_id) AS bank_from_name, 
        NULL as bank_to_name, 
        (SELECT supplier.trade_name FROM supplier WHERE supplier.id = check_slip.supplier_id) AS supplier,
        (SELECT vendor.trade_name FROM vendor WHERE vendor.id = check_slip.vendor_id) AS vendor,
        check_slip.payee AS payee, check_slip.amount AS amount, 
        check_slip.status, 
        check_slip.is_deleted, 
        check_slip.supplier_id,
        check_slip.vendor_id
    FROM check_slip
    )
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

        $sql .= ' ORDER BY date DESC';

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all cash payments details
     */
    public function get_all_payment_by_receive($receive_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM
(
    SELECT cash_slip.id, 
        cash_entry.receive_id, 
        null AS from_account_name, 
        null AS to_account_name, 
        cash_slip.particulars, 
        null AS check_no,
        NULL AS reference_no,
        cash_slip.added_on AS date, 
        cash_slip.payment_date AS issue_date, 
        CONCAT('CASH-', cash_slip.id, REPLACE(CAST(cash_slip.added_on AS DATE), '-', ''), '1') AS doc_no, 
        'cash' AS payment_mode, NULL AS bank_from_name, NULL as bank_to_name, 
        (SELECT supplier.trade_name FROM supplier WHERE supplier.id = cash_slip.supplier_id) AS supplier, 
        cash_slip.payee AS payee, 
        cash_slip.amount AS amount, 
        cash_slip.status, 
        cash_slip.is_deleted, 
        cash_slip.supplier_id
    FROM cash_slip
    LEFT JOIN (
        SELECT cash_entry.receive_id, cash_entry.cash_slip_id
        FROM cash_entry
    ) cash_entry ON cash_entry.cash_slip_id = cash_slip.id

    UNION ALL

    SELECT bank_slip.id, 
        bank_entry.receive_id, 
        bank_slip.from_account_name,
        bank_slip.to_account_name, 
        bank_slip.particulars, 
        null AS check_no,
        bank_slip.reference_no,
        bank_slip.added_on AS date, 
        bank_slip.payment_date AS issue_date, 
        CONCAT('BANK-',bank_slip.id, REPLACE(CAST(bank_slip.added_on AS DATE), '-', ''), '2') AS doc_no, 
        'bank' AS payment_mode, 
        (SELECT bank.name FROM bank WHERE bank.id = bank_slip.bank_from) AS bank_from_name, 
        (SELECT bank.name FROM bank WHERE bank.id = bank_slip.bank_to) AS bank_to_name, 
        (SELECT supplier.trade_name FROM supplier WHERE supplier.id = bank_slip.supplier_id) AS supplier, 
        bank_slip.payee AS payee, 
        bank_slip.amount AS amount, 
        bank_slip.status, 
        bank_slip.is_deleted, 
        bank_slip.supplier_id
    FROM bank_slip
    LEFT JOIN (
        SELECT bank_entry.receive_id, bank_entry.bank_slip_id
        FROM bank_entry
    ) bank_entry ON bank_entry.bank_slip_id = bank_slip.id

    UNION ALL
    
    SELECT check_slip.id, 
        check_entry.receive_id, null AS from_account_name, 
        null AS to_account_name, 
        check_slip.particulars, 
        check_slip.check_no,
        NULL AS reference_no,
        check_slip.added_on AS date, 
        check_slip.check_date AS issue_date, 
        CONCAT('CHECK-', check_slip.id, REPLACE(CAST(check_slip.added_on AS DATE), '-', ''), '3') AS doc_no, 
        'check' AS payment_mode, 
        (SELECT bank.name FROM bank WHERE bank.id = check_slip.bank_id) AS bank_from_name, 
        NULL as bank_to_name, 
        (SELECT supplier.trade_name FROM supplier WHERE supplier.id = check_slip.supplier_id) AS supplier, 
        check_slip.payee AS payee, 
        check_slip.amount AS amount, 
        check_slip.status, 
        check_slip.is_deleted, 
        check_slip.supplier_id
    FROM check_slip
    LEFT JOIN (
        SELECT check_entry.receive_id, check_entry.check_slip_id
        FROM check_entry
    ) check_entry ON check_entry.check_slip_id = check_slip.id

) supplies_payments
WHERE supplies_payments.is_deleted = 0
EOT;
        $binds = [];

        if ($receive_id) {
            $sql .= ' AND supplies_payments.receive_id = ?';
            $binds[] = $receive_id;
        }

        $sql .= ' GROUP BY supplies_payments.doc_no';

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
FROM cash_slip
WHERE cash_slip.is_deleted = 0
EOT;
        $binds = [];

        if ($payment_date) {
            $sql .= ' AND cash_slip.payment_date = ?';
            $binds[] = $payment_date;
        }

        if ($amount) {
            $sql .= ' AND cash_slip.amount = ?';
            $binds[] = $amount;
        }

        if ($supplier_id) {
            $sql .= ' AND cash_slip.supplier_id = ?';
            $binds[] = $supplier_id;
        }

        if ($payee) {
            $sql .= " AND cash_slip.particulars LIKE ?";
            $binds[] = "%$payee%";
        }

        if ($particulars) {
            $sql .= " AND cash_slip.particulars LIKE ?";
            $binds[] = "%$particulars%";
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}