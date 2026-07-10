<?php

namespace App\Models;

class Credit_card_entry extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'credit_card_slip_id',
        'se_id', // refers to the id of the supplies expense or project expense
        'type',
        'amount',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'credit_card_entry';
    }

    /**
     * Get all credit card entries by credit_card_slip ID
     */
    public function get_details_by_credit_card_slip_id($credit_card_slip_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT credit_card_entry.*, supplies_expense.supplies_expense_date
FROM credit_card_entry
LEFT JOIN supplies_expense ON supplies_expense.id = credit_card_entry.se_id
WHERE credit_card_entry.is_deleted = 0
    AND credit_card_entry.credit_card_slip_id = ?
EOT;
        $binds = [$credit_card_slip_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete credit card entries by credit_card_slip ID
     */
    public function delete_by_credit_card_slip_id($credit_card_slip_id = null, $requested_by = null)
    {
        $database = \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE credit_card_entry
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE credit_card_slip_id = ?
EOT;
        $binds = [$requested_by, $date_now, $credit_card_slip_id];
        return $database->query($sql, $binds);
    }
}
