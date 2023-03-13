<?php

namespace App\Models;

class Ending_inventory extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'branch_id',
        'date',
        'inventory_id',
        'item_id',
        'item_unit_id',
        'breakdown_unit',
        'breakdown_qty',
        'inventory_unit',
        'inventory_qty',
        'actual_inventory_quantity',
        'system_inventory_quantity',
        'variance_inventory_quantity',
        'is_inventory_variance',
        'added_on',
        'added_by',
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

    /**
     * Get ending and initial inventory
     */
    public function get_ending_and_initial($branch_id, $date)
    {
        $db = db_connect();

        $sql = <<<EOT
SELECT item.name, item_unit.inventory_unit AS item_unit_name,
    initial_inventory.qty AS beginning, initial_inventory.delivered_qty AS delivered, initial_inventory.total_qty AS initial_total,
    ending_inventory.actual_inventory_quantity AS actual_end, ending_inventory.system_inventory_quantity AS system_end, ending_inventory.variance_inventory_quantity AS ending_variance,
    initial_inventory.total_qty - ending_inventory.actual_inventory_quantity AS actual_usage, initial_inventory.total_qty - ending_inventory.system_inventory_quantity AS system_usage, 
    (initial_inventory.total_qty - ending_inventory.actual_inventory_quantity) - (initial_inventory.total_qty - ending_inventory.system_inventory_quantity) AS usage_variance
FROM initial_inventory
LEFT JOIN ending_inventory ON initial_inventory.item_id = ending_inventory.item_id
    AND ending_inventory.date = initial_inventory.date
    AND ending_inventory.branch_id = initial_inventory.branch_id
    AND ending_inventory.inventory_id = initial_inventory.inventory_id
LEFT JOIN item ON item.id = initial_inventory.item_id
LEFT JOIN item_unit ON item_unit.id = item.id
WHERE initial_inventory.is_deleted = 0
    AND initial_inventory.date = ?
    AND initial_inventory.branch_id = ?
    AND ending_inventory.is_deleted = 0
EOT;
        $binds = [$date, $branch_id];
        $query = $db->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get branch inventory variance
     */
    public function get_branch_inventory_variance($branch_id, $date)
    {
        $db = db_connect();
        $sql = <<<EOT
SELECT IFNULL(MAX(is_inventory_variance), 0) AS inventory_variance
FROM ending_inventory
WHERE ending_inventory.is_deleted = 0
    AND ending_inventory.branch_id = ?
    AND ending_inventory.date = ?
GROUP BY branch_id, date
EOT;
        $binds = [$branch_id, $date];
        $query = $db->query($sql, $binds);
        return ($query AND $query->getResultArray()) ? $query->getResultArray()[0] : false;
    }
}