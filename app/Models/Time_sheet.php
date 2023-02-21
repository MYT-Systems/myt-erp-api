<?php

namespace App\Models;

class Time_sheet extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'employee_id',
        'branch_id',
        'date',
        'time_in',
        'time_out',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'time_sheet';
    }

    /**
     * Get time_sheet details by ID
     */
    public function get_details_by_id($time_sheet_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM time_sheet
WHERE is_deleted = 0
EOT;
        $binds = [];
        if (isset($time_sheet_id)) {
            $sql .= " AND id = ?";
            $binds[] = $time_sheet_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
    
    /**
     * Get time_sheet details by ID
     */
    public function get_by_status($status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM time_sheet
WHERE time_sheet.is_deleted = 0
EOT;
        $binds = [];
        if (isset($status)) {
            $sql .= " AND time_sheet.status = ?";
            $binds[] = $status;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all time_sheets
     */
    public function get_all_time_sheet()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM time_sheet
WHERE is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get bills
     */

    public function get_bills($type = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM time_sheet
WHERE is_deleted = 0
EOT;
        $binds = [];
        if (isset($type) AND $type == 'open') {
            $sql .= " AND grand_total - paid_amount > 0";

        } elseif (isset($type) AND $type == 'close') {
            $sql .= " AND grand_total - paid_amount <= 0";
        }


        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get time_sheets based on time_sheet name, contact_person, phone_no, tin_no, bir_no, email
     */
   public function search($branch_id = null, $po_id = null, $supplier_id = null, $time_sheet_date = null, 
   $waybill_no = null, $invoice_no = null, $dr_no = null, $remarks = null)
   {
       $database = \Config\Database::connect();
       $sql = <<<EOT
SELECT *
FROM time_sheet
WHERE time_sheet.is_deleted = 0
EOT;

        $binds = [];
        if ($branch_id) {
            $sql .= " AND branch_id = ?";
            $binds[] = $branch_id;
        }

        if ($po_id) {
            $sql .= " AND po_id = ?";
            $binds[] = $po_id;
        }

        if ($supplier_id) {
            $sql .= " AND supplier_id = ?";
            $binds[] = $supplier_id;
        }

        if ($time_sheet_date) {
            $sql .= " AND time_sheet_date = ?";
            $binds[] = $time_sheet_date;
        }

        if ($waybill_no) {
            $sql .= " AND waybill_no = ?";
            $binds[] = $waybill_no;
        }

        if ($invoice_no) {
            $sql .= " AND invoice_no = ?";
            $binds[] = $invoice_no;
        }

        if ($dr_no) {
            $sql .= " AND dr_no = ?";
            $binds[] = $dr_no;
        }

        if ($remarks) {
            $sql .= " AND remarks REGEXP ?";
            $name    = str_replace(' ', '|', $remarks);
            $binds[] = $remarks;
        }
        
        $query = $database->query($sql = null, $binds);
        return $query ? $query->getResultArray() : false;
   }
}