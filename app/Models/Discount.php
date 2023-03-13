<?php

namespace App\Models;

class Discount extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        "valid_from",
        "valid_to",
        "description",
        "sundays",
        "mondays",
        "tuesdays",
        "wednesdays",
        "thursdays",
        "fridays",
        "saturdays",
        "commission_rate",
        "vat_rate",
        "other_fee",
        "discount_amount",
        "type",
        "mm_discount_share",
        "delivery_discount_share",
        "commission_base",
        "remarks",
        "merchant",
        "added_on",
        "added_by",
        "updated_on",
        "updated_by",
        "is_deleted"
    ];

    public function __construct()
    {
        $this->table = 'discount';
    }

    /**
     * Get discount by ID
     */
    public function get_discount_by_id($discount_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT discount.*,
    CONCAT(user.first_name, " ", user.last_name) AS added_by_name
FROM discount
LEFT JOIN user ON user.id = discount.added_by
WHERE discount.is_deleted = 0
    AND discount.id = ?
EOT;
        $binds = [$discount_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get discount details by ID
     */
    public function get_details_by_id($discount_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT discount.*,
    CONCAT(user.first_name, " ", user.last_name) AS added_by_name
FROM discount
LEFT JOIN user ON user.id = discount.added_by
WHERE discount.is_deleted = 0
    AND discount.id = ?
EOT;
        $binds = [$discount_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all discounts
     */
    public function get_all_discount()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT discount.*,
    CONCAT(user.first_name, " ", user.last_name) AS added_by_name
FROM discount
LEFT JOIN user ON user.id = discount.added_by
WHERE discount.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get discount details by discount name
     */
    public function get_details_by_discount_name($discount_name)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT discount.*,
    CONCAT(user.first_name, " ", user.last_name) AS added_by_name
FROM discount
LEFT JOIN user ON user.id = discount.added_by
WHERE discount.is_deleted = 0
    AND discount.name = ?
EOT;
        $binds = [$discount_name];
        $query = $database->query($sql, $binds);

        return !$query->getResult() ? false : $query->getResult()[0];
    }

    /**
     * Get discountess based on discount name, address, contact_person, contact_person_no, tin_no, bir_no
     */
    public function search($branch_id, $date_from, $date_to, $validity, $merchant, $commission_base, $valid_today)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT discount.*,
    CONCAT(user.first_name, " ", user.last_name) AS added_by_name
FROM discount
LEFT JOIN user ON user.id = discount.added_by
LEFT JOIN discount_branch ON discount.id = discount_branch.discount_id
WHERE discount.is_deleted = 0
    AND discount.merchant <> "store"
EOT;
        $binds = [];

        if ($branch_id) {
            $sql .= " AND discount_branch.branch_id = ?";
            $binds[] = $branch_id;
        }

        if ($validity) {
            $condition = ($validity == 'valid' ? "" : " NOT");
            $current_date = date("Y-m-d");
            $sql .= " AND ?$condition BETWEEN valid_from AND valid_to";
            $binds[] = $current_date;
        }

        if ($date_from) {
            $sql .= " AND discount.added_on >= ?";
            $binds[] = $date_from;
        }

        if ($date_to) {
            $sql .= " AND discount.added_on <= ?";
            $binds[] = $date_to;
        }

        if ($merchant) {
            $sql .= " AND discount.merchant = ?";
            $binds[] = $merchant;
        }

        if ($commission_base) {
            $sql .= " AND discount.commission_base = ?";
            $binds[] = $commission_base;
        }

        if ($valid_today) {
            $day_today = strtolower(date('l') . "s");
            $sql .= " AND ? = 1";
            $binds[] = $day_today;
        }

        $sql .= " GROUP BY discount.id";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

}