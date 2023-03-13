<?php

namespace App\Models;

class Daily_sale extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'store_deposit_id',
        'branch_id',
        'is_submitted',
        'date',
        'actual_cash_sales',
        'system_cash_sales',
        'cash_sales_overage',
        'gcash_sales',
        'food_panda_sales',
        'grab_food_sales',
        'total_sales',
        'total_expense',
        'actual_inventory_sales',
        'net_actual_sales',
        'system_inventory_sales',
        'net_system_sales',
        'overage_inventory_sales',
        'inventory_variance',
        'cashier_id',
        'prepared_by',
        'prepared_on',
        'submitted_by',
        'submitted_on',
        'inventory_checker_id',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted',
    ];

    public function __construct()
    {
        $this->table = 'daily_sale';
    }
    
    /**
     * Get daily_sale details by ID
     */
    public function get_details_by_id($daily_sale_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM daily_sale
WHERE daily_sale.is_deleted = 0
EOT;
        $binds = [];
        if (isset($daily_sale_id)) {
            $sql .= ' AND daily_sale.id = ?';
            $binds[] = $daily_sale_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all daily_sales
     */
    public function get_all_daily_sale()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM daily_sale
WHERE daily_sale.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Search daily sales
     */
    public function search($branch_id, $date, $inventory_variance_discrepancy, $cash_variance_discrepancy, $date_from, $date_to)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT daily_sale.*, branch.name AS branch_name, branch.initial_drawer AS initial_cash_in_drawer,
    IF(daily_sale.cash_sales_overage <> 0, 1, 0) AS cash_variance,
    CONCAT(cashier.first_name, " ", cashier.last_name) AS cashier_name,
    CONCAT(preparer.first_name, " ", preparer.last_name) AS preparer_name,
    CONCAT(submitter.first_name, " ", submitter.last_name) AS submitter_name,
    CONCAT(inventory_checker.first_name, " ", inventory_checker.last_name) AS inventory_checker_name
FROM daily_sale
LEFT JOIN branch ON branch.id = daily_sale.branch_id
LEFT JOIN employee AS cashier ON cashier.id = daily_sale.cashier_id
LEFT JOIN employee AS preparer ON preparer.id = daily_sale.cashier_id
LEFT JOIN employee AS submitter ON submitter.id = daily_sale.submitted_by
LEFT JOIN employee AS inventory_checker ON inventory_checker.id = daily_sale.inventory_checker_id
WHERE daily_sale.is_deleted = 0
EOT;

        $binds = [];

        if ($inventory_variance_discrepancy !== null) {
            $sql .= " AND daily_sale.inventory_variance = ?";
            $binds[] = $inventory_variance_discrepancy;
        }

        if ($cash_variance_discrepancy !== null) {
            $condition = $cash_variance_discrepancy ? "<>" : "=";
            $sql .= " AND daily_sale.cash_sales_overage $condition 0";
        }

        if ($branch_id) {
            $branch_id = explode(",", $branch_id);
            $sql .= " AND daily_sale.branch_id IN ?";
            $binds[] = $branch_id;
        }
        if ($date) {
            $sql .= " AND daily_sale.date = ?";
            $binds[] = $date;
        }
        if ($date_from) {
            $sql .= " AND daily_sale.date >= ?";
            $binds[] = $date_from;
        }
        if ($date_to) {
            $sql .= " AND daily_sale.date <= ?";
            $binds[] = $date_to;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Update store deposit id
     */
    public function update_store_deposit($store_deposit_id, $submitter, $sales_date_from, $sales_date_to, $branch_id)
    {
        $database = \Config\Database::connect();
        $current_datetime = date("Y-m-d H:i:s");

        $sql = <<<EOT
UPDATE daily_sale
SET store_deposit_id = ?, is_submitted = 1, submitted_by = ?, submitted_on = ?
WHERE daily_sale.branch_id = ?
    AND daily_sale.date BETWEEN ? AND ?
    AND daily_sale.is_deleted = 0
EOT;

        $binds = [$store_deposit_id, $submitter, $current_datetime, $branch_id, $sales_date_from, $sales_date_to];

        return $database->query($sql, $binds);
    }

    /**
     * Compute total sales per transaction type
     */
    public function compute_total_sales_per_transaction_type($transaction_type, $date_from, $date_to)
    {
        $database = \Config\Database::connect();

        switch($transaction_type) {
            case "gcash":
                $sales_amount = "daily_sale.gcash_sales";
                break;
            case "food_panda":
                $sales_amount = "daily_sale.food_panda_sales";
                break;
            case "grab_food":
                $sales_amount = "daily_sale.grab_food_sales";
                break;
            default:
                $sales_amount = "daily_sale.actual_cash_sales";
                break;
        }

        $sql = <<<EOT
SELECT SUM($sales_amount) AS total_sales
FROM daily_sale
WHERE daily_sale.is_deleted = 0
    AND daily_sale.date BETWEEN ? AND ?
EOT;

        $binds = [$date_from, $date_to];

        $query = $database->query($sql, $binds);
        return ($query AND $query->getResultArray()) ? $query->getResultArray()[0] : false;
    }
}