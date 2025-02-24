<?php

namespace App\Models;

class Requester extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'project_expense_id',
        'requester_name_id',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'requester';
    }

    public function get_details_by_project_expense_id($project_expense_id)
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
SELECT requester_name_id, requester_name.name AS requester_name
FROM requester
LEFT JOIN requester_name ON requester_name.id = requester.requester_name_id
WHERE project_expense_id = ?
AND requester.is_deleted = 0
EOT;
        $binds = [$project_expense_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete all attachment by project_invoice ID
     */
    public function delete_requester_by_project_expense_id($project_expense_id = null, $requested_by = null)
    {
        $database = \Config\Database::connect();

        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
UPDATE requester
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE project_expense_id = ?
    AND is_deleted = 0
EOT;
        $binds = [$requested_by, $date_now, $project_expense_id];

        $query = $database->query($sql, $binds);
        return $query;
    }
}