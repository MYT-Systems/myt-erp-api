<?php

namespace App\Models;

class SE_gcash_slip extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'payment_date',
        'reference_no',
        'account_name',
        'account_no',
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
        $this->table = 'se_gcash_slip';
    }

    /**
     * Get gcash slip details by ID
     */
    public function get_details_by_id($se_gcash_slip_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_gcash_slip.approved_by) AS approved_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_gcash_slip.disapproved_by) AS disapproved_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_gcash_slip.completed_by) AS completed_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_gcash_slip.printed_by) AS printed_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_gcash_slip.added_by) AS added_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_gcash_slip.updated_by) AS updated_by_name,
    (SELECT trade_name FROM supplier WHERE id = se_gcash_slip.supplier_id) AS supplier_name,
    (SELECT CONCAT(first_name, ' ', middle_name, ' ', last_name) FROM user WHERE id = se_gcash_slip.acknowledged_by) AS acknowledged_by_name,
    (SELECT trade_name FROM vendor WHERE id = se_gcash_slip.vendor_id) AS vendor_name
FROM se_gcash_slip
WHERE is_deleted = 0
    AND id = ?
EOT;
        $binds = [$se_gcash_slip_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all gcash slip details
     */
    public function get_all_slip()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_gcash_slip.approved_by) AS approved_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_gcash_slip.disapproved_by) AS disapproved_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_gcash_slip.completed_by) AS completed_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_gcash_slip.printed_by) AS printed_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_gcash_slip.added_by) AS added_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_gcash_slip.updated_by) AS updated_by_name,
    (SELECT trade_name FROM supplier WHERE id = se_gcash_slip.supplier_id) AS supplier_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_gcash_slip.acknowledged_by) AS acknowledged_by_name,
    (SELECT trade_name FROM vendor WHERE id = se_gcash_slip.vendor_id) AS vendor_name
FROM se_gcash_slip
WHERE is_deleted = 0
EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Search
     */
    public function search($payment_date, $amount, $supplier_id, $payee, $particulars)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_gcash_slip.approved_by) AS approved_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_gcash_slip.disapproved_by) AS disapproved_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_gcash_slip.completed_by) AS completed_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_gcash_slip.printed_by) AS printed_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_gcash_slip.added_by) AS added_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_gcash_slip.updated_by) AS updated_by_name,
    (SELECT trade_name FROM supplier WHERE id = se_gcash_slip.supplier_id) AS supplier_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_gcash_slip.acknowledged_by) AS acknowledged_by_name,
    (SELECT trade_name FROM vendor WHERE id = se_gcash_slip.vendor_id) AS vendor_name
FROM se_gcash_slip
WHERE se_gcash_slip.is_deleted = 0
EOT;
        $binds = [];

        if ($payment_date) {
            $sql .= ' AND se_gcash_slip.payment_date = ?';
            $binds[] = $payment_date;
        }

        if ($amount) {
            $sql .= ' AND se_gcash_slip.amount = ?';
            $binds[] = $amount;
        }

        if ($supplier_id) {
            $sql .= ' AND se_gcash_slip.supplier_id = ?';
            $binds[] = $supplier_id;
        }

        if ($payee) {
            $sql .= " AND se_gcash_slip.particulars LIKE ?";
            $binds[] = "%$payee%";
        }

        if ($particulars) {
            $sql .= " AND se_gcash_slip.particulars LIKE ?";
            $binds[] = "%$particulars%";
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}