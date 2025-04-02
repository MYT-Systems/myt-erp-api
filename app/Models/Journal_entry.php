<?php

namespace App\Models;

class Journal_entry extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'date',
        'remarks',
        'total_debit',
        'total_credit',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'journal_entry';
    }

    /**
     * Get journal_entry by ID
     */
    public function get_details_by_id($journal_entry_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT journal_entry.date, journal_entry.remarks, journal_entry.total_debit, journal_entry.total_credit,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE user.id = journal_entry.added_by) AS added_by_name
FROM journal_entry
WHERE journal_entry.is_deleted = 0
    AND journal_entry.id = ?
    AND journal_entry.is_deleted = 0
GROUP BY journal_entry.id
EOT;
        $binds = [$journal_entry_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all journal_entry
     */
    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE user.id = journal_entry.added_by) AS added_by_name
FROM journal_entry
WHERE journal_entry.is_deleted = 0
AND journal_entry.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

}