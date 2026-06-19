<?php

namespace App\Models;

class Supplier_recurring_po extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'supplier_id',
        'type',
        'period',
        'amount',
        'total',
        'is_occupied',
        'purchase_order_id',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'supplier_recurring_po';
    }

    public function get_details_by_supplier_id($supplier_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM supplier_recurring_po
WHERE is_deleted = 0
    AND supplier_id = ?
ORDER BY id ASC
EOT;
        $query = $database->query($sql, [$supplier_id]);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all unoccupied recurring POs — used by auto-generate endpoint
     */
    public function get_unoccupied()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT srp.*
FROM supplier_recurring_po srp
INNER JOIN supplier s ON s.id = srp.supplier_id AND s.is_deleted = 0
WHERE srp.is_deleted = 0
    AND srp.is_occupied = 0
EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Reset is_occupied for templates whose linked SE belongs to a previous billing month,
     * so a new SE can be generated for the current billing period.
     */
    public function reset_previous_period($billing_date)
    {
        $database = \Config\Database::connect();
        $billing_ym = date('Y-m', strtotime($billing_date));

        $sql = <<<EOT
UPDATE supplier_recurring_po srp
INNER JOIN supplies_expense se ON se.id = srp.purchase_order_id AND se.is_deleted = 0
SET srp.is_occupied = 0,
    srp.purchase_order_id = NULL,
    srp.updated_on = ?
WHERE srp.is_deleted = 0
    AND srp.is_occupied = 1
    AND DATE_FORMAT(se.supplies_expense_date, '%Y-%m') != ?
EOT;
        $database->query($sql, [date('Y-m-d H:i:s'), $billing_ym]);
    }

    public function delete_by_supplier_id($supplier_id, $requested_by)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
UPDATE supplier_recurring_po
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE supplier_id = ?
    AND is_deleted = 0
EOT;
        $query = $database->query($sql, [$requested_by, date('Y-m-d H:i:s'), $supplier_id]);
        return $query;
    }
}
