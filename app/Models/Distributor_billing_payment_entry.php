<?php

namespace App\Models;

class Distributor_billing_payment_entry extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'distributor_billing_payment_id',
        'distributor_billing_payment_entry_id',
        'paid_amount',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'distributor_billing_payment_entry';
    }

    /**
     * Get distributor_billing details by ID
     */
    public function get_details_by_distributor_billing_payment_id($distributor_billing_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT distributor_billing_payment_entry.*, customer.name AS customer_name, project.name AS project_name
FROM distributor_billing_payment_entry
LEFT JOIN distributor_billing ON distributor_billing.id = distributor_billing_payment_entry.distributor_billing_id 
LEFT JOIN customer ON customer.id = distributor_billing_payment_entry.customer_id
LEFT JOIN project ON project.id = distributor_billing_payment_entry.project_id
    AND distributor_billing.is_deleted = 0
WHERE distributor_billing_id = ? 
    AND distributor_billing_payment_entry.is_deleted = 0
GROUP BY distributor_billing_payment_entry.id
EOT;
        $binds = [$distributor_billing_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

        /**
     * Get distributor_billing details by ID
     */
    public function get_details_by_id($id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT distributor_billing_payment_entry.*
FROM distributor_billing_payment_entry
LEFT JOIN distributor_billing ON distributor_billing.id = distributor_billing_payment_entry.distributor_billing_id 
    AND distributor_billing.is_deleted = 0
WHERE distributor_billing_id = ? 
    AND distributor_billing_payment_entry.is_deleted = 0
GROUP BY distributor_billing_payment_entry.id
EOT;
        $binds = [$id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Update status by distributor_billing ID
     */
    public function update_status_by_distributor_billing_id($distributor_billing_id = null, $requested_by = null, $status = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE distributor_billing_payment_entry
SET status = ?, updated_on = ?, updated_by = ?
WHERE distributor_billing_id = ?
EOT;
        $binds = [$status, $date_now, $requested_by, $distributor_billing_id];
        return $database->query($sql, $binds);
    }

    /**
    * Delete all distributor_billing_payment_entrys by distributor_billing ID
    */
    public function delete_by_distributor_billing_id($distributor_billing_id = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
UPDATE distributor_billing_payment_entry
SET distributor_billing_payment_entry.is_deleted = 1, updated_by = ?, updated_on = ?
WHERE distributor_billing_id = ?
EOT;
        $binds = [$requested_by, $date_now, $distributor_billing_id];      
        return $database->query($sql, $binds);
    }
}