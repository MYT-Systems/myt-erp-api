<?php

namespace App\Models;

class Credit_card extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'name',
        'card_name',
        'card_no',
        'issuing_bank',
        'credit_limit',
        'current_bal',
        'statement_date',
        'due_date',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'credit_card';
    }

    /**
     * Get credit card details by ID
     */
    public function get_details_by_id($credit_card_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT credit_card.*,
    credit_card.name as credit_card_name,
    credit_card.credit_limit as credit_card_limit,
    credit_card.current_bal as credit_card_current_bal
FROM credit_card
WHERE credit_card.is_deleted = 0
EOT;
        $binds = [];
        if (isset($credit_card_id)) {
            $sql .= " AND credit_card.id = ?";
            $binds[] = $credit_card_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all credit cards
     */
    public function get_all_credit_card()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT credit_card.*,
    credit_card.name as credit_card_name,
    credit_card.credit_limit as credit_card_limit
FROM credit_card
WHERE credit_card.is_deleted = 0
ORDER BY credit_card.name ASC
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get credit cards based on name, card_name, card_no, issuing_bank
     */
    public function search($name = null, $card_name = null, $card_no = null, $issuing_bank = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT credit_card.*,
    credit_card.name as credit_card_name,
    credit_card.credit_limit as credit_card_limit
FROM credit_card
WHERE credit_card.is_deleted = 0
EOT;
        $binds = [];

        if ($name) {
            $sql .= " AND credit_card.name REGEXP ?";
            $name    = str_replace(' ', '|', $name);
            $binds[] = $name;
        }

        if ($card_name) {
            $sql .= " AND credit_card.card_name REGEXP ?";
            $card_name = str_replace(' ', '|', $card_name);
            $binds[]   = $card_name;
        }

        if ($card_no) {
            $sql .= " AND credit_card.card_no REGEXP ?";
            $card_no = str_replace(' ', '|', $card_no);
            $binds[] = $card_no;
        }

        if ($issuing_bank) {
            $sql .= " AND credit_card.issuing_bank REGEXP ?";
            $issuing_bank = str_replace(' ', '|', $issuing_bank);
            $binds[]      = $issuing_bank;
        }

        $sql .= " ORDER BY credit_card.name ASC";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

}
