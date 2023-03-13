<?php

namespace App\Models;

class Daily_sale_employee_deduction extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        "employee_id",
        "daily_sale_id",
        "amount",
        "added_on",
        "added_by",
        "updated_on",
        "updated_by",
        "is_deleted"
    ];

    public function __construct()
    {
        $this->table = 'daily_sale_employee_deduction';
    }

    /**
     * Daily sale deduction cost per employee
     */
    public function get_cost_per_employee($employee_id, $date_from, $date_to)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT SUM(daily_sale_employee_deduction.amount) AS total_cost
FROM daily_sale_employee_deduction
LEFT JOIN daily_sale
ON daily_sale_employee_deduction.daily_sale_id = daily_sale.id
WHERE daily_sale_employee_deduction.is_deleted = 0
    AND daily_sale_employee_deduction.employee_id = ?
    AND DATE(daily_sale.date) BETWEEN ? AND ?
EOT;

        $binds = [$employee_id, $date_from, $date_to];

        $query = $database->query($sql, $binds);
        return ($query AND $query->getResultArray()) ? $query->getResultArray()[0] : false;
    }

}