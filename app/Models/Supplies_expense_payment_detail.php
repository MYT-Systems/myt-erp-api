<?php

namespace App\Models;

class Supplies_expense_payment_detail extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'id',
        'supplies_expense_id',
        'vendor_id',
        'supplier_id',
        'supplies_expense_payment_id',
        'amount',
        'payment_type',
        'payment_date',
        'remarks',
        'from_bank_id',
        'to_bank_id',
        'to_bank_name',
        'reference_number',
        'transaction_number',
        'payment_description',
        'payment_date',
        'from_account_no',
        'from_account_name',
        'to_account_no',
        'to_account_name',
        'transaction_fee',
        'reference_no',
        'payee',
        'particulars',
        'check_no',
        'check_date',
        'issued_date',
        'balance',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'supplies_expense_payment_detail';
    }

    /**
     * Get supplies_expense_payment_detail details by ID
     */
    public function get_details_by_id($supplies_expense_payment_detail_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT supplies_expense_payment_detail.*
FROM supplies_expense_payment_detail
WHERE supplies_expense_payment_detail.id = ?
    AND supplies_expense_payment_detail.is_deleted = 0
EOT;
        $binds = [$supplies_expense_payment_detail_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all supplies_expense_payment_details
     */
    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT supplies_expense_payment_detail.*
FROM supplies_expense_payment_detail
WHERE supplies_expense_payment_detail.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get details by supplies_expense payment ID
     */
    public function get_details_by_supplies_expense_payment_id($supplies_expense_payment_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT supplies_expense_payment_detail.*
FROM supplies_expense_payment_detail
WHERE supplies_expense_payment_detail.supplies_expense_payment_id = ?
    AND supplies_expense_payment_detail.is_deleted = 0
EOT;
        $binds = [$supplies_expense_payment_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}