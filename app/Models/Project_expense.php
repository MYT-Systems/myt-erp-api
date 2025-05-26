<?php

namespace App\Models;

class Project_expense extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'project_id',
        'expense_type_id',
        'project_expense_date',
        'partner_id',
        'supplier_id',
        'requester_name_id',
        'remarks',
        'amount',
        'other_fees',
        'grand_total',
        'paid_amount',
        'status',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'project_expense';
    }

    /**
     * Get project_expense by ID
     */
    public function get_project_expense_by_id($project_expense_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT percentage
FROM project_expense
WHERE project_expense.is_deleted = 0
    AND project_expense.id = ?
EOT;
        $binds = [$project_expense_id];

        $query = $database->query($sql, $binds);
        return $query ? (float)$query->getResultArray()[0]['percentage'] : false;
    }

    /**
     * Get project_expense details by ID
     */
    public function get_details_by_id($project_expense_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT project_expense.*, 
    project.grand_total AS project_price, 
    expense_type.name AS expense_type_name, 
    project.name AS project_name, 
    partner.name AS partner_name,
    supplier.trade_name AS supplier_name,
    requester_name.name AS requester_name
FROM project_expense
LEFT JOIN project ON project.id = project_expense.project_id
LEFT JOIN expense_type ON expense_type.id = project_expense.expense_type_id
LEFT JOIN partner ON partner.id = project_expense.partner_id
LEFT JOIN supplier ON supplier.id = project_expense.supplier_id
LEFT JOIN requester_name ON requester_name.id = project_expense.requester_name_id
WHERE project_expense.is_deleted = 0
AND project_expense.id = ?;
EOT;
        $binds = [$project_expense_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all project_expenses
     */
    public function get_all_project_expense()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM project_expense
WHERE project_expense.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get project_expenseess based on project_expense name, address, contact_person, contact_person_no, tin_no, bir_no
     */
    public function search($project_id = null, $expense_type_id = null, $partner_id = null, $remarks = null, $amount = null, $other_fees = null, $grand_total = null, $status = null, $project_name = null, $supplier_id = null, $distributor_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT project_expense.*, 
    project.name,
    partner.name AS partner_name, 
    distributor.name AS distributor_name,
    supplier.trade_name AS supplier_name,
    CONCAT(adder.first_name, ' ', adder.last_name) AS added_by_name
FROM project_expense
LEFT JOIN project ON project.id = project_expense.project_id
LEFT JOIN distributor ON distributor.id = project.distributor_id
LEFT JOIN supplier ON supplier.id = project_expense.supplier_id
LEFT JOIN partner ON partner.id = project_expense.partner_id
LEFT JOIN user AS adder ON adder.id = project_expense.added_by
WHERE project_expense.is_deleted = 0
EOT;
        $binds = [];

        if ($project_id) { $sql .= " AND project_expense.project_id = ?"; $binds[] = $project_id;}
        if ($expense_type_id) { $sql .= " AND project_expense.expense_type_id = ?"; $binds[] = $expense_type_id;}
        if ($partner_id) { $sql .= " AND project_expense.partner_id = ?"; $binds[] = $partner_id;}
        if ($remarks) { $sql .= " AND project_expense.remarks = ?"; $binds[] = $remarks;}
        if ($amount) { $sql .= " AND project_expense.amount = ?"; $binds[] = $amount;}
        if ($other_fees) { $sql .= " AND project_expense.other_fees = ?"; $binds[] = $other_fees;}
        if ($grand_total) { $sql .= " AND project_expense.grand_total = ?"; $binds[] = $grand_total;}
        if ($status) { $sql .= " AND project_expense.status = ?"; $binds[] = $status;}
        if ($project_name) { $sql .= " AND project.name = ?"; $binds[] = $project_name;}
        if ($supplier_id) { $sql .= " AND project_expense.supplier_id = ?"; $binds[] = $supplier_id;}
        if ($distributor_id) {$sql .= " AND project_expense.distributor_id = ?"; $binds[] = $distributor_id;}

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all project_expenses.
     */
    public function get_project_expense($supplier_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT project_expense.*, 
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = project_expense.added_by) AS added_by_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = project_expense.updated_by) AS updated_by_name
FROM project_expense
WHERE project_expense.is_deleted = 0 
AND project_expense.status = 'approved'
AND project_expense.paid_amount = 0.00
EOT;

        $binds = [];

        if ($supplier_id) {
            $sql .= " AND project_expense.supplier_id = ?";
            $binds[] = $supplier_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all project_expenses.
     */
    public function get_payment_by_expense($start_date = null, $end_date = null, $expense_type_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT 
    project_expense.id AS se_id,
    project_expense.project_expense_date,
    supplier.trade_name AS supplier,
    project_expense.grand_total,
    payment_info.issued_date,
    payment_info.payment_mode,
    payment_info.bank_name
FROM project_expense
LEFT JOIN supplier ON supplier.id = project_expense.supplier_id
LEFT JOIN (
    SELECT 
        se_cash_entry.se_id,
        se_cash_entry.type AS payment_type,
        se_cash_slip.payment_date AS issued_date,
        'cash' AS payment_mode,
        NULL AS bank_name
    FROM se_cash_entry
    INNER JOIN se_cash_slip ON se_cash_slip.id = se_cash_entry.se_cash_slip_id 
    AND se_cash_slip.is_deleted = 0

    UNION ALL

    SELECT 
        se_bank_entry.se_id,
        se_bank_entry.type AS payment_type,
        se_bank_slip.payment_date,
        'bank',
        bank.name AS bank_name
    FROM se_bank_entry
    INNER JOIN se_bank_slip ON se_bank_slip.id = se_bank_entry.se_bank_slip_id 
    AND se_bank_slip.is_deleted = 0
    LEFT JOIN bank ON bank.id = se_bank_slip.bank_from

    UNION ALL

    SELECT 
        se_gcash_entry.se_id,
        se_gcash_entry.type AS payment_type,
        se_gcash_slip.payment_date,
        'gcash',
        NULL AS bank_name
    FROM se_gcash_entry
    INNER JOIN se_gcash_slip ON se_gcash_slip.id = se_gcash_entry.se_gcash_slip_id 
    AND se_gcash_slip.is_deleted = 0

    UNION ALL

    SELECT 
        se_check_entry.se_id,
        se_check_entry.type AS payment_type,
        se_check_slip.issued_date,
        'check',
        NULL AS bank_name
    FROM se_check_entry
    INNER JOIN se_check_slip ON se_check_slip.id = se_check_entry.se_check_slip_id 
    AND se_check_slip.is_deleted = 0
) AS payment_info ON payment_info.se_id = project_expense.id
WHERE project_expense.is_deleted = 0
AND project_expense.status IN ('approved', 'paid')
EOT;

        $binds = [];

        if ($start_date) {
            $sql .= ' AND DATE(project_expense.project_expense_date) >= ?';
            $binds[] = $start_date;
        }

        if ($end_date) {
            $sql .= ' AND DATE(project_expense.project_expense_date) <= ?';
            $binds[] = $end_date;
        }

        if ($expense_type_id) {
            $sql .= ' AND project_expense.expense_type_id = ?';
            $binds[] = $expense_type_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

}