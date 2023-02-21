<?php

namespace App\Models;

class Expense_item extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'expense_id',
        'name',
        'unit',
        'price',
        'qty',
        'total',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'expense_item';
    }

    /**
     * Get expense_item details by ID
     */
    public function get_details_by_id($expense_item_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM expense_item
WHERE expense_item.is_deleted = 0
    AND expense_item.id = ?
EOT;
        $binds = [$expense_item_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all expense_items
     */
    public function get_all_expense_item()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM expense_item
WHERE expense_item.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

  
    /**
     * Get all expense_items by expense_id
     */
    public function get_all_expense_item_by_expense_id($expense_id = null, $item_name = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT expense_item.*
FROM expense_item
WHERE expense_item.is_deleted = 0
    AND expense_item.expense_id = ?
EOT;
        $binds = [$expense_id];

        if (isset($item_name)) {
            $sql .= " AND expense_item.name LIKE ?";
            $binds[] = "%{$item_name}%";
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete expense_item by expense_id
     */
    public function delete_expense_item_by_expense_id($expense_id = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();

        $delete_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
UPDATE expense_item
SET is_deleted = 1,
    updated_by = ?,
    updated_on = ?
WHERE expense_item.is_deleted = 0
    AND expense_item.expense_id = ?

EOT;
        $binds = [$requested_by, $delete_now, $expense_id];

        return $database->query($sql, $binds);
    }

    /**
     * Insert on duplicate key update
     */
    public function insert_on_duplicate_key_update($data = [], $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
INSERT INTO expense_item (
    expense_id,
    name,
    unit,
    price,
    qty,
    total,
    added_by,
    added_on,
    updated_by,
    updated_on,
    is_deleted
) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )
ON DUPLICATE KEY UPDATE
    expense_id = VALUES(expense_id),
    name = VALUES(name),
    unit = VALUES(unit),
    price = VALUES(price),
    qty = VALUES(qty),
    total = VALUES(total),
    added_by = VALUES(added_by),
    added_on = VALUES(added_on),
    updated_by = VALUES(updated_by),
    updated_on = VALUES(updated_on),
    is_deleted = 0
EOT;
    
        $binds = [
            $data['expense_id'],
            $data['name'],
            $data['unit'],
            $data['price'],
            $data['qty'],
            $data['total'],
            $requested_by,
            $date_now,
            $requested_by,
            $date_now,
            0
        ];

        return $database->query($sql, $binds);
    }
}