<?php

namespace App\Models;

class Branch_attachment extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'branch_id',
        'name',
        'file_url',
        'base_64',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'branch_attachment';
    }

    /**
     * Get branch_attachment details by ID
     */
    public function get_details_by_id($branch_attachment_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM branch_attachment
WHERE branch_attachment.is_deleted = 0
EOT;
        $binds = [];
        if (isset($branch_attachment_id)) {
            $sql .= " AND id = ?";
            $binds[] = $branch_attachment_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get branch_attachment details by branch ID
     */
    public function get_details_by_branch_id($branch_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM branch_attachment
WHERE branch_attachment.is_deleted = 0
EOT;
        $binds = [];
        if (isset($branch_id)) {
            $sql .= " AND branch_id = ?";
            $binds[] = $branch_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete all attachment by branch ID
     */
    public function delete_attachments_by_branch_id($branch_id = null, $requested_by = null)
    {
        $database = \Config\Database::connect();

        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
UPDATE branch_attachment
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE branch_id = ?
    AND is_deleted = 0
EOT;
        $binds = [$requested_by, $date_now, $branch_id];

        $query = $database->query($sql, $binds);
        return $query;
    }
}