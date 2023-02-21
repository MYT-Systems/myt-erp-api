<?php

namespace App\Models;

class Item extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'name',
        'detail',
        'type',
        'is_dsr',
        'is_active',
        'is_for_sale',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'item';
    }
    
    /**
     * Get item details by ID
     */
    public function get_details_by_id($item_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM item
WHERE item.is_deleted = 0
EOT;
        $binds = [];
        if (isset($item_id)) {
            $sql .= " AND item.id = ?";
            $binds[] = $item_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all items
     */
    public function get_all_item($branch_id, $all = false)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT item.*
FROM item
WHERE item.is_deleted = 0
EOT;
        $binds = [];
        if ($branch_id && !$all) {
            $sql .= " AND item.id IN (SELECT inventory.item_id FROM inventory WHERE inventory.branch_id = ?)";
            $binds[] = $branch_id;
        }

        $sql .= " ORDER BY item.name ASC";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get item details by item name
     */
    public function get_details_by_item_name($item_name)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM item
WHERE item.is_deleted = 0
    AND item.name = ?
EOT;
        $binds = [$item_name];
        $query = $database->query($sql, $binds);

        return !$query->getResult() ? false : $query->getResult()[0];
    }

    /**
     * Get items based on item name, contact_person, phone_no, tin_no, bir_no, email
     */
    public function search($name, $breakdown_unit, $inventory_unit, $detail, $price, $type, $is_dsr, $is_active, $is_for_sale)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT item.*,
    (SELECT breakdown_unit FROM item_unit WHERE item_id = item.id LIMIT 1) AS breakdown_unit_name,
    (SELECT inventory_unit FROM item_unit WHERE item_id = item.id LIMIT 1) AS inventory_unit_name
FROM item
WHERE item.is_deleted = 0
EOT;
        $binds = [];

        if ($name) {
            $sql .= " AND item.name REGEXP ?";
            $name    = str_replace(' ', '|', $name);
            $binds[] = $name;
        }

        if ($breakdown_unit) {
            $sql .= " AND item_unit.breakdown_unit REGEXP ?";
            $breakdown_unit    = str_replace(' ', '|', $breakdown_unit);
            $binds[] = $breakdown_unit;
        }

        if ($inventory_unit) {
            $sql .= " AND item_unit.inventory_unit REGEXP ?";
            $inventory_unit    = str_replace(' ', '|', $inventory_unit);
            $binds[] = $inventory_unit;
        }

        if ($detail) {
            $sql .= " AND item.detail REGEXP ?";
            $detail    = str_replace(' ', '|', $detail);
            $binds[] = $detail;
        }

        if ($price) {
            $sql .= " AND item_unit.price REGEXP ?";
            $price    = str_replace(' ', '|', $price);
            $binds[] = $price;
        }

        if ($type) {
            $sql .= " AND item.type REGEXP ?";
            $type    = str_replace(' ', '|', $type);
            $binds[] = $type;
        }

        if ($is_dsr || $is_dsr === '0') {
            $sql .= " AND item.is_dsr = ?";
            $binds[] = $is_dsr;
        }

        if ($is_active || $is_active === '0') {
            $sql .= " AND item.is_active = ?";
            $binds[] = $is_active;
        }

        if ($is_for_sale || $is_for_sale == '0') {
            $sql .= " AND item.is_for_sale = ?";
            $binds[] = $is_for_sale;
        }

        $sql .= " ORDER BY item.name ASC";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    public function get_item_classification_by_branch($branch_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT DISTINCT item.type
FROM item
WHERE item.is_deleted = 0
    AND item.id IN (SELECT inventory.item_id FROM inventory WHERE inventory.branch_id = ?)
EOT;
        $binds = [$branch_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    public function get_all_classification()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT DISTINCT item.type
FROM item
WHERE item.is_deleted = 0
EOT;
        $binds = [];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}