<?php

namespace App\Models;

class Franchisee_payment extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'branch_id',
        'franchisee_id',
        'payment_method',
        'payment_date',
        'amount',
        'remarks',
        'from_bank_id',
        'to_bank_id',
        'to_bank_name',
        'invoice_no',
        'cheque_number',
        'cheque_date',
        'reference_number',
        'transaction_number',
        'is_done',
        'deposit_date',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'franchisee_payment';
    }

    /**
     * Get franchisee_payment by ID
     */
    public function get_details_by_id($franchisee_payment_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM bank WHERE id = from_bank_id) AS from_bank_name,
    (SELECT name FROM bank WHERE id = to_bank_id) AS to_bank_name
FROM franchisee_payment
WHERE franchisee_payment.is_deleted = 0
    AND franchisee_payment.id = ?
EOT;
        $binds = [$franchisee_payment_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all franchisee_payment
     */
    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM bank WHERE id = from_bank_id) AS from_bank_name,
    (SELECT name FROM bank WHERE id = to_bank_id) AS to_bank_name
FROM franchisee_payment
WHERE is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * get_details_by_franchisee_id
     */
    public function get_details_by_franchisee_id($franchisee_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM bank WHERE id = from_bank_id) AS from_bank_name,
    (SELECT name FROM bank WHERE id = to_bank_id) AS to_bank_name
FROM franchisee_payment
WHERE franchisee_payment.is_deleted = 0
    AND franchisee_payment.franchisee_id = ?
EOT;
        $binds = [$franchisee_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Search Method
     */
    public function search($branch_id, $franchisee_id, $payment_method, $payment_date, $amount, $remarks, $from_bank_id, $cheque_number, $cheque_date, $reference_number, $transaction_number)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM bank WHERE id = from_bank_id) AS from_bank_name,
    (SELECT name FROM bank WHERE id = to_bank_id) AS to_bank_name
FROM franchisee_payment
WHERE franchisee_payment.is_deleted = 0
EOT;
        $binds = [];

        if ($branch_id) {
            $sql .= ' AND franchisee_payment.branch_id = ?';
            $binds[] = $branch_id;
        }

        if ($franchisee_id) {
            $sql .= ' AND franchisee_payment.franchisee_id = ?';
            $binds[] = $franchisee_id;
        }

        if ($payment_method) {
            $sql .= ' AND franchisee_payment.payment_method = ?';
            $binds[] = $payment_method;
        }

        if ($payment_date) {
            $sql .= ' AND franchisee_payment.payment_date = ?';
            $binds[] = $payment_date;
        }

        if ($amount) {
            $sql .= ' AND franchisee_payment.amount = ?';
            $binds[] = $amount;
        }

        if ($remarks) {
            $sql .= ' AND franchisee_payment.remarks LIKE ?';
            $binds[] = '%' . $remarks . '%';
        }

        if ($from_bank_id) {
            $sql .= ' AND franchisee_payment.from_bank_id LIKE ?';
            $binds[] = '%' . $from_bank_id . '%';
        }

        if ($cheque_number) {
            $sql .= ' AND franchisee_payment.cheque_number = ?';
            $binds[] = $cheque_number;
        }

        if ($cheque_date) {
            $sql .= ' AND franchisee_payment.cheque_date = ?';
            $binds[] = $cheque_date;
        }

        if ($reference_number) {
            $sql .= ' AND franchisee_payment.reference_number = ?';
            $binds[] = $reference_number;
        }

        if ($transaction_number) {
            $sql .= ' AND franchisee_payment.transaction_number = ?';
            $binds[] = $transaction_number;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}