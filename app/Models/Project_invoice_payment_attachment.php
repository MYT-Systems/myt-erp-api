<?php

namespace App\Models;

class Project_invoice_payment_attachment extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'project_expense_id',
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
        $this->table = 'project_invoice_payment_attachment';
    }

    /**
     * Get project_invoice_payment_attachment details by ID
     */
    public function get_details_by_id($project_invoice_payment_attachment_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM project_invoice_payment_attachment
WHERE project_invoice_payment_attachment.is_deleted = 0
EOT;
        $binds = [];
        if (isset($project_invoice_payment_attachment_id)) {
            $sql .= " AND id = ?";
            $binds[] = $project_invoice_payment_attachment_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get project_invoice_payment_attachment details by project_expense ID
     */
    public function get_details_by_project_expense_id($project_expense_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM project_invoice_payment_attachment
WHERE project_invoice_payment_attachment.is_deleted = 0
EOT;
        $binds = [];
        if (isset($project_expense_id)) {
            $sql .= " AND project_expense_id = ?";
            $binds[] = $project_expense_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete all attachment by project_expense ID
     */
    public function delete_attachments_by_project_expense_id($project_expense_id = null, $requested_by = null)
    {
        $database = \Config\Database::connect();

        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
UPDATE project_invoice_payment_attachment
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE project_expense_id = ?
    AND is_deleted = 0
EOT;
        $binds = [$requested_by, $date_now, $project_expense_id];

        $query = $database->query($sql, $binds);
        return $query;
    }
}