<?php

namespace App\Models;

class Purchase extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'branch_id',
        'supplier_id',
        'vendor_id',
        'forwarder_id',
        'purchase_date',
        'delivery_date',
        'delivery_address',
        'grand_total',
        'remarks',
        'requisitioner',
        'status',
        'is_closed',
        'order_status',
        'service_fee',
        'discount',
        'closed_by',
        'closed_on',
        'authorized_by',
        'authorized_on',
        'recommended_by',
        'recommended_on',
        'approved_by',
        'approved_on',
        'disapproved_by',
        'disapproved_on',
        'printed_by',
        'printed_on',
        'sent_by',
        'sent_on',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted',
        'with_payment'
    ];

    public function __construct()
    {
        $this->table = 'purchase';
    }

    /**
     * Get purchase details by ID
     */
    public function get_details_by_id($purchase_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT purchase.*, 
    (SELECT forwarder.name FROM forwarder WHERE forwarder.id = purchase.forwarder_id) AS forwarder_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = purchase.approved_by) AS approved_by_name, 
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = purchase.printed_by) AS printed_by_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = purchase.added_by) AS added_by_name, 
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = purchase.updated_by) AS updated_by_name, 
    (SELECT supplier.trade_name FROM supplier WHERE supplier.id = purchase.supplier_id) AS supplier_trade_name,
    (SELECT branch.name FROM branch WHERE branch.id = purchase.branch_id) AS branch_name,
    (SELECT IF(SUM(receive.paid_amount) >= grand_total, 'closed', 'open') FROM receive WHERE receive.po_id = purchase.id) AS payment_status,
    (SELECT SUM(receive_item.qty) FROM receive_item LEFT JOIN receive ON receive_item.receive_id = receive.id WHERE receive.id = purchase.id) AS total_received_qty,
    (SELECT vendor.trade_name FROM vendor WHERE vendor.id = purchase.vendor_id) AS vendor_trade_name,
    (SELECT CONCAT(employee.first_name, ' ', employee.last_name) FROM employee WHERE employee.id = purchase.requisitioner) AS requisitioner_name,
    (SELECT supplier.email FROM supplier WHERE supplier.id = purchase.supplier_id) AS supplier_email,
    (SELECT vendor.email FROM vendor WHERE vendor.id = purchase.vendor_id) AS vendor_email
FROM purchase
WHERE purchase.id = ?
    AND purchase.is_deleted = 0
EOT;
        $binds = [$purchase_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
    
    /**
     * Get purchase details by ID
     */
    public function filter_purchase_status($status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT purchase.*, 
    (SELECT forwarder.name FROM forwarder WHERE forwarder.id = purchase.forwarder_id) AS forwarder_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = purchase.approved_by) AS approved_by_name, 
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = purchase.printed_by) AS printed_by_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = purchase.added_by) AS added_by_name, 
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = purchase.updated_by) AS updated_by_name, 
    (SELECT supplier.trade_name FROM supplier WHERE supplier.id = purchase.supplier_id) AS supplier_trade_name,
    (SELECT branch.name FROM branch WHERE branch.id = purchase.branch_id) AS branch_name,
    (SELECT IF(SUM(receive.paid_amount) >= grand_total, 'closed', 'open') FROM receive WHERE receive.po_id = purchase.id) AS payment_status,
    (SELECT SUM(receive_item.qty) FROM receive_item LEFT JOIN receive ON receive_item.receive_id = receive.id WHERE receive.id = purchase.id) AS total_received_qty,
    (SELECT vendor.trade_name FROM vendor WHERE vendor.id = purchase.vendor_id) AS vendor_trade_name,
    (SELECT CONCAT(employee.first_name, ' ', employee.last_name) FROM employee WHERE employee.id = purchase.requisitioner) AS requisitioner_name
FROM purchase
WHERE purchase.status = ?
    AND purchase.is_deleted = 0
EOT;
        $binds = [$status];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Filter by order status
     */
    public function filter_order_status($status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT purchase.*, 
    (SELECT forwarder.name FROM forwarder WHERE forwarder.id = purchase.forwarder_id) AS forwarder_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = purchase.approved_by) AS approved_by_name, 
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = purchase.printed_by) AS printed_by_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = purchase.added_by) AS added_by_name, 
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = purchase.updated_by) AS updated_by_name, 
    (SELECT supplier.trade_name FROM supplier WHERE supplier.id = purchase.supplier_id) AS supplier_trade_name,
    (SELECT branch.name FROM branch WHERE branch.id = purchase.branch_id) AS branch_name,
    (SELECT IF(SUM(receive.paid_amount) >= grand_total, 'closed', 'open') FROM receive WHERE receive.po_id = purchase.id) AS payment_status,
    (SELECT SUM(receive_item.qty) FROM receive_item LEFT JOIN receive ON receive_item.receive_id = receive.id WHERE receive.id = purchase.id) AS total_received_qty,
    (SELECT vendor.trade_name FROM vendor WHERE vendor.id = purchase.vendor_id) AS vendor_trade_name,
    (SELECT CONCAT(employee.first_name, ' ', employee.last_name) FROM employee WHERE employee.id = purchase.requisitioner) AS requisitioner_name
FROM purchase
WHERE purchase.order_status = ?
    AND purchase.is_deleted = 0
EOT;
        $binds = [$status];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all purchases
     */
    public function get_all_purchase()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT purchase.*, 
    (SELECT forwarder.name FROM forwarder WHERE forwarder.id = purchase.forwarder_id) AS forwarder_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = purchase.approved_by) AS approved_by_name, 
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = purchase.printed_by) AS printed_by_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = purchase.added_by) AS added_by_name, 
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = purchase.updated_by) AS updated_by_name, 
    (SELECT supplier.trade_name FROM supplier WHERE supplier.id = purchase.supplier_id) AS supplier_trade_name,
    (SELECT branch.name FROM branch WHERE branch.id = purchase.branch_id) AS branch_name,
    (SELECT IF(SUM(receive.paid_amount) >= grand_total, 'closed', 'open') FROM receive WHERE receive.po_id = purchase.id) AS payment_status,
    (SELECT SUM(receive_item.qty) FROM receive_item LEFT JOIN receive ON receive_item.receive_id = receive.id WHERE receive.id = purchase.id) AS total_received_qty,
    (SELECT vendor.trade_name FROM vendor WHERE vendor.id = purchase.vendor_id) AS vendor_trade_name,
    (SELECT CONCAT(employee.first_name, ' ', employee.last_name) FROM employee WHERE employee.id = purchase.requisitioner) AS requisitioner_name
FROM purchase
WHERE purchase.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get purchases based on purchase name, contact_person, phone_no, tin_no, bir_no, email
     * can_be_paid - used by add payment function, this avoid overpaying
     */
    public function search($branch_id, $supplier_id, $vendor_id, $forwarder_id, $purchase_date, $delivery_date, $delivery_address, $remarks, $purpose, $requisitioner, $status, $order_status, $purchase_date_from, $purchase_date_to, $delivery_date_from, $delivery_date_to, $limit_by, $anything)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT purchase.*, 
    forwarder.name AS forwarder_name,
    CONCAT(approver.first_name, ' ', approver.last_name) AS approved_by_name,
    CONCAT(printer.first_name, ' ', printer.last_name) AS printed_by_name,
    CONCAT(adder.first_name, ' ', adder.last_name) AS added_by_name,
    CONCAT(updater.first_name, ' ', updater.last_name) AS updated_by_name,
    supplier.trade_name AS supplier_trade_name,
    branch.name AS branch_name,
    (SELECT IF(SUM(receive.paid_amount) >= grand_total, 'closed', 'open') FROM receive WHERE receive.po_id = purchase.id) AS payment_status,
    (SELECT SUM(receive_item.qty) FROM receive_item LEFT JOIN receive ON receive_item.receive_id = receive.id WHERE receive.id = purchase.id) AS total_received_qty,
    vendor.trade_name AS vendor_trade_name,
    CONCAT(requisitioner.first_name, ' ', requisitioner.last_name) AS requisitioner_name,
    IF(purchase_payment.total_payment >= purchase.grand_total, 1, 0) AS can_be_paid
FROM purchase
LEFT JOIN forwarder ON forwarder.id = purchase.forwarder_id
LEFT JOIN user AS approver ON approver.id = purchase.approved_by
LEFT JOIN user AS printer ON printer.id = purchase.printed_by
LEFT JOIN user AS adder ON adder.id = purchase.added_by
LEFT JOIN user AS updater ON updater.id = purchase.updated_by
LEFT JOIN supplier ON supplier.id = purchase.supplier_id
LEFT JOIN branch ON branch.id = purchase.branch_id
LEFT JOIN vendor ON vendor.id = purchase.vendor_id
LEFT JOIN employee AS requisitioner ON requisitioner.id = purchase.requisitioner
LEFT JOIN purchase_payment ON purchase_payment.purchase_id = purchase.id
WHERE purchase.is_deleted = 0
EOT;
        $binds = [];

        if ($branch_id) {
            $sql .= ' AND purchase.branch_id = ?';
            $binds[] = $branch_id;
        }

        if ($supplier_id) {
            $sql .= ' AND purchase.supplier_id = ?';
            $binds[] = $supplier_id;
        }

        if ($vendor_id) {
            $sql .= ' AND purchase.vendor_id = ?';
            $binds[] = $vendor_id;
        }

        if ($forwarder_id) {
            $sql .= ' AND purchase.forwarder_id = ?';
            $binds[] = $forwarder_id;
        }

        if ($purchase_date) {
            $sql .= ' AND CAST(purchase.purchase_date AS DATE) = ?';
            $binds[] = $purchase_date;
        }

        if ($delivery_date) {
            $sql .= ' AND CAST(purchase.delivery_date AS DATE) = ?';
            $binds[] = $delivery_date;
        }

        if ($delivery_address) {
            $sql .= ' AND purchase.delivery_address LIKE ?';
            $binds[] = $delivery_address;
        }

        if ($remarks) {
            $sql .= ' AND purchase.remarks LIKE ?';
            $binds[] = $remarks;
        }

        if ($purpose) {
            $sql .= ' AND purchase.purpose LIKE ?';
            $binds[] = $purpose;
        }

        if ($requisitioner) {
            $sql .= ' AND purchase.requisitioner LIKE ?';
            $binds[] = $requisitioner;
        }

        if ($status) {
            $sql .= ' AND purchase.status = ? AND purchase.order_status = "pending"';
            $binds[] = $status;
        }

        if ($order_status) {
            $sql .= ' AND purchase.order_status = ? AND (purchase.status = "sent" OR purchase.status = "printed")';
            $binds[] = $order_status;
        }

        if ($purchase_date_from) {
            $sql .= ' AND CAST(purchase.purchase_date AS DATE) >= ?';
            $binds[] = $purchase_date_from;
        }

        if ($purchase_date_to) {
            $sql .= ' AND CAST(purchase.purchase_date AS DATE) <= ?';
            $binds[] = $purchase_date_to;
        }

        if ($anything) {
            $sql .= ' AND (purchase.delivery_address LIKE ? OR purchase.remarks LIKE ? OR purchase.requisitioner LIKE ? OR purchase.status LIKE ? OR purchase.order_status LIKE ? OR forwarder.name LIKE ? OR approver.first_name LIKE ? OR approver.last_name LIKE ? OR printer.first_name LIKE ? OR printer.last_name LIKE ? OR adder.first_name LIKE ? OR adder.last_name LIKE ? OR updater.first_name LIKE ? OR updater.last_name LIKE ? OR supplier.trade_name LIKE ? OR branch.name LIKE ? OR vendor.trade_name LIKE ? OR requisitioner.first_name LIKE ? OR requisitioner.last_name LIKE ?)';
            $new_binds = [];
            for ($i = 0; $i < 19; $i++) {
                $new_binds[] = '%' . $anything . '%';
            }
            $binds = array_merge($binds, $new_binds);
        }

        $sql .= ' ORDER BY purchase.added_on DESC';

        if ($limit_by) {
            $sql .= ' LIMIT ?';
            $binds[] = (int)$limit_by;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}