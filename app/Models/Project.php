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
        'renewal_date',
        'payment_structure',
        'customer_id',
        'address',
        'company',
        'contact_person',
        'contact_number',
        'project_price',
        'vat_type',
        'vat_twelve',
        'vat_net',
        'withholding_tax',
        'wht_percent',
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
     * Get projects that need to be billed
     */
    public function get_projects_to_bill($project_id = null, $billing_date = null)
{
    $database = \Config\Database::connect();

    // Use current date if no billing date is provided
    if (!$billing_date) {
        $billing_date = date('Y-m-d'); // Current date
    }

    $sql = <<<EOT
SELECT * FROM (
    SELECT 
        'recurring' AS billing_type,
        project_recurring_cost.id AS item_id,
        project_recurring_cost.project_id,
        project_recurring_cost.description,
        project_recurring_cost.type,
        project_recurring_cost.period,
        project_recurring_cost.price,
        project_recurring_cost.balance,
        customer.name AS customer_name,
        project.name AS project_name,
        project.project_date,
        project.project_date AS date_reference,
        COUNT(project_invoice_item.id) AS times_billed
    FROM project
    LEFT JOIN project_recurring_cost ON project_recurring_cost.project_id = project.id
    LEFT JOIN customer ON customer.id = project.customer_id
    LEFT JOIN project_invoice_item ON project_invoice_item.item_id = project_recurring_cost.id
        AND project_invoice_item.is_deleted = 0
    WHERE project.is_deleted = 0
    AND project_recurring_cost.is_deleted = 0
    AND project_recurring_cost.is_occupied = 0
    AND project_recurring_cost.balance > 0
    AND NOT EXISTS (
        SELECT 1 
        FROM project_invoice_item 
        JOIN project_invoice ON project_invoice.id = project_invoice_item.project_invoice_id
        WHERE project_invoice_item.item_id = project_recurring_cost.id
        AND DATE_FORMAT(project_invoice.invoice_date, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')
        AND project_invoice_item.is_deleted = 0
        AND project_invoice.is_deleted = 0
    )
    AND (
        CASE 
            WHEN project_recurring_cost.type = 'yearly' THEN DATE_ADD(project.project_date, INTERVAL project_recurring_cost.period YEAR)
            WHEN project_recurring_cost.type = 'monthly' THEN DATE_ADD(project.project_date, INTERVAL project_recurring_cost.period MONTH)
            WHEN project_recurring_cost.type = 'weekly' THEN DATE_ADD(project.project_date, INTERVAL project_recurring_cost.period WEEK)
            WHEN project_recurring_cost.type = 'daily' THEN DATE_ADD(project.project_date, INTERVAL project_recurring_cost.period DAY)
        END
    ) <= DATE_ADD(?, INTERVAL 15 DAY)
    GROUP BY project_recurring_cost.id
    HAVING times_billed < project_recurring_cost.period

    UNION ALL

    SELECT 
        'onetime' AS billing_type,
        project_one_time_fee.id AS item_id,
        project_one_time_fee.project_id,
        project_one_time_fee.description,
        NULL AS type,
        NULL AS period,
        project_one_time_fee.amount AS price,
        project_one_time_fee.balance,
        customer.name AS customer_name,
        project.name AS project_name,
        project.project_date,
        project.project_date AS date_reference,
        NULL AS times_billed
    FROM project
    LEFT JOIN project_one_time_fee ON project_one_time_fee.project_id = project.id
    LEFT JOIN customer ON customer.id = project.customer_id
    WHERE project.is_deleted = 0
    AND project_one_time_fee.is_deleted = 0
    AND project_one_time_fee.is_occupied = 0
    AND project_one_time_fee.balance > 0
    AND project.project_date <= DATE_ADD(?, INTERVAL 15 DAY)
    AND NOT EXISTS (
        SELECT 1 
        FROM project_invoice_item 
        WHERE project_invoice_item.item_id = project_one_time_fee.id
        AND project_invoice_item.is_deleted = 0
    )

    UNION ALL

    SELECT 
        'change_request' AS billing_type,
        project_change_request_item.id AS item_id,
        project_change_request.project_id,
        project_change_request_item.name AS description,
        NULL AS type,
        NULL AS period,
        project_change_request_item.amount AS price,
        project_change_request_item.balance,
        customer.name AS customer_name,
        project.name AS project_name,
        project_change_request.request_date AS date_reference,
        project_change_request.request_date AS project_date,
        NULL AS times_billed
    FROM project
    LEFT JOIN project_change_request ON project_change_request.project_id = project.id
    LEFT JOIN project_change_request_item ON project_change_request_item.project_change_request_id = project_change_request.id
    LEFT JOIN customer ON customer.id = project.customer_id
    WHERE project.is_deleted = 0
    AND project_change_request.is_deleted = 0
    AND project_change_request_item.is_deleted = 0
    AND project_change_request_item.balance > 0
    AND project_change_request.request_date <= DATE_ADD(?, INTERVAL 15 DAY)
    AND NOT EXISTS (
        SELECT 1 
        FROM project_invoice_item 
        WHERE project_invoice_item.item_id = project_change_request_item.id
        AND project_invoice_item.is_deleted = 0
    )
) AS combined
EOT;

    $binds = [$billing_date, $billing_date, $billing_date, $billing_date];

    if ($project_id) {
        $sql .= " WHERE project_id = ? ";
        $binds[] = $project_id;
    }

    $query = $database->query($sql, $binds);
    return $query ? $query->getResultArray() : false;
}


    /**
     * Get project operations
     */
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
SELECT project.*, customer.name AS customer_name, distributor.name AS distributor_name
FROM project
LEFT JOIN distributor ON distributor.id = project.distributor_id
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
    public function get_all_project($project_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT project.*
FROM project
WHERE project.is_deleted = 0
EOT;
        $binds = [];
        if ($project_id) {
            $sql .= ' AND project.id = ?';
            $binds[] = $project_id;
        }

        $sql .= ' GROUP BY project.id';

        $query = $database->query($sql, $binds);
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
    public function search($project_id = null, $name = null, $project_date = null, $start_date = null, $customer_id = null, $address = null, $company = null, $contact_person = null, $contact_number = null, $project_type = null, $renewal_status = null, $distributor_id = null)
    {
        $database = \Config\Database::connect();
        $date_today = date('Y-m-d');

        $sql = <<<EOT
SELECT *
FROM (
    SELECT project.*, 
customer.name AS customer_name, 
distributor.name AS distributor_name,
(CASE
    WHEN PERIOD_DIFF('$date_today', renewal_date) <= 1 THEN 'For Renewal'
    ELSE 'Active'  
END) AS renewal_status
FROM project
LEFT JOIN distributor ON distributor.id = project.distributor_id
LEFT JOIN customer ON customer.id = project.customer_id
WHERE project.is_deleted = 0
) AS project
WHERE 1
EOT;

        $binds = [];
        if ($project_id) {
            $sql .= ' AND project.id = ?';
            $binds[] = $project_id;
        }

        if ($project_date) {
            $sql .= ' AND project.project_date = ?';
            $binds[] = $project_date;
        }

        if ($start_date) {
            $sql .= ' AND project.project_date = ?';
            $binds[] = $start_date;
        }

        if ($name) {
            $sql .= ' AND project.name REGEXP ?';
            $name    = str_replace(' ', '|', $name);
            $binds[] = $name;
        }

        if ($customer_id) {
            $sql .= ' AND project.customer_id = ?';
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

        if ($renewal_status) {
            $sql .= ' AND project.renewal_status = ?';
            $binds[] = $renewal_status;
        }

        if ($distributor_id) {
            $sql .= ' AND project.distributor_id = ?';
            $binds[] = $distributor_id;
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