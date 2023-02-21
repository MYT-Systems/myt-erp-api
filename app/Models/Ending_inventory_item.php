<?php

namespace App\Models;

class Ending_inventory_item extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'ending_inventory_id',
        'inventory_id',
        'item_id',
        'item_unit_id',
        'breakdown_unit',
        'breakdown_qty',
        'actual_breakdown_qty',
        'system_breakdown_qty',
        'variance_breakdown_qty',
        'inventory_unit',
        'inventory_qty',
        'actual_inventory_qty',
        'system_inventory_qty',
        'variance_inventory_qty',
        'total_inventory',
        'total_breakdown',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'ending_inventory_item';
    }
    
    /**
     * Get ending_inventory_item details by ID
     */
    public function get_details_by_id($ending_inventory_item_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM ending_inventory_item
WHERE ending_inventory_item.is_deleted = 0
EOT;
        $binds = [];
        if (isset($ending_inventory_item_id)) {
            $sql .= " AND ending_inventory_item.id = ?";
            $binds[] = $ending_inventory_item_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all ending_inventory_items
     */
    public function get_all_ending_inventory_item()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM ending_inventory_item
WHERE ending_inventory_item.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get ending_inventory_items based on ending_inventory_item name, contact_person, phone_no, tin_no, bir_no, email
     */
    public function search($name = null, $template_name = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM ending_inventory_item
WHERE ending_inventory_item.is_deleted = 0
EOT;
        $binds = [];

        if ($name) {
            $sql .= " AND ending_inventory_item.name REGEXP ?";
            $name    = str_replace(' ', '|', $name);
            $binds[] = $name;
        }

        if ($template_name) {
            $sql .= " AND check_template.name REGEXP ?";
            $template_name = str_replace(' ', '|', $template_name);
            $binds[]       = $template_name;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get details by ending inventory id
     */
    public function get_details_by_ending_inventory_id($ending_inventory_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM ending_inventory_item
WHERE ending_inventory_item.is_deleted = 0
EOT;
        $binds = [];
        if (isset($ending_inventory_id)) {
            $sql .= " AND ending_inventory_item.ending_inventory_id = ?";
            $binds[] = $ending_inventory_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}