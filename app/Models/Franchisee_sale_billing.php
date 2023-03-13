<?php

namespace App\Models;

class Franchisee_sale_billing extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'id',
        'branch_id',
        'franchisee_id',
        'month',
        'total_sale',
        'total_net',
        'royalty_fee',
        'twelve_vat_from_royalty_fee',
        'royalty_fee_net_of_vat',
        's_marketing_fee',
        'twelve_vat_from_s_marketing_fee',
        's_marketing_fee_net_of_vat',
        'total_royalty_fee_and_s_marketing_fee',
        'total_amount_due',
        'status',
        'payment_status',
        'balance',
        'discount',
        'discount_remarks',
        'paid_amount',
        'approved_by',
        'approved_on',
        'prepared_by',
        'prepared_on',
        'printed_by',
        'printed_on',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'franchisee_sale_billing';
    }

    /**
     * Get franchisee_sale_billing by ID
     */
    public function get_details_by_id($franchisee_sale_billing_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM branch WHERE branch.id = franchisee_sale_billing.branch_id) AS branch_name,
    (SELECT name FROM franchisee WHERE franchisee.id = franchisee_sale_billing.franchisee_id) AS franchisee_name,
    (SELECT franchisee.marketing_fee FROM franchisee WHERE franchisee.id = franchisee_sale_billing.franchisee_id) AS marketing_fee_rate,
    (SELECT franchisee.royalty_fee FROM franchisee WHERE franchisee.id = franchisee_sale_billing.franchisee_id) AS royalty_fee_rate
FROM franchisee_sale_billing
WHERE franchisee_sale_billing.is_deleted = 0
    AND franchisee_sale_billing.id = ?
EOT;
        $binds = [$franchisee_sale_billing_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all franchisee_sale_billing
     */
    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM branch WHERE branch.id = franchisee_sale_billing.branch_id) AS branch_name,
    (SELECT name FROM franchisee WHERE franchisee.id = franchisee_sale_billing.franchisee_id) AS franchisee_name
FROM franchisee_sale_billing
WHERE is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    public function search_missing($branch_id, $franchisee_id, $month, $year, $payment_status, $branch_name, $type, $status, $franchisee_name)
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
SELECT franchisee.id AS franchisee_id, franchisee.branch_id, franchisee.opening_start,
    (SELECT name FROM branch WHERE branch.id = franchisee.branch_id) AS branch_name,
    franchisee.name, franchisee.contact_person, franchisee.contact_number, franchisee.franchised_on,
    franchisee.royalty_fee, franchisee.marketing_fee
FROM franchisee
WHERE franchisee.is_deleted = 0
    AND franchisee.id NOT IN (
        SELECT franchisee_sale_billing.franchisee_id
        FROM franchisee_sale_billing
        WHERE franchisee_sale_billing.is_deleted = 0
EOT;
        $binds = [];
        
        if ($year) {
            $sql .= ' AND YEAR(franchisee_sale_billing.month) = ? AND MONTH(franchisee_sale_billing.month) = ?';
            $binds[] = $year;
            $binds[] = $month;
        } elseif ($month) {
            $month = date("Y-m-d", strtotime($month));
            $last_day_of_the_month = date("Y-m-t", strtotime($month));

            $sql .= ' AND franchisee_sale_billing.month BETWEEN ? AND ?';
            $binds[] = $month;
            $binds[] = $last_day_of_the_month;
        }

        $sql .= ')';

        if ($branch_id) {
            $sql .= ' AND franchisee.branch_id = ?';
            $binds[] = $branch_id;
        }

        if ($franchisee_id) {
            $sql .= ' AND franchisee.id = ?';
            $binds[] = $franchisee_id;
        }

        if ($branch_name) {
            $sql .= ' AND (SELECT name FROM branch WHERE branch.id = franchisee.branch_id) LIKE ?';
            $binds[] = '%' . $branch_name . '%';
        }

        if ($year) {
            $sql .= " AND (YEAR(franchisee.opening_start) < ? OR (YEAR(franchisee.opening_start) = ? AND MONTH(franchisee.opening_start) <= ?))";
            $binds[] = $year;
            $binds[] = $year;
            $binds[] = $month;
        } elseif ($month) {
            $sql .= " AND ((YEAR(franchisee.opening_start) < ?) OR (YEAR(franchisee.opening_start) = ? AND MONTH(franchisee.opening_start) <= ?))";
            $year = date('Y', strtotime($month));
            $month = date('m', strtotime($month));
            $binds[] = $year;
            $binds[] = $year;
            $binds[] = $month;
        }

        if ($franchisee_name) {
            $sql .= ' AND franchisee.name LIKE ?';
            $binds[] = '%' . $franchisee_name . '%';
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Search
     */
    public function search($branch_id, $franchisee_id, $month, $payment_status, $branch_name, $status, $month_from, $month_to, $franchisee_name)
    {
        $database = \Config\Database::connect();
        
        $sql = <<<EOT
SELECT franchisee_sale_billing.*,
    branch.name AS branch_name,
    franchisee.name AS franchisee_name,
    (franchisee_sale_billing.total_sale/COUNT(fs_billing_item.id)) AS average_sales
FROM franchisee_sale_billing
LEFT JOIN fs_billing_item ON fs_billing_item.fs_billing_id = franchisee_sale_billing.id
LEFT JOIN franchisee ON franchisee.id = franchisee_sale_billing.franchisee_id
LEFT JOIN branch ON branch.id = franchisee_sale_billing.branch_id
WHERE franchisee_sale_billing.is_deleted = 0
EOT;
        $binds = [];

        if ($branch_id) {
            $sql .= ' AND franchisee_sale_billing.branch_id = ?';
            $binds[] = $branch_id;
        }

        if ($franchisee_id) {
            $sql .= ' AND franchisee_sale_billing.franchisee_id = ?';
            $binds[] = $franchisee_id;
        }

        if ($month) {
            $last_day_of_the_month = date("Y-m-t", strtotime($month));
            $sql .= ' AND franchisee_sale_billing.month BETWEEN ? AND ?';
            $binds[] = $month;
            $binds[] = $last_day_of_the_month;
        }

        if ($payment_status) {
            $sql .= ' AND franchisee_sale_billing.payment_status = ?';
            $binds[] = $payment_status;
        }

        if ($branch_name) {
            $sql .= ' AND branch.name LIKE ?';
            $binds[] = '%' . $branch_name . '%';
        }

        if ($status) {
            $sql .= ' AND franchisee_sale_billing.status = ?';
            $binds[] = $status;
        }

        if ($month_from) {
            $sql .= ' AND franchisee_sale_billing.month >= ?';
            $binds[] = $month_from;
        }

        if ($month_to) {
            $sql .= ' AND franchisee_sale_billing.month <= ?';
            $binds[] = $month_to;
        }

        if ($franchisee_name) {
            $sql .= ' AND franchisee.name LIKE ?';
            $binds[] = '%' . $franchisee_name . '%';
        }

        $sql .= ' GROUP BY franchisee_sale_billing.id';
        $sql .= ' ORDER BY franchisee_sale_billing.id DESC';

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}