<?php

namespace App\Models;

class Transaction_type extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'name',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'transaction_type';
    }

    /**
     * Get transaction_type by ID
     */
    public function get_transaction_type_by_id($transaction_type_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT percentage
FROM transaction_type
WHERE transaction_type.is_deleted = 0
    AND transaction_type.id = ?
EOT;
        $binds = [$transaction_type_id];

        $query = $database->query($sql, $binds);
        return $query ? (float)$query->getResultArray()[0]['percentage'] : false;
    }

    /**
     * Get transaction_type details by ID
     */
    public function get_details_by_id($transaction_type_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM transaction_type
WHERE transaction_type.is_deleted = 0
    AND transaction_type.id = ?
EOT;
        $binds = [$transaction_type_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all transaction_types
     */
    public function get_all_transaction_type()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM transaction_type
WHERE transaction_type.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get transaction_typeess based on transaction_type name, address, contact_person, contact_person_no, tin_no, bir_no
     */
    public function search($name = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM transaction_type
WHERE transaction_type.is_deleted = 0
EOT;
        $binds = [];

        if ($name) {
            $sql .= " AND transaction_type.name REGEXP ?";
            $name    = str_replace(' ', '|', $name);
            $binds[] = $name;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

}