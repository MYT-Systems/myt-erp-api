<?php

namespace App\Models;

class Sales_report extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'branch_id',
        'date_from',
        'date_to',
        'total_sales',
        'cash_on_hand',
        'shortage_overage',
        'wastage',
        'expense_incurred',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'sales_report';
    }

    /**
     * Get sales_report details by ID
     */
    public function get_details_by_id($sales_report_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM sales_report
WHERE is_deleted = 0
EOT;
        $binds = [];
        if (isset($sales_report_id)) {
            $sql .= " AND id = ?";
            $binds[] = $sales_report_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
    
    /**
     * Get sales_report details by ID
     */
    public function get_by_status($status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM sales_report
WHERE sales_report.is_deleted = 0
EOT;
        $binds = [];
        if (isset($status)) {
            $sql .= " AND sales_report.status = ?";
            $binds[] = $status;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all sales_reports
     */
    public function get_all_sales_report()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM sales_report
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
FROM sales_report
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
     * Get sales_reports based on sales_report name, contact_person, phone_no, tin_no, bir_no, email
     */
   public function search($branch_id = null, $po_id = null, $supplier_id = null, $sales_report_date = null, 
   $waybill_no = null, $invoice_no = null, $dr_no = null, $remarks = null)
   {
       $database = \Config\Database::connect();
       $sql = <<<EOT
SELECT *
FROM sales_report
WHERE sales_report.is_deleted = 0
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

        if ($sales_report_date) {
            $sql .= " AND sales_report_date = ?";
            $binds[] = $sales_report_date;
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