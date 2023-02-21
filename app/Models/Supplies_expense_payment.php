<?php

namespace App\Models;

class Supplies_expense_payment extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'supplies_expense_id',
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
        $this->table = 'supplies_expense_payment';
    }

    /**
     * Get supplies_expense_payment details by ID
     */
    public function get_details_by_id($supplies_expense_payment_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT supplies_expense_payment.*
FROM supplies_expense_payment
WHERE supplies_expense_payment.id = ?
    AND supplies_expense_payment.is_deleted = 0
EOT;
        $binds = [$supplies_expense_payment_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all supplies_expense_payments
     */
    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT supplies_expense_payment.*
FROM supplies_expense_payment
WHERE supplies_expense_payment.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get details bt supplies_expense payment ID
     */
    public function get_details_by_supplies_expense_id($supplies_expense_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT supplies_expense_payment.*
FROM supplies_expense_payment
WHERE supplies_expense_payment.supplies_expense_id = ?
    AND supplies_expense_payment.is_deleted = 0
EOT;
        $binds = [$supplies_expense_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}