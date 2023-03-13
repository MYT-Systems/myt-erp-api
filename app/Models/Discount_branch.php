<?php

namespace App\Models;

class Discount_branch extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'branch_id',
        'discount_id',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted',
    ];

    public function __construct()
    {
        $this->table = 'discount_branch';
    }

    public function get_by_discount_id($discount_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT discount_branch.*,
    branch.name AS branch_name
FROM discount_branch
LEFT JOIN branch ON branch.id = discount_branch.branch_id
WHERE discount_branch.is_deleted = 0
    AND discount_branch.discount_id = ?
EOT;

        $binds = [$discount_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
    
    /**
     * Insert on duplicate key update
     */
    public function insert_on_duplicate_key_update($data = null, $requested_by = null)
    {
        $database = \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
INSERT INTO discount_branch (
    branch_id,
    discount_id,
    added_by,
    added_on
) VALUES ( ?, ?, ?, ? )
ON DUPLICATE KEY UPDATE
    branch_id = VALUES(branch_id),
    discount_id = VALUES(discount_id),
    updated_by = VALUES(added_by),
    updated_on = VALUES(added_on)
EOT;
        $binds = [
            $data['branch_id'],
            $data['discount_id'],
            $requested_by,
            $date_now
        ];



        $query = $database->query($sql, $binds);
        return $query ? $database->insertID() : false;
    }

    /**
     * Delete multiple discount_branch NOT IN given IDs
     */
    public function delete_multiple_branches($ids, $discount_id, $requested_by)
    {
        $database = \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
UPDATE discount_branch
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE id NOT IN ?
    AND discount_id = ?
EOT;
        $binds = [$requested_by, $date_now, $ids, $discount_id];

        $query = $database->query($sql, $binds);
        return $query ? true : false;
    }
}