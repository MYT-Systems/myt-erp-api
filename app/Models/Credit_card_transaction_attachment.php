<?php

namespace App\Models;

class Credit_card_transaction_attachment extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'credit_card_transaction_id',
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
        $this->table = 'credit_card_transaction_attachment';
    }

    /**
     * Get credit_card_transaction_attachment details by ID
     */
    public function get_details_by_id($credit_card_transaction_attachment_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM credit_card_transaction_attachment
WHERE credit_card_transaction_attachment.is_deleted = 0
EOT;
        $binds = [];
        if (isset($credit_card_transaction_attachment_id)) {
            $sql .= " AND id = ?";
            $binds[] = $credit_card_transaction_attachment_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get credit_card_transaction_attachment details by credit_card_transaction ID
     */
    public function get_details_by_credit_card_transaction_id($credit_card_transaction_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM credit_card_transaction_attachment
WHERE credit_card_transaction_attachment.is_deleted = 0
EOT;
        $binds = [];
        if (isset($credit_card_transaction_id)) {
            $sql .= " AND credit_card_transaction_id = ?";
            $binds[] = $credit_card_transaction_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete all attachments by credit_card_transaction ID
     */
    public function delete_attachments_by_credit_card_transaction_id($credit_card_transaction_id = null, $requested_by = null)
    {
        $database = \Config\Database::connect();

        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
UPDATE credit_card_transaction_attachment
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE credit_card_transaction_id = ?
    AND is_deleted = 0
EOT;
        $binds = [$requested_by, $date_now, $credit_card_transaction_id];

        $query = $database->query($sql, $binds);
        return $query;
    }
}
