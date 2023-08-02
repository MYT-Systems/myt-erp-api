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
     * Get partner by ID
     */
    public function get_partner_by_id($partner_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT percentage
FROM partner
WHERE partner.is_deleted = 0
    AND partner.id = ?
EOT;
        $binds = [$partner_id];

        $query = $database->query($sql, $binds);
        return $query ? (float)$query->getResultArray()[0]['percentage'] : false;
    }

    /**
     * Get partner details by ID
     */
    public function get_details_by_id($partner_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM partner
WHERE partner.is_deleted = 0
    AND partner.id = ?
EOT;
        $binds = [$partner_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all partners
     */
    public function get_all_partner()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM partner
WHERE partner.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get partneress based on transaction_type_id, branch_id, commission
     */
    public function search($name = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM partner
WHERE partner.is_deleted = 0
EOT;
        $binds = [];

        if ($name) {
            $sql    .= " AND partner.name REGEXP ?";
            $name    = str_replace(' ', '|', $name);
            $binds[] = $name;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


}