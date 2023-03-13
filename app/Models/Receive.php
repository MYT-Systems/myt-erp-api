<?php

namespace App\Models;

class Receive extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'po_id',
        'branch_id',
        'supplier_id',
        'vendor_id',
        'purchase_date',
        'receive_date',
        'purpose',
        'forwarder_id',
        'waybill_no',
        'invoice_no',
        'dr_no',
        'subtotal',
        'freight_cost',
        'service_fee',
        'discount',
        'grand_total',
        'paid_amount',
        'is_closed',
        'balance',
        'remarks',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'receive';
    }

    /**
     * Get receive details by ID
     */
    public function get_details_by_id($receive_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *, 
    IF(receive.balance > 0, 'open', 'closed') as payment_status, 
    (SELECT forwarder.name FROM forwarder WHERE forwarder.id = receive.forwarder_id) AS forwarder_name, 
    (SELECT branch.name FROM branch WHERE branch.id = receive.branch_id) AS branch_name,
    (SELECT supplier.trade_name FROM supplier WHERE supplier.id = receive.supplier_id) AS supplier_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = receive.added_by) AS prepared_by,
    CONCAT('Invoice No. ', receive.invoice_no, ' - ', receive.grand_total) AS invoice_label,
    (SELECT vendor.trade_name FROM vendor WHERE vendor.id = receive.vendor_id) AS vendor_name
FROM receive
WHERE is_deleted = 0
EOT;
        $binds = [];
        if (isset($receive_id)) {
            $sql .= " AND id = ?";
            $binds[] = $receive_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
    
    /**
     * Get receive details by ID
     */
    public function get_by_status($status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *, 
    IF(receive.balance > 0, 'open', 'closed') as payment_status, 
    (SELECT forwarder.name FROM forwarder WHERE forwarder.id = receive.forwarder_id) AS forwarder_name, 
    (SELECT branch.name FROM branch WHERE branch.id = receive.branch_id) AS branch_name,
    (SELECT supplier.trade_name FROM supplier WHERE supplier.id = receive.supplier_id) AS supplier_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = receive.added_by) AS prepared_by,
    CONCAT('Invoice No. ', receive.invoice_no, ' - ', receive.grand_total) AS invoice_label,
    (SELECT vendor.trade_name FROM vendor WHERE vendor.id = receive.vendor_id) AS vendor_name
FROM receive
WHERE receive.is_deleted = 0
EOT;
        $binds = [];
        if (isset($status)) {
            $sql .= " AND receive.status = ?";
            $binds[] = $status;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all receives
     */
    public function get_all_receive($supplier_id, $vendor_id, $bill_type)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *, 
    IF(receive.balance > 0, 'open', 'closed') as payment_status, 
    (SELECT forwarder.name FROM forwarder WHERE forwarder.id = receive.forwarder_id) AS forwarder_name, 
    (SELECT branch.name FROM branch WHERE branch.id = receive.branch_id) AS branch_name,
    (SELECT supplier.trade_name FROM supplier WHERE supplier.id = receive.supplier_id) AS supplier_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = receive.added_by) AS prepared_by,
    CONCAT('Invoice No. ', receive.invoice_no, ' - ', receive.grand_total) AS invoice_label,
    (SELECT vendor.trade_name FROM vendor WHERE vendor.id = receive.vendor_id) AS vendor_name
FROM receive
WHERE is_deleted = 0
EOT;

        $binds = [];
        if ($supplier_id) {
            $sql .= " AND supplier_id = ?";
            $binds[] = $supplier_id;
        }

        if ($vendor_id) {
            $sql .= " AND vendor_id = ?";
            $binds[] = $vendor_id;
        }

        switch ($bill_type) {
            case 'open':
                $sql .= " AND balance > 0";
                break;
            case 'close':
                $sql .= " AND balance < 1";
                break;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get bills
     */

    public function get_bills($type = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *, 
    IF(receive.balance > 0, 'open', 'closed') as payment_status, 
    (SELECT forwarder.name FROM forwarder WHERE forwarder.id = receive.forwarder_id) AS forwarder_name, 
    (SELECT branch.name FROM branch WHERE branch.id = receive.branch_id) AS branch_name,
    (SELECT supplier.trade_name FROM supplier WHERE supplier.id = receive.supplier_id) AS supplier_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = receive.added_by) AS prepared_by,
    CONCAT('Invoice No. ', receive.invoice_no, ' - ', receive.grand_total) AS invoice_label,
    (SELECT vendor.trade_name FROM vendor WHERE vendor.id = receive.vendor_id) AS vendor_name
FROM receive
WHERE is_deleted = 0
EOT;
        $binds = [];
        if (isset($type) AND $type == 'open') {
            $sql .= " AND grand_total - paid_amount > 0";

        } elseif (isset($type) AND $type == 'close') {
            $sql .= " AND grand_total - paid_amount <= 0";
        }


        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get receives based on receive name, contact_person, phone_no, tin_no, bir_no, email
     */
   public function search($po_id, $branch_id, $supplier_id, $vendor_id, $receive_date, $waybill_no, $invoice_no, $dr_no, $remarks, $receive_date_to, $receive_date_from, $payment_status)
   {
       $database = \Config\Database::connect();
       $sql = <<<EOT
SELECT *, 
    (SELECT forwarder.name FROM forwarder WHERE forwarder.id = receive.forwarder_id) AS forwarder_name, 
    (SELECT branch.name FROM branch WHERE branch.id = receive.branch_id) AS branch_name,
    (SELECT supplier.trade_name FROM supplier WHERE supplier.id = receive.supplier_id) AS supplier_name,
    (SELECT vendor.trade_name FROM vendor WHERE vendor.id = receive.vendor_id) AS vendor_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = receive.added_by) AS prepared_by,
    CONCAT('Invoice No. ', receive.invoice_no, ' - ', receive.grand_total) AS invoice_label,
    IF(receive.paid_amount > grand_total, 'overpaid', IF(receive.paid_amount < grand_total, 'open', 'closed')) AS payment_status,
    receive.paid_amount - receive.grand_total AS overpaid_amount
FROM receive
WHERE receive.is_deleted = 0
EOT;

        $binds = [];
        if ($po_id) {
            $sql .= " AND receive.po_id = ?";
            $binds[] = $po_id;
        }

        if ($branch_id) {
            $sql .= " AND receive.branch_id = ?";
            $binds[] = $branch_id;
        }

        if ($supplier_id) {
            $sql .= " AND receive.supplier_id = ?";
            $binds[] = $supplier_id;
        }

        if ($vendor_id) {
            $sql .= " AND receive.vendor_id = ?";
            $binds[] = $vendor_id;
        }

        if ($receive_date) {
            $sql .= " AND receive.receive_date = ?";
            $binds[] = $receive_date;
        }

        if ($waybill_no) {
            $sql .= " AND receive.waybill_no LIKE ?";
            $binds[] = "%$waybill_no%";
        }

        if ($invoice_no) {
            $sql .= " AND receive.invoice_no LIKE ?";
            $binds[] = "%$invoice_no%";
        }

        if ($dr_no) {
            $sql .= " AND receive.dr_no LIKE ?";
            $binds[] = "%$dr_no%";
        }

        if ($remarks) {
            $sql .= " AND receive.remarks LIKE ?";
            $binds[] = "%$remarks%";
        }

        if ($receive_date_to) {
            $sql .= " AND receive.receive_date <= ?";
            $binds[] = $receive_date_to;
        }

        if ($receive_date_from) {
            $sql .= " AND receive.receive_date >= ?";
            $binds[] = $receive_date_from;
        }

        if ($payment_status) {
            if ($payment_status == 'open') {
                $sql .= " AND receive.balance > 0";
            } elseif ($payment_status == 'closed') {
                $sql .= " AND receive.balance < 1 || receive.is_closed = 1";
            } elseif ($payment_status == 'overpaid') {
                $sql .= " AND receive.paid_amount > receive.grand_total AND receive.is_closed = 0";
            }
        }
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
   }

   /**
    * Check duplicate invoice, waybill, or DR numbers
    */
    public function check_duplicate_invoice($waybill_no, $invoice_no, $dr_no)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM receive
WHERE is_deleted = 0
EOT;
        
        $binds = [];
        $conditions = [];

        if ($waybill_no) {
            $conditions[] = "waybill_no = ?";
            $binds[] = $waybill_no;
        }

        if ($invoice_no) {
            $conditions[] = "invoice_no = ?";
            $binds[] = $invoice_no;
        }

        if ($dr_no) {
            $conditions[] = "dr_no = ?";
            $binds[] = $dr_no;
        }

        if ($conditions) {
            $conditions = implode(" OR ", $conditions);
            $conditions = trim($conditions);
            $sql .= (" AND (" . $conditions . ")");
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

   /**
    * Get all receive by purchase order id
    */
    public function get_id_by_po_id($po_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT id, invoice_no
FROM receive
WHERE po_id = ?
EOT;
        $query = $database->query($sql, [$po_id]);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get summary of purchased items
     */
    public function get_summary_of_purchased_item($item_id, $item_name, $purchase_date_from, $purchase_date_to, $receive_date_from, $receive_date_to)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT
    receive.po_id AS po_id,
    item.id AS item_id,
    item.name AS item_name,
    receive.receive_date,
    receive.purchase_date,
    receive.invoice_no,
    receive.waybill_no,
    receive.dr_no,
    supplier.trade_name AS supplier_name,
    vendor.trade_name AS vendor_name,
    receive_item.qty AS quantity,
    receive_item.price,
    receive_item.unit,
    receive_item.type,
    receive_item.total AS total_amount
FROM receive_item
INNER JOIN receive ON receive.id = receive_item.receive_id
INNER JOIN item ON item.id = receive_item.item_id
LEFT JOIN supplier ON supplier.id = receive.supplier_id
LEFT JOIN vendor ON vendor.id = receive.vendor_id
WHERE receive_item.is_deleted = 0
EOT;
        $binds = [];

        if ($item_id) {
            $sql .= " AND receive_item.item_id = ?";
            $binds[] = $item_id;
        }

        if ($item_name) {
            $sql .= " AND item.name LIKE ?";
            $binds[] = "%$item_name%";
        }

        if ($purchase_date_from) {
            $sql .= " AND receive.purchase_date >= ?";
            $binds[] = $purchase_date_from;
        }

        if ($purchase_date_to) {
            $sql .= " AND receive.purchase_date <= ?";
            $binds[] = $purchase_date_to;
        }

        if ($receive_date_from) {
            $sql .= " AND receive.receive_date >= ?";
            $binds[] = $receive_date_from;
        }

        if ($receive_date_to) {
            $sql .= " AND receive.receive_date <= ?";
            $binds[] = $receive_date_to;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}