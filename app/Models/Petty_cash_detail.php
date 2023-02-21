<?php

namespace App\Models;

class Petty_cash_detail extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'id',
        'petty_cash_id',
        'status',
        'approved_by',
        'approved_on',
        'out_type',
        'type', //in our out
        'from',
        'amount',
        'particulars',
        'invoice_no',
        'date',
        'remarks',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'petty_cash_detail';
    }

    /**
     * Get petty_cash_detail details by ID
     */
    public function get_details_by_id($petty_cash_detail_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM petty_cash_detail
WHERE petty_cash_detail.is_deleted = 0
    AND petty_cash_detail.id = ?
EOT;
        $binds = [$petty_cash_detail_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all petty_cash_details
     */
    public function get_all_petty_cash_detail()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM petty_cash_detail
WHERE petty_cash_detail.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /*
    * Delete by petty cash id
    */
    public function delete_by_petty_cash_detail_id($petty_cash_detail_id = null, $requested_by = null, $db = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
UPDATE petty_cash_detail
SET is_deleted = 1
    , updated_by = ?
    , updated_on = NOW()
WHERE petty_cash_detail.id = ?
EOT;
        $binds = [$requested_by, $petty_cash_detail_id];
        
        return $database->query($sql, $binds);
    }

    /*
    * Delete by petty cash id
    */
    public function delete_by_petty_cash_id($petty_cash_id = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
UPDATE petty_cash_detail
SET is_deleted = 1
    , updated_by = ?
    , updated_on = ?
WHERE petty_cash_detail.petty_cash_id = ?
EOT;
        $binds = [$requested_by, $date_now, $petty_cash_id];
        
        return $database->query($sql, $binds);
    }


    /*
    * Get all petty cash details by petty cash id
    */
    public function get_details_by_petty_cash_id($petty_cash_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = petty_cash_detail.added_by) AS added_by_name
FROM petty_cash_detail
WHERE petty_cash_detail.is_deleted = 0
    AND petty_cash_detail.petty_cash_id = ?
EOT;
        $binds = [$petty_cash_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : [];
    }

    /*
    * Search
    */
    public function search($petty_cash_id, $date_from, $date_to, $type, $status = null, $approved_by = null, $approved_on = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE id = petty_cash_detail.added_by) AS added_by_name
FROM petty_cash_detail
WHERE petty_cash_detail.is_deleted = 0
EOT;
        $binds = [];

        if ($petty_cash_id) {
            $sql .= ' AND petty_cash_detail.petty_cash_id = ?';
            $binds[] = $petty_cash_id;
        }

        if ($date_from) {
            $sql .= ' AND petty_cash_detail.date >= ?';
            $binds[] = $date_from;
        }

        if ($date_to) {
            $sql .= ' AND petty_cash_detail.date <= ?';
            $binds[] = $date_to;
        }

        if ($type) {
            $sql .= ' AND petty_cash_detail.type = ?';
            $binds[] = $type;
        }

        if ($status) {
            $sql .= " AND petty_cash_detail.status = ?";
            $binds[] = $status;
        }

        if ($approved_by) {
            $sql .= " AND petty_cash_detail.approved_by = ?";
            $binds[] = $approved_by;
        }

        if ($approved_on) {
            $sql .= " AND DATE(petty_cash_detail.approved_on) = ?";
            $binds[] = $approved_on;
        }

        $sql .= ' ORDER BY petty_cash_detail.date, petty_cash_detail.added_on ASC';

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    protected function get_petty_cash_status_frequency()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT petty_cash_detail.status, COUNT(petty_cash_detail.id) AS frequency
FROM petty_cash_detail
WHERE petty_cash_detail.is_deleted = 0
GROUP BY petty_cash_detail.status
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }
}