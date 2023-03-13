<?php

namespace App\Models;

class Order extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'offline_id',
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
     * Get sales per branch orders
     */
    public function get_sales_per_branch($branch_id, $branch_name, $added_on_from, $added_on_to, $transaction_type)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT order_summary.id, order_summary.branch_id,
    SUM(order_summary.paid_amount) AS paid_amount, SUM(order_summary.change) AS `change`, SUM(order_summary.grand_total) AS grand_total,
    order_summary.remarks, order_summary.gift_cert_code,
    IFNULL(SUM(CASE WHEN order_summary.transaction_type = "store" THEN IFNULL(order_summary.grand_total, 0) END), 0) AS store_sales,
    IFNULL(SUM(CASE WHEN order_summary.transaction_type = "foodpanda" THEN IFNULL(order_summary.grand_total, 0) END), 0) AS foodpanda_sales,
    IFNULL(SUM(CASE WHEN order_summary.transaction_type = "grabfood" THEN IFNULL(order_summary.grand_total, 0) END), 0) AS grabfood_sales,
    order_summary.added_on, order_summary.added_by,
    order_summary.branch_name
FROM (SELECT `order`.id, `order`.branch_id,
        SUM(order.paid_amount) AS paid_amount, SUM(order.change) AS `change`, SUM(order.grand_total) AS grand_total,
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

        $sql .= <<<EOT
    GROUP BY `order`.branch_id, `order`.transaction_type) order_summary
GROUP BY order_summary.branch_id
EOT;

        $query = $database->query($sql, $binds);
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
    
}