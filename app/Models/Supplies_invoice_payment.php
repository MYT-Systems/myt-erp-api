<?php

namespace App\Models;

class Supplies_invoice_payment extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'supplies_expense_id',
        'supplies_receive_id',
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
        $this->table = 'supplies_invoice_payment';
    }

    /**
     * Get project_invoice_payment by ID
     */
    public function get_details_by_id($supplies_invoice_payment_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT trade_name FROM supplier LEFT JOIN supplies_expense ON supplies_expense.supplier_id = supplier.id WHERE supplies_expense.id = supplies_invoice_payment.supplies_expense_id) AS project_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM employee WHERE employee.id = supplies_invoice_payment.added_by) AS added_by_name,
    (SELECT name FROM bank WHERE bank.id = supplies_invoice_payment.from_bank_id) AS from_bank_name,
    (SELECT name FROM bank WHERE bank.id = supplies_invoice_payment.to_bank_id) AS to_bank_name
FROM supplies_invoice_payment
WHERE supplies_invoice_payment.is_deleted = 0
    AND supplies_invoice_payment.id = ?
EOT;
        $binds = [$supplies_invoice_payment_id];

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
SELECT 
    supplies_invoice_payment.*, 
    supplier.trade_name AS project_name,
    CONCAT(employee.first_name, ' ', employee.last_name) AS added_by_name,
    from_bank.name AS from_bank_name,
    to_bank.name AS to_bank_name,
    credit_from.name AS credit_from
FROM supplies_invoice_payment
LEFT JOIN supplies_expense ON supplies_expense.id = supplies_invoice_payment.supplies_expense_id
LEFT JOIN supplier ON supplier.id = supplies_expense.supplier_id
LEFT JOIN employee ON employee.id = supplies_invoice_payment.added_by
LEFT JOIN bank AS from_bank ON from_bank.id = supplies_invoice_payment.from_bank_id
LEFT JOIN bank AS to_bank ON to_bank.id = supplies_invoice_payment.to_bank_id
LEFT JOIN bank AS credit_from ON credit_from.id = supplies_invoice_payment.from_bank_id  -- Changed from to_bank_id to from_bank_id
WHERE supplies_invoice_payment.is_deleted = 0;
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    public function get_details_by_supplies_receive_id($supplies_receive_id = null)
{
    $database = \Config\Database::connect();
    $sql = <<<EOT
SELECT 
    supplies_invoice_payment.*, 
    supplier.trade_name AS project_name,
    CONCAT(employee.first_name, ' ', employee.last_name) AS added_by_name,
    from_bank.name AS from_bank_name,
    to_bank.name AS to_bank_name,
    credit_from.name AS credit_from
FROM supplies_invoice_payment
LEFT JOIN supplies_expense ON supplies_expense.id = supplies_invoice_payment.supplies_expense_id
LEFT JOIN supplier ON supplier.id = supplies_expense.supplier_id
LEFT JOIN employee ON employee.id = supplies_invoice_payment.added_by
LEFT JOIN bank AS from_bank ON from_bank.id = supplies_invoice_payment.from_bank_id
LEFT JOIN bank AS to_bank ON to_bank.id = supplies_invoice_payment.to_bank_id
LEFT JOIN bank AS credit_from ON credit_from.id = supplies_invoice_payment.from_bank_id  -- Changed from to_bank_id to from_bank_id
WHERE supplies_invoice_payment.is_deleted = 0
    AND supplies_invoice_payment.supplies_receive_id = ?
EOT;
    
    $binds = [$supplies_receive_id];
    $query = $database->query($sql, $binds);
    return $query ? $query->getResultArray() : [];
}


    /**
    * get balance from project_invoice
    */
    public function get_balance($project_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT supplies_invoice_payment.*, 
    (SELECT CONCAT(first_name, ' ', last_name) FROM employee WHERE employee.id = project_invoice_payment.added_by) AS added_by_name,
    (SELECT name FROM bank WHERE bank.id = supplies_invoice_payment.from_bank_id) AS from_bank_name,
    (SELECT name FROM bank WHERE bank.id = supplies_invoice_payment.to_bank_id) AS to_bank_name
FROM supplies_invoice_payment
WHERE supplies_invoice_payment.is_deleted = 0
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
    public function search($supplies_expense_id, $supplies_receive_id, $payment_method, $payment_date_from, $payment_date_to, $from_bank_id, $cheque_number, $cheque_date_from, $cheque_date_to, $reference_number, $transaction_number, $branch_name, $date_from, $date_to)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT supplies_invoice_payment.*, 
    (SELECT CONCAT(first_name, ' ', last_name) FROM employee WHERE employee.id = project_invoice_payment.added_by) AS added_by_name,
    (SELECT name FROM bank WHERE bank.id = supplies_invoice_payment.from_bank_id) AS from_bank_name,
    (SELECT name FROM bank WHERE bank.id = supplies_invoice_payment.to_bank_id) AS to_bank_name
FROM supplies_invoice_payment
WHERE supplies_invoice_payment.is_deleted = 0
EOT;
        $binds = [];

        if ($supplies_expense_id) {
            $sql .= ' AND supplies_invoice_payment.supplies_expense_id = ?';
            $binds[] = $project_id;
        }

        if ($supplies_receive_id) {
            $sql .= ' AND supplies_invoice_payment.supplies_receive_id = ?';
            $binds[] = $supplies_receive_id;
        }

        if ($payment_method) {
            $sql .= ' AND supplies_invoice_payment.payment_method = ?';
            $binds[] = $payment_method;
        }

        if ($payment_date_from) {
            $sql .= ' AND supplies_invoice_payment.payment_date >= ?';
            $binds[] = $payment_date_from;
        }

        if ($payment_date_to) {
            $sql .= ' AND supplies_invoice_payment.payment_date <= ?';
            $binds[] = $payment_date_to;
        }

        if ($from_bank_id) {
            $sql .= ' AND supplies_invoice_payment.from_bank_id LIKE ?';
            $binds[] = '%' . $from_bank_id . '%';
        }

        if ($cheque_number) {
            $sql .= ' AND supplies_invoice_payment.cheque_number LIKE ?';
            $binds[] = '%' . $cheque_number . '%';
        }

        if ($cheque_date_from) {
            $sql .= ' AND supplies_invoice_payment.cheque_date >= ?';
            $binds[] = $cheque_date_from;
        }

        if ($cheque_date_to) {
            $sql .= ' AND supplies_invoice_payment.cheque_date <= ?';
            $binds[] = $cheque_date_to;
        }

        if ($reference_number) {
            $sql .= ' AND supplies_invoice_payment.reference_number LIKE ?';
            $binds[] = '%' . $reference_number . '%';
        }

        if ($transaction_number) {
            $sql .= ' AND supplies_invoice_payment.transaction_number LIKE ?';
            $binds[] = '%' . $transaction_number . '%';
        }

        if ($date_from && $date_to) {
            $sql .= <<<EOT

AND supplies_invoice_payment.payment_date BETWEEN ? AND ?
EOT;
            $binds[] = $date_from;
            $binds[] = $date_to;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete project_invoice_payment by project_invoice_id
     */
    public function delete_by_supplies_receive_id($supplies_receive_id = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();

        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE supplies_invoice_payment
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE supplies_invoice_payment.is_deleted = 0
    AND supplies_invoice_payment.supplies_receive_id = ?
EOT;
        $binds = [$requested_by, $date_now, $supplies_receive_id];

        return $database->query($sql, $binds);
    }

//     /**
//      * Get payments with franchise sale details
//      */
//     public function get_payment_with_sale_details($project_id, $project_name, $date_from, $date_to, $payment_status)
//     {
//         $database = \Config\Database::connect();

//         $sql = <<<EOT
// SELECT project_invoice.id, project_invoice_payment.payment_date, project.name AS project_name, branch.name AS branch_name, 'Invoice' AS doc_type,
//     '0.00' AS royalty_fee,
//     '0.00' AS marketing_fee,
//     project_invoice.grand_total AS sales_invoice,
//     project_invoice_payment.deposit_date,
//     bank.name AS deposit_to,
//     project_invoice_payment.cheque_number,
//     project_invoice_payment.cheque_date,
//     project_invoice_payment.reference_number,
//     project_invoice_payment.payment_type AS pay_mode
// FROM project_invoice
// LEFT JOIN project_invoice_payment
//     ON project_invoice_payment.project_invoice_id = project_invoice.id
// LEFT JOIN project
//     ON project.id = project_invoice.project_id
// LEFT JOIN branch
//     ON branch.id = project_invoice.buyer_project_id
// LEFT JOIN bank
//     ON bank.id = project_invoice_payment.to_bank_id
// WHERE project_invoice.is_deleted = 0
//     AND project_invoice_payment.is_deleted = 0
// EOT;
//         $binds = [];

//         if ($project_id) {
//             $sql .= ' AND project_invoice.project_id = ?';
//             $binds[] = $project_id;
//         }

//         if ($project_name) {
//             $sql .= ' AND project.name LIKE ?';
//             $binds[] = "%" . $project_name . "%";
//         }

//         if ($project_id) {
//             $sql .= ' AND project_invoice.buyer_project_id = ?';
//             $binds[] = $project_id;
//         }

//         if ($date_from) {
//             $sql .= ' AND project_invoice_payment.payment_date >= ?';
//             $binds[] = $date_from;
//         }

//         if ($date_to) {
//             $sql .= ' AND project_invoice_payment.payment_date <= ?';
//             $binds[] = $date_to;
//         }

//         if ($payment_status) {
//             if ($payment_status == 'paid') {
//                 $sql .= ' AND project_invoice.payment_status = "closed_bill"';
//             } else {
//                 $sql .= ' AND project_invoice.payment_status = "open_bill"';
//             }
//         }

//         $query = $database->query($sql, $binds);
//         return $query ? $query->getResultArray() : false;
//     }
}