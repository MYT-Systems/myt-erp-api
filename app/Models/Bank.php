<?php

namespace App\Models;

class Bank extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'name',
        'account_name',
        'account_no',
        'beginning_bal',
        'check_template_id',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'bank';
    }
    
    /**
     * Get bank details by ID
     */
    public function get_details_by_id($bank_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT bank.*, 
    bank.name as bank_name,
    bank.beginning_bal as bank_beginning_bal,
    check_template.id as check_template_id, 
    check_template.name as check_template_name, 
    check_template.file_name as check_template_file_name, 
    check_template.added_by as check_template_added_by, 
    check_template.added_on as check_template_added_on, 
    check_template.updated_by as check_template_updated_by, 
    check_template.updated_on as check_template_updated_on, 
    check_template.is_deleted as check_template_is_deleted
FROM bank
LEFT JOIN check_template ON bank.check_template_id = check_template.id
WHERE bank.is_deleted = 0
EOT;
        $binds = [];
        if (isset($bank_id)) {
            $sql .= " AND bank.id = ?";
            $binds[] = $bank_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all banks
     */
    public function get_all_bank()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT bank.*,
    bank.name as bank_name, 
    bank.beginning_bal as bank_beginning_bal,
    check_template.id as check_template_id, 
    check_template.name as check_template_name, 
    check_template.file_name as check_template_file_name, 
    check_template.added_by as check_template_added_by, 
    check_template.added_on as check_template_added_on, 
    check_template.updated_by as check_template_updated_by, 
    check_template.updated_on as check_template_updated_on, 
    check_template.is_deleted as check_template_is_deleted
FROM bank
LEFT JOIN check_template ON bank.check_template_id = check_template.id
WHERE bank.is_deleted = 0
ORDER BY bank.name ASC
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get banks based on bank name, contact_person, phone_no, tin_no, bir_no, email
     */
    public function search($name = null, $template_name = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT bank.*,
    bank.name as bank_name, 
    bank.beginning_bal as bank_beginning_bal,
    check_template.id as check_template_id, 
    check_template.name as check_template_name, 
    check_template.file_name as check_template_file_name, 
    check_template.added_by as check_template_added_by, 
    check_template.added_on as check_template_added_on, 
    check_template.updated_by as check_template_updated_by, 
    check_template.updated_on as check_template_updated_on, 
    check_template.is_deleted as check_template_is_deleted
FROM bank
LEFT JOIN check_template ON bank.check_template_id = check_template.id
WHERE bank.is_deleted = 0
EOT;
        $binds = [];

        if ($name) {
            $sql .= " AND bank.name REGEXP ?";
            $name    = str_replace(' ', '|', $name);
            $binds[] = $name;
        }

        if ($template_name) {
            $sql .= " AND check_template.name REGEXP ?";
            $template_name = str_replace(' ', '|', $template_name);
            $binds[]       = $template_name;
        }

        $sql .= " ORDER BY bank.name ASC";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

}