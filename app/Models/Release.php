<?php

namespace App\Models;

class Release extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'branch_id',
        'release_date',
        'remarks',
        'type',
        'total_cost',
        'approved_by',
        'approved_on',
        'printed_by',
        'printed_on',
        'released_by',
        'released_on',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'release';
    }

    /**
     * Get release details by ID
     */
    public function get_details_by_id($release_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM release
WHERE is_deleted = 0
EOT;
        $binds = [];
        if (isset($release_id)) {
            $sql .= " AND id = ?";
            $binds[] = $release_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
    
    /**
     * Get release details by ID
     */
    public function get_by_status($status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM release
WHERE release.is_deleted = 0
EOT;
        $binds = [];
        if (isset($status)) {
            $sql .= " AND release.status = ?";
            $binds[] = $status;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all releases
     */
    public function get_all_release()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM release
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
FROM release
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
     * Get releases based on release name, contact_person, phone_no, tin_no, bir_no, email
     */
   public function search($branch_id = null, $po_id = null, $supplier_id = null, $release_date = null, 
   $waybill_no = null, $invoice_no = null, $dr_no = null, $remarks = null)
   {
       $database = \Config\Database::connect();
       $sql = <<<EOT
SELECT *
FROM release
WHERE release.is_deleted = 0
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

        if ($release_date) {
            $sql .= " AND release_date = ?";
            $binds[] = $release_date;
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