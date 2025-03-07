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
        'wht_percent',
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

    /**
     * Search 
     */
    public function search($project_change_request_id = null, $customer_id = null, $project_id = null, $name = null, $request_date = null, $address = null, $company = null, $remarks = null, $anything = null)
    {
        $database = \Config\Database::connect();
        
        $sql = <<<EOT
SELECT project.*, 
       project_change_request.*, 
       customer.name AS customer_name
FROM project_change_request
LEFT JOIN project ON project.id = project_change_request.project_id
LEFT JOIN customer ON customer.id = project.customer_id
WHERE project_change_request.is_deleted = 0
EOT;

        $binds = [];

        if ($project_change_request_id) {
            $sql .= ' AND project_change_request.id = ?';
            $binds[] = $project_change_request_id;
        }

        if ($project_id) {
            $sql .= ' AND project_change_request.project_id = ?';
            $binds[] = $project_id;
        }

        if ($customer_id) {
            $sql .= ' AND project.customer_id = ?';
            $binds[] = $customer_id;
        }

        if ($request_date) {
            $sql .= ' AND project_change_request.request_date = ?';
            $binds[] = $request_date;
        }

        if ($address) {
            $sql .= ' AND project.address REGEXP ?';
            $address = str_replace(' ', '|', $address);
            $binds[] = $address;
        }

        if ($name) {
            $sql .= ' AND project.name REGEXP ?';
            $name    = str_replace(' ', '|', $name);
            $binds[] = $name;
        }

        if ($company) {
            $sql .= ' AND project.company REGEXP ?';
            $company = str_replace(' ', '|', $company);
            $binds[] = $company;
        }

        if ($remarks) {
            $sql .= ' AND project_change_request.remarks LIKE ?';
            $binds[] = '%' . $remarks . '%';
        }

        $sql .= ' ORDER BY project_change_request.request_date DESC';

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}