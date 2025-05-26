<?php

namespace App\Models;

class Petty_cash_detail_attachment extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'petty_cash_detail_id',
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
        $this->table = 'petty_cash_detail_attachment';
    }

    /**
     * Get petty_cash_detail_attachment details by ID
     */
    public function get_details_by_id($project_expense_attachment_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM petty_cash_detail_attachment
WHERE petty_cash_detail_attachment.is_deleted = 0
EOT;
        $binds = [];
        if (isset($project_expense_attachment_id)) {
            $sql .= " AND id = ?";
            $binds[] = $project_expense_attachment_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get petty_cash_detail_attachment details by project_expense ID
     */
    public function get_details_by_project_expense_id($petty_cash_detail_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM petty_cash_detail_attachment
WHERE petty_cash_detail_attachment.is_deleted = 0
EOT;
        $binds = [];
        if (isset($petty_cash_detail_id)) {
            $sql .= " AND petty_cash_detail_id = ?";
            $binds[] = $petty_cash_detail_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete all attachment by project_expense ID
     */
    public function delete_attachments_by_project_expense_id($petty_cash_detail_id = null, $requested_by = null)
    {
        $database = \Config\Database::connect();

        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
UPDATE petty_cash_detail_attachment
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE petty_cash_detail_id = ?
    AND is_deleted = 0
EOT;
        $binds = [$requested_by, $date_now, $petty_cash_detail_id];

        $query = $database->query($sql, $binds);
        return $query;
    }
}