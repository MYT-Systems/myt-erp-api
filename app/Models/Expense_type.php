<?php

namespace App\Models;

class Expense_type extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'id',
        'description',
        'name',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'expense_type';
    }

    /**
     * Get forwarder by ID
     */
    public function get_expense_type_by_id($expense_type_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT percentage
FROM expense_type
WHERE expense_type.is_deleted = 0
    AND expense_type.id = ?
EOT;
        $binds = [$expense_type_id];

        $query = $database->query($sql, $binds);
        return $query ? (float)$query->getResultArray()[0]['percentage'] : false;
    }

    /**
     * Get forwarder details by ID
     */
    public function get_details_by_id($expense_type_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM expense_type
WHERE expense_type.is_deleted = 0
    AND expense_type.id = ?
EOT;
        $binds = [$expense_type_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all forwarders
     */
    public function get_all_expense_type()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM expense_type
WHERE expense_type.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get forwarderess based on transaction_type_id, branch_id, commission
     */
    public function search($name = null, $description = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM expense_type
WHERE expense_type.is_deleted = 0
EOT;
        $binds = [];

        
        if ($name) {
            $sql    .= " AND expense_type.name REGEXP ?";
            $id    = str_replace(' ', '|', $name);
            $binds[] = $name;
        }
        if ($description) {
            $sql    .= " AND expense_type.description REGEXP ?";
            $description = str_replace(' ', '|', $description);
            $binds[] = $description;
        }


        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


}