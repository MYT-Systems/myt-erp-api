<?php

namespace App\Models;

class Project_invoice_payment extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'project_id',
        'project_invoice_id',
        'payment_type',
        'payment_date',
        'remarks',
        'branch',
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
        $this->table = 'project_invoice_payment';
    }

    /**
     * Get project_invoice_payment by ID
     */
    public function get_details_by_id($project_invoice_payment_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM project WHERE project.id = project_invoice_payment.project_id) AS project_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM employee WHERE employee.id = project_invoice_payment.added_by) AS added_by_name,
    (SELECT name FROM bank WHERE bank.id = project_invoice_payment.from_bank_id) AS from_bank_name,
    (SELECT name FROM bank WHERE bank.id = project_invoice_payment.to_bank_id) AS to_bank_name
FROM project_invoice_payment
WHERE project_invoice_payment.is_deleted = 0
    AND project_invoice_payment.id = ?
EOT;
        $binds = [$project_invoice_payment_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all project_invoice_payment
     */
    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM project WHERE project.id = project_invoice_payment.project_id) AS project_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM employee WHERE employee.id = project_invoice_payment.added_by) AS added_by_name,
    (SELECT name FROM bank WHERE bank.id = project_invoice_payment.from_bank_id) AS from_bank_name,
    (SELECT name FROM bank WHERE bank.id = project_invoice_payment.to_bank_id) AS to_bank_name
FROM project_invoice_payment
WHERE is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all project_invoice_payment by project_invoice_id
     */
    public function get_details_by_project_invoices_id($project_invoice_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT 
    project_invoice_payment.*, 
    project.name AS project_name,
    CONCAT(user.first_name, ' ', user.last_name) AS added_by_name,
    (SELECT name FROM bank WHERE bank.id = project_invoice_payment.from_bank_id) AS from_bank_name,
    (SELECT name FROM bank WHERE bank.id = project_invoice_payment.to_bank_id) AS to_bank_name
FROM project_invoice_payment
LEFT JOIN project ON project.id = project_invoice_payment.project_id
LEFT JOIN user ON user.id = project_invoice_payment.added_by
WHERE project_invoice_payment.is_deleted = 0
    AND project_invoice_payment.project_invoice_id = ?;
EOT;
        $binds = [$project_invoice_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
    * get balance from project_invoice
    */
    public function get_balance($project_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT project_invoice_payment.*, 
    project.name AS project_name, 
    customer.name AS customer_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM employee WHERE employee.id = project_invoice_payment.added_by) AS added_by_name,
    (SELECT name FROM bank WHERE bank.id = project_invoice_payment.from_bank_id) AS from_bank_name,
    (SELECT name FROM bank WHERE bank.id = project_invoice_payment.to_bank_id) AS to_bank_name
FROM project_invoice_payment
LEFT JOIN project ON project.id = project_invoice_payment.project_id
LEFT JOIN customer ON customer.id = project.customer_id
WHERE project_invoice_payment.is_deleted = 0
EOT;
        $binds = [];

        if ($project_id) {
            $sql .= ' AND project_invoice_payment.project_id = ?';
            $binds[] = $project_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Search
     */
    public function search($project_id, $customer_id, $project_invoice_id, $payment_method, $deposit_date_from, $deposit_date_to, $from_bank_id, $cheque_number, $cheque_date_from, $cheque_date_to, $reference_number, $transaction_number, $branch_name, $date_from, $date_to)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT project_invoice_payment.*, 
    project.name AS project_name, 
    customer.name AS customer_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM employee WHERE employee.id = project_invoice_payment.added_by) AS added_by_name,
    (SELECT name FROM bank WHERE bank.id = project_invoice_payment.from_bank_id) AS from_bank_name,
    (SELECT name FROM bank WHERE bank.id = project_invoice_payment.to_bank_id) AS to_bank_name
FROM project_invoice_payment
LEFT JOIN project ON project.id = project_invoice_payment.project_id
LEFT JOIN customer ON customer.id = project.customer_id
WHERE project_invoice_payment.is_deleted = 0
EOT;
        $binds = [];

        if ($project_id) {
            $sql .= ' AND project_invoice_payment.project_id = ?';
            $binds[] = $project_id;
        }

        if ($customer_id) {
            $sql .= ' AND customer.id = ?';
            $binds[] = $customer_id;
        }

        if ($project_invoice_id) {
            $sql .= ' AND project_invoice_payment.project_invoice_id = ?';
            $binds[] = $project_invoice_id;
        }

        if ($payment_method) {
            $sql .= ' AND project_invoice_payment.payment_method = ?';
            $binds[] = $payment_method;
        }

        if ($deposit_date_from) {
            $sql .= ' AND project_invoice_payment.deposit_date >= ?';
            $binds[] = $payment_date_from;
        }

        if ($deposit_date_to) {
            $sql .= ' AND project_invoice_payment.deposit_date <= ?';
            $binds[] = $payment_date_to;
        }

        if ($from_bank_id) {
            $sql .= ' AND project_invoice_payment.from_bank_id LIKE ?';
            $binds[] = '%' . $from_bank_id . '%';
        }

        if ($cheque_number) {
            $sql .= ' AND project_invoice_payment.cheque_number LIKE ?';
            $binds[] = '%' . $cheque_number . '%';
        }

        if ($cheque_date_from) {
            $sql .= ' AND project_invoice_payment.cheque_date >= ?';
            $binds[] = $cheque_date_from;
        }

        if ($cheque_date_to) {
            $sql .= ' AND project_invoice_payment.cheque_date <= ?';
            $binds[] = $cheque_date_to;
        }

        if ($reference_number) {
            $sql .= ' AND project_invoice_payment.reference_number LIKE ?';
            $binds[] = '%' . $reference_number . '%';
        }

        if ($transaction_number) {
            $sql .= ' AND project_invoice_payment.transaction_number LIKE ?';
            $binds[] = '%' . $transaction_number . '%';
        }

        if ($branch_name) {
            $sql .= ' AND (SELECT name FROM branch WHERE branch.id = (SELECT project_id FROM project WHERE project.id = project_invoice_payment.project_id)) LIKE ?';
            $binds[] = '%' . $branch_name . '%';
        }

        if ($date_from && $date_to) {
            $sql .= ' AND project_invoice_payment.deposit_date BETWEEN ? AND ?';
            $binds[] = $date_from;
            $binds[] = $date_to;
        }

        $sql .= ' ORDER BY project_invoice_payment.payment_date DESC';

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Delete project_invoice_payment by project_invoice_id
     */
    public function delete_by_project_invoice_id($project_invoice_id = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();

        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE project_invoice_payment
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE project_invoice_payment.is_deleted = 0
    AND project_invoice_payment.project_invoice_id = ?
EOT;
        $binds = [$requested_by, $date_now, $project_invoice_id];

        return $database->query($sql, $binds);
    }

    /**
     * Get payments with franchise sale details
     */
    public function get_payment_with_sale_details($project_id, $project_name, $date_from, $date_to, $payment_status)
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
SELECT project_invoice.id, project_invoice_payment.payment_date, project.name AS project_name, branch.name AS branch_name, 'Invoice' AS doc_type,
    '0.00' AS royalty_fee,
    '0.00' AS marketing_fee,
    project_invoice.grand_total AS sales_invoice,
    project_invoice_payment.deposit_date,
    bank.name AS deposit_to,
    project_invoice_payment.cheque_number,
    project_invoice_payment.cheque_date,
    project_invoice_payment.reference_number,
    project_invoice_payment.payment_type AS pay_mode
FROM project_invoice
LEFT JOIN project_invoice_payment
    ON project_invoice_payment.project_invoice_id = project_invoice.id
LEFT JOIN project
    ON project.id = project_invoice.project_id
LEFT JOIN branch
    ON branch.id = project_invoice.buyer_project_id
LEFT JOIN bank
    ON bank.id = project_invoice_payment.to_bank_id
WHERE project_invoice.is_deleted = 0
    AND project_invoice_payment.is_deleted = 0
EOT;
        $binds = [];

        if ($project_id) {
            $sql .= ' AND project_invoice.project_id = ?';
            $binds[] = $project_id;
        }

        if ($project_name) {
            $sql .= ' AND project.name LIKE ?';
            $binds[] = "%" . $project_name . "%";
        }

        if ($project_id) {
            $sql .= ' AND project_invoice.buyer_project_id = ?';
            $binds[] = $project_id;
        }

        if ($date_from) {
            $sql .= ' AND project_invoice_payment.payment_date >= ?';
            $binds[] = $date_from;
        }

        if ($date_to) {
            $sql .= ' AND project_invoice_payment.payment_date <= ?';
            $binds[] = $date_to;
        }

        if ($payment_status) {
            if ($payment_status == 'paid') {
                $sql .= ' AND project_invoice.payment_status = "closed_bill"';
            } else {
                $sql .= ' AND project_invoice.payment_status = "open_bill"';
            }
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}