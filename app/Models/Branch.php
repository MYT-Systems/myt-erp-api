<?php

namespace App\Models;

class Branch extends MYTModel
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
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'branch';
    }

    public function get_branch_operations($branch_type, $user_id, $branch_id, $branch_name, $status, $date)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT branch.id, branch.name AS branch_name,
    IF(branch_operation_log.time_in IS NOT NULL AND branch_operation_log.time_out IS NULL, "open", "closed") AS status,
    branch_operation_log.time_in AS time_open,
    branch_operation_log.time_out AS time_close
FROM branch
LEFT JOIN branch_operation_log
ON branch.id = branch_operation_log.branch_id
JOIN_CONDITION
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
            $branch_id = explode(',', $branch_id);
            $sql .= " AND branch.id IN ?";
            $binds[] = $branch_id;
        }

        if ($branch_name) {
            $sql .= " AND branch.name LIKE ?";
            $binds[] = "%" . $branch_name . "%";
        }

        if ($status) {
            $sql .= ' AND IF(branch_operation_log.time_in IS NOT NULL AND branch_operation_log.time_out IS NULL, "open", "closed") = ?';
            $binds[] = $status;
        }

        if ($date) {
            $sql = str_replace("JOIN_CONDITION", "AND DATE(branch_operation_log.time_in) = ?", $sql);
            $sql .= " AND (branch_operation_log.time_in IS NULL OR DATE(branch_operation_log.time_in) = ?)";
            $binds[] = $date;
            $binds = array_merge([$date], $binds);
        } else {
            $sql = str_replace("JOIN_CONDITION", "", $sql);
        }

        $sql .= " GROUP BY branch.id";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
    
    /**
     * Get branch details by ID
     */
    public function get_details_by_id($branch_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM branch
WHERE branch.is_deleted = 0
EOT;
        $binds = [];
        if (isset($branch_id)) {
            $sql .= (is_array($branch_id) ? ' AND branch.id IN ?' : ' AND branch.id = ?');
            $binds[] = $branch_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all branchs
     */
    public function get_all_branch()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT branch.*,
    IF (user.id IS NULL, 0, 1) AS has_account_already,
    CONCAT(user.first_name, ' ', user.last_name) AS user_name
FROM branch
LEFT JOIN user ON user.branch_id = branch.id
WHERE branch.is_deleted = 0
GROUP BY branch.id
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get branch details by branch name
     */
    public function get_details_by_branch_name($branch_name)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM branch
WHERE branch.is_deleted = 0
    AND branch.name = ?
EOT;
        $binds = [$branch_name];
        $query = $database->query($sql, $binds);

        return !$query->getResult() ? false : $query->getResult()[0];
    }

    /**
     * Search
     */
    public function search($branch_id, $name, $address, $phone_no, $contact_person, $contact_person_no, $franchisee_name, $franchisee_contact_no, $tin_no, $bir_no, $contract_start, $contract_end, $opening_date, $is_open, $is_franchise, $no_inventory_group)
    {
        $database = \Config\Database::connect();
        
        $sql = <<<EOT
SELECT branch.*, inventory_group.name AS inventory_group, price_level.name AS price_level_name
FROM branch
LEFT JOIN inventory_group_detail ON inventory_group_detail.branch_id = branch.id AND inventory_group_detail.is_deleted = 0
LEFT JOIN inventory_group ON inventory_group.id = inventory_group_detail.inventory_group_id
LEFT JOIN price_level ON price_level.id = branch.price_level
WHERE branch.is_deleted = 0
EOT;
        $binds = [];

        if ($branch_id) {
            $sql .= ' AND branch.id = ?';
            $binds[] = $branch_id;
        }

        if ($name) {
            $sql .= ' AND branch.name REGEXP ?';
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

        if ($no_inventory_group) {
            $sql .= ' AND branch.id NOT IN (SELECT branch_id FROM inventory_group_detail WHERE is_deleted = 0)';
        } elseif ($no_inventory_group == '0') {
            $sql .= ' AND branch.id IN (SELECT branch_id FROM inventory_group_detail WHERE is_deleted = 0)';
        }

        $sql .= ' ORDER BY branch.name ASC';

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
    
    public function close_branches($branches)
    {
        $db = db_connect();
        $current_datetime = date("Y-m-d H:i:s");

        $sql = <<<EOT
UPDATE `branch`
SET is_open = 0, closed_on = ?
WHERE id IN ?
EOT;
        $binds = [$current_datetime, $branches];

        $query = $db->query($sql, $binds);
        return $query ? true : false;
    }

}