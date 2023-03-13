<?php

namespace App\Models;

class User extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'employee_id',
        'pin',
        'username',
        'password',
        'password_reset',
        'token',
        'api_key',
        'last_name',
        'first_name',
        'middle_name',
        'email',
        'branch_id',
        'type',
        'status',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'user';
    }
    
    /**
     * Get user details by ID
     */
    public function get_details_by_id($user_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT user.id, 
    user.pin, 
    user.password_reset, 
    user.username, 
    user.password, 
    user.last_name, 
    user.first_name, 
    user.middle_name, 
    user.email, 
    user.type, 
    user.branch_id,
    branch.name AS branch_name,
    branch.price_level,
    user.employee_id,
    (SELECT CONCAT(first_name, ' ', last_name) FROM employee WHERE id = user.employee_id) AS employee_name
FROM user
LEFT JOIN branch ON branch.id = user.branch_id
WHERE user.is_deleted = 0
EOT;
        $binds = [];
        if (isset($user_id)) {
            if (is_array($user_id))
                $sql .= " AND user.id IN ?";
            else
                $sql .= " AND user.id = ?";
            
            $binds[] = $user_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get user details by user pin
     */
    public function get_details_by_pin($pin = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT user.id, 
    user.pin, 
    user.password_reset, 
    user.username, 
    user.password, 
    user.last_name, 
    user.first_name, 
    user.middle_name, 
    user.email, 
    user.type, 
    user.branch_id,
    branch.name AS branch_name,
    branch.price_level,
    user.employee_id,
    (SELECT CONCAT(first_name, ' ', last_name) FROM employee WHERE id = user.employee_id) AS employee_name
FROM user
LEFT JOIN branch ON branch.id = user.branch_id
WHERE user.is_deleted = 0
EOT;
        $binds = [];
        if (isset($pin)) {
            $sql .= " AND user.pin = ?";
            $binds[] = $pin;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all users
     */
    public function get_all_users()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT user.id, 
    user.password_reset, 
    user.username, 
    user.password, 
    user.last_name, 
    user.first_name, 
    user.middle_name, 
    user.email, 
    user.type, 
    user.branch_id,
    branch.name AS branch_name,
    branch.price_level,
    user.employee_id,
    (SELECT CONCAT(first_name, ' ', last_name) FROM employee WHERE id = user.employee_id) AS employee_name
FROM user
LEFT JOIN branch ON branch.id = user.branch_id
WHERE user.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }


    public function get_outlet_type($id)
    {
        $database = db_connect();
        $sql = <<<EOT
SELECT user.*, outlet_type
FROM user_branch
LEFT JOIN branch ON branch.id = user_branch.branch_id
LEFT JOIN user ON user.id = user_branch.user_id
WHERE user_id = ?
    AND user_branch.is_deleted = 0
LIMIT 1
EOT;
        $binds = [$id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get user details by username
     */
    public function get_details_by_username($username)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT user.id, 
    user.pin, 
    user.password_reset, 
    user.username, 
    user.password, 
    user.last_name, 
    user.first_name, 
    user.middle_name, 
    user.email, 
    user.type, 
    user.branch_id,
    branch.name AS branch_name,
    branch.price_level,
    user.employee_id,
    (SELECT CONCAT(first_name, ' ', last_name) FROM employee WHERE id = user.employee_id) AS employee_name
FROM user
LEFT JOIN branch ON branch.id = user.branch_id
WHERE user.is_deleted = 0
    AND user.status = "active"
    AND user.username = ?
EOT;
        $binds = [$username];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResult() : false;
    }

    /**
     * Get users based on user name or branch
     */
    public function search($name = null, $status = null, $branch_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT user.*
FROM user
LEFT JOIN user_branch ON user.id = user_branch.user_id AND user_branch.is_deleted = 0
WHERE user.is_deleted = 0
EOT;
        $binds = [];
        
        if (isset($name)) {
            $sql .= " AND (user.first_name LIKE ? OR user.last_name LIKE ?)";
            $binds[] = "%$name%";
            $binds[] = "%$name%";
        }

        if (isset($status)) {
            $sql .= " AND user.status = ?";
            $binds[] = $status;
        }

        if (isset($branch_id)) {
            $sql .= " AND user_branch.branch_id = ?";
            $binds[] = $branch_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Log out all users in the given list
     */
    public function log_out_all_users($users)
    {
        $db = db_connect();

        $sql = <<<EOT
UPDATE `user`
SET api_key = NULL, token = NULL
WHERE id IN ?
EOT;
        $binds = [$users];

        $query = $db->query($sql, $binds);
        return $query ? true : false;
    }

}