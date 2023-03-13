<?php

namespace App\Models;

class Attendance extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'branch_id',
        'employee_id',
        'datetime',
        'total_minutes',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'attendance';
    }

    /**
     * Get attendance details by ID
     */
    public function get_details_by_id($attendance_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT CONCAT(first_name, ' ', last_name) FROM employee WHERE id = attendance.employee_id) AS employee_name,
    (SELECT name FROM branch WHERE id = attendance.branch_id) AS branch_name
FROM attendance
WHERE is_deleted = 0
    AND id = ?
EOT;
        $binds = [$attendance_id];
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all attendances' details
     */
    public function get_all_attendance($branch_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT CONCAT(first_name, ' ', last_name) FROM employee WHERE id = attendance.employee_id) AS employee_name,
    (SELECT name FROM branch WHERE id = attendance.branch_id) AS branch_name
FROM attendance
WHERE is_deleted = 0
EOT;
        $binds = [];

        if (isset($branch_id)) {
            $sql .= " AND attendance.branch_id = ?";
            $binds[] = $branch_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get the latest attendance for the employee
     */
    public function get_latest_attendance_today($employee_id, $branch_id)
    {
        $current_date = date('Y-m-d');
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM attendance
WHERE is_deleted = 0
    AND employee_id = ?
    AND branch_id = ?
    AND DATE(datetime) = ?
ORDER BY id DESC
LIMIT 1
EOT;
        $binds = [$employee_id, $branch_id, $current_date];
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Search
     */
    public function search($group_by_employees, $group_datetime, $branch_id = null, $branch_name = null, $employee_id  = null, $employee_name = null, $date = null, $date_from  = null, $date_to = null, $branches = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT attendance.id, branch_id, employee_id, datetime, SUM(total_minutes) AS total_minutes,
    CONCAT(first_name, ' ', last_name) AS employee_name,
    branch.name AS branch_name
FROM attendance
LEFT JOIN branch ON branch.id = attendance.branch_id
LEFT JOIN employee ON employee.id = attendance.employee_id
WHERE attendance.is_deleted = 0
EOT;
        $binds = [];
        if (isset($branch_id)) {
            $sql .= " AND attendance.branch_id = ?";
            $binds[] = $branch_id;
        } elseif ($branches) {
            $sql .= " AND attendance.branch_id IN ?";
            $binds[] = $branches;
        }

        if (isset($branch_name)) {
            $sql .= " AND branch.name LIKE ?";
            $binds[] = "%" . $branch_name . "%";
        }
        if (isset($employee_id)) {
            $sql .= " AND attendance.employee_id = ?";
            $binds[] = $employee_id;
        }
        if (isset($employee_name)) {
            $sql .= " AND CONCAT(first_name, ' ', last_name) LIKE ?";
            $binds[] = "%$employee_name%";
        }
        if (isset($date)) {
            $sql .= " AND DATE(attendance.datetime) = ?";
            $binds[] = $date;
        }
        if (isset($date_from)) {
            $sql .= " AND DATE(attendance.datetime) >= ?";
            $binds[] = $date_from;
        }
        if (isset($date_to)) {
            $sql .= " AND DATE(attendance.datetime) <= ?";
            $binds[] = $date_to;
        }

        if ($group_datetime) {
            $sql .= (($group_by_employees) ? 
            " GROUP BY attendance.branch_id, DATE(attendance.datetime), attendance.employee_id" :
            " GROUP BY attendance.branch_id, DATE(attendance.datetime)" );
        } else {
            $sql .= (($group_by_employees) ? 
            " GROUP BY attendance.branch_id, attendance.employee_id" :
            " GROUP BY attendance.branch_id" );
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

}