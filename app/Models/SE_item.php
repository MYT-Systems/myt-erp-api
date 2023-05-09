<?php

namespace App\Models;

class SE_item extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'se_id',
        'name',
        'qty',
        'unit',
        'price',
        'total',
        'remarks',
        'received_qty',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'se_item';
    }

    /**
     * Get se_item details by se ID
     */
    public function get_details_by_se_id($se_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
    (SELECT se_item.qty 
        FROM se_item 
        WHERE se_item.se_id = se_item.se_id
            AND se_item.name = se_item.name
            AND se_item.is_deleted = 0
            ORDER BY se_item.id DESC LIMIT 1 OFFSET 1) AS prev_received_qty,
    (SELECT purchase_item.qty - purchase_item.received_qty 
        FROM purchase_item 
        WHERE purchase_item.id = se_item.se_item_id
            AND purchase_item.is_deleted = 0
            LIMIT 1) AS remaining_qty
    FROM se_item
    LEFT JOIN receive ON receive.id = se_item.se_id AND receive.is_deleted = 0
    WHERE se_item.is_deleted = 0
FROM se_item
WHERE is_deleted = 0
EOT;
        $binds = [];
        if (isset($se_id)) {
            $sql .= " AND se_id = ?";
            $binds[] = $se_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all se_items
     */

    public function get_se_items_by_se_id($se_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM se_item
WHERE is_deleted = 0
AND se_id = ?
EOT;
        $binds = [$se_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all ses
     */
    public function get_all_se()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM se_item
WHERE is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get items based on item name, contact_person, phone_no, tin_no, bir_no, email
     */
   public function search(
    $supplier_id = null, $se_date = null, $location = null, $ship_via = null, $grand_total = null, $remarks = null, 
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
    * Delete all se_items by se ID
    */
    public function delete_by_expense_id($se_id = null, $requested_by = null)
    {
        $database = \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE se_item
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE se_id = ?
EOT;
        $binds = [$requested_by, $date_now, $se_id];
        return $database->query($sql, $binds);
    }

    /**
     * Get details by supplies expense ID
     */
    public function get_details_by_supplies_expense_id($se_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT se_item.*,
se_item.name AS item_name,
SUM(supplies_receive_item.qty) AS received_qty,
se_item.qty - se_item.received_qty AS remaining_qty
FROM se_item
LEFT JOIN supplies_expense ON supplies_expense.id = se_item.se_id
AND supplies_expense.is_deleted = 0
LEFT JOIN supplies_receive_item ON supplies_receive_item.se_item_id = se_item.id
AND supplies_receive_item.is_deleted = 0
WHERE se_id = ?
AND se_item.is_deleted = 0
GROUP BY se_item.id
EOT;
        $binds = [$se_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Update received qty by se ID
     */
    public function update_receive_qty_by_id($se_item_id = null, $quantity = null, $requested_by = null)
    {
        $database = \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE se_item
SET received_qty = received_qty + ?, updated_by = ?, updated_on = ?
WHERE id = ?
EOT;
        $binds = [$quantity, $requested_by, $date_now, $se_item_id];
        $query = $database->query($sql, $binds);
        
        return $query ? true : false;
    }

    /**
     * Check if all se items are received
     */
    public function is_all_received_by_se_id($se_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT * 
FROM se_item
WHERE se_item.is_deleted = 0
    AND se_id = ?
    AND received_qty < qty
EOT;
        $binds = [$se_id];
        $query = $database->query($sql, $binds);
        return $query ? ($query->getResultArray() ? false : true) : false;
    }

}