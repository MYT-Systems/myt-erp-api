<?php

namespace App\Models;

class Store_deposit extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'branch_id',
        'status',
        'transaction_type',
        'amount',
        'sales_date_from',
        'sales_date_to',
        'reference_no',
        'deposited_to',
        'deposited_on',
        'deposited_by',
        'posted_on',
        'posted_by',
        'checked_on',
        'checked_by',
        'added_on',
        'added_by',
        'updated_on',
        'updated_by',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'store_deposit';
    }

    public function search($transaction_type, $branch_id, $status, $deposited_to, $date_from, $date_to, $wide_search)
    {
        $db = db_connect();

        $sql = <<<EOT
SELECT store_deposit.*, daily_sale.date AS sales_date, branch.name AS branch_name, 
    daily_sale.id AS daily_sale_id,
    (CASE
        WHEN transaction_type = "cash" THEN daily_sale.actual_cash_sales
        WHEN transaction_type = "gcash" THEN daily_sale.gcash_sales
        WHEN transaction_type = "food_panda" THEN daily_sale.food_panda_sales
        WHEN transaction_type = "grab_food" THEN daily_sale.grab_food_sales
    END) AS total_sales,
    bank.name AS bank_deposited,
    CONCAT(first_name, " ", last_name) AS deposited_by,
    store_deposit_attachment.base64 AS image_attachment
FROM store_deposit
LEFT JOIN store_deposit_attachment
    ON store_deposit_attachment.store_deposit_id = store_deposit.id
LEFT JOIN daily_sale
    ON daily_sale.store_deposit_id = store_deposit.id
        AND daily_sale.branch_id = store_deposit.branch_id
LEFT JOIN branch
    ON branch.id = store_deposit.branch_id
LEFT JOIN bank
    ON bank.id = store_deposit.deposited_to
LEFT JOIN employee
    ON employee.id = store_deposit.deposited_by
WHERE store_deposit.is_deleted = 0
EOT;

        $binds = [];
        if ($branch_id) {
            $sql .= " AND store_deposit.branch_id = ?";
            $binds[] = $branch_id;
        }

        if ($transaction_type) {
            $sql .= " AND store_deposit.transaction_type = ?";
            $binds[] = $transaction_type;
        }

        if ($status) {
            $sql .= " AND store_deposit.status = ?";
            $binds[] = $status;
        }

        if ($deposited_to) {
            $sql .= " AND store_deposit.deposited_to = ?";
            $binds[] = $deposited_to;
        }

        if ($date_from) {
            $sql .= " AND DATE(store_deposit.deposited_on) >= ?";
            $binds[] = $date_from;
        }

        if ($date_to) {
            $sql .= " AND DATE(store_deposit.deposited_on) <= ?";
            $binds[] = $date_to;
        }

        if ($wide_search) {
            $sql .= " AND (branch.name LIKE ? OR store_deposit.daily_sale_id LIKE ? OR bank.name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?)";
            for ($i=0; $i<4; $i++)
                $binds[] = "%" . $wide_search . "%";
        }

        $query = $db->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    public function get_store_deposit_within_date_range($branch_id, $sales_date_from, $sales_date_to)
    {
        $db = db_connect();
        $sql = <<<EOT
SELECT *
FROM store_deposit
WHERE is_deleted = 0
    AND branch_id = ?
    AND (
        ? <= sales_date_to OR
        ? < sales_date_to
    )
EOT;
        $binds = [$branch_id, $sales_date_from, $sales_date_to];
        $query = $db->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}