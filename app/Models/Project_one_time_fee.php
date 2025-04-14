<?php

namespace App\Models;

class Project_one_time_fee extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'project_id',
        'project_invoice_id',
        'description',
        'type',
        'period',
        'amount',
        'balance',
        'is_occupied',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'project_one_time_fee';
    }

    public function get_details_by_project_id($project_id)
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
SELECT id, description, type, period, amount
FROM project_one_time_fee
WHERE project_id = ?
AND is_deleted = 0
EOT;
        $binds = [$project_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

     /**
     * Delete project_one_time_fee by project_id
     */
    public function delete_one_time_fees_by_project_id($project_id = null, $requested_by = null)
    {
        $database = \Config\Database::connect();

        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
UPDATE project_one_time_fee
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE project_id = ?
    AND is_deleted = 0
EOT;
        $binds = [$requested_by, $date_now, $project_id];

        $query = $database->query($sql, $binds);
        return $query;
    }
}