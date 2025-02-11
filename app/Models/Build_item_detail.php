<?php

namespace App\Models;

class Build_item_detail extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'build_item_id',
        'item_id',
        'qty',
        'item_unit_id',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'build_item_detail';
    }
    
    /**
     * Get build_item_detail details by ID
     */
    public function get_details_by_id($build_item_detail_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM item WHERE id = build_item_detail.item_id) AS item_name,
    (SELECT breakdown_unit FROM item_unit WHERE id = build_item_detail.item_unit_id) AS breakdown_unit,
    (SELECT inventory_unit FROM item_unit WHERE id = build_item_detail.item_unit_id) AS inventory_unit
FROM build_item_detail
WHERE build_item_detail.is_deleted = 0
    AND build_item_detail.id = ?
EOT;
        $bind = [$build_item_detail_id];

        $query = $database->query($sql, $bind);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all build_item_details
     */
    public function get_all_build_item_detail()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM item WHERE id = build_item_detail.item_id) AS item_name,
    (SELECT breakdown_unit FROM item_unit WHERE id = build_item_detail.item_unit_id) AS breakdown_unit,
    (SELECT inventory_unit FROM item_unit WHERE id = build_item_detail.item_unit_id) AS inventory_unit
FROM build_item_detail
WHERE build_item_detail.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get build_item_details based on build_item_detail name, contact_person, phone_no, tin_no, bir_no, email
     */
    public function search($name = null, $is_addon = null, $details = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM item WHERE id = build_item_detail.item_id) AS item_name,
    (SELECT breakdown_unit FROM item_unit WHERE id = build_item_detail.item_unit_id) AS breakdown_unit,
    (SELECT inventory_unit FROM item_unit WHERE id = build_item_detail.item_unit_id) AS inventory_unit
FROM build_item_detail
WHERE build_item_detail.is_deleted = 0
EOT;
        $binds = [];

        if ($name) {
            $sql .= " AND build_item_detail.name REGEXP ?";
            $name    = str_replace(' ', '|', $name);
            $binds[] = $name;
        }

        if ($is_addon) {
            $sql .= " AND is_addon = ?";
            $binds[] = $is_addon;
        }

        if ($details) {
            $sql .= " AND details REGEXP ?";
            $details = str_replace(' ', '|', $details);
            $binds[]        = $details;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get details by build_item_id
     */
    public function get_details_by_build_item_id($build_item_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM item WHERE id = build_item_detail.item_id) AS item_name,
    (SELECT breakdown_unit FROM item_unit WHERE id = build_item_detail.item_unit_id) AS breakdown_unit,
    (SELECT inventory_unit FROM item_unit WHERE id = build_item_detail.item_unit_id) AS inventory_unit
FROM build_item_detail
WHERE build_item_detail.is_deleted = 0
    AND build_item_detail.build_item_id = ?
EOT;
        $bind = [$build_item_id];

        $query = $database->query($sql, $bind);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete by build item id
     */
    public function delete_by_build_item_id($build_item_id = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_today = date('Y-m-d H:i:s');
        $sql = <<<EOT
UPDATE build_item_detail
SET is_deleted = 1,
    updated_by = ?,
    updated_on = ?
WHERE build_item_detail.is_deleted = 0
    AND build_item_detail.build_item_id = ?
EOT;
        $bind = [$requested_by, $date_today, $build_item_id];

        return $database->query($sql, $bind);
    }

    /**
     * Insert build item by detail
     */
    public function insert_on_duplicate($values = [], $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();

        $date_today = date('Y-m-d H:i:s');
        $sql = <<<EOT
INSERT INTO build_item_detail (build_item_id, item_id, qty, item_unit_id, added_by, added_on, updated_by, updated_on, is_deleted)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
ON DUPLICATE KEY UPDATE
    qty = VALUES(qty),
    updated_by = VALUES(updated_by),
    updated_on = VALUES(updated_on),
    is_deleted = 0
EOT;
        $binds = [(int)$values['build_item_id'], (int)$values['item_id'], (float)$values['qty'], (int)$values['item_unit_id'], $requested_by, $date_today, $requested_by, $date_today, 0];
        return $database->query($sql, $binds);
    }
}