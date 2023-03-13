<?php

namespace App\Models;

class Order_detail_ingredient extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'branch_id',
        'order_detail_id',
        'product_id',
        'item_id',
        'qty',
        'unit',
        'added_by',
        'added_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'order_detail_ingredient';
    }

    public function get_system_inventory_qty($branch_id = null, $item_id = null, $unit = null, $daily = false, $group_by = false)
    {
        $database = \Config\Database::connect();
        $date_now = date('Y-m-d');

        $sql = <<<EOT
SELECT IFNULL(SUM(order_detail_ingredient.qty), 0) AS total_qty
FROM order_detail_ingredient
WHERE order_detail_ingredient.is_deleted = 0
EOT;
        $binds = [];

        if ($daily) {
            $sql .= " AND DATE(order_detail_ingredient.added_on) = ?";
            $binds[] = $date_now;
        }

        if ($branch_id) {
            $sql .= " AND order_detail_ingredient.branch_id = ?";
            $binds[] = $branch_id;
        }

        if ($item_id) {
            $sql .= " AND order_detail_ingredient.item_id = ?";
            $binds[] = $item_id;
        }

        if ($unit) {
            $sql .= " AND order_detail_ingredient.unit = ?";
            $binds[] = $unit;
        }

        if ($group_by) {
            $sql .= " GROUP BY order_detail_ingredient.item_id";
        }
        
        $query = $database->query($sql, $binds);
        return ($query AND $query->getResultArray()) ? $query->getResultArray()[0] : false;
    }

    public function get_actual_inventory_sales($branch_id = null, $date = null)
    {
        $database = \Config\Database::connect();
        $date_now = date('Y-m-d');

        $sql = <<<EOT
SELECT SUM(final_item_sales.amount) AS grand_total
FROM (
    SELECT item.name AS item_name, SUM(inventory_usage.qty) AS `qty`, IFNULL(franchise_sale_item_price.price_3, 0) AS price,
        (SUM(inventory_usage.qty) * IFNULL(franchise_sale_item_price.price_3, 0)) AS amount
    FROM (
        SELECT ending_inventory.branch_id, ending_inventory.item_id,
            (initial_inventory.total_qty - ending_inventory.actual_inventory_quantity) AS `qty`,
            ending_inventory.inventory_unit AS unit,
            ending_inventory.date,
            ending_inventory.is_deleted
        FROM ending_inventory
        JOIN initial_inventory
        ON initial_inventory.item_id = ending_inventory.item_id
            AND initial_inventory.branch_id = ending_inventory.branch_id
            AND initial_inventory.date = ending_inventory.date
            AND initial_inventory.unit = ending_inventory.inventory_unit
            ADDITIONAL_CONDITIONS
    ) AS inventory_usage
    LEFT JOIN franchise_sale_item_price
    ON inventory_usage.item_id = franchise_sale_item_price.item_id
        AND franchise_sale_item_price.unit = inventory_usage.unit
    LEFT JOIN item ON item.id = inventory_usage.item_id
    WHERE inventory_usage.is_deleted = 0
        AND item.is_dsr = 1
        AND item.is_active = 1
EOT;
        $binds = [];
        $additional_condition_binds = [];

        $condition = "";

        if ($branch_id) {
            $condition .= " AND ending_inventory.branch_id = ?";
            $sql .= " AND inventory_usage.branch_id = ?";
            $additional_condition_binds[] = $branch_id;
            $binds[] = $branch_id;
        }

        if ($date) {
            $condition .= " AND ending_inventory.date = ?";
            $sql .= " AND inventory_usage.date = ?";
            $additional_condition_binds[] = $date;
            $binds[] = $date;
        }

        $binds = array_merge($additional_condition_binds, $binds);
        $sql = str_replace("ADDITIONAL_CONDITIONS", $condition, $sql);

        $sql .= " GROUP BY inventory_usage.item_id) final_item_sales";

        $query = $database->query($sql, $binds);
        return ($query AND $query->getResultArray()) ? $query->getResultArray()[0] : false;
    }

    public function get_actual_inventory_sales_by_item($branch_id = null, $date = null)
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
SELECT item.name AS item_name, SUM(inventory_usage.qty) AS `usage`, IFNULL(franchise_sale_item_price.price_3, 0) AS price,
    (SUM(inventory_usage.qty) * IFNULL(franchise_sale_item_price.price_3, 0)) AS amount
FROM (
    SELECT ending_inventory.branch_id, ending_inventory.item_id,
        (initial_inventory.total_qty - ending_inventory.actual_inventory_quantity) AS `qty`,
        ending_inventory.inventory_unit AS unit,
        ending_inventory.date,
        ending_inventory.is_deleted
    FROM ending_inventory
    JOIN initial_inventory
    ON initial_inventory.item_id = ending_inventory.item_id
        AND initial_inventory.branch_id = ending_inventory.branch_id
        AND initial_inventory.date = ending_inventory.date
        AND initial_inventory.unit = ending_inventory.inventory_unit
        ADDITIONAL_CONDITIONS
) AS inventory_usage
LEFT JOIN franchise_sale_item_price
ON inventory_usage.item_id = franchise_sale_item_price.item_id
    AND franchise_sale_item_price.unit = inventory_usage.unit
LEFT JOIN item ON item.id = inventory_usage.item_id
WHERE inventory_usage.is_deleted = 0
    AND item.is_dsr = 1
    AND item.is_active = 1
EOT;
        $binds = [];
        $additional_condition_binds = [];

        $condition = "";

        if ($branch_id) {
            $condition .= " AND ending_inventory.branch_id = ?";
            $sql .= " AND inventory_usage.branch_id = ?";
            $additional_condition_binds[] = $branch_id;
            $binds[] = $branch_id;
        }

        if ($date) {
            $condition .= " AND ending_inventory.date = ?";
            $sql .= " AND inventory_usage.date = ?";
            $additional_condition_binds[] = $date;
            $binds[] = $date;
        }

        $binds = array_merge($binds, $additional_condition_binds);
        $sql = str_replace("ADDITIONAL_CONDITIONS", $condition, $sql);

        $sql .= " GROUP BY inventory_usage.item_id";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    public function get_system_inventory_sales($branch_id = null, $date = null)
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
SELECT SUM(final_item_sales.amount) AS grand_total
FROM (
    SELECT item.name AS item_name, SUM(inventory_usage.qty) AS `qty`, IFNULL(franchise_sale_item_price.price_3, 0) AS price,
        (SUM(inventory_usage.qty) * IFNULL(franchise_sale_item_price.price_3, 0)) AS amount
    FROM (
        SELECT ending_inventory.branch_id, ending_inventory.item_id,
            (initial_inventory.total_qty - ending_inventory.system_inventory_quantity) AS `qty`,
            ending_inventory.inventory_unit AS unit,
            ending_inventory.date,
            ending_inventory.is_deleted
        FROM ending_inventory
        JOIN initial_inventory
        ON initial_inventory.item_id = ending_inventory.item_id
            AND initial_inventory.branch_id = ending_inventory.branch_id
            AND initial_inventory.unit = ending_inventory.inventory_unit
            AND initial_inventory.date = ending_inventory.date
            ADDITIONAL_CONDITIONS
    ) AS inventory_usage
    LEFT JOIN franchise_sale_item_price
    ON inventory_usage.item_id = franchise_sale_item_price.item_id
        AND franchise_sale_item_price.unit = inventory_usage.unit
    LEFT JOIN item ON item.id = inventory_usage.item_id
    WHERE inventory_usage.is_deleted = 0
        AND item.is_dsr = 1
        AND item.is_active = 1
EOT;
        $binds = [];
        $additional_condition_binds = [];

        $condition = "";

        if ($branch_id) {
            $condition .= " AND ending_inventory.branch_id = ?";
            $sql .= " AND inventory_usage.branch_id = ?";
            $additional_condition_binds[] = $branch_id;
            $binds[] = $branch_id;
        }

        if ($date) {
            $condition .= " AND ending_inventory.date = ?";
            $sql .= " AND inventory_usage.date = ?";
            $additional_condition_binds[] = $date;
            $binds[] = $date;
        }

        $binds = array_merge($additional_condition_binds, $binds);
        $sql = str_replace("ADDITIONAL_CONDITIONS", $condition, $sql);

        $sql .= " GROUP BY inventory_usage.item_id) final_item_sales";

        $query = $database->query($sql, $binds);
        return ($query AND $query->getResultArray()) ? $query->getResultArray()[0] : false;
    }

    public function get_system_inventory_sales_by_item($branch_id = null, $date = null)
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
SELECT item.name AS item_name, SUM(inventory_usage.qty) AS `usage`, IFNULL(franchise_sale_item_price.price_3, 0) AS price,
    (SUM(inventory_usage.qty) * IFNULL(franchise_sale_item_price.price_3, 0)) AS amount
FROM (
    SELECT ending_inventory.branch_id, ending_inventory.item_id,
        (initial_inventory.total_qty - ending_inventory.system_inventory_quantity) AS `qty`,
        ending_inventory.inventory_unit AS unit,
        ending_inventory.date,
        ending_inventory.is_deleted
    FROM ending_inventory
    JOIN initial_inventory
    ON initial_inventory.item_id = ending_inventory.item_id
        AND initial_inventory.branch_id = ending_inventory.branch_id
        AND initial_inventory.unit = ending_inventory.inventory_unit
        AND initial_inventory.date = ending_inventory.date
        ADDITIONAL_CONDITIONS
) AS inventory_usage
LEFT JOIN franchise_sale_item_price
ON inventory_usage.item_id = franchise_sale_item_price.item_id
    AND franchise_sale_item_price.unit = inventory_usage.unit
LEFT JOIN item ON item.id = inventory_usage.item_id
WHERE inventory_usage.is_deleted = 0
    AND item.is_dsr = 1
    AND item.is_active = 1
EOT;
        $binds = [];
        $additional_condition_binds = [];

        $condition = "";

        if ($branch_id) {
            $condition .= " AND ending_inventory.branch_id = ?";
            $sql .= " AND inventory_usage.branch_id = ?";
            $additional_condition_binds[] = $branch_id;
            $binds[] = $branch_id;
        }

        if ($date) {
            $condition .= " AND ending_inventory.date = ?";
            $sql .= " AND inventory_usage.date = ?";
            $additional_condition_binds[] = $date;
            $binds[] = $date;
        }

        $binds = array_merge($binds, $additional_condition_binds);
        $sql = str_replace("ADDITIONAL_CONDITIONS", $condition, $sql);

        $sql .= " GROUP BY inventory_usage.item_id";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    public function search($branch_id, $date)
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
SELECT item.id AS item_id,
    item_unit.id AS item_unit_id,
    item.name AS item_name,
    item_unit.breakdown_value,
    item_unit.inventory_value
    item_unit.breakdown_unit,
    item_unit.inventory_unit
FROM order_detail_ingredient
LEFT JOIN item ON item.id = order_detail_ingredient.item_id
LEFT JOIN item_unit ON item_unit.item_id = order_detail_ingredient.item_id
WHERE order_detail_ingredient.is_deleted = 0
EOT;
        $binds = [];

        if ($branch_id) {
            $sql .= " AND order_detail_ingredient.branch_id = ?";
            $binds[] = $branch_id;
        }

        if ($date) {
            $sql .= " AND DATE(order_detail_ingredient.added_on) = ?";
            $binds[] = $date;
        }

        $sql .= " GROUP BY order_detail_ingredient.item_id";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

}