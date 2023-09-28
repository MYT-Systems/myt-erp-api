<?php

namespace App\Models;

class Subscription_billing extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'project_id',
        'billing_date',
        'grand_total',
        'paid_amount',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'subscription_billing';
    }

    /**
     * Get subscription_billing details by ID
     */
    public function get_details_by_id($subscription_billing_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT subscription_billing.*, project.name AS project_name
FROM subscription_billing
LEFT JOIN project ON project.id = subscription_billing.project_id
WHERE subscription_billing.id = ?
    AND subscription_billing.is_deleted = 0
    AND project.is_deleted = 0
EOT;
        $binds = [$subscription_billing_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
    
    /**
     * Get subscription_billing details by ID
     */
    public function filter_subscription_billing_status($status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT subscription_billing.*
FROM subscription_billing
WHERE subscription_billing.status = ?
    AND subscription_billing.is_deleted = 0
EOT;
        $binds = [$status];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Filter by order status
     */
    public function filter_order_status($status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT subscription_billing.*
FROM subscription_billing
WHERE subscription_billing.order_status = ?
    AND subscription_billing.is_deleted = 0
EOT;
        $binds = [$status];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all subscription_billings
     */
    public function get_all_subscription_billing($status = null, $project_name = null, $billing_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT subscription_billing.*, project.name AS project_name, project.address AS project_address, customer.name AS customer_name, project.contact_person AS project_contact_person, project.contact_number AS project_contact_number
FROM subscription_billing
LEFT JOIN project ON project.id = subscription_billing.project_id
LEFT JOIN customer ON customer.id = project.customer_id
WHERE subscription_billing.is_deleted = 0
EOT;

$binds = [];

if($billing_id) {
    $sql .= <<<EOT

AND subscription_billing.id = ?
EOT;
        $binds[] = $billing_id;
}

if($status) {
    $sql .= <<<EOT

AND subscription_billing.status = ?
EOT;
        $binds[] = $status;
}

if($project_name) {
    $sql .= <<<EOT

AND project.name LIKE ?
EOT;
        $binds[] = "%" . $project_name . "%";
}

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get subscription_billings based on subscription_billing name, contact_person, phone_no, tin_no, bir_no, email
     * can_be_paid - used by add payment function, this avoid overpaying
     */
    public function search($project_id, $limit_by = null, $anything = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT subscription_billing.*, project.name AS project_name
FROM subscription_billing
LEFT JOIN project ON project.id = subscription_billing.project_id
WHERE subscription_billing.is_deleted = 0
EOT;
        $binds = [];

        if ($project_id) {
            $sql .= ' AND subscription_billing.project_id = ?';
            $binds[] = $project_id;
        }

        $sql .= ' ORDER BY subscription_billing.added_on DESC';

        if ($limit_by) {
            $sql .= ' LIMIT ?';
            $binds[] = (int)$limit_by;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}