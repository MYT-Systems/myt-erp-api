<?php

namespace App\Models;

class Ending_inventory extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'branch_id',
        'date',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'ending_inventory';
    }
    
    /**
     * Get ending_inventory details by ID
     */
    public function get_details_by_id($ending_inventory_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM ending_inventory
WHERE ending_inventory.is_deleted = 0
EOT;
        $binds = [];
        if (isset($ending_inventory_id)) {
            $sql .= " AND ending_inventory.id = ?";
            $binds[] = $ending_inventory_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all ending_inventorys
     */
    public function get_all_ending_inventory()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM ending_inventory
WHERE ending_inventory.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get ending_inventorys based on ending_inventory name, contact_person, phone_no, tin_no, bir_no, email
     */
    public function search($name = null, $template_name = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM ending_inventory
WHERE ending_inventory.is_deleted = 0
EOT;
        $binds = [];

        if ($name) {
            $sql .= " AND ending_inventory.name REGEXP ?";
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
     * Check if item is recorded already
     */
    public function is_recorded($item_id, $item_unit_id, $unit_type, $unit, $ending_inventory_id, $inventory_type)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM ending_inventory
WHERE ending_inventory.is_deleted = 0
AND ending_inventory.item_id = ?
AND ending_inventory.item_unit_id = ?
AND ending_inventory.unit_type = ?
AND ending_inventory.unit_name = ?
AND ending_inventory.ending_inventory_id = ?
AND ending_inventory.inventory_type = ?
EOT;
        $binds = [$item_id, $item_unit_id, $unit_type, $unit, $ending_inventory_id, $inventory_type];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Initialize the daily item inventory
     */ 
    public function initialize_ending_inventory($item_id, $item_unit_id, $unit_type, $unit, $ending_inventory_id, $inventory_type)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
INSERT INTO ending_inventory (item_id, item_unit_id, unit_type, unit_name, ending_inventory_id, inventory_type)
VALUES (?, ?, ?, ?, ?, ?)
EOT;
        $binds = [$item_id, $item_unit_id, $unit_type, $unit, $ending_inventory_id, $inventory_type];

        return $database->query($sql, $binds);
    }

    /**
     * get details from yesterday ending
     */
    public function get_details_from_yesterday_ending($branch_id = null, $ending_inventory_id = null)
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
SELECT *
FROM ending_inventory
WHERE ending_inventory.is_deleted = 0
    AND ending_inventory.branch_id = ?
    AND ending_inventory.id < ?
    ORDER BY ending_inventory.id DESC
    LIMIT 1
EOT;
        $binds = [$branch_id, $ending_inventory_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}