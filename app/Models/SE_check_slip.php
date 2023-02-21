<?php

namespace App\Models;

class SE_check_slip extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'bank_id',
        'check_no',
        'check_date',
        'issued_date',
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
        $this->table = 'se_check_slip';
    }

    /**
     * Get check slip details by ID
     */
    public function get_details_by_id($se_check_slip_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM bank WHERE id = se_check_slip.bank_id) AS bank_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_check_slip.approved_by) AS approved_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_check_slip.disapproved_by) AS disapproved_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_check_slip.completed_by) AS completed_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_check_slip.printed_by) AS printed_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_check_slip.added_by) AS added_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_check_slip.updated_by) AS updated_by_name,
    (SELECT trade_name FROM supplier WHERE id = se_check_slip.supplier_id) AS supplier_name,
    (SELECT trade_name FROM vendor WHERE id = se_check_slip.vendor_id) AS vendor_name
FROM se_check_slip
WHERE is_deleted = 0
    AND id = ?
EOT;
        $binds = [$se_check_slip_id];
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all check slip details
     */
    public function get_all_slip()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM bank WHERE id = se_check_slip.bank_id) AS bank_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_check_slip.approved_by) AS approved_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_check_slip.disapproved_by) AS disapproved_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_check_slip.completed_by) AS completed_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_check_slip.printed_by) AS printed_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_check_slip.added_by) AS added_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_check_slip.updated_by) AS updated_by_name,
    (SELECT trade_name FROM supplier WHERE id = se_check_slip.supplier_id) AS supplier_name,
    (SELECT trade_name FROM vendor WHERE id = se_check_slip.vendor_id) AS vendor_name
FROM se_check_slip
WHERE is_deleted = 0
EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Search
     */
    public function search($bank_id, $check_no, $check_date, $amount, $supplier_id, $payee, $particulars)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM bank WHERE id = se_check_slip.bank_id) AS bank_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_check_slip.approved_by) AS approved_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_check_slip.disapproved_by) AS disapproved_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_check_slip.completed_by) AS completed_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_check_slip.printed_by) AS printed_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_check_slip.added_by) AS added_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = se_check_slip.updated_by) AS updated_by_name,
    (SELECT trade_name FROM supplier WHERE id = se_check_slip.supplier_id) AS supplier_name,
    (SELECT trade_name FROM vendor WHERE id = se_check_slip.vendor_id) AS vendor_name
FROM se_check_slip
WHERE se_check_slip.is_deleted = 0
EOT;
        $binds = [];

        if ($bank_id) {
            $sql .= ' AND se_check_slip.bank_id = ?';
            $binds[] = $bank_id;
        }

        if ($check_no) {
            $sql .= ' AND se_check_slip.check_no = ?';
            $binds[] = $check_no;
        }

        if ($check_date) {
            $sql .= ' AND se_check_slip.check_date = ?';
            $binds[] = $check_date;
        }

        if ($amount) {
            $sql .= ' AND se_check_slip.amount = ?';
            $binds[] = $amount;
        }

        if ($supplier_id) {
            $sql .= ' AND se_check_slip.supplier_id = ?';
            $binds[] = $supplier_id;
        }

        if ($payee) {
            $sql .= ' AND se_check_slip.payee = ?';
            $binds[] = $payee;
        }

        if ($particulars) {
            $sql .= " AND se_check_slip.particulars LIKE ?";
            $binds[] = "%$particulars%";
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Check if check no. is already used
     */
    public function is_check_no_used($check_no = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT id
FROM (
SELECT id
FROM se_check_slip
WHERE check_no = ?
    AND se_check_slip.is_deleted = 0
) AS check_slip
EOT;
        $binds = [$check_no];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * generate unused check no.
     */
    public function generate_unused_check_no()
    {
        // get last check no.
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT check_no 
FROM 
(
(SELECT check_no
FROM se_check_slip
WHERE is_deleted = 0

UNION ALL

SELECT check_no
FROM se_check_slip
WHERE is_deleted = 1) AS check_no
)
ORDER BY check_no DESC
LIMIT 1
EOT;
        $query = $database->query($sql);
        $result = $query ? $query->getResultArray() : false;

        if ($result) {
            $last_check_no = $result[0]['check_no'];
            $last_check_no = (int) $last_check_no;
            $last_check_no++;
            $last_check_no = str_pad($last_check_no, 6, '0', STR_PAD_LEFT);
            return $last_check_no;
        } else {
            return '000001';
        }
    }

}