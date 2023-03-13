<?php

namespace App\Models;

class Branch_operation_log extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'branch_id',
        'user_id',
        'time_in',
        'time_out',
        'date',
        'is_automatic_logout'
    ];

    public function __construct()
    {
        $this->table = 'branch_operation_log';
    }

    public function get_all($branch_type, $user_id, $branch_id, $date)
    {
        $db = db_connect();
        $sql = <<<EOT
SELECT branch_operation_log.*
FROM branch_operation_log
LEFT JOIN branch ON branch.id = branch_operation_log.branch_id
WHERE branch.is_deleted = 0
EOT;
        $binds = [];
        switch ($branch_type) {
            case 'company-owned':
                $sql .= " AND branch.is_franchise = 0";
                break;
            default:
                break;
        }

        if ($user_id) {
            $sql .= " AND branch_operation_log.user_id = ?";
            $binds[] = $user_id;
        }

        if ($branch_id) {
            $sql .= " AND branch_operation_log.branch_id = ?";
            $binds[] = $branch_id;
        }

        if ($date) {
            $sql .= " AND DATE(branch_operation_log.time_in) = ?";
            $binds[] = $date;
        }

        $query = $db->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
    
    public function get_user_not_logged_out()
    {
        $db = db_connect();
        $current_date = date("Y-m-d");

        $sql = <<<EOT
SELECT *
FROM branch_operation_log
WHERE time_out IS NULL
    AND DATE(time_in) = ?
EOT;
        $binds = [$current_date];

        $query = $db->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Log out all not logged out users
     */
    public function log_out_all_users()
    {
        $db = db_connect();
        $current_date = date("Y-m-d");
        $current_datetime = $current_date . " 21:30:00";

        $sql = <<<EOT
UPDATE branch_operation_log
SET time_out = ?, is_automatic_logout = 1
WHERE time_out IS NULL
    AND DATE(time_in) = ?
EOT;
        $binds = [$current_datetime, $current_date];

        $query = $db->query($sql, $binds);
        return $query ? true : false;
    }
}