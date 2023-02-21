<?php

namespace App\Models;

class User_branch extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'user_id',
        'branch_id',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'user_branch';
    }

    /**
     * Get user details by ID
     */
    public function get_branches_by_user($user_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT branch.id, branch.name
FROM user_branch
LEFT JOIN branch ON branch.id = user_branch.branch_id
WHERE branch.is_deleted = 0
    AND user_branch.is_deleted = 0
    AND user_branch.user_id = ?
EOT;
        $binds = [$user_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * _attempt_delete_by_user_id
     */
    public function _attempt_delete_by_user_id($user_id = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
UPDATE user_branch
SET is_deleted = 1,
    updated_by = ?,
    updated_on = ?
WHERE user_id = ?
EOT;
        $binds = [$requested_by, $date_now, $user_id];

        return $database->query($sql, $binds);
    }

    /*
    * insert_on_duplicate
    */
    public function insert_on_duplicate($data = [], $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
INSERT INTO user_branch (user_id, branch_id, added_by, added_on, updated_by, updated_on, is_deleted)
VALUES (?, ?, ?, ?, ?, ?, ?)
ON DUPLICATE KEY UPDATE
    is_deleted = 0,
    updated_by = ?,
    updated_on = ?
EOT;
        $binds = [
            $data['user_id'],
            $data['branch_id'],
            $requested_by,
            $date_now,
            $requested_by,
            $date_now,
            0,
            $requested_by,
            $date_now
        ];
        return $database->query($sql, $binds);
    }
}