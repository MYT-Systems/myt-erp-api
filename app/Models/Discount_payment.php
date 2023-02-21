<?php

namespace App\Models;

class Discount_payment extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'payment_id',
        'discount_id',
        'name',
        'id_no',
        'percentage',
        'product_id',
        'product_name',
        'product_price',
        'discount_price',
        'savings',
        'added_on',
        'added_by',
        'updated_on',
        'updated_by',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'discount_payment';
    }

    /**
     * Get discount_payment by ID
     */
    public function get_discount_payment_by_id($discount_payment_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT percentage
FROM discount_payment
WHERE discount_payment.is_deleted = 0
    AND discount_payment.id = ?
EOT;
        $binds = [$discount_payment_id];

        $query = $database->query($sql, $binds);
        return $query ? (float)$query->getResultArray()[0]['percentage'] : false;
    }

    /**
     * Get discount_payment details by ID
     */
    public function get_details_by_id($discount_payment_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM discount_payment
WHERE discount_payment.is_deleted = 0
    AND discount_payment.id = ?
EOT;
        $binds = [$discount_payment_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all discount_payments
     */
    public function get_all_discount_payment()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM discount_payment
WHERE discount_payment.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get discount_payment details by discount_payment name
     */
    public function get_details_by_discount_payment_name($discount_payment_name)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM discount_payment
WHERE discount_payment.is_deleted = 0
    AND discount_payment.name = ?
EOT;
        $binds = [$discount_payment_name];
        $query = $database->query($sql, $binds);

        return !$query->getResult() ? false : $query->getResult()[0];
    }

    /**
     * Get discount_paymentess based on discount_payment name, address, contact_person, contact_person_no, tin_no, bir_no
     */
    public function search($name = null, $percentage = null, $valid_from = null, $valid_to = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM discount_payment
WHERE discount_payment.is_deleted = 0
EOT;
        $binds = [];

        if ($name) {
            $sql .= " AND discount_payment.name REGEXP ?";
            $name    = str_replace(' ', '|', $name);
            $binds[] = $name;
        }

        if ($percentage) {
            $sql .= " AND discount_payment.percentage = ?";
            $binds[] = $percentage;
        }

        if ($valid_from) {
            $sql .= " AND discount_payment.valid_from = ?";
            $binds[] = $valid_from;
        }

        if ($valid_to) {
            $sql .= " AND discount_payment.valid_to = ?";
            $binds[] = $valid_to;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get discount payment details by payment id
     */
    public function get_details_by_payment_id($payment_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM discount_payment
WHERE discount_payment.is_deleted = 0
    AND discount_payment.payment_id = ?
EOT;
        $binds = [$payment_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}