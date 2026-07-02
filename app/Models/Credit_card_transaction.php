<?php

namespace App\Models;

class Credit_card_transaction extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'credit_card_id',
        'type',
        'txn_date',
        'amount',
        'remarks',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted',
    ];

    public function __construct()
    {
        $this->table = 'credit_card_transaction';
    }

    /**
     * Get all transactions for a given credit card
     */
    public function get_all_by_credit_card_id($credit_card_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT credit_card_transaction.*
FROM credit_card_transaction
WHERE credit_card_transaction.is_deleted = 0
    AND credit_card_transaction.credit_card_id = ?
ORDER BY credit_card_transaction.txn_date DESC, credit_card_transaction.id DESC
EOT;

        $query = $database->query($sql, [$credit_card_id]);
        return $query ? $query->getResultArray() : false;
    }
}
