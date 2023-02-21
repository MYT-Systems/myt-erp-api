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

}