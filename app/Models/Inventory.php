<?php

namespace App\Models;

class Inventory extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'branch_id',
        'item_id',
        'item_unit_id',
        'beginning_qty',
        'current_qty',
        'min',
        'max',
        'critical_level',
        'acceptable_variance',
        'unit',
        'status',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'inventory';
    }

    /**
     * Get negative inventory
     */
    public function get_negative_qty()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT branch.name AS branch, COUNT(inventory.id) AS negative_items
FROM inventory
LEFT JOIN branch ON branch.id = inventory.branch_id
WHERE inventory.is_deleted = 0
    AND inventory.current_qty < 0
GROUP BY inventory.branch_id
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get inventory details by ID
     */
    public function get_details_by_id($inventory_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
(SELECT item.name FROM item WHERE item.id = inventory.item_id) AS item_name,
(SELECT item.type FROM item WHERE item.id = inventory.item_id) AS item_type,
(SELECT item_unit.inventory_unit FROM item_unit WHERE item_unit.id = inventory.item_unit_id) AS inventory_unit_name,
(SELECT item_unit.breakdown_unit FROM item_unit WHERE item_unit.id = inventory.item_unit_id) AS breakdown_unit_name,
(SELEcT name FROM branch WHERE id = inventory.branch_id) AS branch_name
FROM inventory
WHERE inventory.is_deleted = 0
    AND inventory.id = ?
EOT;
        $binds = [$inventory_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    public function get_details_by_ids($inventory_ids = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
(SELECT item.name FROM item WHERE item.id = inventory.item_id) AS item_name,
(SELECT item.type FROM item WHERE item.id = inventory.item_id) AS item_type,
(SELECT item_unit.inventory_unit FROM item_unit WHERE item_unit.id = inventory.item_unit_id) AS inventory_unit_name,
(SELECT item_unit.breakdown_unit FROM item_unit WHERE item_unit.id = inventory.item_unit_id) AS breakdown_unit_name,
(SELEcT name FROM branch WHERE id = inventory.branch_id) AS branch_name
FROM inventory
WHERE inventory.is_deleted = 0
EOT;
        $binds = [];

        if ($inventory_ids) {
            $sql .= " AND inventory.id IN (";
            foreach ($inventory_ids as $key => $inventory_id) {
                $sql .= "?,";
                $binds[] = $inventory_id;
            }
            $sql = rtrim($sql, ',');
            $sql .= ")";
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all inventorys
     */
    public function get_all_inventory($branch_id, $is_low_level = null, $is_high_level = null) 
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT * 
FROM (
SELECT *,
(SELECT item.name FROM item WHERE item.id = inventory.item_id) AS item_name,
(SELECT item.type FROM item WHERE item.id = inventory.item_id) AS item_type,
(SELECT item_unit.inventory_unit FROM item_unit WHERE item_unit.id = inventory.item_unit_id) AS inventory_unit_name,
(SELECT item_unit.breakdown_unit FROM item_unit WHERE item_unit.id = inventory.item_unit_id) AS breakdown_unit_name,
(SELECT name FROM branch WHERE id = inventory.branch_id) AS branch_name
FROM inventory
WHERE inventory.is_deleted = 0
) AS inventory
WHERE inventory.is_deleted = 0
EOT;
        $binds = [];
        if (isset($branch_id)) {
            $sql .= " AND inventory.branch_id = ?";
            $binds[] = $branch_id;
        }

        if ($is_low_level) {
            $sql .= " AND inventory.current_qty < inventory.min";
        }

        if ($is_high_level) {
            $sql .= " AND inventory.current_qty > inventory.max";
        }

        $sql .= " ORDER BY inventory.item_name ASC";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get inventoryess based on transaction_type_id, branch_id, commission
     */
    public function search($branch_id, $item_id, $beginning_qty, $current_qty, $unit, $status, $name, $item_type, $limit_by, $low_stock, $high_stock, $normal_stock, $for_end_inventory)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM (
    SELECT inventory.*,
    item.name AS item_name,
    item.type AS item_type,
    item_unit.inventory_unit AS inventory_unit_name,
    item_unit.breakdown_unit AS breakdown_unit_name,
    item_unit.inventory_value,
    item_unit.breakdown_value,
    branch.name AS branch_name,
    inventory.current_qty AS inventory_current_qty,
    inventory.current_qty * (item_unit.breakdown_value / item_unit.inventory_value) AS breakdown_current_qty,
    item_unit.price AS item_unit_price
    FROM inventory
    LEFT JOIN item_unit ON item_unit.id = inventory.item_unit_id
    LEFT JOIN item ON item.id = inventory.item_id
    LEFT JOIN branch ON branch.id = inventory.branch_id
    WHERE inventory.is_deleted = 0
) AS inventory
WHERE inventory.is_deleted = 0
EOT;
        $binds = [];

        if ($branch_id) {
            $sql .= ' AND inventory.branch_id = ?';
            $binds[] = $branch_id;
        }

        if ($item_id) {
            $sql .= ' AND inventory.item_id = ?';
            $binds[] = $item_id;
        }

        if ($beginning_qty) {
            $sql .= ' AND inventory.beginning_qty = ?';
            $binds[] = $beginning_qty;
        }

        if ($current_qty) {
            $sql .= ' AND inventory.current_qty = ?';
            $binds[] = $current_qty;
        }

        if ($unit) {
            $sql .= ' AND inventory.unit = ?';
            $binds[] = $unit;
        }

        if ($status) {
            $sql .= ' AND inventory.status = ?';
            $binds[] = $status;
        }

        if ($name) {
            $sql .= ' AND item_name LIKE ?';
            $binds[] = "%$name%";
        }

        if ($item_type AND $item_type != "all") {
            $sql .= ' AND item_type = ?';
            $binds[] = $item_type;
        }

        if ($low_stock) {
            $sql .= ' AND inventory.current_qty < inventory.min';
        }

        if ($high_stock) {
            $sql .= ' AND inventory.current_qty > inventory.max';
        }

        if ($normal_stock) {
            $sql .= ' AND inventory.current_qty > inventory.min AND inventory.current_qty < inventory.max';
        }

        if ($for_end_inventory) {
            $sql .= ' AND inventory.for_end_inventory = 1';
        }

        $sql .= " ORDER BY inventory.item_name";

        if ($limit_by) {
            $sql .= ' LIMIT ?';
            $binds[] = (int)$limit_by;
        }
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get the current qty of an item
     */
    public function get_inventory_qty_by_branch($item_id = null, $branch_id = null, $inventory_unit = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT current_qty
FROM inventory
WHERE inventory.is_deleted = 0
    AND inventory.item_id = ?
    AND branch_id = ?
    AND (SELECT item_unit.inventory_unit FROM item_unit WHERE item_unit.id = inventory.item_unit_id) = ?
EOT;
        $binds = [$item_id, $branch_id, $inventory_unit];

        $query = $database->query($sql, $binds);
        $result = $query ? $query->getResultArray() : null;
        return $result ? (float)$result[0]['current_qty'] : 0;
    }

    /** 
     * Get the inventory qty by item id, branch id and item unit id
     */
    public function get_inventory_detail($item_id = null, $branch_id = null, $item_unit_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT item.name FROM item WHERE item.id = inventory.item_id) AS item_name,
    (SELECT branch.name FROM branch WHERE branch.id = inventory.branch_id) AS branch_name
FROM inventory
WHERE inventory.is_deleted = 0
EOT;
        $binds = [];

        if ($item_id) {
            $sql .= ' AND inventory.item_id = ?';
            $binds[] = $item_id;
        }

        if ($branch_id) {
            $sql .= ' AND inventory.branch_id = ?';
            $binds[] = $branch_id;
        }

        if ($item_unit_id) {
            $sql .= ' AND inventory.item_unit_id = ?';
            $binds[] = $item_unit_id;
        }
  
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Update the quantity of the inventory
     */
    public function update_quantity($where = null, $quantity = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();

        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE inventory
SET current_qty = current_qty + ?, updated_by = ?, updated_on = ?
WHERE inventory.is_deleted = 0
    AND inventory.branch_id = ?
    AND inventory.item_id = ?
    AND inventory.item_unit_id = ?
EOT;
        $binds = [$quantity, $requested_by, $date_now, $where['branch_id'], $where['item_id'], $where['item_unit_id']];

        return $database->query($sql, $binds);
    }

    /**
     * Get the item history
     * 
     * Database to consider when getting the history:
     * - Receive table
     * - Receive Item table
     * - Transfer (Temporary since it is auto approved )
     * - Transfer Receive table
     * - Transfer Receive Item table
     * - Daily Inventory table
     * - Build item table
     * - Build item detail table
     * - Adjustment
     * - Franchisee Payment
     */
    public function get_item_history($item_id = null, $branch_id = null, $encoded_on_to = null, $encded_on_from = null, $doc_type = null, $item_unit_id = null, $branch_name = null, $doc_no = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM 
(   
    (SELECT NULL AS qty_in, 
        IFNULL(SUM(order_detail_ingredient.qty), 0) AS qty_out, 
        order_detail_ingredient.unit, 
        IF(`order`.offline_id IS NULL, `order`.id, CONCAT(`order`.id, "-", `order`.offline_id)) AS doc_no, 
        DATE(`order`.added_on) AS doc_date, 
        `order`.added_on AS encoded_on, 
        "Order" AS doc_type, 
        `order`.id AS doc_id, 
        NULL AS branch_from,
        branch.name AS branch_name,
        NULL AS supplier_id,
        NULL AS supplier_name,
        `order`.added_by AS added_by,
        CONCAT(user.first_name, ' ', user.last_name) AS added_by_name,
        NULL as slip_no
    FROM `order`
    LEFT JOIN order_detail ON `order`.id = order_detail.order_id
    LEFT JOIN order_detail_ingredient ON order_detail.id = order_detail_ingredient.order_detail_id
    LEFT JOIN branch ON branch.id = `order`.branch_id
    LEFT JOIN user ON user.id = `order`.added_by
    WHERE `order`.is_deleted = 0
        AND order_detail_ingredient.item_id = ?
        AND `order`.branch_id = ?
    GROUP BY `order`.id
    )
    
    UNION ALL
    
    (SELECT receive_item.qty AS qty_in, 
        NULL AS qty_out, 
        receive_item.unit, 
        receive.id AS doc_no, 
        receive.receive_date AS doc_date, 
        receive_item.added_on AS encoded_on, 
        "Purchase Invoice" AS doc_type, 
        receive.id AS doc_id, 
        NULL AS branch_from,
        branch.name AS branch_name,
        receive.supplier_id AS supplier_id,
        supplier.trade_name AS supplier_name,
        receive_item.added_by AS added_by,
        CONCAT(user.first_name, ' ', user.last_name) AS added_by_name,
        IF(receive.invoice_no IS NULL, IF(receive.dr_no IS NULL, receive.id, receive.dr_no), receive.invoice_no) as slip_no
    FROM receive_item
    LEFT JOIN receive ON receive.id = receive_item.receive_id
        AND receive.is_deleted = 0
    LEFT JOIN inventory ON inventory.id = receive_item.inventory_id
        AND inventory.is_deleted = 0
    LEFT JOIN branch ON branch.id = receive.branch_id
        AND branch.is_deleted = 0
    LEFT JOIN supplier ON supplier.id = receive.supplier_id
        AND supplier.is_deleted = 0
    LEFT JOIN user ON user.id = receive_item.added_by
        AND user.is_deleted = 0
    WHERE receive_item.is_deleted = 0
        AND receive_item.item_id = ?
        AND receive.branch_id = ?
    )

    UNION ALL

    (SELECT build_item.qty AS qty_in, 
        NULL AS qty_out, 
        item_unit.inventory_unit AS unit, 
        build_item.id AS doc_no, 
        build_item.production_date AS doc_date, 
        build_item.added_on AS encoded_on, 
        "Build Item" AS doc_type, 
        build_item.id AS doc_id, 
        build_item.to_branch_id AS branch_from,
        branch.name AS branch_name,
        NULL AS supplier_id,
        NULL AS supplier_name,
        build_item.added_by AS added_by,
        CONCAT(user.first_name, ' ', user.last_name) AS added_by_name,
        IF(build_item.production_slip_no IS NULL, build_item.id, build_item.production_slip_no) as slip_no
    FROM build_item
    LEFT JOIN item_unit ON item_unit.id = build_item.item_unit_id
        AND item_unit.is_deleted = 0
    LEFT JOIN branch ON branch.id = build_item.to_branch_id
        AND branch.is_deleted = 0
    LEFT JOIN user ON user.id = build_item.added_by
        AND user.is_deleted = 0
    WHERE build_item.is_deleted = 0
        AND build_item.item_id = ?
        AND build_item.to_branch_id = ?
    )

    UNION ALL

    (SELECT NULL AS qty_in, 
        build_item_detail.qty AS qty_out, 
        item_unit.inventory_unit AS unit, 
        build_item.id AS doc_no, 
        build_item.production_date AS doc_date, 
        build_item.added_on AS encoded_on, 
        "Build Item" AS doc_type, 
        build_item.id AS doc_id, 
        build_item.from_branch_id AS branch_from,
        branch.name AS branch_name,
        NULL AS supplier_id,
        NULL AS supplier_name,
        build_item.added_by AS added_by,
        CONCAT(user.first_name, ' ', user.last_name) AS added_by_name,
        IF(build_item.production_slip_no IS NULL, build_item.id, build_item.production_slip_no) as slip_no
    FROM build_item_detail
    LEFT JOIN build_item ON build_item.id = build_item_detail.build_item_id
        AND build_item.is_deleted = 0
    LEFT JOIN item_unit ON item_unit.id = build_item_detail.item_unit_id
        AND item_unit.is_deleted = 0
    LEFT JOIN branch ON branch.id = build_item.from_branch_id
        AND branch.is_deleted = 0
    LEFT JOIN user ON user.id = build_item.added_by
        AND user.is_deleted = 0
    WHERE build_item_detail.is_deleted = 0
        AND build_item_detail.item_id = ?
        AND build_item.from_branch_id = ?
    )

    UNION ALL

    (SELECT NULL AS qty_in, 
        transfer_receive_item.qty AS qty_out, 
        transfer_receive_item.unit, 
        transfer_receive.transfer_id AS doc_no, 
        IF(transfer_receive.updated_on IS NULL, DATE(transfer_receive.added_on), DATE(transfer_receive.updated_on)) AS doc_date, 
        transfer_receive_item.added_on AS encoded_on, 
        "Transfer" AS doc_type, 
        transfer_receive.transfer_id AS doc_id, 
        transfer_receive.branch_from,
        branch.name AS branch_name,
        NULL AS supplier_id,
        NULL AS supplier_name,
        transfer_receive.added_by AS added_by,
        CONCAT(user.first_name, " ", user.last_name) AS added_by_name,
        null as slip_no
    FROM transfer_receive_item
    LEFT JOIN transfer_receive ON transfer_receive.id = transfer_receive_item.transfer_receive_id
        AND transfer_receive.is_deleted = 0
    LEFT JOIN branch ON branch.id = transfer_receive.branch_from
        AND branch.is_deleted = 0
    LEFT JOIN user ON user.id = transfer_receive_item.added_by
        AND user.is_deleted = 0
    WHERE transfer_receive_item.is_deleted = 0
        AND transfer_receive_item.item_id = ?
        AND transfer_receive.branch_from = ?
        AND transfer_receive.status = "completed"
    )

    UNION ALL

    (SELECT transfer_receive_item.qty AS qty_in, 
        NULL AS qty_out, 
        transfer_receive_item.unit, 
        transfer_receive.transfer_id AS doc_no, 
        IF(transfer_receive.updated_on IS NULL, DATE(transfer_receive.added_on), DATE(transfer_receive.updated_on)) AS doc_date, 
        transfer_receive_item.added_on AS encoded_on, 
        "Transfer Receive" AS doc_type, 
        transfer_receive.transfer_id AS doc_id, 
        transfer_receive.branch_to,
        (SELECT branch.name FROM branch WHERE branch.id = transfer_receive.branch_to) AS branch_name,
        NULL AS supplier_id,
        NULL AS supplier_name,
        transfer_receive.added_by AS added_by,
        (SELECT CONCAT(user.first_name, " ", user.last_name) FROM user WHERE user.id = transfer_receive.added_by) AS added_by_name,
        null as slip_no
    FROM transfer_receive_item
    LEFT JOIN transfer_receive ON transfer_receive.id = transfer_receive_item.transfer_receive_id
        AND transfer_receive.is_deleted = 0
    LEFT JOIN branch ON branch.id = transfer_receive.branch_from
        AND branch.is_deleted = 0
    LEFT JOIN user ON user.id = transfer_receive_item.added_by
        AND user.is_deleted = 0
    WHERE transfer_receive_item.is_deleted = 0
        AND transfer_receive_item.item_id = ?
        AND transfer_receive.branch_to = ?
        AND transfer_receive.status = "completed"
    )

    UNION ALL

    (SELECT IF(adjustment.difference > 0, adjustment.difference, NULL) AS qty_in,
        IF(adjustment.difference < 0, adjustment.difference, NULL) AS qty_out,
        adjustment.unit,
        adjustment.id AS doc_no,
        adjustment.added_on AS doc_date,
        adjustment.added_on AS encoded_on,
        "Adjustment" AS doc_type,
        adjustment.id AS doc_id,
        adjustment.branch_id AS branch_from,
        branch.name AS branch_name,
        NULL AS supplier_id,
        NULL AS supplier_name,
        adjustment.added_by AS added_by,
        CONCAT(user.first_name, " ", user.last_name) AS added_by_name,
        adjustment.id as slip_no
    FROM adjustment
    LEFT JOIN branch ON branch.id = adjustment.branch_id
        AND branch.is_deleted = 0
    LEFT JOIN user ON user.id = adjustment.added_by
        AND user.is_deleted = 0
    WHERE adjustment.is_deleted = 0
        AND adjustment.item_id = ?
        AND adjustment.branch_id = ?
        AND adjustment.status = 'approved'
    )

    UNION ALL

    (SELECT franchisee_sale_item.qty AS qty_in,
        NULL AS qty_out,
        franchisee_sale_item.unit,
        franchisee_sale.id AS doc_no,
        franchisee_sale.sales_date AS doc_date,
        franchisee_sale_item.added_on AS encoded_on,
        "Franchisee Sale" AS doc_type,
        franchisee_sale.id AS doc_id,
        franchisee_sale.buyer_branch_id AS branch_from,
        branch.name AS branch_name,
        franchisee_sale.franchisee_id AS supplier_id,
        franchisee.name AS supplier_name,
        franchisee_sale.added_by AS added_by,
        CONCAT(user.first_name, ' ', user.last_name) AS added_by_name,
        franchisee_sale.id as slip_no
    FROM franchisee_sale_item
    LEFT JOIN franchisee_sale ON franchisee_sale.id = franchisee_sale_item.franchisee_sale_id
        AND franchisee_sale.is_deleted = 0
    LEFT JOIN branch ON branch.id = franchisee_sale.buyer_branch_id
        AND branch.is_deleted = 0
    LEFT JOIN user ON user.id = franchisee_sale_item.added_by
        AND user.is_deleted = 0
    LEFT JOIN franchisee ON franchisee.id = franchisee_sale.franchisee_id
        AND franchisee.is_deleted = 0
    WHERE franchisee_sale_item.is_deleted = 0
        AND franchisee_sale_item.item_id = ?
        AND franchisee_sale.buyer_branch_id = ?
        AND franchisee_sale.fs_status = 'invoiced'
    )

    UNION ALL

    (SELECT NULL AS qty_in,
        franchisee_sale_item.qty AS qty_out,
        franchisee_sale_item.unit,
        franchisee_sale.id AS doc_no,
        franchisee_sale.sales_date AS doc_date,
        franchisee_sale_item.added_on AS encoded_on,
        "Franchisee Sale" AS doc_type,
        franchisee_sale.id AS doc_id,
        franchisee_sale.seller_branch_id AS branch_from,
        branch.name AS branch_name,
        franchisee_sale.franchisee_id AS supplier_id,
        franchisee.name AS supplier_name,
        franchisee_sale.added_by AS added_by,
        CONCAT(user.first_name, ' ', user.last_name) AS added_by_name,
        franchisee_sale.id as slip_no
    FROM franchisee_sale_item
    LEFT JOIN franchisee_sale ON franchisee_sale.id = franchisee_sale_item.franchisee_sale_id
        AND franchisee_sale.is_deleted = 0
    LEFT JOIN branch ON branch.id = franchisee_sale.seller_branch_id
        AND branch.is_deleted = 0
    LEFT JOIN user ON user.id = franchisee_sale_item.added_by
        AND user.is_deleted = 0
    LEFT JOIN franchisee ON franchisee.id = franchisee_sale.franchisee_id
        AND franchisee.is_deleted = 0
    WHERE franchisee_sale_item.is_deleted = 0
        AND franchisee_sale_item.item_id = ?
        AND franchisee_sale.fs_status = 'invoiced'
        AND franchisee_sale.seller_branch_id = ?
    )
) as item_history
WHERE item_history.encoded_on is not null
EOT;
        $binds = [$item_id, $branch_id, $item_id, $branch_id, $item_id, $branch_id, $item_id, $branch_id, $item_id, $branch_id, $item_id, $branch_id, $item_id, $branch_id, $item_id, $branch_id, $item_id, $branch_id];

        if ($encoded_on_to) {
            $sql .= " AND encoded_on <= ?";
            $binds[] = $encoded_on_to;
        }

        if ($encded_on_from) {
            $sql .= " AND encoded_on >= ?";
            $binds[] = $encded_on_from;
        }
        
        if ($doc_type) {
            $sql .= " AND doc_type = ?";
            $binds[] = $doc_type;
        }

        if ($item_unit_id) {
            $sql .= " AND item_history.unit = (SELECT inventory_unit FROM item_unit WHERE id = ?)";
            $binds[] = $item_unit_id;
        }

        if ($branch_name) {
            $sql .= " AND item_history.branch_name LIKE ?";
            $binds[] = "%$branch_name%";
        }

        if ($doc_no) {
            $sql .= " AND item_history.doc_no LIKE ?";
            $binds[] = "%$doc_no%";
        }

        $sql .= " ORDER BY encoded_on ASC";

        $query = $database->query($sql, $binds);

        return  $query ? $query->getResultArray() : [];
    }


    public function match_qty_base_computer_count()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM (
    SELECT inventory.id, inventory.beginning_qty, inventory.current_qty, (inventory.beginning_qty +  SUM(item_history.qty_in) - SUM(IF(item_history.qty_out < 0, item_history.qty_out * -1, item_history.qty_out))) AS computed, item_history.branch_id, item_history.item_id, item_history.unit, SUM(item_history.qty_in) AS total_qty_in, SUM(item_history.qty_out) AS total_qty_out
    FROM 
    (    
        (SELECT receive_item.qty AS qty_in, 
            0 AS qty_out, 
            receive_item.unit, 
            CONCAT_WS('<br>',receive.invoice_no,receive.waybill_no,receive.dr_no) AS doc_no, 
            receive.receive_date AS doc_date, 
            receive_item.added_on AS encoded_on, 
            "Purchase Invoice" AS doc_type, 
            receive.id AS doc_id, 
            NULL AS branch_from,
            NULL AS branch_name,
            receive.supplier_id AS supplier_id,
            (SELECT supplier.trade_name FROM supplier WHERE supplier.id = receive.supplier_id) AS supplier_name,
            receive_item.item_id,
            receive.branch_id
        FROM receive_item
        LEFT JOIN receive ON receive.id = receive_item.receive_id
            AND receive.is_deleted = 0
        LEFT JOIN inventory ON inventory.id = receive_item.inventory_id
            AND inventory.is_deleted = 0
        WHERE receive_item.is_deleted = 0
        )

        UNION ALL

        (SELECT build_item.qty AS qty_in, 
            0 AS qty_out, 
            (SELECT inventory_unit FROM item_unit WHERE id = build_item.item_unit_id) AS unit, 
            build_item.id AS doc_no, 
            build_item.production_date AS doc_date, 
            build_item.added_on AS encoded_on, 
            "Build Item" AS doc_type, 
            build_item.id AS doc_id, 
            build_item.to_branch_id AS branch_from,
            (SELECT branch.name FROM branch WHERE branch.id = build_item.to_branch_id) AS branch_name,
            NULL AS supplier_id,
            NULL AS supplier_name,
            build_item.item_id,
            build_item.to_branch_id AS branch_id
        FROM build_item
        WHERE build_item.is_deleted = 0
        )

        UNION ALL

        (SELECT 0 AS qty_in, 
            build_item_detail.qty AS qty_out, 
            (SELECT inventory_unit FROM item_unit WHERE id = build_item_detail.item_unit_id) AS unit, 
            build_item.id AS doc_no, 
            build_item.production_date AS doc_date, 
            build_item.added_on AS encoded_on, 
            "Build Item" AS doc_type, 
            build_item.id AS doc_id, 
            build_item.from_branch_id AS branch_from,
            (SELECT branch.name FROM branch WHERE branch.id = build_item.from_branch_id) AS branch_name,
            NULL AS supplier_id,
            NULL AS supplier_name,
            build_item_detail.item_id,
            build_item.from_branch_id AS branch_id
        FROM build_item_detail
        LEFT JOIN build_item ON build_item.id = build_item_detail.build_item_id
            AND build_item.is_deleted = 0
        WHERE build_item_detail.is_deleted = 0
        )

        UNION ALL

        (SELECT 0 AS qty_in, 
            IFNULL(transfer_item.qty, transfer_receive_item.qty) AS qty_out,
            transfer_receive_item.unit, 
            transfer_receive.transfer_id AS doc_no,
            transfer_receive.transfer_receive_date AS doc_date, 
            transfer_receive_item.added_on AS encoded_on, 
            "Transfer" AS doc_type, 
            transfer_receive.transfer_id AS doc_id, 
            transfer_receive.branch_from,
            (SELECT branch.name FROM branch WHERE branch.id = transfer_receive.branch_from) AS branch_name,
            NULL AS supplier_id,
            NULL AS supplier_name,
            transfer_receive_item.item_id,
            transfer_receive.branch_from AS branch_id
        FROM transfer_receive_item
        LEFT JOIN transfer_receive ON transfer_receive.id = transfer_receive_item.transfer_receive_id
            AND transfer_receive.is_deleted = 0
        LEFT JOIN transfer_item ON transfer_item.id = transfer_receive_item.transfer_item_id
            AND transfer_item.is_deleted = 0
        WHERE transfer_receive_item.is_deleted = 0
        )

        UNION ALL

        (SELECT transfer_receive_item.qty AS qty_in, 
            0 AS qty_out, 
            transfer_receive_item.unit, 
            transfer_receive.transfer_id AS doc_no,
            transfer_receive.completed_on AS doc_date, 
            transfer_receive_item.added_on AS encoded_on, 
            "Transfer Receive" AS doc_type, 
            transfer_receive.transfer_id AS doc_id, 
            transfer_receive.branch_to,
            (SELECT branch.name FROM branch WHERE branch.id = transfer_receive.branch_to) AS branch_name,
            NULL AS supplier_id,
            NULL AS supplier_name,
            transfer_receive_item.item_id,
            transfer_receive.branch_to AS branch_id
        FROM transfer_receive_item
        LEFT JOIN transfer_receive ON transfer_receive.id = transfer_receive_item.transfer_receive_id
            AND transfer_receive.is_deleted = 0
        WHERE transfer_receive_item.is_deleted = 0
        )

        UNION ALL

        (SELECT IF(adjustment.difference > 0, adjustment.difference, NULL) AS qty_in,
            IF(adjustment.difference < 0, adjustment.difference, NULL) AS qty_out,
            adjustment.unit,
            adjustment.id AS doc_no,
            adjustment.added_on AS doc_date,
            adjustment.added_on AS encoded_on,
            "Adjustment" AS doc_type,
            adjustment.id AS doc_id,
            adjustment.branch_id AS branch_from,
            (SELECT branch.name FROM branch WHERE branch.id = adjustment.branch_id) AS branch_name,
            NULL AS supplier_id,
            NULL AS supplier_name,
            adjustment.item_id,
            adjustment.branch_id
        FROM adjustment
        WHERE adjustment.is_deleted = 0
            AND adjustment.status = 'approved'
        )

        UNION ALL

        (SELECT franchisee_sale_item.qty AS qty_in,
            0 AS qty_out,
            franchisee_sale_item.unit,
            franchisee_sale.id AS doc_no,
            franchisee_sale.sales_date AS doc_date,
            franchisee_sale_item.added_on AS encoded_on,
            "Franchisee Sale" AS doc_type,
            franchisee_sale.id AS doc_id,
            franchisee_sale.buyer_branch_id AS branch_from,
            (SELECT branch.name FROM branch WHERE branch.id = franchisee_sale.buyer_branch_id) AS branch_name,
            franchisee_sale.franchisee_id AS supplier_id,
            (SELECT franchisee.name FROM franchisee WHERE franchisee.id = franchisee_sale.franchisee_id) AS supplier_name,
            franchisee_sale_item.item_id,
            franchisee_sale.buyer_branch_id AS branch_id
        FROM franchisee_sale_item
        LEFT JOIN franchisee_sale ON franchisee_sale.id = franchisee_sale_item.franchisee_sale_id
            AND franchisee_sale.is_deleted = 0
            AND franchisee_sale_item.is_deleted = 0
            AND franchisee_sale.fs_status = 'invoiced'
        )

        UNION ALL

        (SELECT 0 AS qty_in,
            franchisee_sale_item.qty AS qty_out,
            franchisee_sale_item.unit,
            franchisee_sale.id AS doc_no,
            franchisee_sale.sales_date AS doc_date,
            franchisee_sale_item.added_on AS encoded_on,
            "Franchisee Sale" AS doc_type,
            franchisee_sale.id AS doc_id,
            franchisee_sale.seller_branch_id AS branch_from,
            (SELECT branch.name FROM branch WHERE branch.id = franchisee_sale.seller_branch_id) AS branch_name,
            franchisee_sale.franchisee_id AS supplier_id,
            (SELECT franchisee.name FROM franchisee WHERE franchisee.id = franchisee_sale.franchisee_id) AS supplier_name,
            franchisee_sale_item.item_id,
            franchisee_sale.seller_branch_id AS branch_id
        FROM franchisee_sale_item
        LEFT JOIN franchisee_sale ON franchisee_sale.id = franchisee_sale_item.franchisee_sale_id
            AND franchisee_sale.is_deleted = 0
            AND franchisee_sale_item.is_deleted = 0
            AND franchisee_sale.fs_status = 'invoiced'
        )
    ) as item_history
    LEFT JOIN inventory ON inventory.item_id = item_history.item_id
        AND inventory.branch_id = item_history.branch_id
        AND inventory.is_deleted = 0
    WHERE item_history.encoded_on is not null
    GROUP BY item_history.branch_id, item_history.item_id, item_history.unit
) AS computer_history
WHERE computer_history.current_qty <> computer_history.computed
EOT;

        $query = $database->query($sql);
        $item_histories = $query->getResultArray();

        $item_histories = $item_histories;
        $inventory = new Inventory();

        foreach ($item_histories as $item_history) {
            $inventory_id = $item_history['id'];
            $current_qty = $item_history['current_qty'];
            $computed = $item_history['computed'];

            if ($computed > 0)
                $inventory->update($inventory_id, ['current_qty' => $computed]);
        }

        return true;
    }


    /*
    * Get low in stock items based on item_id, branch_id
    */
    public function get_low_stock_items($item_id = null, $branch_id = null) {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT inventory.*
FROM inventory
WHERE inventory.current_qty < inventory.min
    AND inventory.is_deleted = 0
EOT;
        $binds = [];
        if ($item_id) {
            $sql .= ' AND inventory.item_id = ?';
            $binds[] = $item_id;
        }

        if ($branch_id) {
            $sql .= ' AND inventory.branch_id = ?';
            $binds[] = $branch_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /*
    * get_warehouse_inventory_ids
    */
    public function get_warehouse_inventory_ids($item_id = null, $item_unit_id = null, $branch_id = null) {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT inventory.id
FROM inventory
WHERE inventory.item_id = ?
    AND inventory.item_unit_id = ?
    AND inventory.branch_id = ?
    AND inventory.is_deleted = 0
EOT;
        $binds = [$item_id, $item_unit_id, $branch_id];
        // var_dump($binds);
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : [];
    }

    /*
    * get_warehouse_inventory_ids
    */
    public function get_warehouse_inventory_details($item_id = null) {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT branch.name,
    inventory.*
FROM inventory
LEFT JOIN branch ON branch.id = inventory.branch_id
WHERE inventory.branch_id IN (0, 1, 2, 3, 4)
    AND inventory.is_deleted = 0
    AND inventory.item_id = ?
EOT;
        $binds = [$item_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : [];
    }

    /**
     * Get inventories based on inventory group
     */
    public function get_inventory_group_inventory_details($item_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT inventory_group.name as inventory_group_name,
    inventory_group_inventory_detail.*
FROM (
SELECT branch.name AS branch_name,
    (SELECT inventory_group_id
        FROM inventory_group_detail
        WHERE inventory_group_detail.branch_id = inventory.branch_id
            AND inventory_group_detail.is_deleted = 0
        LIMIT 1
        ) AS inventory_group_id,
    inventory.*
FROM inventory
LEFT JOIN branch ON branch.id = inventory.branch_id
WHERE inventory.branch_id IN (
        SELECT branch_id 
        FROM inventory_group_detail
        WHERE inventory_group_detail.is_deleted = 0
    )
    AND inventory.is_deleted = 0
    AND inventory.item_id = ?
) AS inventory_group_inventory_detail
LEFT JOIN inventory_group ON inventory_group.id = inventory_group_inventory_detail.inventory_group_id
WHERE inventory_group.is_deleted = 0
GROUP BY inventory_group.name
EOT;
        $binds = [$item_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : [];
    }

    /**
     * Get transferrable inventories
     */
    public function get_transferrable_items($requesting_branch_id, $requested_branch_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT requesting_inventory.*,
    item.name AS item_name,
    item_unit.inventory_unit AS inventory_unit,
    item_unit.breakdown_unit AS breakdown_unit,
    branch.name AS branch_name,
    (SELECT current_qty - min
        FROM inventory
        WHERE inventory.item_id = inventory.item_id
            AND inventory.branch_id = ?
                AND inventory.item_unit_id = requesting_inventory.item_unit_id
            AND inventory.is_deleted = 0
            AND inventory.current_qty > inventory.min
    ) AS transferrable_qty,
    requesting_inventory.max - requesting_inventory.current_qty AS qty_needed
FROM inventory AS requesting_inventory
LEFT JOIN item ON item.id = requesting_inventory.item_id
LEFT JOIN item_unit ON item_unit.id = requesting_inventory.item_unit_id
LEFT JOIN branch ON branch.id = requesting_inventory.branch_id
WHERE requesting_inventory.branch_id = ?
    AND requesting_inventory.is_deleted = 0
    AND requesting_inventory.current_qty < requesting_inventory.min
    AND requesting_inventory.item_id IN (
        SELECT inventory.item_id
        FROM inventory
        WHERE inventory.branch_id = ?
            AND inventory.is_deleted = 0
            AND inventory.current_qty > inventory.min
        );
EOT;

        $binds = [$requested_branch_id, $requesting_branch_id, $requested_branch_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : [];
    }

    /**
     * Get inventory by item_id, branch_id, item_unit_id
     */
    public function get_inventory_by_details($item_id = null, $branch_id = null, $item_unit_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT inventory.*
FROM inventory
WHERE inventory.item_id = ?
    AND inventory.branch_id = ?
    AND inventory.item_unit_id = ?
    AND inventory.is_deleted = 0
LIMIT 1
EOT;
        $binds = [$item_id, $branch_id, $item_unit_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : [];
    }

    /**
     * Get Initial Inventory
     */
    public function get_initial_inventory($branch_id)
    {
        $db = db_connect();
        $current_date = date("Y-m-d 00:00:00");
        $yesterday_date = date("Y-m-d", strtotime("-1 day"));

        $sql = <<<EOT
SELECT inventory.id AS inventory_id,
    inventory.item_id,
    inventory.current_qty,
    SUM(IFNULL(transfer_receive_item.qty, 0)) AS delivered_qty,
    inventory.unit AS inventory_unit
FROM inventory
LEFT JOIN daily_sale ON daily_sale.branch_id = inventory.branch_id
    AND daily_sale.date = ?
LEFT JOIN transfer_receive 
ON transfer_receive.branch_to = inventory.branch_id
    AND transfer_receive.completed_on > IFNULL(daily_sale.added_on, ?)
    AND transfer_receive.status = "completed"
LEFT JOIN transfer_receive_item ON transfer_receive.id = transfer_receive_item.transfer_receive_id
    AND inventory.item_id = transfer_receive_item.item_id
    AND transfer_receive_item.unit = inventory.unit
WHERE inventory.branch_id = ?
GROUP BY inventory.item_id
EOT;

        $binds = [$yesterday_date, $current_date, $branch_id];

        $query = $db->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get Inventory by branch
     */
    public function get_inventory_by_branch($branch_id)
    {
        $db = db_connect();
        $current_date = date("Y-m-d");

        $sql = <<<EOT
SELECT item.id AS item_id,
    item_unit.id AS item_unit_id,
    item.name AS item_name,
    item_unit.breakdown_value,
    item_unit.inventory_value
    item_unit.breakdown_unit,
    item_unit.inventory_unit
FROM inventory
LEFT JOIN item ON item.id = inventory.item_id
LEFT JOIN item_unit ON item_unit.item_id = inventory.item_id
WHERE inventory.is_deleted = 0
    AND inventory.branch_id = ?
EOT;

        $binds = [$branch_id];

        $query = $db->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get item levels (min, max, current, and etc.)
     */
    public function get_item_levels($item_id)
    {
        $db = db_connect();
        $sql = <<<EOT
SELECT inventory.min, inventory.max, inventory.critical_level, inventory.current_qty, inventory.acceptable_variance, inventory.beginning_qty, inventory.unit,
    branch.name AS branch_name, branch.id AS branch_id,
    NULL AS inventory_group_id, NULL AS inventory_group_name
FROM inventory
LEFT JOIN branch ON branch.id = inventory.branch_id
WHERE inventory.item_id = ?
    AND inventory.is_deleted = 0

UNION ALL

SELECT NULL AS min, NULL AS max, NULL AS critical_level, NULL AS current_qty, NULL AS acceptable_variance, NULL AS beginning_qty, NULL AS unit,
    NULL AS branch_name, NULL AS branch_id,
    inventory_group.name AS inventory_group_name, inventory_group.id AS inventory_group_id
FROM inventory_group_detail
LEFT JOIN inventory_group ON inventory_group.id = inventory_group_detail.inventory_group_id
WHERE inventory_group_detail.is_deleted = 0
    AND inventory_group_detail.branch_id IN (
            SELECT branch_id
            FROM inventory
            WHERE inventory.item_id = ?
                AND inventory.is_deleted = 0
    )
GROUP BY inventory_group.id
EOT;

        $binds = [$item_id, $item_id];

        $query = $db->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}