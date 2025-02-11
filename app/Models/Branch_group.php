<?php

namespace App\Models;

class Branch_group extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'name',
        'supervisor',
        'supervisor_id',
        'details',
        'number_of_branch',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'branch_group';
    }

    /**
     * Get details by id
     */
    public function get_details_by_id($id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM branch_group
WHERE is_deleted = 0
    AND id = ?
EOT;
        $binds = [$id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all branch group
     */
    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM branch_group
WHERE is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Search function
     */
    public function search($name = null, $supervisor = null, $supervisor_id = null, $details = null, $number_of_branch = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT branch_group.*, IF(branch.is_open = 1, "Open", "Closed") AS branch_status
FROM branch_group
LEFT JOIN branch_group_detail ON branch_group_detail.branch_group_id = branch_group.id
LEFT JOIN branch ON branch.id = branch_group_detail.branch_id
WHERE branch_group.is_deleted = 0
EOT;
        $binds = [];
        if ($name) {
            $sql .= " AND branch_group.name LIKE ?";
            $binds[] = "%$name%";
        }

        if ($supervisor) {
            $sql .= " AND branch_group.supervisor LIKE ?";
            $binds[] = "%$supervisor%";
        }

        if ($supervisor_id) {
            $sql .= " AND branch_group.supervisor_id = ?";
            $binds[] = $supervisor_id;
        }

        if ($details) {
            $sql .= " AND branch_group.details LIKE ?";
            $binds[] = "%$details%";
        }

        if ($number_of_branch) {
            $sql .= " AND branch_group.number_of_branch = ?";
            $binds[] = $number_of_branch;
        }

        $sql .= " GROUP BY branch_group.id";

        $query = $database->query($sql, $binds);

        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get branches of supervisor
     */
    public function get_branches_per_supervisor($supervisor_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT DISTINCT branch_group_detail.branch_id
FROM branch_group
LEFT JOIN branch_group_detail ON branch_group_detail.branch_group_id = branch_group.id
WHERE branch_group.is_deleted = 0
    AND branch_group_detail.is_deleted = 0
    AND branch_group.supervisor_id = ?
EOT;
        $binds = [$supervisor_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}