<?php

namespace App\Models;

class Subscription_billing_entry extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'subscription_billing_id',
        'project_client_id',
        'project_id',
        'subscription_billing_entry_date',
        'due_date',
        'amount',
        'paid_amount',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'subscription_billing_entry';
    }

    /**
     * Get subscription_billing details by ID
     */
    public function get_details_by_subscription_billing_id($subscription_billing_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT subscription_billing_entry.*, customer.name AS customer_name, project.name AS project_name, project.project_date AS project_date, subscription_billing_entry.amount - subscription_billing_entry.paid_amount AS balance
FROM subscription_billing_entry
LEFT JOIN subscription_billing ON subscription_billing.id = subscription_billing_entry.subscription_billing_id 
LEFT JOIN project_client ON project_client.id = subscription_billing_entry.project_client_id
LEFT JOIN customer ON customer.id = project_client.customer_id
LEFT JOIN project ON project.id = subscription_billing_entry.project_id
    AND subscription_billing.is_deleted = 0
WHERE subscription_billing_id = ? 
    AND subscription_billing_entry.is_deleted = 0
GROUP BY subscription_billing_entry.id
EOT;
        $binds = [$subscription_billing_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

        /**
     * Get subscription_billing details by ID
     */
    public function get_details_by_id($id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT subscription_billing_entry.*, subscription_billing_entry.amount - subscription_billing_entry.paid_amount AS balance
FROM subscription_billing_entry
LEFT JOIN subscription_billing ON subscription_billing.id = subscription_billing_entry.subscription_billing_id 
    AND subscription_billing.is_deleted = 0
WHERE subscription_billing_id = ? 
    AND subscription_billing_entry.is_deleted = 0
GROUP BY subscription_billing_entry.id
EOT;
        $binds = [$id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Update status by subscription_billing ID
     */
    public function update_status_by_subscription_billing_id($subscription_billing_id = null, $requested_by = null, $status = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE subscription_billing_entry
SET status = ?, updated_on = ?, updated_by = ?
WHERE subscription_billing_id = ?
EOT;
        $binds = [$status, $date_now, $requested_by, $subscription_billing_id];
        return $database->query($sql, $binds);
    }

    /**
    * Delete all subscription_billing_entrys by subscription_billing ID
    */
    public function delete_by_subscription_billing_id($subscription_billing_id = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
UPDATE subscription_billing_entry
SET subscription_billing_entry.is_deleted = 1, updated_by = ?, updated_on = ?
WHERE subscription_billing_id = ?
EOT;
        $binds = [$requested_by, $date_now, $subscription_billing_id];      
        return $database->query($sql, $binds);
    }
}