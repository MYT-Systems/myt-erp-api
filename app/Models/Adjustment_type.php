<?php

namespace App\Models;

class Adjustment_type extends MYTModel
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
        $this->table = 'adjustment_type';
    }
    
    /**
     * Get item details by ID
     */
    public function get_details_by_id($id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT adjustment_type.*
FROM adjustment_type
WHERE adjustment_type.is_deleted = 0
    AND adjustment_type.id = ?
EOT;
        $binds = [$id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /*
    * Get all adjustment_type
    */
    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT adjustment_type.*
FROM adjustment_type
WHERE adjustment_type.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }
}