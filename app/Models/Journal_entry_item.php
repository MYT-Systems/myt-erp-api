<?php

namespace App\Models;

class Journal_entry_item extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'journal_entry_id',
        'project_id',
        'expense_type_id',
        'debit',
        'credit',
        'remarks',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'journal_entry_item';
    }

    /**
     * Get journal_entry_item by ID
     */
    public function get_details_by_id($journal_entry_item_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
FROM journal_entry_item
WHERE journal_entry_item.is_deleted = 0
    AND journal_entry_item.id = ?
EOT;
        $binds = [$journal_entry_item_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all journal_entry_item
     */
    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM journal_entry_item
WHERE is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get details by journal entry ID
     */
    public function get_details_by_journal_entry_id($journal_entry_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT journal_entry_item.id, journal_entry_item.debit, journal_entry_item.credit, journal_entry_item.remarks, journal_entry_item.expense_type_id, journal_entry_item.project_id,
    expense_type.name AS expense_type_name,
    project.name AS project_name
FROM journal_entry_item
LEFT JOIN expense_type ON expense_type.id = journal_entry_item.expense_type_id
LEFT JOIN project ON project.id = journal_entry_item.project_id
WHERE journal_entry_item.is_deleted = 0
    AND journal_entry_item.journal_entry_id = ?
EOT;
        $binds = [$journal_entry_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : [];
    }

    /**
     * Delete journal_entry_item by journal_entry_id
     */
    public function delete_by_journal_entry_id($journal_entry_id = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();

        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE journal_entry_item
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE journal_entry_item.is_deleted = 0
    AND journal_entry_item.journal_entry_id = ?
EOT;
        $binds = [$requested_by, $date_now, $journal_entry_id];

        return $database->query($sql, $binds);
    }

    /**
     * Insert project_invoice_item
     */
//     public function insert_on_duplicate($values = [], $requested_by = null, $db = null)
//     {
//         $database = $db ? $db : \Config\Database::connect();

//         $date_today = date('Y-m-d H:i:s');
//         $sql = <<<EOT
// INSERT INTO project_invoice_item (project_invoice_id, item_name, unit, price, qty, subtotal, added_by, added_on, updated_by, updated_on, is_deleted)
// VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, 0)
// ON DUPLICATE KEY UPDATE
//     project_invoice_id = VALUES(project_invoice_id),
//     item_name = VALUES(item_name),
//     unit = VALUES(unit),
//     price = VALUES(price),
//     qty = VALUES(qty),
//     subtotal = VALUES(subtotal),
//     updated_by = VALUES(updated_by),
//     updated_on = VALUES(updated_on),
//     is_deleted = 0
// EOT;

//         $binds = [
//             $values['project_invoice_id'],
//             $values['item_name'],
//             $values['unit'],
//             $values['price'],
//             $values['qty'],
//             $values['subtotal'],
//             $requested_by,
//             $date_today
//         ];

//         return $database->query($sql, $binds);
//     }
}