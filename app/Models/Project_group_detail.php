<?php

namespace App\Models;

class Project_group_detail extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'project_group_id',
        'project_id',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'project_group_detail';
    }

    /**
     * Get details by project group id
     */
    public function get_details_by_project_group_id($project_group_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM project WHERE id = project_group_detail.project_id) AS project_name
FROM project_group_detail
WHERE is_deleted = 0
EOT;
        $binds = [];
        if ($project_group_id) {
            $sql .= " AND project_group_id = ?";
            $binds[] = $project_group_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete by project group id
     */
    public function delete_by_project_group_id($project_group_id = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
UPDATE project_group_detail
SET is_deleted = 1,
    updated_by = ?,
    updated_on = ?
WHERE project_group_id = ?
EOT;
        $binds = [$requested_by, $date_now, $project_group_id];

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
INSERT INTO project_group_detail
(
    project_group_id,
    project_id,
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
            $data['project_group_id'],
            $data['project_id'],
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
     * Get project group details by project id, project group id
     */
    public function get_details_by_project_id_and_project_group_id($project_id = null, $project_group_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT id
FROM project_group_detail
WHERE is_deleted = 0
EOT;
        $binds = [];
        if ($project_id) {
            $sql .= " AND project_id = ?";
            $binds[] = $project_id;
        }
        if ($project_group_id) {
            $sql .= " AND project_group_id = ?";
            $binds[] = $project_group_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getRowArray() : false;
    }
}