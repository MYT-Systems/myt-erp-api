<?php

namespace App\Models;

class Petty_cash_item extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'id',
        'petty_cash_id',
        'petty_cash_detail_id',
        'name',
        'unit',
        'qty',
        'price',
        'total',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'petty_cash_item';
    }

    /**
     * Get petty_cash_item details by ID
     */
    public function get_details_by_id($petty_cash_item_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM petty_cash_item
WHERE petty_cash_item.is_deleted = 0
    AND petty_cash_item.id = ?
EOT;
        $binds = [$petty_cash_item_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all petty_cash_items
     */
    public function get_all_petty_cash_item()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM petty_cash_item
WHERE petty_cash_item.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Insert on duplicate
     */
    public function insert_on_duplicate($values = [], $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $sql = <<<EOT
INSERT INTO petty_cash_item (petty_cash_id, petty_cash_detail_id, name, unit, qty, price, total, added_by, added_on, updated_by, updated_on, is_deleted) 
VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), null, null, 0)
ON DUPLICATE KEY UPDATE
    qty = VALUES(qty),
    price = VALUES(price),
    total = VALUES(total),
    updated_by = VALUES(updated_by),
    updated_on = NOW(),
    is_deleted = 0
EOT;
        $binds = [
            $values['petty_cash_id'],
            $values['petty_cash_detail_id'],
            $values['name'],
            $values['unit'],
            $values['qty'],
            $values['price'],
            $values['total'],
            $requested_by
        ];

        return $database->query($sql, $binds);
    }

    /*
    * Delete by petty_cash_detail_id
    */
    public function delete_by_petty_cash_detail_id($petty_cash_detail_id, $requested_by, $db)
    {
        $database = $db ? $db : \Config\Database::connect();
        $sql = <<<EOT
UPDATE petty_cash_item
SET is_deleted = 1,
    updated_by = ?,
    updated_on = NOW()
WHERE petty_cash_detail_id = ?
EOT;
        $binds = [$requested_by, $petty_cash_detail_id];

        return $database->query($sql, $binds);
    }

    /*
    * Delete by petty_cash_id
    */
    public function delete_by_petty_cash_id($petty_cash_id, $requested_by, $db)
    {
        $database = $db ? $db : \Config\Database::connect();
        $sql = <<<EOT
UPDATE petty_cash_item
SET is_deleted = 1,
    updated_by = ?,
    updated_on = NOW()
WHERE petty_cash_id = ?
EOT;
        $binds = [$requested_by, $petty_cash_id];

        return $database->query($sql, $binds);
    }

    /*
    * Get petty cash items by petty cash detail id
    */
    public function get_details_by_petty_cash_detail_id($petty_cash_detail_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM petty_cash_item
WHERE petty_cash_item.is_deleted = 0
    AND petty_cash_item.petty_cash_detail_id = ?
EOT;
        $binds = [$petty_cash_detail_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}