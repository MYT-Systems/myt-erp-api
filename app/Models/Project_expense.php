<?php

namespace App\Models;

class Project_expense extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'project_id',
        'expense_type_id',
        'partner_id',
        'remarks',
        'amount',
        'other_fees',
        'grand_total',
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
SELECT *
FROM project_expense
WHERE project_expense.is_deleted = 0
    AND project_expense.id = ?
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
    public function search($name = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM project_expense
WHERE project_expense.is_deleted = 0
EOT;
        $binds = [];

        if ($name) {
            $sql .= " AND project_expense.name REGEXP ?";
            $name    = str_replace(' ', '|', $name);
            $binds[] = $name;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

}