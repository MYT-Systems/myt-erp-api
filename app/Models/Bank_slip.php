<?php

namespace App\Models;

class Bank_slip extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'purchase_payment_id',
        'payment_date',
        'bank_from',
        'from_account_no',
        'from_account_name',
        'bank_to',
        'to_account_no',
        'to_account_name',
        'transaction_fee',
        'reference_no',
        'amount',
        'supplier_id',
        'vendor_id',
        'payee',
        'particulars',
        'acknowledged_by',
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
        $this->table = 'bank_slip';
    }

    /**
     * Get bank slip details by ID
     */
    public function get_details_by_id($bank_slip_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM bank WHERE id = bank_slip.bank_from) AS bank_from_name,
    (SELECT name FROM bank WHERE id = bank_slip.bank_to) AS bank_to_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = bank_slip.approved_by) AS approved_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = bank_slip.disapproved_by) AS disapproved_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = bank_slip.completed_by) AS completed_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = bank_slip.printed_by) AS printed_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = bank_slip.added_by) AS added_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = bank_slip.updated_by) AS updated_by_name,
    (SELECT trade_name FROM supplier WHERE id = bank_slip.supplier_id) AS supplier_name,
    (SELECT trade_name FROM vendor WHERE id = bank_slip.vendor_id) AS vendor_name
FROM bank_slip
WHERE is_deleted = 0
    AND id = ?
EOT;
        $binds = [$bank_slip_id];
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all bank slip details
     */
    public function get_all_slip()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM bank WHERE id = bank_slip.bank_from) AS bank_from_name,
    (SELECT name FROM bank WHERE id = bank_slip.bank_to) AS bank_to_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = bank_slip.approved_by) AS approved_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = bank_slip.disapproved_by) AS disapproved_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = bank_slip.completed_by) AS completed_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = bank_slip.printed_by) AS printed_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = bank_slip.added_by) AS added_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = bank_slip.updated_by) AS updated_by_name,
    (SELECT trade_name FROM supplier WHERE id = bank_slip.supplier_id) AS supplier_name,
    (SELECT trade_name FROM vendor WHERE id = bank_slip.vendor_id) AS vendor_name
FROM bank_slip
WHERE is_deleted = 0
EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Search
     */
    public function search($payment_date, $amount, $supplier_id, $vendor_id, $payee, $particulars)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM bank WHERE id = bank_slip.bank_from) AS bank_from_name,
    (SELECT name FROM bank WHERE id = bank_slip.bank_to) AS bank_to_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = bank_slip.approved_by) AS approved_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = bank_slip.disapproved_by) AS disapproved_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = bank_slip.completed_by) AS completed_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = bank_slip.printed_by) AS printed_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = bank_slip.added_by) AS added_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = bank_slip.updated_by) AS updated_by_name,
    (SELECT trade_name FROM supplier WHERE id = bank_slip.supplier_id) AS supplier_name,
    (SELECT trade_name FROM vendor WHERE id = bank_slip.vendor_id) AS vendor_name
FROM bank_slip
WHERE bank_slip.is_deleted = 0
EOT;
        $binds = [];

        if ($payment_date) {
            $sql .= ' AND bank_slip.payment_date = ?';
            $binds[] = $payment_date;
        }

        if ($amount) {
            $sql .= ' AND bank_slip.amount = ?';
            $binds[] = $amount;
        }

        if ($supplier_id) {
            $sql .= ' AND bank_slip.supplier_id = ?';
            $binds[] = $supplier_id;
        }

        if ($vendor_id) {
            $sql .= ' AND bank_slip.vendor_id = ?';
            $binds[] = $vendor_id;
        }

        if ($payee) {
            $sql .= " AND bank_slip.particulars LIKE ?";
            $binds[] = "%$payee%";
        }

        if ($particulars) {
            $sql .= " AND bank_slip.particulars LIKE ?";
            $binds[] = "%$particulars%";
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}