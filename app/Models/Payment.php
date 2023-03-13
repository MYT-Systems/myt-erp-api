<?php

namespace App\Models;

class Payment extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'branch_id',
        'order_id',
        'price_level_type_id',
        'transaction_no',
        'reference_no',
        'payment_type',
        'paid_amount',
        'subtotal',
        'merchant_discount_id',
        'merchant_discount_share',
        'discount',
        'additional_discounts',
        'grand_total',
        'commission',
        'remarks',
        'acc_no',
        'cvc_cvv',
        'card_type',
        'card_expiry',
        'card_bank',
        'proof',
        'or_no',
        'void_reason',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'payment';
    }
    
    /**
     * Get payment details by ID
     */
    public function get_details_by_id($payment_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM payment
WHERE payment.is_deleted = 0
    AND payment.id = ?
EOT;
        $binds = [$payment_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all payments
     */
    public function get_all_payment()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM payment
WHERE payment.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get payments based on payment name, contact_person, phone_no, tin_no, bir_no, email
     */
    public function search($name = null, $is_addon = null, $details = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM payment
WHERE payment.is_deleted = 0
EOT;
        $binds = [];

        if ($name) {
            $sql .= " AND payment.name REGEXP ?";
            $name    = str_replace(' ', '|', $name);
            $binds[] = $name;
        }

        if ($is_addon) {
            $sql .= " AND is_addon = ?";
            $binds[] = $is_addon;
        }

        if ($details) {
            $sql .= " AND details REGEXP ?";
            $details = str_replace(' ', '|', $details);
            $binds[]        = $details;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get the sales for today
     */
    public function get_sales_for_today()
    {
        $database = \Config\Database::connect();
        $date_now = date('Y-m-d');
        $sql = <<<EOT
SELECT SUM(grand_total) AS total_sales
FROM payment
WHERE payment.is_deleted = 0
    AND payment.added_on >= ?
EOT;
        $binds = [$date_now];

        $query = $database->query($sql, $binds);
        return $query ? (float)$query->getResultArray()[0]['total_sales'] : false;
    }

    /**
     * Get the sales between two dates
     */
    public function get_sales_between_two_dates($start_date = null, $end_date = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT SUM(grand_total) AS total_sales
FROM payment
WHERE payment.is_deleted = 0
    AND payment.added_on BETWEEN ? AND ?
EOT;
        $binds = [$start_date, $end_date];

        $query = $database->query($sql, $binds);
        return $query ? (float)$query->getResultArray()[0]['total_sales'] : false;
    }

    /**
     * Get sales based on parameters
     * get_sales(true, $branch_id)
     */
    public function get_sales($today = null, $branch_id = null, $start_date = null, $end_date = null, $price_level_type_id = null, $payment_type = null, $transaction_type = null)
    {
        $database = \Config\Database::connect();
        $date_now = date('Y-m-d');
        $sql = <<<EOT
SELECT SUM(IFNULL(payment.grand_total, 0)) AS total_sales
FROM payment
LEFT JOIN `order` ON `order`.id = payment.order_id
WHERE payment.is_deleted = 0
EOT;
        $binds = [];

        if ($today) {
            $sql .= " AND DATE(payment.added_on) = ?";
            $binds[] = $date_now;
        }
        if ($start_date) {
            $sql .= " AND DATE(payment.added_on) >= ?";
            $binds[] = $start_date;
        }
        if ($end_date) {
            $sql .= " AND DATE(payment.added_on) <= ?";
            $binds[] = $end_date;
        }
        if ($branch_id) {
            $sql .= " AND payment.branch_id = ?";
            $binds[] = $branch_id;
        }
        if ($price_level_type_id) {
            $sql .= " AND payment.price_level_type_id = ?";
            $binds[] = $price_level_type_id;
        }
        if ($payment_type) {
            $sql .= " AND payment.payment_type = ?";
            $binds[] = $payment_type;
        }
        if ($transaction_type) {
            $sql .= " AND `order`.transaction_type = ?";
            $binds[] = $transaction_type;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray()[0]['total_sales'] : false;
    }

    /**
     * Get sales report
     */
    public function get_current_sales_report($branch_id = null)
    {
        $database = \Config\Database::connect();
        $date_now = date('Y-m-d');
        $sql = <<<EOT
SELECT SUM(grand_total) AS total_sales,
    (SELECT SUM(grand_total)
        FROM payment
        WHERE payment.is_deleted = 0
            AND payment.added_on >= ?
            AND payment.payment_type = 'cash'
            AND payment.branch_id = ?
    ) AS cash_sales,
    (SELECT SUM(grand_total)
        FROM payment
        WHERE payment.is_deleted = 0
            AND payment.added_on >= ?
            AND payment.payment_type = 'gcash'
            AND payment.branch_id = ?
    ) AS gcash_sales,
    (SELECT SUM(grand_total)
        FROM payment
        WHERE payment.is_deleted = 0
            AND payment.added_on >= ?
            AND payment.payment_type = 'food_panda'
            AND payment.branch_id = ?
    ) AS food_panda_sales,
    (SELECT SUM(grand_total)
        FROM payment
        WHERE payment.is_deleted = 0
            AND payment.added_on >= ?
            AND payment.payment_type = 'grab_food_sales'
            AND payment.branch_id = ?
    ) AS grab_food_sales
FROM payment
WHERE payment.is_deleted = 0
    AND payment.added_on >= CURDATE()
    AND payment.branch_id = ?
EOT;
        $binds = [$date_now, $branch_id, $date_now, $branch_id, $date_now, $branch_id, $date_now, $branch_id, $branch_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get sales by order id
     */
    public function get_details_by_order_id($order_id = null, $payment_type = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT payment.*
FROM payment
LEFT JOIN price_level_type
    ON price_level_type.id = payment.price_level_type_id
WHERE payment.is_deleted = 0
    AND payment.order_id = ?
EOT;
        $binds = [$order_id];

        if ($payment_type) {
            $sql .= " AND payment.payment_type = ?";
            $binds[] = $payment_type;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get data for discount reports
     */
    public function get_discount_reports($branch_id, $date, $transaction_type)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT payment.*, branch.name AS branch_name,
    payment.subtotal AS gross_value,
    payment.discount AS partner_funded_discount,
    (payment.grand_total + payment.merchant_discount_share) AS sales_revenue
FROM payment
LEFT JOIN order ON payment.order_id = order.id
    AND order.is_deleted = 0
LEFT JOIN branch ON branch.id = payment.branch_id
WHERE payment.is_deleted = 0
    AND payment.merchant_discount_id IS NOT NULL
    AND payment.branch_id = ?
EOT;
        $binds = [$branch_id];

        if ($date) {
            $sql .= " AND DATE(payment.added_on) = ?";
            $binds[] = $date;
        }

        if ($transaction_type) {
            $sql .= " AND order.transaction_type = ?";
            $binds[] = $transaction_type;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get data for discount reports
     */
    public function get_discount_invoice($branch_id, $date, $transaction_type)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT computation.*,
    (computation.commission + computation.total_fees_and_adjustments) AS vat_and_deductions,
    (computation.sales_revenue + (computation.commission + computation.total_fees_and_adjustments)) AS total_amount_paid_out
FROM (
    SELECT SUM(payment.subtotal) AS gross_value,
        SUM(payment.grand_total + payment.merchant_discount_share) AS sales_revenue,
        (CASE
            WHEN discount.commission_base = "gross_sales" THEN ((discount.commission_rate/100) * SUM(payment.subtotal))
            WHEN discount.commission_base = "sales_revenue" THEN ((discount.commission_rate/100) * SUM(payment.grand_total + payment.merchant_discount_share))
        END) AS commission,
        discount.other_fee AS total_fees_and_adjustments
    FROM payment
    LEFT JOIN order ON payment.order_id = order.id
        AND order.is_deleted = 0
    LEFT JOIN discount ON discount.id = payment.merchant_discount_id
    LEFT JOIN branch ON branch.id = payment.branch_id
    WHERE payment.is_deleted = 0
        AND payment.merchant_discount_id IS NOT NULL
        AND payment.branch_id = ?
EOT;
        $binds = [$branch_id];

        if ($date) {
            $sql .= " AND DATE(payment.added_on) = ?";
            $binds[] = $date;
        }

        if ($transaction_type) {
            $sql .= " AND order.transaction_type = ?";
            $binds[] = $transaction_type;
        }

        $sql .= " ) AS computation";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}