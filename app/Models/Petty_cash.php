<?php

namespace App\Models;

class Petty_cash extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'id',
        'name',
        'beginning_petty_cash',
        'current_petty_cash',
        'details',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'petty_cash';
    }

    /**
     * Get petty_cash details by ID
     */
    public function get_details_by_id($petty_cash_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = petty_cash.added_by) AS added_by_name
FROM petty_cash
WHERE petty_cash.is_deleted = 0
    AND petty_cash.id = ?
EOT;
        $binds = [$petty_cash_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all petty_cashs
     */
    public function get_all_petty_cash()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM petty_cash
WHERE petty_cash.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

}