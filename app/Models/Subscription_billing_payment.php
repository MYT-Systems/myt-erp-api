<?php

namespace App\Models;

class Subscription_billing_payment extends MYTModel
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
        $this->table = 'subscription_billing_payment';
    }

    /**
     * Get subscription_billing_payment details by ID
     */
    public function get_details_by_id($subscription_billing_payment_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT subscription_billing_payment.*
FROM subscription_billing_payment
WHERE subscription_billing_payment.id = ?
    AND subscription_billing_payment.is_deleted = 0
EOT;
        $binds = [$subscription_billing_payment_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
    
    /**
     * Get subscription_billing_payment details by ID
     */
    public function filter_subscription_billing_payment_status($status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT subscription_billing_payment.*
FROM subscription_billing_payment
WHERE subscription_billing_payment.status = ?
    AND subscription_billing_payment.is_deleted = 0
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
SELECT subscription_billing_payment.*
FROM subscription_billing_payment
WHERE subscription_billing_payment.order_status = ?
    AND subscription_billing_payment.is_deleted = 0
EOT;
        $binds = [$status];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all subscription_billing_payments
     */
    public function get_all_subscription_billing_payment()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT subscription_billing_payment.*
FROM subscription_billing_payment
WHERE subscription_billing_payment.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get subscription_billing_payments based on subscription_billing_payment name, contact_person, phone_no, tin_no, bir_no, email
     * can_be_paid - used by add payment function, this avoid overpaying
     */
    public function search($name, $limit_by, $anything)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT subscription_billing_payment.*, 
FROM subscription_billing_payment
WHERE subscription_billing_payment.is_deleted = 0
EOT;
        $binds = [];

        if ($name) {
            $sql .= ' AND subscription_billing_payment.name = ?';
            $binds[] = $branch_id;
        }

        $sql .= ' ORDER BY subscription_billing_payment.added_on DESC';

        if ($limit_by) {
            $sql .= ' LIMIT ?';
            $binds[] = (int)$limit_by;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}