<?php

namespace App\Models;

class Franchisee_sale_payment extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'franchisee_id',
        'franchisee_sale_id',
        'payment_type',
        'payment_date',
        'remarks',
        'from_bank_id',
        'to_bank_id',
        'to_bank_name',
        'cheque_number',
        'cheque_date',
        'reference_number',
        'transaction_number',
        'payment_description',
        'invoice_no',
        'term_day',
        'delivery_address',
        'paid_amount',
        'grand_total',
        'subtotal',
        'service_fee',
        'delivery_fee',
        'withholding_tax',
        'is_done',
        'deposit_date',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'franchisee_sale_payment';
    }

    /**
     * Get franchisee_sale_payment by ID
     */
    public function get_details_by_id($franchisee_sale_payment_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM franchisee WHERE franchisee.id = franchisee_sale_payment.franchisee_id) AS franchisee_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM employee WHERE employee.id = franchisee_sale_payment.added_by) AS added_by_name,
    (SELECT name FROM bank WHERE bank.id = franchisee_sale_payment.from_bank_id) AS from_bank_name,
    (SELECT name FROM bank WHERE bank.id = franchisee_sale_payment.to_bank_id) AS to_bank_name
FROM franchisee_sale_payment
WHERE franchisee_sale_payment.is_deleted = 0
    AND franchisee_sale_payment.id = ?
EOT;
        $binds = [$franchisee_sale_payment_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all franchisee_sale_payment
     */
    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM franchisee WHERE franchisee.id = franchisee_sale_payment.franchisee_id) AS franchisee_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM employee WHERE employee.id = franchisee_sale_payment.added_by) AS added_by_name,
    (SELECT name FROM bank WHERE bank.id = franchisee_sale_payment.from_bank_id) AS from_bank_name,
    (SELECT name FROM bank WHERE bank.id = franchisee_sale_payment.to_bank_id) AS to_bank_name
FROM franchisee_sale_payment
WHERE is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all franchisee_sale_payment by franchisee_sale_id
     */
    public function get_details_by_franchisee_sales_id($franchisee_sale_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM franchisee WHERE franchisee.id = franchisee_sale_payment.franchisee_id) AS franchisee_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM employee WHERE employee.id = franchisee_sale_payment.added_by) AS added_by_name,
    (SELECT name FROM bank WHERE bank.id = franchisee_sale_payment.from_bank_id) AS from_bank_name,
    (SELECT name FROM bank WHERE bank.id = franchisee_sale_payment.to_bank_id) AS to_bank_name
FROM franchisee_sale_payment
WHERE franchisee_sale_payment.is_deleted = 0
    AND franchisee_sale_payment.franchisee_sale_id = ?
EOT;
        $binds = [$franchisee_sale_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Search
     */
    public function search($franchisee_id, $franchisee_sale_id, $payment_method, $payment_date_from, $payment_date_to, $from_bank_id, $cheque_number, $cheque_date_from, $cheque_date_to, $reference_number, $transaction_number, $branch_name)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM franchisee WHERE franchisee.id = franchisee_sale_payment.franchisee_id) AS franchisee_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM employee WHERE employee.id = franchisee_sale_payment.added_by) AS added_by_name,
    (SELECT name FROM bank WHERE bank.id = franchisee_sale_payment.from_bank_id) AS from_bank_name,
    (SELECT name FROM bank WHERE bank.id = franchisee_sale_payment.to_bank_id) AS to_bank_name
FROM franchisee_sale_payment
WHERE franchisee_sale_payment.is_deleted = 0
EOT;
        $binds = [];

        if ($franchisee_id) {
            $sql .= ' AND franchisee_sale_payment.franchisee_id = ?';
            $binds[] = $franchisee_id;
        }

        if ($franchisee_sale_id) {
            $sql .= ' AND franchisee_sale_payment.franchisee_sale_id = ?';
            $binds[] = $franchisee_sale_id;
        }

        if ($payment_method) {
            $sql .= ' AND franchisee_sale_payment.payment_method = ?';
            $binds[] = $payment_method;
        }

        if ($payment_date_from) {
            $sql .= ' AND franchisee_sale_payment.payment_date >= ?';
            $binds[] = $payment_date_from;
        }

        if ($payment_date_to) {
            $sql .= ' AND franchisee_sale_payment.payment_date <= ?';
            $binds[] = $payment_date_to;
        }

        if ($from_bank_id) {
            $sql .= ' AND franchisee_sale_payment.from_bank_id LIKE ?';
            $binds[] = '%' . $from_bank_id . '%';
        }

        if ($cheque_number) {
            $sql .= ' AND franchisee_sale_payment.cheque_number LIKE ?';
            $binds[] = '%' . $cheque_number . '%';
        }

        if ($cheque_date_from) {
            $sql .= ' AND franchisee_sale_payment.cheque_date >= ?';
            $binds[] = $cheque_date_from;
        }

        if ($cheque_date_to) {
            $sql .= ' AND franchisee_sale_payment.cheque_date <= ?';
            $binds[] = $cheque_date_to;
        }

        if ($reference_number) {
            $sql .= ' AND franchisee_sale_payment.reference_number LIKE ?';
            $binds[] = '%' . $reference_number . '%';
        }

        if ($transaction_number) {
            $sql .= ' AND franchisee_sale_payment.transaction_number LIKE ?';
            $binds[] = '%' . $transaction_number . '%';
        }

        if ($branch_name) {
            $sql .= ' AND (SELECT name FROM branch WHERE branch.id = (SELECT branch_id FROM franchisee WHERE franchisee.id = franchisee_sale_payment.franchisee_id)) LIKE ?';
            $binds[] = '%' . $branch_name . '%';
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get payments with franchise sale details
     */
    public function get_payment_with_sale_details($franchisee_id, $franchisee_name, $branch_id, $date_from, $date_to)
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
SELECT franchisee_sale.id, franchisee_sale_payment.payment_date, franchisee.name AS franchisee_name, branch.name AS branch_name, 'Invoice' AS doc_type,
    '0.00' AS royalty_fee,
    '0.00' AS marketing_fee,
    franchisee_sale.grand_total AS sales_invoice,
    franchisee_sale_payment.deposit_date,
    bank.name AS deposit_to,
    franchisee_sale_payment.cheque_number,
    franchisee_sale_payment.cheque_date,
    franchisee_sale_payment.reference_number,
    franchisee_sale_payment.payment_type AS pay_mode
FROM franchisee_sale_payment
LEFT JOIN franchisee_sale
    ON franchisee_sale_payment.franchisee_sale_id = franchisee_sale.id
LEFT JOIN franchisee
    ON franchisee.id = franchisee_sale.franchisee_id
LEFT JOIN branch
    ON branch.id = franchisee_sale.buyer_branch_id
LEFT JOIN bank
    ON bank.id = franchisee_sale_payment.to_bank_id
WHERE franchisee_sale.is_deleted = 0
    AND franchisee_sale_payment.is_deleted = 0
EOT;
        $binds = [];

        if ($franchisee_id) {
            $sql .= ' AND franchisee_sale.franchisee_id = ?';
            $binds[] = $franchisee_id;
        }

        if ($franchisee_name) {
            $sql .= ' AND franchisee.name LIKE ?';
            $binds[] = "%" . $franchisee_name . "%";
        }

        if ($branch_id) {
            $sql .= ' AND franchisee_sale.buyer_branch_id = ?';
            $binds[] = $branch_id;
        }

        if ($date_from) {
            $sql .= ' AND franchisee_sale_payment.payment_date >= ?';
            $binds[] = $date_from;
        }

        if ($date_to) {
            $sql .= ' AND franchisee_sale_payment.payment_date <= ?';
            $binds[] = $date_to;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}