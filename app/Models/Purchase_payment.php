<?php

namespace App\Models;

class Purchase_payment extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'purchase_id',
        'total_payment',
        'balance',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'purchase_payment';
    }

    /**
     * Get purchase_payment details by ID
     */
    public function get_details_by_id($purchase_payment_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT purchase_payment.*
FROM purchase_payment
WHERE purchase_payment.id = ?
    AND purchase_payment.is_deleted = 0
EOT;
        $binds = [$purchase_payment_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all purchase_payments
     */
    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT purchase_payment.*
FROM purchase_payment
WHERE purchase_payment.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get details bt purchase payment ID
     */
    public function get_details_by_purchase_id($purchase_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT purchase_payment.*
FROM purchase_payment
WHERE purchase_payment.purchase_id = ?
    AND purchase_payment.is_deleted = 0
EOT;
        $binds = [$purchase_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}