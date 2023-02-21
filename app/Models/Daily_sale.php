<?php

namespace App\Models;

class Daily_sale extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'branch_id',
        'status',
        'cash_count_id',
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
        'cashier_id',
        'prepared_by',
        'prepared_on',
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
     * Get daily sale to be used for store deposit report
     */
    public function get_daily_sale_for_store_deposit_report()
    {
        
    }
}