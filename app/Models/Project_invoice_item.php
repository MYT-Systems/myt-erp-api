<?php

namespace App\Models;

class Project_invoice_item extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'project_invoice_id',
        'item_name',
        'item_id',
        'item_balance',
        'unit',
        'price',
        'qty',
        'subtotal',
        'billed_amount',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'project_invoice_item';
    }

    /**
     * Get project_invoice_item by ID
     */
    public function get_details_by_id($project_invoice_item_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM item WHERE item.id = project_invoice_item.item_id) AS item_name
FROM project_invoice_item
WHERE project_invoice_item.is_deleted = 0
    AND project_invoice_item.id = ?
EOT;
        $binds = [$project_invoice_item_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all project_invoice_item
     */
    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM item WHERE item.id = project_invoice_item.item_id) AS item_name
FROM project_invoice_item
WHERE is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get details by project invoice ID
     */
    public function get_details_by_project_invoices_id($project_invoices_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT project_invoice_item.*
FROM project_invoice_item
WHERE project_invoice_item.is_deleted = 0
    AND project_invoice_item.project_invoice_id = ?
EOT;
        $binds = [$project_invoices_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete project_invoice_item by project_invoice_id
     */
    public function delete_by_project_invoice_id($project_invoice_id = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();

        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE project_invoice_item
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE project_invoice_item.is_deleted = 0
    AND project_invoice_item.project_invoice_id = ?
EOT;
        $binds = [$requested_by, $date_now, $project_invoice_id];

        return $database->query($sql, $binds);
    }

    /**
     * Insert project_invoice_item
     */
    public function insert_on_duplicate($values = [], $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();

        $date_today = date('Y-m-d H:i:s');
        $sql = <<<EOT
INSERT INTO project_invoice_item (project_invoice_id, item_name, unit, price, qty, subtotal, added_by, added_on, updated_by, updated_on, is_deleted)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, 0)
ON DUPLICATE KEY UPDATE
    project_invoice_id = VALUES(project_invoice_id),
    item_name = VALUES(item_name),
    unit = VALUES(unit),
    price = VALUES(price),
    qty = VALUES(qty),
    subtotal = VALUES(subtotal),
    updated_by = VALUES(updated_by),
    updated_on = VALUES(updated_on),
    is_deleted = 0
EOT;

        $binds = [
            $values['project_invoice_id'],
            $values['item_name'],
            $values['unit'],
            $values['price'],
            $values['qty'],
            $values['subtotal'],
            $requested_by,
            $date_today
        ];

        return $database->query($sql, $binds);
    }
}