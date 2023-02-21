<?php

namespace App\Models;

class Payment_attachment extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'payment_id',
        'type',
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
        $this->table = 'payment_attachment';
    }

    /**
     * Get payment_attachment details by ID
     */
    public function get_details_by_id($payment_attachment_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM payment_attachment
WHERE payment_attachment.is_deleted = 0
EOT;
        $binds = [];
        if (isset($payment_attachment_id)) {
            $sql .= " AND id = ?";
            $binds[] = $payment_attachment_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get payment_attachment details by payment ID
     */
    public function get_details_by_payment_id($payment_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT payment_attachment.id, payment_attachment.type, payment_attachment.name, payment_attachment.file_url, payment_attachment.base_64
FROM payment_attachment
WHERE payment_attachment.is_deleted = 0
    AND payment_attachment.payment_id = ?
EOT;
        $binds = [$payment_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete attachment by payment ID
     */
    public function delete_attachments_by_payment_id($payment_id = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();

        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE payment_attachment
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE payment_id = ?
EOT;
        $binds = [$requested_by, $date_now, $payment_id];
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}