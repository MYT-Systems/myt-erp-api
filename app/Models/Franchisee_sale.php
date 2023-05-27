<?php

namespace App\Models;

class Franchisee_sale extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'franchisee_id',
        'sales_date',
        'delivery_date',
        'delivery_fee',
        'service_fee',
        'franchise_order_no',
        'transfer_slip_no',
        'order_request_date',
        'seller_branch_id',
        'buyer_branch_id',
        'sales_invoice_no',
        'dr_no',
        'ship_via',
        'charge_invoice_no',
        'collection_invoice_no',
        'address',
        'remarks',
        'sales_staff',
        'grand_total',
        'balance',
        'paid_amount',
        'payment_status',
        'fs_status',
        'fully_paid_on',
        'is_closed',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'franchisee_sale';
    }

    /**
     * Get franchisee_sale by ID
     */
    public function get_details_by_id($franchisee_sale_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM franchisee WHERE franchisee.id = franchisee_sale.franchisee_id) AS franchisee_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE user.id = franchisee_sale.added_by) AS added_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM employee WHERE employee.id = franchisee_sale.sales_staff) AS sales_staff_name,
    (SELECT name FROM branch WHERE branch.id = franchisee_sale.seller_branch_id) AS seller_branch_name,
    (SELECT name FROM branch WHERE branch.id = franchisee_sale.buyer_branch_id) AS buyer_branch_name,
    (SELECT current_credit_limit FROM franchisee WHERE franchisee.id = franchisee_sale.franchisee_id) AS current_credit_limit
FROM franchisee_sale
WHERE franchisee_sale.is_deleted = 0
    AND franchisee_sale.id = ?
EOT;
        $binds = [$franchisee_sale_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all franchisee_sale
     */
    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM franchisee WHERE franchisee.id = franchisee_sale.franchisee_id) AS franchisee_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM employee WHERE employee.id = franchisee_sale.added_by) AS added_by_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM employee WHERE employee.id = franchisee_sale.sales_staff) AS sales_staff_name,
    (SELECT name FROM branch WHERE branch.id = franchisee_sale.seller_branch_id) AS seller_branch_name,
    (SELECT name FROM branch WHERE branch.id = franchisee_sale.buyer_branch_id) AS buyer_branch_name,
    (SELECT current_credit_limit FROM franchisee WHERE franchisee.id = franchisee_sale.franchisee_id) AS current_credit_limit
FROM franchisee_sale
WHERE is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Search
     */
    public function search($franchise_sale_id = null, $franchisee_id = null, $franchisee_name = null, $sales_date_from = null, $sales_date_to = null, $delivery_date_from = null, $delivery_date_to = null, $order_request_date_from = null, $order_request_date_to = null, $seller_branch_id = null, $buyer_branch_id = null, $sales_invoice_no = null, $dr_no = null, $charge_invoice_no = null, $collection_invoice_no = null, $address = null, $remarks = null, $sales_staff = null, $payment_status = null, $status = null, $fully_paid_on  = null, $anything = null, $id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT franchisee_sale.*,
    franchisee.name AS franchisee_name,
    CONCAT(adder.first_name, ' ', adder.last_name) AS added_by_name,
    CONCAT(sales_staff.first_name, ' ', sales_staff.last_name) AS sales_staff_name,
    buyer_branch.name AS buyer_branch_name,
    seller_branch.name AS seller_branch_name,
    franchisee.current_credit_limit,
    IF (franchisee_sale.is_closed = 1, 'closed_bill', IF (franchisee_sale.paid_amount > franchisee_sale.grand_total, 'overpaid', franchisee_sale.payment_status)) AS payment_status
FROM franchisee_sale
LEFT JOIN branch AS buyer_branch ON buyer_branch.id = franchisee_sale.buyer_branch_id
LEFT JOIN branch AS seller_branch ON seller_branch.id = franchisee_sale.seller_branch_id
LEFT JOIN franchisee ON franchisee.id = franchisee_sale.franchisee_id
LEFT JOIN employee AS adder ON adder.id = franchisee_sale.added_by
LEFT JOIN employee AS sales_staff ON sales_staff.id = franchisee_sale.sales_staff
WHERE franchisee_sale.is_deleted = 0
EOT;
        $binds = [];

        if ($franchise_sale_id) {
            $sql .= ' AND franchisee_sale.id = ?';
            $binds[] = $franchise_sale_id;
        }

        if ($franchisee_id) {
            $sql .= ' AND franchisee_sale.franchisee_id = ?';
            $binds[] = $franchisee_id;
        }
        
        if ($franchisee_name) {
            $sql .= ' AND (SELECT name FROM franchisee WHERE franchisee.id = franchisee_sale.franchisee_id) LIKE ?';
            $binds[] = '%' . $franchisee_name . '%';
        }

        if ($sales_date_from) {
            $sql .= ' AND franchisee_sale.sales_date >= ?';
            $binds[] = $sales_date_from;
        }

        if ($sales_date_to) {
            $sql .= ' AND franchisee_sale.sales_date <= ?';
            $binds[] = $sales_date_to;
        }

        if ($delivery_date_from) {
            $sql .= ' AND franchisee_sale.delivery_date >= ?';
            $binds[] = $delivery_date_from;
        }

        if ($delivery_date_to) {
            $sql .= ' AND franchisee_sale.delivery_date <= ?';
            $binds[] = $delivery_date_to;
        }

        if ($order_request_date_from) {
            $sql .= ' AND franchisee_sale.order_request_date >= ?';
            $binds[] = $order_request_date_from;
        }

        if ($order_request_date_to) {
            $sql .= ' AND franchisee_sale.order_request_date <= ?';
            $binds[] = $order_request_date_to;
        }

        if ($seller_branch_id) {
            $sql .= ' AND franchisee_sale.seller_branch_id = ?';
            $binds[] = $seller_branch_id;
        }

        if ($buyer_branch_id) {
            $sql .= ' AND franchisee_sale.buyer_branch_id = ?';
            $binds[] = $buyer_branch_id;
        }

        if ($sales_invoice_no) {
            $sql .= ' AND franchisee_sale.sales_invoice_no = ?';
            $binds[] = $sales_invoice_no;
        }

        if ($dr_no) {
            $sql .= ' AND franchisee_sale.dr_no = ?';
            $binds[] = $dr_no;
        }

        if ($charge_invoice_no) {
            $sql .= ' AND franchisee_sale.charge_invoice_no = ?';
            $binds[] = $charge_invoice_no;
        }

        if ($collection_invoice_no) {
            $sql .= ' AND franchisee_sale.collection_invoice_no = ?';
            $binds[] = $collection_invoice_no;
        }

        if ($address) {
            $sql .= ' AND franchisee_sale.address LIKE ?';
            $binds[] = '%' . $address . '%';
        }

        if ($remarks) {
            $sql .= ' AND franchisee_sale.remarks LIKE ?';
            $binds[] = '%' . $remarks . '%';
        }

        if ($sales_staff) {
            $sql .= ' AND franchisee_sale.sales_staff = ?';
            $binds[] = $sales_staff;
        }

        if ($payment_status == 'overpaid') {
            $sql .= ' AND franchisee_sale.paid_amount > franchisee_sale.grand_total';
            $sql .= ' AND (franchisee_sale.is_closed = 0 OR franchisee_sale.is_closed IS NULL)'; 
        } else if ($payment_status) {
            $sql .= ' AND franchisee_sale.payment_status = ?';
            $binds[] = $payment_status;
        }

        if ($status) {
            $sql .= ' AND franchisee_sale.fs_status = ?';
            $binds[] = $status;
        }

        if ($fully_paid_on) {
            $sql .= ' AND franchisee_sale.fully_paid_on = ?';
            $binds[] = $fully_paid_on;
        }

        if ($id) {
            $sql .= ' AND franchisee_sale.id = ?';
            $binds[] = $id;
        }

        if ($anything) {
            $sql .= ' AND (franchisee_sale.id LIKE ? OR franchisee_sale.sales_invoice_no LIKE ? OR franchisee_sale.dr_no LIKE ? OR franchisee_sale.charge_invoice_no LIKE ? OR franchisee_sale.collection_invoice_no LIKE ? OR franchisee_sale.address LIKE ? OR franchisee_sale.remarks LIKE ? OR franchisee_sale.sales_staff LIKE ? OR franchisee_sale.fs_status LIKE ? OR franchisee_sale.fully_paid_on LIKE ? OR franchisee.name LIKE ? OR buyer_branch.name LIKE ? OR seller_branch.name LIKE ? OR sales_staff.first_name LIKE ? OR sales_staff.last_name LIKE ? OR adder.first_name LIKE ? OR adder.last_name LIKE ?)';
            $new_binds = [];
            for ($i = 0; $i < 17; $i++) {
                $new_binds[] = '%' . $anything . '%';
            }
            $binds = array_merge($binds, $new_binds);
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /*
     *search_franchisee_sale_item
    */
    public function search_franchisee_sale_item($franchisee_name, $item_id, $sales_date_from, $sales_date_to)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT  item.name AS item_name,
        franchisee_sale_item.item_id,
        franchisee_sale_item.item_unit_id,
        item_unit.inventory_unit,
        item_unit.breakdown_unit,
        franchisee_sale.id AS franchisee_sale_id,
        franchisee_sale.sales_date,
        franchisee_sale.sales_invoice_no AS invoice_no,
        franchisee.name AS franchisee_name,
        seller_branch.name AS seller_branch_name,
        buyer_branch.name AS buyer_branch_name,
        franchisee_sale_item.qty AS total_quantity,
        franchisee_sale_item.subtotal AS total_subtotal,
        franchisee_sale_item.price AS average_price,
        franchisee_sale_item.discount AS total_discount
FROM franchisee_sale_item
LEFT JOIN franchisee_sale ON franchisee_sale.id = franchisee_sale_item.franchisee_sale_id
LEFT JOIN franchisee ON franchisee.id = franchisee_sale.franchisee_id
LEFT JOIN branch AS seller_branch ON seller_branch.id = franchisee_sale.seller_branch_id
LEFT JOIN branch AS buyer_branch ON buyer_branch.id = franchisee_sale.buyer_branch_id
LEFT JOIN item ON item.id = franchisee_sale_item.item_id
LEFT JOIN item_unit ON item_unit.id = franchisee_sale_item.item_unit_id
WHERE franchisee_sale_item.is_deleted = 0
EOT;
        $binds = [];

        if ($franchisee_name) {
            $sql .= '  AND franchisee.name LIKE ?';
            $binds[] = '%' . $franchisee_name . '%';
        }

        if ($item_id) {
            $sql .= ' AND franchisee_sale_item.item_id = ?';
            $binds[] = $item_id;
        }

        if ($sales_date_from) {
            $sql .= ' AND franchisee_sale.sales_date >= ?';
            $binds[] = $sales_date_from;
        }

        if ($sales_date_to) {
            $sql .= ' AND franchisee_sale.sales_date <= ?';
            $binds[] = $sales_date_to;
        }

        // $sql .= ' GROUP BY franchisee_sale_item.item_id, franchisee_sale_item.item_unit_id';

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}