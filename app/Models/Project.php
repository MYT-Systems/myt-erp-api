<?php

namespace App\Models;

class Project extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'name',
        'distributor_id',
        'billing_date',
        'project_date',
        'start_date',
        'customer_id',
        'address',
        'company',
        'contact_person',
        'contact_number',
        'project_type',
        'project_price',
        'vat_type',
        'vat_twelve',
        'vat_net',
        'withholding_tax',
        'grand_total',
        'balance',
        'recurring_cost_total',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'project';
    }

    /**
     * Get project that are in need of billing for a certain date
     * If no billing in the past 30 days then client must show
     */
    public function get_recurring_cost_to_bill($project_id = null, $billing_date = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT project_recurring_cost.*, customer.name AS customer_name, project.name AS project_name
FROM project
    LEFT JOIN project_recurring_cost ON project_recurring_cost.project_id = project.id
    LEFT JOIN subscription_billing_entry ON subscription_billing_entry.project_recurring_cost_id = project_recurring_cost.id
    LEFT JOIN subscription_billing ON subscription_billing.id = subscription_billing_entry.subscription_billing_id
    LEFT JOIN customer ON customer.id = project.customer_id

WHERE (subscription_billing.billing_date IS NULL OR subscription_billing.billing_date < ( 
    CASE 
        WHEN project_recurring_cost.type = 'yearly' THEN DATE_SUB(?, INTERVAL project_recurring_cost.period YEAR)
        WHEN project_recurring_cost.type = 'monthly' THEN DATE_SUB(?, INTERVAL project_recurring_cost.period MONTH)
        WHEN project_recurring_cost.type = 'weekly' THEN DATE_SUB(?, INTERVAL project_recurring_cost.period WEEK)
    END)
    )
    AND (project.start_date < ( 
    CASE 
        WHEN project_recurring_cost.type = 'yearly' THEN DATE_SUB(?, INTERVAL project_recurring_cost.period YEAR)
        WHEN project_recurring_cost.type = 'monthly' THEN DATE_SUB(?, INTERVAL project_recurring_cost.period MONTH)
        WHEN project_recurring_cost.type = 'weekly' THEN DATE_SUB(?, INTERVAL project_recurring_cost.period WEEK)
    END))
    AND project.is_deleted = 0
    AND project_recurring_cost.is_deleted = 0
    AND (subscription_billing_entry.is_deleted = 0 OR subscription_billing_entry.is_deleted IS NULL)
    AND (subscription_billing.is_deleted = 0 OR subscription_billing.is_deleted IS NULL)
EOT;
    $binds = [$billing_date, $billing_date, $billing_date, $billing_date, $billing_date, $billing_date];

if($project_id) {
    $sql .= <<<EOT

    AND project.id = ?    
EOT;
        $binds[] = $project_id;
}

    $sql .= <<<EOT

GROUP BY project_recurring_cost.id
EOT;

        // 2023-08-31

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    public function get_project_operations($project_type, $user_id, $project_id, $project_name, $status, $date)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT project.id, project.name AS project_name,
    IF(project_operation_log.time_in IS NOT NULL AND project_operation_log.time_out IS NULL, "open", "closed") AS status,
    project_operation_log.time_in AS time_open,
    project_operation_log.time_out AS time_close
FROM project
LEFT JOIN project_operation_log
ON project.id = project_operation_log.project_id
JOIN_CONDITION
WHERE project.is_deleted = 0
EOT;

        $binds = [];
        switch ($project_type) {
            case 'company-owned':
                $sql .= " AND project.is_franchise = 0";
                break;
            default:
                break;
        }

        if ($user_id) {
            $sql .= " AND project_operation_log.user_id = ?";
            $binds[] = $user_id;
        }

        if ($project_id) {
            $project_id = explode(',', $project_id);
            $sql .= " AND project.id IN ?";
            $binds[] = $project_id;
        }

        if ($project_name) {
            $sql .= " AND project.name LIKE ?";
            $binds[] = "%" . $project_name . "%";
        }

        if ($status) {
            $sql .= ' AND IF(project_operation_log.time_in IS NOT NULL AND project_operation_log.time_out IS NULL, "open", "closed") = ?';
            $binds[] = $status;
        }

        if ($date) {
            $sql = str_replace("JOIN_CONDITION", "AND DATE(project_operation_log.time_in) = ?", $sql);
            $sql .= " AND (project_operation_log.time_in IS NULL OR DATE(project_operation_log.time_in) = ?)";
            $binds[] = $date;
            $binds = array_merge([$date], $binds);
        } else {
            $sql = str_replace("JOIN_CONDITION", "", $sql);
        }

        $sql .= " GROUP BY project.id";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
    
    /**
     * Get project details by ID
     */
    public function get_details_by_id($project_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT project.*, customer.name AS customer_name
FROM project
LEFT JOIN customer ON customer.id = project.customer_id
WHERE project.is_deleted = 0
EOT;
        $binds = [];
        if (isset($project_id)) {
            $sql .= (is_array($project_id) ? ' AND project.id IN ?' : ' AND project.id = ?');
            $binds[] = $project_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all projects
     */
    public function get_all_project()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT project.*
FROM project
WHERE project.is_deleted = 0
GROUP BY project.id
EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get project details by project name
     */
    public function get_details_by_project_name($project_name)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM project
WHERE project.is_deleted = 0
    AND project.name = ?
EOT;
        $binds = [$project_name];
        $query = $database->query($sql, $binds);

        return !$query->getResult() ? false : $query->getResult()[0];
    }

    /**
     * Search
     */
    public function search($project_id = null, $name = null, $project_date = null, $start_date = null, $customer_id = null, $address = null, $company = null, $contact_person = null, $contact_number = null, $project_type = null)
    {
        $database = \Config\Database::connect();
        
        $sql = <<<EOT
SELECT project.*, customer.name AS customer_name
FROM project
LEFT JOIN customer ON customer.id = project.customer_id
WHERE project.is_deleted = 0
EOT;

        $binds = [];
        if ($project_id) {
            $sql .= ' AND project.id = ?';
            $binds[] = $project_id;
        }

        if ($project_date) {
            $sql .= ' AND project_date = ?';
            $binds[] = $project_date;
        }

        if ($start_date) {
            $sql .= ' AND project_date = ?';
            $binds[] = $start_date;
        }

        if ($name) {
            $sql .= ' AND project.name REGEXP ?';
            $name    = str_replace(' ', '|', $name);
            $binds[] = $name;
        }

        if ($customer_id) {
            $sql .= ' AND customer.id = ?';
            $binds[] = $customer_id;
        }

        if ($address) {
            $sql .= ' AND project.address REGEXP ?';
            $address = str_replace(' ', '|', $address);
            $binds[] = $address;
        }

        if ($company) {
            $sql .= ' AND project.company REGEXP ?';
            $phone_no = str_replace(' ', '|', $company);
            $binds[] = $company;
        }

        if ($contact_number) {
            $sql .= ' AND project.contact_number REGEXP ?';
            $name    = str_replace(' ', '|', $contact_number);
            $binds[] = $contact_number;
        }

        if ($project_type) {
            $sql .= ' AND project.project_type REGEXP ?';
            $name    = str_replace(' ', '|', $project_type);
            $binds[] = $project_type;
        }

        $sql .= ' ORDER BY project.name ASC';

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
    
    public function close_projects($projects)
    {
        $db = db_connect();
        $current_datetime = date("Y-m-d H:i:s");

        $sql = <<<EOT
UPDATE `project`
SET is_open = 0, closed_on = ?
WHERE id IN ?
EOT;
        $binds = [$current_datetime, $projects];

        $query = $db->query($sql, $binds);
        return $query ? true : false;
    }

}