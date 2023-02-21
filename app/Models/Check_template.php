<?php

namespace App\Models;

class Check_template extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'name',
        'file_name',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'check_template';
    }
    
    /**
     * Get check_template details by ID
     */
    public function get_details_by_id($check_template_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM check_template
WHERE check_template.is_deleted = 0
EOT;
        $binds = [];
        if (isset($check_template_id)) {
            $sql .= " AND check_template.id = ?";
            $binds[] = $check_template_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all check_templates
     */
    public function get_all_check_template()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM check_template
WHERE check_template.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get check_template details by check_template name
     */
    public function get_details_by_check_template_name($check_template_name)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM check_template
WHERE check_template.is_deleted = 0
    AND check_template.name = ?
EOT;
        $binds = [$check_template_name];
        $query = $database->query($sql, $binds);

        return !$query->getResult() ? false : $query->getResult();
    }

    /**
     * Get check_templates based on check_template name, contact_person, phone_no, tin_no, bir_no, email
     */
    public function search($name = null, $file_name = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM check_template
WHERE check_template.is_deleted = 0
EOT;
        $binds = [];

        if ($name) {
            $sql .= " AND check_template.name REGEXP ?";
            $name = str_replace(' ', '|', $name);
            $binds[] = $name;
        }

        if ($file_name) {
            $sql .= " AND check_template.file_name REGEXP ?";
            $file_name = str_replace(' ', '|', $file_name);
            $binds[] = $file_name;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

}