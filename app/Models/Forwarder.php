<?php

namespace App\Models;

class Forwarder extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'name',
        'address',
        'phone_no',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'forwarder';
    }

    /**
     * Get forwarder by ID
     */
    public function get_forwarder_by_id($forwarder_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT percentage
FROM forwarder
WHERE forwarder.is_deleted = 0
    AND forwarder.id = ?
EOT;
        $binds = [$forwarder_id];

        $query = $database->query($sql, $binds);
        return $query ? (float)$query->getResultArray()[0]['percentage'] : false;
    }

    /**
     * Get forwarder details by ID
     */
    public function get_details_by_id($forwarder_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM forwarder
WHERE forwarder.is_deleted = 0
    AND forwarder.id = ?
EOT;
        $binds = [$forwarder_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all forwarders
     */
    public function get_all_forwarder()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM forwarder
WHERE forwarder.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get forwarderess based on transaction_type_id, branch_id, commission
     */
    public function search($name = null, $address = null, $phone_no = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM forwarder
WHERE forwarder.is_deleted = 0
EOT;
        $binds = [];

        if ($name) {
            $sql    .= " AND forwarder.name REGEXP ?";
            $name    = str_replace(' ', '|', $name);
            $binds[] = $name;
        }

        if ($address) {
            $sql    .= " AND forwarder.address REGEXP ?";
            $address = str_replace(' ', '|', $address);
            $binds[] = $address;
        }

        if ($phone_no) {
            $sql .= " AND forwarder.phone_no = ?";
            $binds[] = $phone_no;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


}