<?php

namespace App\Models;

class Order extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'branch_id',
        'paid_amount',
        'change',
        'grand_total',
        'remarks',
        'gift_cert_code',
        'transaction_type',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'order';
    }
    
    /**
     * Get order details by ID
     */
    public function get_details_by_id($order_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT `order`.*,
    branch.name AS branch_name
FROM `order`
LEFT JOIN branch ON `order`.branch_id = branch.id
WHERE `order`.is_deleted = 0
    AND `order`.id = ?
EOT;

        $binds = [$order_id];
        $query = $database->query($sql, $binds);
        
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all orders
     */
    public function get_all_order($branch_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT `order`.*,
    branch.name AS branch_name
FROM `order`
LEFT JOIN branch ON `order`.branch_id = branch.id
WHERE `order`.is_deleted = 0
EOT;
        $bind = [];
        if ($branch_id) {
            $sql .= " AND branch_id = ?";
            $bind[] = $branch_id;
        }

        $query = $database->query($sql, $bind);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get orderess based on order name, address, contact_person, contact_person_no, tin_no, bir_no
     */
    public function search($branch_id, $branch_name, $added_on_from, $added_on_to, $transaction_type, $group_orders)
    {
        $database = \Config\Database::connect();

        if ($group_orders) {
            $payments = "SUM(order.paid_amount) AS paid_amount, SUM(order.change) AS `change`, SUM(order.grand_total) AS grand_total";
        } else {
            $payments = "order.paid_amount AS paid_amount, order.change AS `change`, order.grand_total AS grand_total";
        }
        
        $sql = <<<EOT
SELECT `order`.id, `order`.branch_id, $payments,
    remarks, gift_cert_code, transaction_type, `order`.added_on, `order`.added_by,
    branch.name AS branch_name
FROM `order`
LEFT JOIN branch ON `order`.branch_id = branch.id
WHERE `order`.is_deleted = 0
EOT;
        $binds = [];

        if ($branch_id) {
            $branches = explode(",", $branch_id);
            $sql .= " AND order.branch_id IN ?";
            $binds[] = $branches;
        }

        if ($branch_name) {
            $sql .= " AND branch.name LIKE ?";
            $binds[] = "%$branch_name%";
        }

        if ($added_on_from) {
            $sql .= " AND DATE(order.added_on) >= ?";
            $binds[] = $added_on_from;
        }

        if ($added_on_to) {
            $sql .= " AND DATE(order.added_on) <= ?";
            $binds[] = $added_on_to;
        }

        if ($transaction_type) {
            $sql .= " AND order.transaction_type = ?";
            $binds[] = $transaction_type;
        }

        if ($group_orders) {
            $sql .= " GROUP BY order.branch_id";
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get breakdown qty of an item on order detail and order product detail
     * breakdown_qty = number of product ordered * number of item used in product
     * breakdown_qty = breakdown_qty + number of addon ordered * number of item used in addon
     */
    public function get_item_breakdown_quantity($item_id, $item_unit_id, $branch_id) {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT item.name, 
    (SUM(order_detail.qty * product_item.qty) + SUM(order_product_detail.qty * product_item.qty)) AS breakdown_qty
FROM `order` o
JOIN `order_detail` ON o.id = order_detail.order_id
JOIN `order_product_detail` order_product_detail ON order_detail.id = order_product_detail.order_detail_id
JOIN product_item ON order_product_detail.addon_id = product_item.product_id
JOIN item ON product_item.item_id = item.id
JOIN item_unit ON item.id = item_unit.id
GROUP BY item.id;
EOT;
        $binds = [];

        if ($item_id) {
            $sql .= " AND item.id = ?";
            $binds[] = $item_id;
        }

        if ($item_unit_id) {
            $sql .= " AND item_unit.id = ?";
            $binds[] = $item_unit_id;
        }

        if ($branch_id) {
            $sql .= " AND o.branch_id = ?";
            $binds[] = $branch_id;
        }
    }
}