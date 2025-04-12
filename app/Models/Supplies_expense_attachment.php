<?php

namespace App\Models;

class Supplies_expense_attachment extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'supplies_expense_id',
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
        $this->table = 'supplies_expense_attachment';
    }

    /**
     * Get supplies_expense_attachment details by ID
     */
    public function get_details_by_id($supplies_expense_attachment_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM supplies_expense_attachment
WHERE supplies_expense_attachment.is_deleted = 0
EOT;
        $binds = [];
        if (isset($supplies_expense_attachment_id)) {
            $sql .= " AND id = ?";
            $binds[] = $supplies_expense_attachment_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get project_invoice_payment_attachment details by project_invoice ID
     */
    public function get_details_by_supplies_expense_id($supplies_expense_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM supplies_expense_attachment
WHERE supplies_expense_attachment.is_deleted = 0
EOT;
        $binds = [];
        if (isset($project_invoice_payment_id)) {
            $sql .= " AND supplies_expense_id = ?";
            $binds[] = $supplies_expense_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete all attachment by supplies_expense ID
     */
    public function delete_attachments_by_supplies_expense_id($supplies_expense_id = null, $requested_by = null)
    {
        $database = \Config\Database::connect();

        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
UPDATE supplies_expense_attachment
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE supplies_expense_id = ?
    AND is_deleted = 0
EOT;
        $binds = [$requested_by, $date_now, $supplies_expense_id];

        $query = $database->query($sql, $binds);
        return $query;
    }
}