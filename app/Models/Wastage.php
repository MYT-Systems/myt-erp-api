<?php

namespace App\Models;

class wastage extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'branch_id',
        'wastage_date',
        'description',
        'grand_total',
        'remarks',
        'status',
        'approved_by',
        'approved_on',
        'rejected_by',
        'rejected_on',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'wastage';
    }

    /**
     * Get wastage details by ID
     */
    public function get_details_by_id($wastage_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT wastage.*,
    branch.name AS branch_name
FROM wastage
LEFT JOIN branch ON branch.id = wastage.branch_id
WHERE wastage.is_deleted = 0
    AND wastage.id = ?
EOT;
        $binds = [$wastage_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all wastages
     */
    public function get_all_wastage()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT wastage.*,
    branch.name AS branch_name
FROM wastage
LEFT JOIN branch ON branch.id = wastage.branch_id
WHERE wastage.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get wastageess based on transaction_type_id, branch_id, commission
     */
    public function search($branch_id, $wastage_date_from, $wastage_date_to, $description, $remarks, $branch_name)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT wastage.*,
    branch.name AS branch_name
FROM wastage
LEFT JOIN branch ON branch.id = wastage.branch_id
WHERE wastage.is_deleted = 0
EOT;
        $binds = [];

        if ($branch_id) {
            $sql .= ' AND wastage.branch_id = ?';
            $binds[] = $branch_id;
        }

        if ($wastage_date_from) {
            $sql .= ' AND wastage.wastage_date >= ?';
            $binds[] = $wastage_date_from;
        }

        if ($wastage_date_to) {
            $sql .= ' AND wastage.wastage_date <= ?';
            $binds[] = $wastage_date_to;
        }

        if ($description) {
            $sql .= ' AND wastage.description LIKE ?';
            $binds[] = '%' . $description . '%';
        }

        if ($remarks) {
            $sql .= ' AND wastage.remarks LIKE ?';
            $binds[] = '%' . $remarks . '%';
        }

        if ($branch_name) {
            $sql .= ' AND branch.name LIKE ?';
            $binds[] = '%' . $branch_name . '%';
        }
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

}