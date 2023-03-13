<?php

namespace App\Models;

class Cash_advance extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        "entry_id",
        "employee_id",
        "status",
        "date",
        "billing_start_month",
        "amount",
        "disbursement_type",
        "terms",
        "purpose",
        "remarks",
        "other_fees",
        "approved_on",
        "approved_by",
        "printed_on",
        "printed_by",
        "added_on",
        "added_by",
        "updated_on",
        "updated_by",
        "is_deleted"
    ];

    public function __construct()
    {
        $this->table = 'cash_advance';
    }

    /**
     * Get all banks
     */
    public function get($cash_advance_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT cash_advance.*,
    CONCAT(employee.first_name, " ", employee.last_name) AS employee_name
FROM cash_advance
LEFT JOIN employee ON cash_advance.employee_id = employee.id
WHERE cash_advance.is_deleted = 0
EOT;
        $binds = [];
        if ($cash_advance_id) {
            $sql .= " AND cash_advance.id = ?";
            $binds[] = $cash_advance_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get banks based on bank name, contact_person, phone_no, tin_no, bir_no, email
     */
    public function search($employee_name, $status, $date_from, $date_to, $billing_start = null, $employee_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT cash_advance.*,
    SUM(IFNULL(cash_advance_payment.paid_amount, 0)) AS paid_amount,
    (cash_advance.amount - SUM(IFNULL(cash_advance_payment.paid_amount, 0))) AS balance,
    CONCAT(employee.first_name, " ", employee.last_name) AS employee_name,
    CONCAT(preparer.first_name, " ", preparer.last_name) AS prepared_by_name,
    CONCAT(approver.first_name, " ", approver.last_name) AS approved_by_name,
    CONCAT(printer.first_name, " ", printer.last_name) AS printed_by_name
FROM cash_advance
LEFT JOIN cash_advance_payment ON cash_advance.id = cash_advance_payment.cash_advance_id
LEFT JOIN employee ON cash_advance.employee_id = employee.id
LEFT JOIN user AS preparer ON cash_advance.added_by = preparer.id
LEFT JOIN user AS approver ON cash_advance.approved_by = approver.id
LEFT JOIN user AS printer ON cash_advance.printed_by = printer.id
WHERE cash_advance.is_deleted = 0
EOT;
        $binds = [];

        if ($employee_name) {
            $sql .= ' AND CONCAT(employee.first_name, " ", employee.last_name) LIKE ?';
            $binds[] = "%" . $employee_name . "%";
        }

        if ($status) {
            $sql .= " AND cash_advance.status = ?";
            $binds[] = $status;
        }

        if ($date_from) {
            $sql .= " AND DATE(cash_advance.date) >= ?";
            $binds[] = $date_from;
        }

        if ($date_to) {
            $sql .= " AND DATE(cash_advance.date) <= ?";
            $binds[] = $date_to;
        }

        if ($billing_start) {
            $current_date = date("Y-m-d");
            $sql .= " AND cash_advance.billing_start_month" <= $current_date;
        }

        if ($employee_id) {
            $sql .= " AND cash_advance.employee_id = ?";
            $binds[] = $employee_id;
        }

        $sql .= " GROUP BY cash_advance.id";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

}