<?php

namespace App\Models;

class Supplier_price extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'supplier_id',
        'item_id',
        'price',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'supplier_price';
    }

    /**
     * Get supplier_price details by ID
     */
    public function get_details_by_id($supplier_price_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM supplier_price
WHERE is_deleted = 0
EOT;
        $binds = [];
        if (isset($supplier_price_id)) {
            $sql .= " AND id = ?";
            $binds[] = $supplier_price_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
    
    /**
     * Get supplier_price details by ID
     */
    public function get_by_status($status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM supplier_price
WHERE supplier_price.is_deleted = 0
EOT;
        $binds = [];
        if (isset($status)) {
            $sql .= " AND supplier_price.status = ?";
            $binds[] = $status;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all supplier_prices
     */
    public function get_all_supplier_price()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM supplier_price
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
FROM supplier_price
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
     * Get supplier_prices based on supplier_price name, contact_person, phone_no, tin_no, bir_no, email
     */
   public function search($supplier_id = null, $item_id = null, $price = null)
{
       $database = \Config\Database::connect();
       $sql = <<<EOT
SELECT *
FROM supplier_price
WHERE supplier_price.is_deleted = 0
EOT;

        $binds = [];
        if ($supplier_id) {
            $sql .= " AND supplier_id = ?";
            $binds[] = $supplier_id;
        }

        if ($item_id) {
            $sql .= " AND item_id = ?";
            $binds[] = $item_id;
        }

        if ($price) {
            $sql .= " AND price = ?";
            $binds[] = $price;
        }
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
   }
}