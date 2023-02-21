<?php

namespace App\Models;

class Adjustment extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'inventory_id',
        'branch_id',
        'item_id',
        'type_id',
        'counted_by',
        'physical_count',
        'unit',
        'cost',
        'approved_on',
        'approved_by',
        'disapproved_on',
        'disapproved_by',
        'difference',
        'computer_count',
        'difference_cost',
        'status',
        'remarks',
        'admin_remarks',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'adjustment';
    }

    /**
     * Get bank adjustment details by ID
     */
    public function get_details_by_id($adjustment_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT adjustment.*,
    CONCAT(approver.first_name, ' ', approver.last_name)  AS approved_by_name,
    CONCAT(disapprover.first_name, ' ', disapprover.last_name) AS disapproved_by_name,
    CONCAT(author.first_name, ' ', author.last_name)  AS added_by_name,
    CONCAT(employee.first_name, ' ', employee.last_name) AS counted_by_name,
    adjustment_type.name AS type_name,
    item.name AS item_name,
    branch.name AS branch_name
FROM adjustment
LEFT JOIN user AS approver ON approver.id = adjustment.approved_by AND approver.is_deleted = 0
LEFT JOIN user AS disapprover ON disapprover.id = adjustment.disapproved_by AND disapprover.is_deleted = 0
LEFT JOIN user AS author ON author.id = adjustment.added_by AND author.is_deleted = 0
LEFT JOIN employee ON employee.id = adjustment.counted_by AND employee.is_deleted = 0
LEFT JOIN adjustment_type ON adjustment_type.id = adjustment.type_id AND adjustment_type.is_deleted = 0
LEFT JOIN item ON item.id = adjustment.item_id AND item.is_deleted = 0
LEFT JOIN branch ON branch.id = adjustment.branch_id AND branch.is_deleted = 0
    WHERE adjustment.is_deleted = 0
EOT;

        $binds = [];
        if ($adjustment_id) {
            $sql .= " AND adjustment.id = ?";
            $binds[] = $adjustment_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all bank adjustment details
     */
    public function get_all_adjustment()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT adjustment.*,
    CONCAT(approver.first_name, ' ', approver.last_name)  AS approved_by_name,
    CONCAT(disapprover.first_name, ' ', disapprover.last_name) AS disapproved_by_name,
    CONCAT(author.first_name, ' ', author.last_name)  AS added_by_name,
    CONCAT(employee.first_name, ' ', employee.last_name) AS counted_by_name,
    adjustment_type.name AS type_name,
    item.name AS item_name,
branch.name AS branch_name
FROM adjustment
LEFT JOIN user AS approver ON approver.id = adjustment.approved_by AND approver.is_deleted = 0
LEFT JOIN user AS disapprover ON disapprover.id = adjustment.disapproved_by AND disapprover.is_deleted = 0
LEFT JOIN user AS author ON author.id = adjustment.added_by AND author.is_deleted = 0
LEFT JOIN employee ON employee.id = adjustment.counted_by AND employee.is_deleted = 0
LEFT JOIN adjustment_type ON adjustment_type.id = adjustment.type_id AND adjustment_type.is_deleted = 0
LEFT JOIN item ON item.id = adjustment.item_id AND item.is_deleted = 0
LEFT JOIN branch ON branch.id = adjustment.branch_id AND branch.is_deleted = 0
    WHERE adjustment.is_deleted = 0
EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Search
     */
    public function search($inventory_id, $branch_id, $item_id, $type_id, $counted_by, $status, $added_on_from, $added_on_to, $item_name, $limit_by, $remarks, $request_type)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT adjustment.*,
    CONCAT(approver.first_name, ' ', approver.last_name) AS approved_by_name,
    CONCAT(disapprover.first_name, ' ', disapprover.last_name) AS disapproved_by_name,
    CONCAT(author.first_name, ' ', author.last_name)  AS added_by_name,
    CONCAT(employee.first_name, ' ', employee.last_name) AS counted_by_name,
    adjustment_type.name AS type_name,
    item.name AS item_name,
    branch.name AS branch_name
FROM adjustment
LEFT JOIN user AS approver ON approver.id = adjustment.approved_by AND approver.is_deleted = 0
LEFT JOIN user AS disapprover ON disapprover.id = adjustment.disapproved_by AND disapprover.is_deleted = 0
LEFT JOIN user AS author ON author.id = adjustment.added_by AND author.is_deleted = 0
LEFT JOIN employee ON employee.id = adjustment.counted_by AND employee.is_deleted = 0
LEFT JOIN adjustment_type ON adjustment_type.id = adjustment.type_id AND adjustment_type.is_deleted = 0
LEFT JOIN item ON item.id = adjustment.item_id AND item.is_deleted = 0
LEFT JOIN branch ON branch.id = adjustment.branch_id AND branch.is_deleted = 0
WHERE adjustment.is_deleted = 0
EOT;
        $binds = [];

        if ($inventory_id) {
            $sql .= <<<EOT

AND adjustment.inventory_id = ?
EOT;
            $binds[] = $inventory_id;
        }

        if ($branch_id) {
            $sql .= <<<EOT

AND adjustment.branch_id = ?
EOT;
            $binds[] = $branch_id;
        }

        if ($item_id) {
            $sql .= <<<EOT

AND adjustment.item_id = ?
EOT;
            $binds[] = $item_id;
        }

        if ($type_id) {
            $sql .= <<<EOT

AND adjustment.type_id = ?
EOT;
            $binds[] = $type_id;
        }

        if ($counted_by) {
            $sql .= <<<EOT

AND adjustment.counted_by = ?
EOT;
            $binds[] = $counted_by;
        }

        if ($status) {
            $sql .= <<<EOT

AND adjustment.status = ?
EOT;
            $binds[] = $status;
        }

        if ($added_on_from) {
            $sql .= <<<EOT

AND adjustment.added_on >= ?
EOT;
            $binds[] = $added_on_from;
        }

        if ($added_on_to) {
            $sql .= <<<EOT

AND adjustment.added_on <= ?
EOT;
            $binds[] = $added_on_to;
        }

        if ($item_name) {
            $sql .= <<<EOT

AND adjustment.item_name LIKE ?
EOT;
            $binds[] = '%' . $item_name . '%';
        }

        if ($remarks) {
            $sql .= <<<EOT

AND adjustment.remarks LIKE ?
EOT;
            $binds[] = '%' . $remarks . '%';
        }

        switch ($request_type) {
            case 'office':

                $sql .= <<<EOT

AND author.type <> 'branch'
EOT;

                break;
            case 'store':
                $sql .= <<<EOT

AND author.type = 'branch'
EOT;
                break;
            default:
                break;
        }

        $sql .= <<<EOT

ORDER BY adjustment.added_on DESC
EOT;
        if ($limit_by) {
            $sql .= <<<EOT

LIMIT ?
EOT;
            $binds[] = (int) $limit_by;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}
