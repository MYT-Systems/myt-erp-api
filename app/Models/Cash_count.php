<?php

namespace App\Models;

class Cash_count extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'branch_id',
        'count_date',
        'bill_1000',
        'bill_500',
        'bill_200',
        'bill_100',
        'bill_50',
        'bill_20',
        'coin_20',
        'coin_10',
        'coin_5',
        'coin_1',
        'cent_25',
        'cent_10',
        'cent_5',
        'cent_1',
        'total_count',
        'type',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'cash_count';
    }

    /**
     * Get cash_count details by ID
     */
    public function get_details_by_id($cash_count_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT cash_count.*,
    branch.name AS branch_name,
    CONCAT(preparer.first_name, ' ', preparer.last_name) AS prepared_by_name,
    CONCAT(adder.first_name, ' ', adder.last_name) AS added_by_name
FROM cash_count
LEFT JOIN branch ON cash_count.branch_id = branch.id
LEFT JOIN user AS preparer ON cash_count.added_by = preparer.id
LEFT JOIN user AS adder ON cash_count.added_by = adder.id
WHERE cash_count.is_deleted = 0
    AND cash_count.id = ?
EOT;
        $binds = [$cash_count_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all cash_counts
     */
    public function get_all_cash_count()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM cash_count
WHERE cash_count.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get cash_countess based on transaction_type_id, branch_id, commission
     */
    public function search($branch_id, $branch_name, $sales_report_id, $is_reviewed, $prepared_by, $approved_by, $count_date_from, $count_date_to, $type)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT cash_count.*,
    branch.name AS branch_name,
    CONCAT(preparer.first_name, ' ', preparer.last_name) AS prepared_by_name,
    CONCAT(adder.first_name, ' ', adder.last_name) AS added_by_name
FROM cash_count
LEFT JOIN branch ON cash_count.branch_id = branch.id
LEFT JOIN user AS preparer ON cash_count.added_by = preparer.id
LEFT JOIN user AS adder ON cash_count.added_by = adder.id
WHERE cash_count.is_deleted = 0
EOT;
        $binds = [];

        if ($branch_id) {
            $sql .= " AND cash_count.branch_id = ?";
            $binds[] = $branch_id;
        }
        if ($sales_report_id) {
            $sql .= " AND cash_count.sales_report_id = ?";
            $binds[] = $sales_report_id;
        }
        if ($is_reviewed) {
            $sql .= " AND cash_count.is_reviewed = ?";
            $binds[] = $is_reviewed;
        }
        if ($prepared_by) {
            $sql .= " AND cash_count.prepared_by = ?";
            $binds[] = $prepared_by;
        }
        if ($approved_by) {
            $sql .= " AND cash_count.approved_by = ?";
            $binds[] = $approved_by;
        }
        if ($count_date_from) {
            $sql .= " AND cash_count.count_date >= ?";
            $binds[] = $count_date_from;
        }
        if ($count_date_to) {
            $sql .= " AND cash_count.count_date <= ?";
            $binds[] = $count_date_to;
        }
        if ($type) {
            $sql .= " AND cash_count.type = ?";
            $binds[] = $type;
        }
        if ($branch_name) {
            $sql .= " AND branch.name LIKE ?";
            $binds[] = "%$branch_name%";
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
FROM cash_count
WHERE cash_count.is_deleted = 0
    AND cash_count.transaction_type_id = ?
    AND cash_count.branch_id = ?
EOT;
        $binds = [$transaction_type_id, $branch_id];

        $query = $database->query($sql, $binds);
        return $query ? (float)$query->getResultArray()[0]['commission'] : false;
    }

}