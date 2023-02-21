<?php

namespace App\Models;

class Branch_commission extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'transaction_type_id',
        'branch_id',
        'commission',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'branch_commission';
    }

    /**
     * Get branch_commission by ID
     */
    public function get_branch_commission_by_id($branch_commission_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT percentage
FROM branch_commission
WHERE branch_commission.is_deleted = 0
    AND branch_commission.id = ?
EOT;
        $binds = [$branch_commission_id];

        $query = $database->query($sql, $binds);
        return $query ? (float)$query->getResultArray()[0]['percentage'] : false;
    }

    /**
     * Get branch_commission details by ID
     */
    public function get_details_by_id($branch_commission_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM branch_commission
WHERE branch_commission.is_deleted = 0
    AND branch_commission.id = ?
EOT;
        $binds = [$branch_commission_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all branch_commissions
     */
    public function get_all_branch_commission()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM branch_commission
WHERE branch_commission.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get branch_commissioness based on transaction_type_id, branch_id, commission
     */
    public function search($transaction_type_id = null, $branch_id = null, $commission = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM branch_commission
WHERE branch_commission.is_deleted = 0
EOT;
        $binds = [];

        if ($transaction_type_id) {
            $sql .= " AND branch_commission.transaction_type_id = ?";
            $binds[] = $transaction_type_id;
        }

        if ($branch_id) {
            $sql .= " AND branch_commission.branch_id = ?";
            $binds[] = $branch_id;
        }

        if ($commission) {
            $sql .= " AND branch_commission.commission = ?";
            $binds[] = $commission;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get commission based on transaction_type_id, branch_id
     */
    public function get_commission($transaction_type_id = null, $branch_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT commission
FROM branch_commission
WHERE branch_commission.is_deleted = 0
    AND branch_commission.transaction_type_id = ?
    AND branch_commission.branch_id = ?
EOT;
        $binds = [$transaction_type_id, $branch_id];
        $query = $database->query($sql, $binds);
        return $query ? ($query->getResultArray() ? (float)$query->getResultArray()[0]['commission'] : false) : false;
    }

}