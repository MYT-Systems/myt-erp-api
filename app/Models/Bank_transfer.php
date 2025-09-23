<?php

namespace App\Models;

class Bank_transfer extends MYTModel {

    protected $primaryKey = 'id';
    protected $AutoIncrement = true;
    protected $allowedFields = [
        'transaction_date',
        'reference_no',
        'bank_from_id',
        'bank_to_id',
        'amount',
        'remarks',
        'added_on',
        'added_by',
        'updated_on',
        'updated_by',
        'is_deleted'
    ];

     public function __construct()
    {
        $this->table = 'bank_transfer';
    }

    /**
     * Get details of a bank transfer by ID
     */
    public function get_details_by_id($bank_transfer_id = null)
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
    SELECT *
    FROM bank_transfer
    WHERE bank_transfer.is_deleted = 0
      AND bank_transfer.id = ?
    EOT;

        $binds = [$bank_transfer_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all bank transfers
     */
    public function get_all_bank_transfer()
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
    SELECT *
    FROM bank_transfer
    WHERE bank_transfer.is_deleted = 0
    EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    
    /**
    * Search bank_transfer records based on filters
     */
    public function search($transaction_date = null, $reference_no = null, $bank_from_id = null, $bank_to_id = null, $amount = null, $remarks = null)
    {
        $db = \Config\Database::connect();

        $sql = <<<EOT
    SELECT bank_transfer.*,
        bank_from.name AS bank_from_name,
        bank_to.name AS bank_to_name
    FROM bank_transfer 
    LEFT JOIN bank bank_from ON bank_from.id = bank_transfer.bank_from_id
    LEFT JOIN bank bank_to ON bank_to.id = bank_transfer.bank_to_id
    WHERE bank_transfer.is_deleted = 0
    EOT;

        $binds = [];

        if ($transaction_date) {
            $sql .= " AND bank_transfer.transaction_date = ?";
            $binds[] = $transaction_date;
        }

        if ($reference_no) {
            $sql .= " AND bank_transfer.reference_no LIKE ?";
            $binds[] = "%$reference_no%";
        }

        if ($bank_from_id) {
            $sql .= " AND bank_transfer.bank_from_id = ?";
            $binds[] = $bank_from_id;
        }

        if ($bank_to_id) {
            $sql .= " AND bank_transfer.bank_to_id = ?";
            $binds[] = $bank_to_id;
        }

        if ($amount) {
            $sql .= " AND bank_transfer.amount = ?";
            $binds[] = $amount;
        }

        if ($remarks) {
            $sql .= " AND bank_transfer.remarks LIKE ?";
            $binds[] = "%$remarks%";
        }

        $sql .= " ORDER BY bank_transfer.transaction_date DESC";

        $query = $db->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


}