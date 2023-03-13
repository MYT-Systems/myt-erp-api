<?php

namespace App\Models;

class Payroll extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        "employee_id",
        "date_from",
        "date_to",
        "release_date",
        "rate",
        "working_hours",
        "basic_pay",
        "philhealth",
        "sss",
        "hdmf",
        "cash_advance",
        "wastage",
        "shortage",
        "daily_allowance",
        "communication_allowance",
        "transportation_allowance",
        "food_allowance",
        "hmo_allowance",
        "tech_allowance",
        "ops_allowance",
        "special_allowance",
        "holidays",
        "remarks",
        "total_deduction",
        "total_addition",
        "grand_total",
        "added_on",
        "added_by",
        "updated_on",
        "updated_by",
        "is_deleted"
    ];

    public function __construct()
    {
        $this->table = 'payroll';
    }

    /**
     * Search payroll
     */
    public function search($employee_id, $date_from, $date_to)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT payroll.*,
    CONCAT(user.first_name, " ", user.last_name) AS employee_name
FROM payroll
LEFT JOIN employee ON employee.id = payroll.employee_id
WHERE payroll.is_deleted = 0
EOT;
        $binds = [];

        if ($employee_id) {
            $sql .= " AND payroll.employee_id = ?";
            $binds[] = $employee_id;
        }

        if ($date_from) {
            $sql .= " AND payroll.release_date >= ?";
            $binds[] = $date_from;
        }

        if ($date_to) {
            $sql .= " AND payroll.release_date <= ?";
            $binds[] = $date_to;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

}