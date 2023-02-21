<?php

namespace App\Models;

class Supplies_receive extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'branch_id',
        'se_id',
        'supplier_id',
        'vendor_id',
        'purchase_date',
        'supplies_receive_date',
        'type',
        'purpose',
        'forwarder_id',
        'waybill_no',
        'invoice_no',
        'dr_no',
        'subtotal',
        'freight_cost',
        'discount',
        'grand_total',
        'paid_amount',
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
        $this->table = 'supplies_receive';
    }

    /**
     * Get supplies_receive details by ID
     */
    public function get_details_by_id($supplies_receive_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *, 
    (SELECT branch.name FROM branch WHERE branch.id = supplies_receive.branch_id) AS branch_name,
    IF(supplies_receive.balance > 0, 'open', 'closed') as payment_status, 
    (SELECT forwarder.name FROM forwarder WHERE forwarder.id = supplies_receive.forwarder_id) AS forwarder_name, 
    (SELECT supplier.trade_name FROM supplier WHERE supplier.id = supplies_receive.supplier_id) AS supplier_name,
    (SELECT vendor.trade_name FROM vendor WHERE vendor.id = supplies_receive.vendor_id) AS vendor_name,
    (SELECT branch.name FROM branch WHERE branch.id = supplies_receive.branch_id) AS branch_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = supplies_receive.added_by) AS prepared_by,
    (SELECT supplier.contact_person FROM supplier WHERE supplier.id = supplies_receive.supplier_id) AS contact_person,
    (SELECT supplier.phone_no FROM supplier WHERE supplier.id = supplies_receive.supplier_id) AS phone_no,
    CONCAT('Invoice No. ', supplies_receive.invoice_no, ' - ', supplies_receive.grand_total) AS invoice_label 
FROM supplies_receive
WHERE is_deleted = 0
EOT;
        $binds = [];
        if (isset($supplies_receive_id)) {
            $sql .= " AND id = ?";
            $binds[] = $supplies_receive_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
    
    /**
     * Get supplies_receive details by ID
     */
    public function get_by_status($status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *, 
    (SELECT branch.name FROM branch WHERE branch.id = supplies_receive.branch_id) AS branch_name,
    IF(supplies_receive.balance > 0, 'open', 'closed') as payment_status, 
    (SELECT forwarder.name FROM forwarder WHERE forwarder.id = supplies_receive.forwarder_id) AS forwarder_name, 
    (SELECT supplier.trade_name FROM supplier WHERE supplier.id = supplies_receive.supplier_id) AS supplier_name,
    (SELECT vendor.trade_name FROM vendor WHERE vendor.id = supplies_receive.vendor_id) AS vendor_name,
    (SELECT branch.name FROM branch WHERE branch.id = supplies_receive.branch_id) AS branch_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = supplies_receive.added_by) AS prepared_by,
    (SELECT supplier.contact_person FROM supplier WHERE supplier.id = supplies_receive.supplier_id) AS contact_person,
    (SELECT supplier.phone_no FROM supplier WHERE supplier.id = supplies_receive.supplier_id) AS phone_no,
    CONCAT('Invoice No. ', supplies_receive.invoice_no, ' - ', supplies_receive.grand_total) AS invoice_label 
FROM supplies_receive
WHERE supplies_receive.is_deleted = 0
EOT;
        $binds = [];
        if (isset($status)) {
            $sql .= " AND supplies_receive.status = ?";
            $binds[] = $status;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all supplies_receives
     */
    public function get_all_receive($supplier_id, $vendor_id, $bill_type)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *, 
    (SELECT branch.name FROM branch WHERE branch.id = supplies_receive.branch_id) AS branch_name,
    IF(supplies_receive.balance > 0, 'open', 'closed') as payment_status, 
    (SELECT forwarder.name FROM forwarder WHERE forwarder.id = supplies_receive.forwarder_id) AS forwarder_name, 
    (SELECT supplier.trade_name FROM supplier WHERE supplier.id = supplies_receive.supplier_id) AS supplier_name,
    (SELECT vendor.trade_name FROM vendor WHERE vendor.id = supplies_receive.vendor_id) AS vendor_name,
    (SELECT branch.name FROM branch WHERE branch.id = supplies_receive.branch_id) AS branch_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = supplies_receive.added_by) AS prepared_by,
    (SELECT supplier.contact_person FROM supplier WHERE supplier.id = supplies_receive.supplier_id) AS contact_person,
    (SELECT supplier.phone_no FROM supplier WHERE supplier.id = supplies_receive.supplier_id) AS phone_no,
    CONCAT('Invoice No. ', supplies_receive.invoice_no, ' - ', supplies_receive.grand_total) AS invoice_label 
FROM supplies_receive
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
    (SELECT branch.name FROM branch WHERE branch.id = supplies_receive.branch_id) AS branch_name,
    IF(supplies_receive.balance > 0, 'open', 'closed') as payment_status, 
    (SELECT forwarder.name FROM forwarder WHERE forwarder.id = supplies_receive.forwarder_id) AS forwarder_name, 
    (SELECT supplier.trade_name FROM supplier WHERE supplier.id = supplies_receive.supplier_id) AS supplier_name,
    (SELECT vendor.trade_name FROM vendor WHERE vendor.id = supplies_receive.vendor_id) AS vendor_name,
    (SELECT branch.name FROM branch WHERE branch.id = supplies_receive.branch_id) AS branch_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = supplies_receive.added_by) AS prepared_by,
    (SELECT supplier.contact_person FROM supplier WHERE supplier.id = supplies_receive.supplier_id) AS contact_person,
    (SELECT supplier.phone_no FROM supplier WHERE supplier.id = supplies_receive.supplier_id) AS phone_no,
    CONCAT('Invoice No. ', supplies_receive.invoice_no, ' - ', supplies_receive.grand_total) AS invoice_label 
FROM supplies_receive
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
     * Get supplies_receives based on supplies_receive name, contact_person, phone_no, tin_no, bir_no, email
     */
   public function search($branch_id, $se_id, $supplier_id, $vendor_id, $supplies_receive_date, $waybill_no, $invoice_no, $dr_no, $remarks, $purchase_date_from, $purchase_date_to, $se_receive_date_from, $se_receive_date_to, $bill_type)
   {
       $database = \Config\Database::connect();
       $sql = <<<EOT
SELECT *, supplies_receive.se_id AS se_receive_id,
    (SELECT branch.name FROM branch WHERE branch.id = supplies_receive.branch_id) AS branch_name,
    IF(supplies_receive.balance > 0, 'open', 'closed') as payment_status, 
    (SELECT forwarder.name FROM forwarder WHERE forwarder.id = supplies_receive.forwarder_id) AS forwarder_name, 
    (SELECT supplier.trade_name FROM supplier WHERE supplier.id = supplies_receive.supplier_id) AS supplier_name,
    (SELECT vendor.trade_name FROM vendor WHERE vendor.id = supplies_receive.vendor_id) AS vendor_name,
    (SELECT branch.name FROM branch WHERE branch.id = supplies_receive.branch_id) AS branch_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = supplies_receive.added_by) AS prepared_by,
    (SELECT supplier.contact_person FROM supplier WHERE supplier.id = supplies_receive.supplier_id) AS contact_person,
    (SELECT supplier.phone_no FROM supplier WHERE supplier.id = supplies_receive.supplier_id) AS phone_no,
    (SELECT se_bank_slip.reference_no FROM se_bank_slip LEFT JOIN se_bank_entry ON se_bank_slip.id = se_bank_entry.se_bank_slip_id WHERE se_bank_entry.se_id = supplies_receive.se_id) AS reference_no,
    CONCAT('Invoice No. ', supplies_receive.invoice_no, ' - ', supplies_receive.grand_total) AS invoice_label 
FROM supplies_receive
WHERE is_deleted = 0
EOT;

        $binds = [];
        if ($branch_id) {
            $sql .= " AND branch_id = ?";
            $binds[] = $branch_id;
        }

        if ($se_id) {
            $sql .= " AND se_id = ?";
            $binds[] = $se_id;
        }

        if ($supplier_id) {
            $sql .= " AND supplier_id = ?";
            $binds[] = $supplier_id;
        }

        if ($vendor_id) {
            $sql .= " AND vendor_id = ?";
            $binds[] = $vendor_id;
        }

        if ($supplies_receive_date) {
            $sql .= " AND supplies_receive_date = ?";
            $binds[] = $supplies_receive_date;
        }

        if ($waybill_no) {
            $sql .= " AND waybill_no = ?";
            $binds[] = $waybill_no;
        }

        if ($invoice_no) {
            $sql .= " AND invoice_no = ?";
            $binds[] = $invoice_no;
        }

        if ($dr_no) {
            $sql .= " AND dr_no = ?";
            $binds[] = $dr_no;
        }

        if ($remarks) {
            $sql .= " AND remarks REGEXP ?";
            $name    = str_replace(' ', '|', $remarks);
            $binds[] = $remarks;
        }

        if ($purchase_date_from != '') {
            $sql .= ' AND CAST(supplies_receive.purchase_date AS DATE) >= ?';
            $binds[] = $purchase_date_from;
        }

        if ($purchase_date_to != '') {
            $sql .= ' AND CAST(supplies_receive.purchase_date AS DATE) <= ?';
            $binds[] = $purchase_date_to;
        }

        if ($se_receive_date_from != '') {
            $sql .= ' AND CAST(supplies_receive.supplies_receive_date AS DATE) >= ?';
            $binds[] = $se_receive_date_from;
        }

        if ($se_receive_date_to != '') {
            $sql .= ' AND CAST(supplies_receive.supplies_receive_date AS DATE) <= ?';
            $binds[] = $se_receive_date_to;
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
    * Get all receive by purchase order id
    */
    public function get_id_by_se_id($se_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT id, invoice_no
FROM supplies_receive
WHERE se_id = ?
EOT;
        $query = $database->query($sql, [$se_id]);
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
        if ($waybill_no OR $invoice_no OR $dr_no) {
            $sql .= " AND (";
        }

        if ($waybill_no) {
            $sql .= " OR waybill_no = ?";
            $binds[] = $waybill_no;
        }

        if ($invoice_no) {
            $sql .= " OR invoice_no = ?";
            $binds[] = $invoice_no;
        }

        if ($dr_no) {
            $sql .= " OR dr_no = ?";
            $binds[] = $dr_no;
        }

        if ($waybill_no OR $invoice_no OR $dr_no) {
            $sql .= ")";
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}