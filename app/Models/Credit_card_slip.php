<?php

namespace App\Models;

class Credit_card_slip extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'payment_date',
        'reference_no',
        'credit_card_id',
        'supplier_id',
        'vendor_id',
        'payee',
        'particulars',
        'acknowledged_by',
        'amount',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'credit_card_slip';
    }

    /**
     * Get credit card slip details by ID
     */
    public function get_details_by_id($credit_card_slip_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT credit_card_slip.*,
    credit_card.card_name AS card_name,
    credit_card.card_no AS card_no,
    credit_card.credit_limit AS credit_limit,
    credit_card.current_bal AS current_bal,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = credit_card_slip.acknowledged_by) AS acknowledged_by_name,
    (SELECT trade_name FROM supplier WHERE id = credit_card_slip.supplier_id) AS supplier_name,
    (SELECT trade_name FROM vendor WHERE id = credit_card_slip.vendor_id) AS vendor_name
FROM credit_card_slip
LEFT JOIN credit_card ON credit_card.id = credit_card_slip.credit_card_id
WHERE credit_card_slip.is_deleted = 0
    AND credit_card_slip.id = ?
EOT;
        $binds = [$credit_card_slip_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all credit card slip details
     */
    public function get_all_slip()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT credit_card_slip.*,
    credit_card.card_name AS card_name,
    credit_card.card_no AS card_no,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = credit_card_slip.acknowledged_by) AS acknowledged_by_name,
    (SELECT trade_name FROM supplier WHERE id = credit_card_slip.supplier_id) AS supplier_name,
    (SELECT trade_name FROM vendor WHERE id = credit_card_slip.vendor_id) AS vendor_name
FROM credit_card_slip
LEFT JOIN credit_card ON credit_card.id = credit_card_slip.credit_card_id
WHERE credit_card_slip.is_deleted = 0
EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }
}
