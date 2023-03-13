<?php

namespace App\Models;

class Fs_billing_item extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'id',
        'fs_billing_id',
        'date',
        'sale',
        'is_closed',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'fs_billing_item';
    }

    /**
     * Get fs_billing_item by ID
     */
    public function get_details_by_id($fs_billing_item_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM fs_billing_item
WHERE fs_billing_item.is_deleted = 0
    AND fs_billing_item.id = ?
EOT;
        $binds = [$fs_billing_item_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all fs_billing_item
     */
    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM fs_billing_item
WHERE is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * get franchisee billing by billing id
     */
    public function get_fs_billing_item_by_fs_billing_id($fs_billing_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM fs_billing_item
WHERE fs_billing_item.is_deleted = 0
    AND fs_billing_item.fs_billing_id = ?
EOT;
        $binds = [$fs_billing_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * delete franchisee billing item by frachisee sale billing id
     */
    public function delete_fs_billing_item_by_fs_billing_id($fs_billing_id = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();

        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE fs_billing_item
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE fs_billing_item.is_deleted = 0
    AND fs_billing_item.fs_billing_id = ?
EOT;
        $binds = [$requested_by, $date_now, $fs_billing_id];

        return $database->query($sql, $binds);
    }

        /**
     * Insert on duplicate key update
     */
    public function insert_on_duplicate_key_update($data = [], $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();

        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
INSERT INTO fs_billing_item (
    fs_billing_id,
    date,
    sale,
    is_closed,
    added_by,
    added_on,
    updated_by,
    updated_on,
    is_deleted
) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?
) ON DUPLICATE KEY UPDATE
    fs_billing_id = VALUES(fs_billing_id),
    date = VALUES(date),
    sale = VALUES(sale),
    is_closed = VALUES(is_closed),
    updated_by = VALUES(updated_by),
    updated_on = VALUES(updated_on),
    is_deleted = 0
EOT;

        $binds = [
            $data['fs_billing_id'],
            $data['date'],
            $data['sale'],
            $data['is_closed'],
            $requested_by,
            $date_now,
            $requested_by,
            $date_now,
            0
        ];

        return $database->query($sql, $binds);
    }
}