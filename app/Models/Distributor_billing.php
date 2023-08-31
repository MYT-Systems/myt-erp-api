<?php

namespace App\Models;

class Distributor_billing extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'distributor_id',
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
        $this->table = 'distributor_billing';
    }

    /**
     * Get distributor_billing details by ID
     */
    public function get_details_by_id($distributor_billing_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT distributor_billing.*, distributor.name AS distributor_name
FROM distributor_billing
LEFT JOIN distributor ON distributor.id = distributor_billing.distributor_id
WHERE distributor_billing.id = ?
    AND distributor_billing.is_deleted = 0
    AND distributor.is_deleted = 0
EOT;
        $binds = [$distributor_billing_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
    
    /**
     * Get distributor_billing details by ID
     */
    public function filter_distributor_billing_status($status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT distributor_billing.*
FROM distributor_billing
WHERE distributor_billing.status = ?
    AND distributor_billing.is_deleted = 0
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
SELECT distributor_billing.*
FROM distributor_billing
WHERE distributor_billing.order_status = ?
    AND distributor_billing.is_deleted = 0
EOT;
        $binds = [$status];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all distributor_billings
     */
    public function get_all_distributor_billing()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT distributor_billing.*, distributor.name AS distributor_name
FROM distributor_billing
LEFT JOIN distributor ON distributor.id = distributor_billing.distributor_id
WHERE distributor_billing.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get distributor_billings based on distributor_billing name, contact_person, phone_no, tin_no, bir_no, email
     * can_be_paid - used by add payment function, this avoid overpaying
     */
    public function search($name, $limit_by, $anything)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT distributor_billing.*, 
FROM distributor_billing
WHERE distributor_billing.is_deleted = 0
EOT;
        $binds = [];

        if ($name) {
            $sql .= ' AND distributor_billing.name = ?';
            $binds[] = $branch_id;
        }

        $sql .= ' ORDER BY distributor_billing.added_on DESC';

        if ($limit_by) {
            $sql .= ' LIMIT ?';
            $binds[] = (int)$limit_by;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}