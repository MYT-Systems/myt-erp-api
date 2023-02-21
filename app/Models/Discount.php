<?php

namespace App\Models;

class Discount extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'name',
        'percentage',
        'valid_from',
        'valid_to',
        'added_on',
        'added_by',
        'updated_on',
        'updated_by',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'discount';
    }

    /**
     * Get discount by ID
     */
    public function get_discount_by_id($discount_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT percentage
FROM discount
WHERE discount.is_deleted = 0
    AND discount.id = ?
EOT;
        $binds = [$discount_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get discount details by ID
     */
    public function get_details_by_id($discount_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM discount
WHERE discount.is_deleted = 0
    AND discount.id = ?
EOT;
        $binds = [$discount_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all discounts
     */
    public function get_all_discount()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM discount
WHERE discount.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get discount details by discount name
     */
    public function get_details_by_discount_name($discount_name)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM discount
WHERE discount.is_deleted = 0
    AND discount.name = ?
EOT;
        $binds = [$discount_name];
        $query = $database->query($sql, $binds);

        return !$query->getResult() ? false : $query->getResult()[0];
    }

    /**
     * Get discountess based on discount name, address, contact_person, contact_person_no, tin_no, bir_no
     */
    public function search($name = null, $percentage = null, $valid_from = null, $valid_to = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM discount
WHERE discount.is_deleted = 0
EOT;
        $binds = [];

        if ($name) {
            $sql .= " AND discount.name REGEXP ?";
            $name    = str_replace(' ', '|', $name);
            $binds[] = $name;
        }

        if ($percentage) {
            $sql .= " AND discount.percentage = ?";
            $binds[] = $percentage;
        }

        if ($valid_from) {
            $sql .= " AND discount.valid_from = ?";
            $binds[] = $valid_from;
        }

        if ($valid_to) {
            $sql .= " AND discount.valid_to = ?";
            $binds[] = $valid_to;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

}