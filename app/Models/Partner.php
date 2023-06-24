<?php

namespace App\Models;

class Partner extends MYTModel
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
        $this->table = 'partner';
    }
    
    /**
     * Get item details by ID
     */
    public function get_details_by_id($id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT partner.*
FROM partner
WHERE partner.is_deleted = 0
    AND partner.id = ?
EOT;
        $binds = [$id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /*
    * Get all partner
    */
    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT partner.*
FROM partner
WHERE partner.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }
}