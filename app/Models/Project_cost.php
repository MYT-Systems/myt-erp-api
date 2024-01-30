<?php

namespace App\Models;

class Project_cost extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'project_id',
        'description',
        'type',
        'amount',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'project_cost';
    }

    public function get_details_by_project_id($project_id)
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
SELECT description, type, amount
FROM project_cost
WHERE project_id = ?
AND is_deleted = 0
EOT;
        $binds = [$project_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}