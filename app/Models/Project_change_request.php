<?php

namespace App\Models;

class Project_change_request extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'project_id',
        'request_date',
        'request_no',
        'remarks',
        'subtotal',
        'vat_twelve',
        'vat_net',
        'wht',
        'is_wht',
        'grand_total',
        'vat_type',
        'balance',
        'paid_amount',
        'discount',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'project_change_request';
    }

    /**
     * Get project_change_request by ID
     */
    public function get_details_by_id($project_change_request_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM project_change_request
WHERE project_change_request.is_deleted = 0
EOT;
        $binds = [];
        if (isset($project_change_request_id)) {
            $sql .= " AND id = ?";
            $binds[] = $project_change_request_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get project_change_request by ID
     */
    public function get_details_by_project_id($project_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM project_change_request
WHERE project_change_request.is_deleted = 0
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
    public function delete_change_requests_by_project_id($project_id = null, $requested_by = null)
    {
        $database = \Config\Database::connect();

        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
UPDATE project_change_request
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE project_id = ?
    AND is_deleted = 0
EOT;
        $binds = [$requested_by, $date_now, $project_id];

        $query = $database->query($sql, $binds);
        return $query;
    }
}