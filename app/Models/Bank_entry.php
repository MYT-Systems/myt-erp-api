<?php

namespace App\Models;

class Bank_entry extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'bank_slip_id',
        'receive_id',
        'amount',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'bank_entry';
    }

    /**
     * Get bank entry details by ID
     */
    public function get_details_by_id($bank_entry_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT CONCAT(IF(invoice_no IS NULL, 'DR. No', 'Invoice No.'), IFNULL(invoice_no, dr_no), ' - ', grand_total) FROM receive WHERE id = bank_entry.receive_id) AS invoice_label 
FROM bank_entry
WHERE is_deleted = 0
    AND id = ?
EOT;
        $binds = [$bank_entry_id];
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all bank entries' details
     */
    public function get_all_entry()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT CONCAT(IF(invoice_no IS NULL, 'DR. No', 'Invoice No.'), IFNULL(invoice_no, dr_no), ' - ', grand_total) from receive WHERE receive.id = bank_entry.receive_id) AS invoice_label 
FROM bank_entry
WHERE is_deleted = 0
EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all bank entries by bank_slip ID
     */
    public function get_details_by_slip_id($bank_slip_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT CONCAT(IF(invoice_no IS NULL, 'DR. No', 'Invoice No.'), IFNULL(invoice_no, dr_no), ' - ', grand_total) from receive WHERE receive.id = bank_entry.receive_id) AS invoice_label 
FROM bank_entry
WHERE is_deleted = 0
    AND bank_slip_id = ?
EOT;
        $binds = [$bank_slip_id];
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete bank entries by bank_slip ID
     */
    public function delete_by_slip_id($bank_slip_id = null, $requested_by = null)
    {
        $database = \Config\Database::connect();

        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
UPDATE bank_entry
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE bank_slip_id = ?
EOT;
        $binds = [$requested_by, $date_now, $bank_slip_id];

        $query = $database->query($sql, $binds);
        return $query ? true : false;
    }
}