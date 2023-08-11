<?php

namespace App\Models;

class Project_recurring_cost extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'project_id',
        'description',
        'type',
        'period',
        'price',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'project_recurring_cost';
    }

    /**
     * Get project_recurring_cost details by ID
     */
    public function get_details_by_id($project_recurring_cost_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM project_recurring_cost
WHERE project_recurring_cost.is_deleted = 0
EOT;
        $binds = [];
        if (isset($project_recurring_cost_id)) {
            $sql .= " AND id = ?";
            $binds[] = $project_recurring_cost_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get project_recurring_cost details by project ID
     */
    public function get_details_by_project_id($project_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM project_recurring_cost
WHERE project_recurring_cost.is_deleted = 0
EOT;
        $binds = [];
        if (isset($project_id)) {
            $sql .= " AND project_id = ?";
            $binds[] = $project_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete all recurring_cost by project ID
     */
    public function delete_recurring_costs_by_project_id($project_id = null, $requested_by = null)
    {
        $database = \Config\Database::connect();

        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
UPDATE project_recurring_cost
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE project_id = ?
    AND is_deleted = 0
EOT;
        $binds = [$requested_by, $date_now, $project_id];

        $query = $database->query($sql, $binds);
        return $query;
    }
}