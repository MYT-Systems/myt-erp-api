<?php

namespace App\Models;

class Attendance_entry extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'attendance_id',
        'time_in',
        'time_out',
        'worked_minutes',
        'is_automatic_timeout',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'attendance_entry';
    }

    /**
     * Get total minutes including untimed out employee
     */
    public function get_total_work_minutes($attendance_id)
    {
        $database = \Config\Database::connect();
        $current_datetime = date("Y-m-d H:i:s");

        $sql = <<<EOT
SELECT SUM(IF(time_out IS NULL, TIMESTAMPDIFF(MINUTE, time_in, ?), worked_minutes)) AS total_worked_minutes
FROM attendance_entry
WHERE is_deleted = 0
    AND attendance_id = ?
GROUP BY attendance_id
EOT;
        $binds = [$current_datetime, $attendance_id];

        $query = $database->query($sql, $binds);
        return ($query AND $query->getResultArray()) ? $query->getResultArray()[0] : false;
    }

    /**
     * Get entries without time out
     */
    public function get_not_timed_out()
    {
        $database = \Config\Database::connect();
        $current_date = date("Y-m-d");

        $sql = <<<EOT
SELECT attendance_entry.*, attendance.total_minutes,
    branch.operation_days, branch.operation_times
FROM attendance_entry
LEFT JOIN attendance ON attendance.id = attendance_entry.attendance_id
LEFT JOIN branch ON branch.id = attendance.branch_id
WHERE attendance_entry.is_deleted = 0
    AND attendance_entry.time_out IS NULL
    AND DATE(attendance_entry.time_in) = ?
EOT;

        $binds = [$current_date];
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get attendance_entry details by ID
     */
    public function get_details_by_id($attendance_entry_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM attendance_entry
WHERE is_deleted = 0
    AND id = ?
EOT;
        $binds = [$attendance_entry_id];
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all attendance entries' details
     */
    public function get_all_attendance_entry()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT CONCAT(first_name, ' ', last_name) FROM employees WHERE id = attendance_entry.employee_id) AS employee_name,
    (SELECT name FROM branches WHERE id = attendance_entry.branch_id) AS branch_name
FROM attendance_entry
WHERE is_deleted = 0
EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all attendance entries by attendance_id
     */
    public function get_details_by_attendance_id($attendance_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM attendance_entry
WHERE is_deleted = 0
    AND attendance_id = ?
EOT;
        $binds = [$attendance_id];
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get the latest attendance entry for the attendance
     */
    public function get_latest_attendance_entry($attendance_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM attendance_entry
WHERE is_deleted = 0
    AND attendance_id = ?
ORDER BY id DESC
LIMIT 1
EOT;
        $binds = [$attendance_id];
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    public function get_work_minutes_by_employee($employee_id, $date_from, $date_to)
    {
        $sql = <<<EOT
SELECT employee_id, SUM(attendance_entry.worked_minutes) AS total_worked_minutes
FROM attendance_entry
LEFT JOIN attendance ON attendance.id = attendance_entry.attendance_id
WHERE attendance_entry.is_deleted = 0
    AND attendance.employee_id = ?
    AND DATE(time_in) BETWEEN ? AND ?
    AND attendance_entry.time_out IS NOT NULL
    AND attendance_entry.worked_minutes <> 0
GROUP BY attendance.employee_id
EOT;
        $binds = [$employee_id, $date_from, $date_to];
        $query = $database->query($sql, $binds);
        return ($query AND $query->getResultArray()) ? $query->getResultArray()[0] : false;
    }
}