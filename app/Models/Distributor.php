<?php

namespace App\Models;

class Distributor extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'name',
        'address',
        'contact_person',
        'contact_no',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'distributor';
    }

    /**
     * Get distributor details by ID
     */
    public function get_details_by_id($distributor_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT distributor.*
FROM distributor
WHERE distributor.id = ?
    AND distributor.is_deleted = 0
EOT;
        $binds = [$distributor_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
    
    /**
     * Get distributor details by ID
     */
    public function filter_distributor_status($status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT distributor.*
FROM distributor
WHERE distributor.status = ?
    AND distributor.is_deleted = 0
EOT;
        $binds = [$status];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Filter by order status
     */
    public function filter_order_status($status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT distributor.*
FROM distributor
WHERE distributor.order_status = ?
    AND distributor.is_deleted = 0
EOT;
        $binds = [$status];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all distributors
     */
    public function get_all_distributor()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT distributor.*
FROM distributor
WHERE distributor.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get distributors based on distributor name, contact_person, phone_no, tin_no, bir_no, email
     * can_be_paid - used by add payment function, this avoid overpaying
     */
    public function search($name, $limit_by, $anything)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT distributor.*, 
FROM distributor
WHERE distributor.is_deleted = 0
EOT;
        $binds = [];

        if ($name) {
            $sql .= ' AND distributor.name = ?';
            $binds[] = $branch_id;
        }

        $sql .= ' ORDER BY distributor.added_on DESC';

        if ($limit_by) {
            $sql .= ' LIMIT ?';
            $binds[] = (int)$limit_by;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}