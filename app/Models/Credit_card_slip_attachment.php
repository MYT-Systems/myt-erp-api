<?php

namespace App\Models;

class Credit_card_slip_attachment extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'credit_card_slip_id',
        'file_name',
        'file_path',
        'file_url',
        'mime',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'credit_card_slip_attachment';
    }

    /**
     * Get credit_card_slip_attachment details by credit_card_slip ID
     */
    public function get_details_by_credit_card_slip_id($credit_card_slip_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT credit_card_slip_attachment.*
FROM credit_card_slip_attachment
WHERE credit_card_slip_attachment.is_deleted = 0
EOT;
        $binds = [];
        if (isset($credit_card_slip_id)) {
            $sql .= " AND credit_card_slip_id = ?";
            $binds[] = $credit_card_slip_id;
        }

        $sql .= " GROUP BY credit_card_slip_attachment.id";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete attachments by credit_card_slip ID
     */
    public function delete_attachment_by_credit_card_slip_id($credit_card_slip_id = null, $requested_by = null)
    {
        $database = \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE credit_card_slip_attachment
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE credit_card_slip_id = ?
EOT;
        $binds = [$requested_by, $date_now, $credit_card_slip_id];

        $query = $database->query($sql, $binds);
        return $query;
    }
}
