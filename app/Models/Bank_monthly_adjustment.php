<?php

namespace App\Models;

class Bank_monthly_adjustment extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'bank_id',
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
        $this->table = 'bank_monthly_adjustment';
    }

    public function get_by_bank_and_month($bank_id, $type, $year, $month)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT * FROM bank_monthly_adjustment
WHERE is_deleted = 0
  AND bank_id = ?
  AND type = ?
  AND YEAR(txn_date) = ?
  AND MONTH(txn_date) = ?
LIMIT 1
EOT;
        $query = $database->query($sql, [$bank_id, $type, $year, $month]);
        return $query ? $query->getResultArray() : false;
    }
}
