<?php

namespace App\Models;

class Check_entry extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'check_slip_id',
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
        $this->table = 'check_entry';
    }

    /**
     * Get check entry details by ID
     */
    public function get_details_by_id($check_entry_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT CONCAT(IF(invoice_no IS NULL, 'DR. No', 'Invoice No.'), IFNULL(invoice_no, dr_no), ' - ', grand_total) from receive WHERE receive.id = check_entry.receive_id) AS invoice_label 
FROM check_entry
WHERE is_deleted = 0
    AND id = ?
EOT;
        $binds = [$check_entry_id];
        
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
    (SELECT CONCAT(IF(invoice_no IS NULL, 'DR. No', 'Invoice No.'), IFNULL(invoice_no, dr_no), ' - ', grand_total) from receive WHERE receive.id = check_entry.receive_id) AS invoice_label 
FROM check_entry
WHERE is_deleted = 0
EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all check entries by check_slip ID
     */
    public function get_details_by_slip_id($check_slip_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT CONCAT(IF(invoice_no IS NULL, 'DR. No', 'Invoice No.'), IFNULL(invoice_no, dr_no), ' - ', grand_total) from receive WHERE receive.id = check_entry.receive_id) AS invoice_label 
FROM check_entry
WHERE is_deleted = 0
    AND check_slip_id = ?
EOT;
        $binds = [$check_slip_id];
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete check entries by check_slip ID
     */
    public function delete_by_slip_id($check_slip_id = null, $requested_by = null)
    {
        $database = \Config\Database::connect();
        
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE check_entry
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE check_slip_id = ?
EOT;
        $binds = [$requested_by, $date_now, $check_slip_id];

        $query = $database->query($sql, $binds);
        return $query ? true : false;
    }
}