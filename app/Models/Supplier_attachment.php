<?php

namespace App\Models;

class Supplier_attachment extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'supplier_id',
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
        $this->table = 'supplier_attachment';
    }

    /**
     * Get supplier_attachment details by ID
     */
    public function get_details_by_id($supplier_attachment_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM supplier_attachment
WHERE supplier_attachment.is_deleted = 0
EOT;
        $binds = [];
        if (isset($supplier_attachment_id)) {
            $sql .= " AND id = ?";
            $binds[] = $supplier_attachment_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get supplier_attachment details by supplier ID
     */

    public function get_details_by_supplier_id($supplier_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM supplier_attachment
WHERE supplier_attachment.is_deleted = 0
    AND supplier_attachment.supplier_id = ?
EOT;
        $binds = [$supplier_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete attachment by supplier ID
     */
    public function delete_attachment_by_supplier_id($supplier_id = null, $requested_by = null, $db = null)
    {
        $database = $db ?? \Config\Database::connect();

        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE supplier_attachment
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE supplier_id = ?
EOT;

        $binds = [$requested_by, $date_now, $supplier_id];

        $query = $database->query($sql, $binds);
        return $query;
    }
}
