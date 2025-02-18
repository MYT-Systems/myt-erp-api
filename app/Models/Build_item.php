<?php

namespace App\Models;

class Build_item extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'from_branch_id',
        'to_branch_id',
        'item_id',
        'qty',
        'item_unit_id',
        'production_date',
        'production_slip_no',
        'expiration_date',
        'yield',
        'batch',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'build_item';
    }

    /**
     * Get production reports
     */
    public function production_report($item_id, $date_from, $date_to)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT build_item.item_id, item.name, item_unit.inventory_unit AS unit, SUM(build_item.qty) AS qty, 
    raw_material.name AS raw_material_name, SUM(build_item_detail.qty) AS raw_material_qty, raw_material_unit.inventory_unit AS raw_material_unit,
    (SELECT AVG(IFNULL(yield, 0)) FROM build_item WHERE build_item.id = build_item_id GROUP BY item_id) AS average_yield
FROM build_item
LEFT JOIN build_item_detail ON build_item.id = build_item_detail.build_item_id
LEFT JOIN item ON item.id = build_item.item_id
LEFT JOIN item_unit ON item_unit.id = build_item.item_unit_id
LEFT JOIN item AS raw_material ON raw_material.id = build_item_detail.item_id
LEFT JOIN item_unit AS raw_material_unit ON raw_material_unit.id = build_item_detail.item_unit_id
WHERE build_item.is_deleted = 0
    AND DATE(build_item.production_date) BETWEEN ? AND ?
EOT;
        $binds = [];

        if (!$date_from AND !$date_to) {
            $date_to = date("Y-m-d");
            $date_from = date("Y-m-d", strtotime($date_to . "-1 week"));
        } elseif (!$date_from) {
            $date_from = date("Y-m-d", strtotime($date_to . "-1 week"));
        } elseif (!$date_to) {
            $date_to = date("Y-m-d");
        }

        $binds[] = $date_from;
        $binds[] = $date_to;

        if ($item_id) {
            $sql .= " AND build_item.item_id = ?";
            $binds[] = $item_id;
        }

        $sql .= " GROUP BY build_item.item_id, build_item_detail.item_id";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
    
    /**
     * Get build_item details by ID
     */
    public function get_details_by_id($build_item_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT current_qty FROM inventory WHERE item_id = build_item.item_id AND branch_id = build_item.from_branch_id AND item_unit_id = build_item.item_unit_id LIMIT 1) AS from_branch_current_qty,
    (SELECT current_qty FROM inventory WHERE item_id = build_item.item_id AND branch_id = build_item.to_branch_id AND item_unit_id = build_item.item_unit_id LIMIT 1) AS to_branch_current_qty,
    (SELECT name FROM item WHERE id = build_item.item_id LIMIT 1) AS item_name,
    (SELECT name FROM branch WHERE id = build_item.from_branch_id LIMIT 1) AS from_branch_name,
    (SELECT name FROM branch WHERE id = build_item.to_branch_id LIMIT 1) AS to_branch_name,
    (SELECT breakdown_unit FROM item_unit WHERE id = build_item.item_unit_id LIMIT 1) AS breakdown_unit,
    (SELECT inventory_unit FROM item_unit WHERE id = build_item.item_unit_id LIMIT 1) AS inventory_unit
FROM build_item
WHERE build_item.is_deleted = 0
    AND build_item.id = ?
ORDER BY build_item.added_on DESC
EOT;
        $bind = [$build_item_id];

        $query = $database->query($sql, $bind);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all build_items
     */
    public function get_all_build_item()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT current_qty FROM inventory WHERE item_id = build_item.item_id AND branch_id = build_item.from_branch_id AND item_unit_id = build_item.item_unit_id LIMIT 1) AS from_branch_current_qty,
    (SELECT current_qty FROM inventory WHERE item_id = build_item.item_id AND branch_id = build_item.to_branch_id AND item_unit_id = build_item.item_unit_id LIMIT 1) AS to_branch_current_qty,
    SUM(qty) AS total_qty,
    (SELECT name FROM item WHERE id = build_item.item_id LIMIT 1) AS item_name,
    (SELECT name FROM branch WHERE id = build_item.from_branch_id LIMIT 1) AS from_branch_name,
    (SELECT name FROM branch WHERE id = build_item.to_branch_id LIMIT 1) AS to_branch_name,
    (SELECT breakdown_unit FROM item_unit WHERE id = build_item.item_unit_id LIMIT 1) AS breakdown_unit,
    (SELECT inventory_unit FROM item_unit WHERE id = build_item.item_unit_id LIMIT 1) AS inventory_unit
FROM build_item
WHERE build_item.is_deleted = 0
GROUP BY build_item.id
ORDER BY build_item.added_on DESC
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get build_items based on build_item name, contact_person, phone_no, tin_no, bir_no, email
     */
    public function search($from_branch_id, $to_branch_id, $item_id, $qty, $item_unit_id, $production_date, $production_slip_no, $expiration_date, $added_on_from, $added_on_to, $yield, $batch, $name)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT current_qty FROM inventory WHERE item_id = build_item.item_id AND branch_id = build_item.from_branch_id AND item_unit_id = build_item.item_unit_id LIMIT 1) AS from_branch_current_qty,
    (SELECT current_qty FROM inventory WHERE item_id = build_item.item_id AND branch_id = build_item.to_branch_id AND item_unit_id = build_item.item_unit_id LIMIT 1) AS to_branch_current_qty,
    SUM(qty) AS total_qty,
    (SELECT name FROM item WHERE id = build_item.item_id LIMIT 1) AS item_name,
    (SELECT name FROM branch WHERE id = build_item.from_branch_id LIMIT 1) AS from_branch_name,
    (SELECT name FROM branch WHERE id = build_item.to_branch_id LIMIT 1) AS to_branch_name,
    (SELECT breakdown_unit FROM item_unit WHERE id = build_item.item_unit_id LIMIT 1) AS breakdown_unit,
    (SELECT inventory_unit FROM item_unit WHERE id = build_item.item_unit_id LIMIT 1) AS inventory_unit
FROM build_item
WHERE build_item.is_deleted = 0
EOT;
        $binds = [];

        if ($from_branch_id) {
            $sql .= ' AND from_branch_id = ?';
            $binds[] = $from_branch_id;
        }

        if ($to_branch_id) {
            $sql .= ' AND to_branch_id = ?';
            $binds[] = $to_branch_id;
        }

        if ($item_id) {
            $sql .= ' AND item_id = ?';
            $binds[] = $item_id;
        }

        if ($qty) {
            $sql .= ' AND qty = ?';
            $binds[] = $qty;
        }

        if ($item_unit_id) {
            $sql .= ' AND item_unit_id = ?';
            $binds[] = $item_unit_id;
        }

        if ($production_date) {
            $sql .= ' AND production_date = ?';
            $binds[] = $production_date;
        }

        if ($production_slip_no) {
            $sql .= ' AND production_slip_no = ?';
            $binds[] = $production_slip_no;
        }

        if ($expiration_date) {
            $sql .= ' AND expiration_date = ?';
            $binds[] = $expiration_date;
        }

        if ($added_on_from) {
            $sql .= ' AND DATE(production_date) >= ?';
            $binds[] = $added_on_from;
        }

        if ($added_on_to) {
            $sql .= ' AND DATE(production_date) <= ?';
            $binds[] = $added_on_to;
        }

        if ($yield) {
            $sql .= ' AND yield = ?';
            $binds[] = $yield;
        }

        if ($batch) {
            $sql .= ' AND batch = ?';
            $binds[] = $batch;
        }

        if ($name) {
            $sql .= ' AND (SELECT name FROM item WHERE id = build_item.item_id LIMIT 1) LIKE ?';
            $binds[] = '%' . $name . '%';
        }

        $sql .= ' GROUP BY build_item.id';
        $sql .= ' ORDER BY build_item.added_on DESC';

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

}