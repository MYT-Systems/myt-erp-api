<?php

namespace App\Models;

class Project extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'name',
        'type',
        'initial_drawer',
        'address',
        'phone_no',
        'contact_person',
        'contact_person_no',
        'franchisee_name',
        'franchisee_contact_no',
        'tin_no',
        'bir_no',
        'contract_start',
        'contract_end',
        'opening_date',
        'is_franchise',
        'operation_days',
        'operation_times',
        'delivery_days',
        'delivery_times',
        'price_level',
        'rental_monthly_fee',
        'is_open',
        'opened_on',
        'closed_on',
        'operation_log_id',
        'inventory_group_id',
        'project_group_id',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted',
        'company'
    ];

    public function __construct()
    {
        $this->table = 'project';
    }

    public function get_project_operations($project_type, $user_id, $project_id, $project_name, $status, $date)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT project.id, project.name AS project_name,
    IF(project_operation_log.time_in IS NOT NULL AND project_operation_log.time_out IS NULL, "open", "closed") AS status,
    project_operation_log.time_in AS time_open,
    project_operation_log.time_out AS time_close
FROM project
LEFT JOIN project_operation_log
ON project.id = project_operation_log.project_id
JOIN_CONDITION
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
            $project_id = explode(',', $project_id);
            $sql .= " AND project.id IN ?";
            $binds[] = $project_id;
        }

        if ($project_name) {
            $sql .= " AND project.name LIKE ?";
            $binds[] = "%" . $project_name . "%";
        }

        if ($status) {
            $sql .= ' AND IF(project_operation_log.time_in IS NOT NULL AND project_operation_log.time_out IS NULL, "open", "closed") = ?';
            $binds[] = $status;
        }

        if ($date) {
            $sql = str_replace("JOIN_CONDITION", "AND DATE(project_operation_log.time_in) = ?", $sql);
            $sql .= " AND (project_operation_log.time_in IS NULL OR DATE(project_operation_log.time_in) = ?)";
            $binds[] = $date;
            $binds = array_merge([$date], $binds);
        } else {
            $sql = str_replace("JOIN_CONDITION", "", $sql);
        }

        $sql .= " GROUP BY project.id";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
    
    /**
     * Get project details by ID
     */
    public function get_details_by_id($project_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM project
WHERE project.is_deleted = 0
EOT;
        $binds = [];
        if (isset($project_id)) {
            $sql .= (is_array($project_id) ? ' AND project.id IN ?' : ' AND project.id = ?');
            $binds[] = $project_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all projects
     */
    public function get_all_project()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT project.*
FROM project
WHERE project.is_deleted = 0
GROUP BY project.id
EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get project details by project name
     */
    public function get_details_by_project_name($project_name)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM project
WHERE project.is_deleted = 0
    AND project.name = ?
EOT;
        $binds = [$project_name];
        $query = $database->query($sql, $binds);

        return !$query->getResult() ? false : $query->getResult()[0];
    }

    /**
     * Search
     */
    public function search($project_id = null, $name = null, $address = null, $phone_no = null, $contact_person = null, $contact_person_no = null, $franchisee_name = null, $franchisee_contact_no = null, $tin_no = null, $bir_no = null, $contract_start = null, $contract_end = null, $opening_date = null, $is_open = null, $is_franchise = null, $no_project_group = null, $no_inventory_group = null)
    {
        $database = \Config\Database::connect();
        
        $sql = <<<EOT
SELECT project.*, project_group.name AS project_group, price_level.name AS price_level_name
FROM project
LEFT JOIN project_group_detail ON project_group_detail.project_id = project.id AND project_group_detail.is_deleted = 0
LEFT JOIN project_group ON project_group.id = project_group_detail.project_group_id
LEFT JOIN price_level ON price_level.id = project.price_level
WHERE project.is_deleted = 0
EOT;

        $binds = [];

        if ($project_id) {
            $sql .= ' AND project.id = ?';
            $binds[] = $project_id;
        }

        if ($name) {
            $sql .= ' AND project.name REGEXP ?';
            $name    = str_replace(' ', '|', $name);
            $binds[] = $name;
        }

        if ($address) {
            $sql .= ' AND incharge REGEXP ?';
            $address = str_replace(' ', '|', $address);
            $binds[] = $address;
        }

        if ($phone_no) {
            $sql .= ' AND phone_no REGEXP ?';
            $phone_no = str_replace(' ', '|', $phone_no);
            $binds[] = $phone_no;
        }

        if ($contact_person) {
            $sql .= ' AND contact_person REGEXP ?';
            $contact_person = str_replace(' ', '|', $contact_person);
            $binds[]        = $contact_person;
        }

        if ($contact_person_no) {
            $sql .= ' AND contact_person_no = ?';
            $binds[] = $contact_person_no;
        }

        if ($franchisee_name) {
            $sql .= ' AND franchisee_name REGEXP ?';
            $franchisee_name = str_replace(' ', '|', $franchisee_name);
            $binds[]         = $franchisee_name;
        }

        if ($franchisee_contact_no) {
            $sql .= ' AND franchisee_contact_no = ?';
            $binds[] = $franchisee_contact_no;
        }

        if ($tin_no) {
            $sql .= ' AND tin_no = ?';
            $binds[] = $tin_no;
        }

        if ($bir_no) {
            $sql .= ' AND bir_no = ?';
            $binds[] = $bir_no;
        }

        if ($contract_start) {
            $sql .= ' AND contract_start = ?';
            $binds[] = $contract_start;
        }

        if ($contract_end) {
            $sql .= ' AND contract_end = ?';
            $binds[] = $contract_end;
        }

        if ($opening_date) {
            $sql .= ' AND opening_date = ?';
            $binds[] = $opening_date;
        }

        if ($is_open) {
            $sql .= ' AND is_open = ?';
            $binds[] = $is_open;
        }

        if ($is_franchise == '0' || $is_franchise) {
            $is_franchise = explode(',', $is_franchise);
            $is_franchise = array_map('intval', $is_franchise);
            $sql .= ' AND is_franchise IN (' . implode(',', $is_franchise) . ')';
        }

        if ($no_project_group) {
            $sql .= ' AND project.id NOT IN (SELECT project_id FROM project_group_detail WHERE is_deleted = 0)';
        } elseif ($no_project_group == '0') {
            $sql .= ' AND project.id IN (SELECT project_id FROM project_group_detail WHERE is_deleted = 0)';
        }

        if ($no_inventory_group) {
            $sql .= ' AND project.id NOT IN (SELECT project_id FROM inventory_group_detail WHERE is_deleted = 0)';
        } elseif ($no_inventory_group == '0') {
            $sql .= ' AND project.id IN (SELECT project_id FROM inventory_group_detail WHERE is_deleted = 0)';
        }

        $sql .= ' ORDER BY project.name ASC';

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
    
    public function close_projects($projects)
    {
        $db = db_connect();
        $current_datetime = date("Y-m-d H:i:s");

        $sql = <<<EOT
UPDATE `project`
SET is_open = 0, closed_on = ?
WHERE id IN ?
EOT;
        $binds = [$current_datetime, $projects];

        $query = $db->query($sql, $binds);
        return $query ? true : false;
    }

}