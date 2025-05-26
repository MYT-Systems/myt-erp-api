<?php

namespace App\Models;

class SE_check_entry extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'se_check_slip_id',
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
        $this->table = 'se_check_entry';
    }

    public function get_details($se_id = null, $type = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT se_check_entry.*, se_check_slip.*, 'check' AS payment_method
FROM se_check_entry
LEFT JOIN se_check_slip ON se_check_slip.id = se_check_entry.se_check_slip_id
WHERE se_check_entry.is_deleted = 0
    AND se_check_entry.se_id = ?
    AND se_check_entry.type = ?
EOT;
        $binds = [$se_id, $type];
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get check entry details by ID
     */
    public function get_details_by_id($se_check_entry_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT CONCAT(IF(invoice_no IS NULL, 'DR. No', 'Invoice No.'), IFNULL(invoice_no, dr_no), ' - ', grand_total) from receive WHERE receive.id = se_check_entry.se_id) AS invoice_label 
FROM se_check_entry
WHERE is_deleted = 0
    AND id = ?
EOT;
        $binds = [$se_check_entry_id];
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all check entries' details
     */
    public function get_all_entry()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT CONCAT(IF(invoice_no IS NULL, 'DR. No', 'Invoice No.'), IFNULL(invoice_no, dr_no), ' - ', grand_total) from receive WHERE receive.id = se_check_entry.se_id) AS invoice_label 
FROM se_check_entry
WHERE is_deleted = 0
EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all check entries by check_slip ID
     */
    public function get_details_by_slip_id($se_check_slip_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT se_check_entry.*, supplies_expense.supplies_expense_date,
    (SELECT CONCAT(IF(invoice_no IS NULL, 'DR. No', 'Invoice No.'), IFNULL(invoice_no, dr_no), ' - ', grand_total) from receive WHERE receive.id = se_check_entry.se_id) AS invoice_label 
FROM se_check_entry
LEFT JOIN supplies_expense ON supplies_expense.id = se_check_entry.se_id
WHERE se_check_entry.is_deleted = 0
    AND se_check_entry.se_check_slip_id = ?
EOT;
        $binds = [$se_check_slip_id];
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete check entries by check_slip ID
     */
    public function delete_by_slip_id($se_check_slip_id = null, $requested_by = null)
    {
        $database = \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE se_check_entry
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE se_check_slip_id = ?
EOT;
        $binds = [$requested_by, $date_now, $se_check_slip_id];

        $query = $database->query($sql, $binds);
        return $query ? true : false;
    }
}