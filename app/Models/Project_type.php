<?php

namespace App\Models;

class Project_type extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'project_id',
        'project_type_name_id',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'project_type';
    }

    public function get_details_by_project_id($project_id)
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
SELECT project_type_name_id, project_type_name.name AS project_type_name
FROM project_type
LEFT JOIN project_type_name ON project_type_name.id = project_type.project_type_name_id
WHERE project_id = ?
AND is_deleted = 0
EOT;
        $binds = [$project_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}