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
SELECT requester_name_id, project_type_name.name AS project_type_name
FROM requester
LEFT JOIN requester_name ON requester_name.id = requester.requester_name_id
WHERE project_expense_id = ?
AND requester.is_deleted = 0
EOT;
        $binds = [$project_expense_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}