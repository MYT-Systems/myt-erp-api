<?php

namespace App\Models;

class Distributor_client extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'distributor_id',
        'customer_id',
        'project_id',
        'distributor_client_date',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'distributor_client';
    }

    /**
     * Get distributor details by ID
     */
    public function get_details_by_distributor_id($distributor_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT distributor_client.*, customer.name AS customer_name, project.name AS project_name
FROM distributor_client
LEFT JOIN distributor ON distributor.id = distributor_client.distributor_id 
LEFT JOIN customer ON customer.id = distributor_client.customer_id
LEFT JOIN project ON project.id = distributor_client.project_id
    AND distributor.is_deleted = 0
WHERE distributor_client.distributor_id = ? 
    AND distributor_client.is_deleted = 0
GROUP BY distributor_client.id
EOT;
        $binds = [$distributor_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

        /**
     * Get distributor details by ID
     */
    public function get_details_by_id($id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT distributor_client.*
FROM distributor_client
LEFT JOIN distributor ON distributor.id = distributor_client.distributor_id 
    AND distributor.is_deleted = 0
WHERE distributor_id = ? 
    AND distributor_client.is_deleted = 0
GROUP BY distributor_client.id
EOT;
        $binds = [$id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Update status by distributor ID
     */
    public function update_status_by_distributor_id($distributor_id = null, $requested_by = null, $status = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE distributor_client
SET status = ?, updated_on = ?, updated_by = ?
WHERE distributor_id = ?
EOT;
        $binds = [$status, $date_now, $requested_by, $distributor_id];
        return $database->query($sql, $binds);
    }

    /**
    * Delete all distributor_clients by distributor ID
    */
    public function delete_by_distributor_id($distributor_id = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
UPDATE distributor_client
SET distributor_client.is_deleted = 1, updated_by = ?, updated_on = ?
WHERE distributor_id = ?
EOT;
        $binds = [$requested_by, $date_now, $distributor_id];      
        return $database->query($sql, $binds);
    }
}