<?php

namespace App\Models;

class Branch_group_detail extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'branch_group_id',
        'branch_id',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'branch_group_detail';
    }

    /**
     * Get details by branch group id
     */
    public function get_details_by_branch_group_id($branch_group_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM branch WHERE id = branch_group_detail.branch_id) AS branch_name
FROM branch_group_detail
WHERE is_deleted = 0
EOT;
        $binds = [];
        if ($branch_group_id) {
            $sql .= " AND branch_group_id = ?";
            $binds[] = $branch_group_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete by branch group id
     */
    public function delete_by_branch_group_id($branch_group_id = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
UPDATE branch_group_detail
SET is_deleted = 1,
    updated_by = ?,
    updated_on = ?
WHERE branch_group_id = ?
EOT;
        $binds = [$requested_by, $date_now, $branch_group_id];

        return $database->query($sql, $binds);
    }
   
    /*
    * insert_on_duplicate
    */
    public function insert_on_duplicate($data = [], $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
INSERT INTO branch_group_detail
(
    branch_group_id,
    branch_id,
    added_by,
    added_on,
    updated_by,
    updated_on,
    is_deleted
)
VALUES
(
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    0
)
ON DUPLICATE KEY UPDATE
    updated_by = ?,
    updated_on = ?,
    is_deleted = 0
EOT;
        $binds = [
            $data['branch_group_id'],
            $data['branch_id'],
            $requested_by,
            $date_now,
            $requested_by,
            $date_now,
            $requested_by,
            $date_now
        ];

        return $database->query($sql, $binds);
    }

    /**
     * Get branch group details by branch id, branch group id
     */
    public function get_details_by_branch_id_and_branch_group_id($branch_id = null, $branch_group_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT id
FROM branch_group_detail
WHERE is_deleted = 0
EOT;
        $binds = [];
        if ($branch_id) {
            $sql .= " AND branch_id = ?";
            $binds[] = $branch_id;
        }
        if ($branch_group_id) {
            $sql .= " AND branch_group_id = ?";
            $binds[] = $branch_group_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getRowArray() : false;
    }
}