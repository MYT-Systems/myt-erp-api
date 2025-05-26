<?php

namespace App\Models;

class SE_cash_entry extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'se_cash_slip_id',
        'se_id', //  refers to the id of the supplies receive or supplies expense receive
        'type',
        'amount',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'se_cash_entry';
    }

    public function get_details($se_id = null, $type = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT se_cash_entry.*, se_cash_slip.*, 'cash' AS payment_method, se_cash_slip.id AS se_cash_slip_id
FROM se_cash_entry
LEFT JOIN se_cash_slip ON se_cash_slip.id = se_cash_entry.se_cash_slip_id
WHERE se_cash_entry.is_deleted = 0
    AND se_cash_entry.se_id = ?
    AND se_cash_entry.type = ?
EOT;
        $binds = [$se_id, $type];
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get cash entry details by ID
     */
    public function get_details_by_id($se_cash_entry_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT CONCAT(IF(invoice_no IS NULL, 'DR. No', 'Invoice No.'), IFNULL(invoice_no, dr_no), ' - ', grand_total) from receive WHERE receive.id = se_cash_entry.se_id) AS invoice_label 
FROM se_cash_entry
WHERE is_deleted = 0
    AND id = ?
EOT;
        $binds = [$se_cash_entry_id];
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all cash entries' details
     */
    public function get_all_entry()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT CONCAT(IF(invoice_no IS NULL, 'DR. No', 'Invoice No.'), IFNULL(invoice_no, dr_no), ' - ', grand_total) from receive WHERE receive.id = se_cash_entry.se_id) AS invoice_label 
FROM se_cash_entry
WHERE is_deleted = 0
EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all cash entries by cash_slip ID
     */
    public function get_details_by_slip_id($se_cash_slip_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT se_cash_entry.*, supplies_expense.supplies_expense_date,
    (SELECT CONCAT(IF(invoice_no IS NULL, 'DR. No', 'Invoice No.'), IFNULL(invoice_no, dr_no), ' - ', grand_total) from receive WHERE receive.id = se_cash_entry.se_id) AS invoice_label 
FROM se_cash_entry
LEFT JOIN supplies_expense ON supplies_expense.id = se_cash_entry.se_id
WHERE se_cash_entry.is_deleted = 0
    AND se_cash_entry.se_cash_slip_id = ?
EOT;
        $binds = [$se_cash_slip_id];
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete cash entries by cash_slip ID
     */
    public function delete_by_slip_id($se_cash_slip_id = null, $requested_by = null)
    {
        $database = \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE se_cash_entry
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE se_cash_slip_id = ?
EOT;
        $binds = [$requested_by, $date_now, $se_cash_slip_id];
        return $database->query($sql, $binds);
    }
}