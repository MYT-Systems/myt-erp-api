<?php

namespace App\Models;

class Distributor_billing_payment extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'billing_id',
        'payment_type',
        'reference_no',
        'remarks',
        'payment_date',
        'grand_total',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'distributor_billing_payment';
    }

    /**
     * Get distributor_billing_payment details by ID
     */
    public function get_details_by_id($distributor_billing_payment_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT distributor_billing_payment.*, distributor.name AS distributor_name
FROM distributor_billing_payment
LEFT JOIN distributor ON distributor.id = distributor_billing_payment.distributor_id
WHERE distributor_billing_payment.id = ?
    AND distributor_billing_payment.is_deleted = 0
    AND distributor.is_deleted = 0
EOT;
        $binds = [$distributor_billing_payment_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
    
    /**
     * Get distributor_billing_payment details by ID
     */
    public function filter_distributor_billing_payment_status($status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT distributor_billing_payment.*
FROM distributor_billing_payment
WHERE distributor_billing_payment.status = ?
    AND distributor_billing_payment.is_deleted = 0
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
SELECT distributor_billing_payment.*
FROM distributor_billing_payment
WHERE distributor_billing_payment.order_status = ?
    AND distributor_billing_payment.is_deleted = 0
EOT;
        $binds = [$status];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all distributor_billing_payments
     */
    public function get_all_distributor_billing_payment()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT distributor_billing_payment.*
FROM distributor_billing_payment
WHERE distributor_billing_payment.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get distributor_billing_payments based on distributor_billing_payment name, contact_person, phone_no, tin_no, bir_no, email
     * can_be_paid - used by add payment function, this avoid overpaying
     */
    public function search($name, $limit_by, $anything)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT distributor_billing_payment.*, 
FROM distributor_billing_payment
WHERE distributor_billing_payment.is_deleted = 0
EOT;
        $binds = [];

        if ($name) {
            $sql .= ' AND distributor_billing_payment.name = ?';
            $binds[] = $branch_id;
        }

        $sql .= ' ORDER BY distributor_billing_payment.added_on DESC';

        if ($limit_by) {
            $sql .= ' LIMIT ?';
            $binds[] = (int)$limit_by;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}