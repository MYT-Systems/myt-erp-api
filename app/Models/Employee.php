<?php

namespace App\Models;

class Employee extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        "username",
        "password",
        "password_reset",
        "token",
        "api_key",
        "last_name",
        "first_name",
        "middle_name",
        "suffix",
        "contact_no",
        "address",
        "gender",
        "birthdate",
        "civil_status",
        "nationality",
        "religion",
        "remarks",
        "profile_picture",
        "sss",
        "hdmf",
        "philhealth",
        "employment_status",
        "salary_type",
        "salary",
        "daily_allowance",
        "communication_allowance",
        "transportation_allowance",
        "food_allowance",
        "hmo_allowance",
        "tech_allowance",
        "ops_allowance",
        "special_allowance",
        "email",
        "status",
        "type",
        "added_by",
        "added_on",
        "updated_by",
        "updated_on",
        "is_deleted"
    ];

    public function __construct()
    {
        $this->table = 'employee';
    }
    
    /**
     * Get employee details by ID
     */
    public function get_details_by_id($employee_id = null)
    {
        
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT employee.*
FROM employee
WHERE employee.is_deleted = 0
EOT;
        $binds = [];
        if (isset($employee_id)) {
            $sql .= " AND employee.id = ?";
            $binds[] = $employee_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all employees
     */
    public function get_all_employees()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT employee.*
FROM employee
WHERE employee.is_deleted = 0
ORDER BY employee.last_name ASC, employee.first_name ASC
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get employee details by username
     */
    public function get_details_by_username($username)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT employee.*
FROM employee
WHERE employee.username = ?
    AND is_deleted = 0
    AND status = 'active'
EOT;
        $binds = [$username];
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResult() : false;
    }

    /**
     * Get employees based on employee name or branch
     */
    public function search($username, $name, $email, $status, $type)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT employee.*
FROM employee
WHERE employee.is_deleted = 0
EOT;
        $binds = [];
        if (isset($username)) {
            $sql .= " AND employee.username LIKE ?";
            $binds[] = "%$username%";
        }

        if (isset($name)) {
            $sql .= " AND CONCAT(employee.first_name, ' ', employee.middle_name, ' ',employee.last_name) LIKE ?";
            $binds[] = "%$name%";
        }

        if (isset($email)) {
            $sql .= " AND employee.email LIKE ?";
            $binds[] = "%$email%";
        }

        if (isset($status)) {
            $sql .= " AND employee.status = ?";
            $binds[] = $status;
        }

        if (isset($type)) {
            $sql .= " AND employee.type = ?";
            $binds[] = $type;
        }

        $sql .= " GROUP BY employee.id";
        $sql .= " ORDER BY employee.last_name ASC, employee.first_name ASC";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

}