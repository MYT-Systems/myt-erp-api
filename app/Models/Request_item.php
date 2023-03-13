<?php

namespace App\Models;

class Request_item extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'request_id',
        'item_id',
        'unit',
        'qty',
        'price',
        'total',
        'received',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'request_item';
    }

    /**
     * Get request_item details by request ID
     */
    public function get_details_by_request_id($request_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT 
    item.name AS item_name,
    request_item.*,
    inventory.current_qty
FROM request_item
LEFT JOIN item ON item.id = request_item.item_id
LEFT JOIN request ON request.id = request_item.request_id
LEFT JOIN inventory ON inventory.item_id = item.id AND inventory.branch_id = request.branch_to
WHERE request_item.is_deleted = 0
EOT;
        $binds = [];
        if (isset($request_id)) {
            $sql .= " AND request_item.request_id = ?";
            $binds[] = $request_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    
    /**
     * Get request_item details by ID
     */
    public function get_by_status($status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM request_item
WHERE request_item.is_deleted = 0
EOT;
        $binds = [];
        if (isset($status)) {
            $sql .= " AND request_item.status = ?";
            $binds[] = $status;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all request_items
     */

    public function get_request_items_by_request_id($request_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM request_item
WHERE is_deleted = 0
EOT;
        $binds = [];
        if (isset($request_id)) {
            $sql .= " AND request_id = ?";
            $binds[] = $request_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all requests
     */
    public function get_all_request()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM request_item
WHERE is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get items based on item name, contact_person, phone_no, tin_no, bir_no, email
     */
   public function search(
    $supplier_id = null, $request_date = null, $location = null, $ship_via = null, $grand_total = null, $remarks = null, 
    $requisitioner = null, $status = null, $authorized_by = null, $recommended_by = null, $approved_by = null, $disapproved_by = null, $printed_by = null)
   {
       $database = \Config\Database::connect();
       $sql = <<<EOT
SELECT *
FROM item
WHERE item.is_deleted = 0
EOT;
       $binds = [];

       if ($name) {
           $sql .= " AND item.name REGEXP ?";
           $name    = str_replace(' ', '|', $name);
           $binds[] = $name;
       }

       if ($unit) {
           $sql .= " AND item.unit REGEXP ?";
           $name    = str_replace(' ', '|', $unit);
           $binds[] = $unit;
       }

       if ($price) {
           $sql .= " AND price = ?";
           $binds[] = $price;
       }

       if ($category) {
           $sql .= " AND item.category REGEXP ?";
           $name    = str_replace(' ', '|', $category);
           $binds[] = $category;
       }

       $query = $database->query($sql, $binds);
       return $query ? $query->getResultArray() : false;
   }

   
   /**
    * Delete by request item by request ID
    */
    public function delete_by_request_id($request_id = null, $requested_by = null)
    {
        $database = \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE request_item
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE request_id = ?
EOT;
        $binds = [$requested_by, $date_now, $request_id];
        return $database->query($sql, $binds);
    }

    /**
     * Update status by request ID
     */
    public function update_status_by_request_id($request_id = null, $requested_by = null, $status = null)
    {
        $database = \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE request_item
SET status = ?, updated_on = ?, updated_by = ?
WHERE request_id = ?
EOT;
        $binds = [$status, $date_now, $requested_by, $request_id];

        $query = $database->query($sql, $binds);
        return $query ? true : false;
    }
}