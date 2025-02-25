<?php

namespace App\Models;

class Project_change_request_item extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'project_change_request_id',
        'project_invoice_id',
        'name',
        'description',
        'amount',
        'balance',
        'billed_amount',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'project_change_request_item';
    }

    /**
     * Get project_change_request_item by ID
     */
    public function get_details_by_id($project_change_request_item_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM item WHERE item.id = project_change_request_item.item_id) AS item_name
FROM project_change_request_item
WHERE project_change_request_item.is_deleted = 0
    AND project_change_request_item.id = ?
EOT;
        $binds = [$project_change_request_item];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all project_change_request_item
     */
    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM item WHERE item.id = project_change_request_item.item_id) AS item_name
FROM project_invoice_item
WHERE is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get details by project change request ID
     */
    public function get_details_by_project_change_requests_id($project_change_request_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT project_change_request_item.*
FROM project_change_request_item
WHERE project_change_request_item.is_deleted = 0
    AND project_change_request_item.project_change_request_id = ?
EOT;
        $binds = [$project_change_request_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get details by project change request ID
     */
    public function get_details_by_project_id($project_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT project_change_request_item.*, project_change_request.project_id
FROM project_change_request_item
LEFT JOIN project_change_request ON project_change_request.id = project_change_request_item.project_change_request_id
WHERE project_change_request_item.is_deleted = 0
    AND project_change_request.project_id = ?
EOT;
        $binds = [$project_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete project_change_request_item by project_change_request_id
     */
    public function delete_by_project_change_request_id($project_change_request_id = null, $requested_by = null, $db = null)
    {
        $database = \Config\Database::connect();

        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
UPDATE project_change_request_item
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE project_change_request_item.is_deleted = 0
    AND project_change_request_item.project_change_request_id = ?
EOT;
        $binds = [$requested_by, $date_now, $project_change_request_id];

        $query = $database->query($sql, $binds);
        return $query;
    }

    /**
     * Insert project_change_request_item
     */
    public function insert_on_duplicate($values = [], $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();

        $date_today = date('Y-m-d H:i:s');
        $sql = <<<EOT
INSERT INTO project_change_request_item (project_change_request_id, name, amount, added_by, added_on, updated_by, updated_on, is_deleted)
VALUES (?, ?, ?, ?, ?, NULL, NULL, 0)
ON DUPLICATE KEY UPDATE
    project_change_request_id = VALUES(project_change_request_id),
    name = VALUES(name),
    amount = VALUES(amount),
    updated_by = VALUES(updated_by),
    updated_on = VALUES(updated_on),
    is_deleted = 0
EOT;

        $binds = [
            $values['project_change_request_id'],
            $values['name'],
            $values['amount'],
            $requested_by,
            $date_today
        ];

        return $database->query($sql, $binds);
    }
    
}