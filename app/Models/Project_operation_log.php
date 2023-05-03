<?php

namespace App\Models;

class Project_operation_log extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'project_id',
        'user_id',
        'time_in',
        'time_out',
        'date',
        'is_automatic_logout'
    ];

    public function __construct()
    {
        $this->table = 'project_operation_log';
    }

    public function get_all($project_type, $user_id, $project_id, $date)
    {
        $db = db_connect();
        $sql = <<<EOT
SELECT project_operation_log.*
FROM project_operation_log
LEFT JOIN project ON project.id = project_operation_log.project_id
WHERE project.is_deleted = 0
EOT;
        $binds = [];
        switch ($project_type) {
            case 'company-owned':
                $sql .= " AND project.is_franchise = 0";
                break;
            default:
                break;
        }

        if ($user_id) {
            $sql .= " AND project_operation_log.user_id = ?";
            $binds[] = $user_id;
        }

        if ($project_id) {
            $sql .= " AND project_operation_log.project_id = ?";
            $binds[] = $project_id;
        }

        if ($date) {
            $sql .= " AND DATE(project_operation_log.time_in) = ?";
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
FROM project_operation_log
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
UPDATE project_operation_log
SET time_out = ?, is_automatic_logout = 1
WHERE time_out IS NULL
    AND DATE(time_in) = ?
EOT;
        $binds = [$current_datetime, $current_date];

        $query = $db->query($sql, $binds);
        return $query ? true : false;
    }
}