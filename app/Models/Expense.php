<?php

namespace App\Models;

class Expense extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'branch_id',
        'status',
        'expense_date',
        'store_name',
        'invoice_no',
        'encoded_by',
        'grand_total',
        'remarks',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'expense';
    }

    /**
     * Get expense details by ID
     */
    public function get_details_by_id($expense_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT expense.*,
    CONCAT(encoder.first_name, ' ', encoder.last_name) AS encoded_by_name,
    CONCAT(adder.first_name, ' ', adder.last_name) AS added_by_name,
    branch.name AS branch_name
FROM expense
LEFT JOIN employee AS encoder ON expense.encoded_by = encoder.id
LEFT JOIN user AS adder ON expense.added_by = adder.id
LEFT JOIN branch ON expense.branch_id = branch.id
WHERE expense.is_deleted = 0
    AND expense.id = ?
EOT;
        $binds = [$expense_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all expenses
     */
    public function get_all_expense()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT expense.*,
    CONCAT(encoder.first_name, ' ', encoder.last_name) AS encoded_by_name,
    CONCAT(adder.first_name, ' ', adder.last_name) AS added_by_name,
    branch.name AS branch_name
FROM expense
LEFT JOIN employee AS encoder ON expense.encoded_by = encoder.id
LEFT JOIN user AS adder ON expense.added_by = adder.id
LEFT JOIN branch ON expense.branch_id = branch.id
WHERE expense.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get expenseess based on transaction_type_id, branch_id, commission
     */
    public function search($branch_id, $branch_name, $expense_date, $description, $amount, $expense_date_from, $expense_date_to, $by_branch)
    {
        $database = \Config\Database::connect();

        $grand_total = $by_branch ? "IFNULL(SUM(expense.grand_total), 0) AS grand_total" : "expense.grand_total";

        $sql = <<<EOT
SELECT expense.id, expense.branch_id, expense.expense_date, expense.store_name, expense.invoice_no,
    expense.encoded_by, $grand_total, expense.remarks, expense.added_by, expense.added_on,
    CONCAT(employee.first_name, ' ', employee.last_name) AS encoded_by_name,
    branch.name AS branch_name
FROM expense
LEFT JOIN employee ON expense.encoded_by = employee.id
LEFT JOIN branch ON expense.branch_id = branch.id
WHERE expense.is_deleted = 0
EOT;
        $binds = [];

        if ($branch_id) {
            $branch_id = explode(",", $branch_id);
            $sql .= " AND expense.branch_id IN ?";
            $binds[] = $branch_id;
        }

        if ($branch_name) {
            $sql .= " AND branch.name LIKE ?";
            $binds[] = "%" . $branch_name . "%";
        }

        if ($expense_date) {
            $sql .= " AND expense.expense_date = ?";
            $binds[] = $expense_date;
        }

        if ($description) {
            $sql .= " AND description REGEXP ? ";
            $name = str_replace(' ', '|', $description);
            $binds[] = $name;
        }

        if ($amount) {
            $sql .= " AND expense.amount = ?";
            $binds[] = $amount;
        }

        if ($expense_date_from) {
            $sql .= " AND expense.expense_date >= ?";
            $binds[] = $expense_date_from;
        }

        if ($expense_date_to) {
            $sql .= " AND expense.expense_date <= ?";
            $binds[] = $expense_date_to;
        }

        if ($by_branch) {
            $sql .= " GROUP BY expense.branch_id, expense.expense_date";
            // $binds[] = $by_branch;
        }
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get commission based on transaction_type_id, branch_id
     */
    public function get_commission($transaction_type_id = null, $branch_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT commission
FROM expense
WHERE expense.is_deleted = 0
    AND expense.transaction_type_id = ?
    AND expense.branch_id = ?
EOT;
        $binds = [$transaction_type_id, $branch_id];

        $query = $database->query($sql, $binds);
        return $query ? (float)$query->getResultArray()[0]['commission'] : false;
    }

    /**
     * Get total expense based on transaction_type_id, branch_id
     */
    public function get_total_expense($branch_id = null, $expense_date = null, $expense_date_from = null, $expense_date_to = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT IFNULL(SUM(grand_total), 0) AS total
FROM expense
WHERE expense.is_deleted = 0
    AND status = "approved"
EOT;
        $binds = [];
        if ($branch_id) {
            $sql .= " AND expense.branch_id = ?";
            $binds[] = $branch_id;
        }

        if ($expense_date) {
            $sql .= " AND expense.expense_date = ?";
            $binds[] = $expense_date;
        }

        if ($expense_date_from) {
            $sql .= " AND expense.expense_date >= ?";
            $binds[] = $expense_date_from;
        }

        if ($expense_date_to) {
            $sql .= " AND expense.expense_date <= ?";
            $binds[] = $expense_date_to;
        }

        $query = $database->query($sql, $binds);
        return $query ? (float)$query->getResultArray()[0]['total'] : false;
    }

    /**
     * Get request details by ID
     */
    public function get_by_status($status = null, $branches = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT expense.*
FROM expense
WHERE expense.is_deleted = 0
EOT;
        $binds = [];
        if ($status) {
            $sql .= " AND expense.status = ?";
            $binds[] = $status;
        }

        if ($branches) {
            $sql .= " AND expense.branch_id IN ?";
            $binds[] = $branches;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}