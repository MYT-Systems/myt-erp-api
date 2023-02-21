<?php

namespace App\Models;

class Expense_attachment extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'expense_id',
        'name',
        'file_url',
        'base_64',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'expense_attachment';
    }

    /**
     * Get expense_attachment details by ID
     */
    public function get_details_by_id($expense_attachment_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM expense_attachment
WHERE expense_attachment.is_deleted = 0
EOT;
        $binds = [];
        if (isset($expense_attachment_id)) {
            $sql .= " AND id = ?";
            $binds[] = $expense_attachment_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get expense_attachment details by expense ID
     */
    public function get_details_by_expense_id($expense_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM expense_attachment
WHERE expense_attachment.is_deleted = 0
    AND expense_attachment.expense_id = ?
EOT;
        $binds = [$expense_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete attachment by expense ID
     */
    public function delete_attachments_by_expense_id($expense_id = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();

        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE expense_attachment
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE expense_id = ?
EOT;
        $binds = [$requested_by, $date_now, $expense_id];
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}