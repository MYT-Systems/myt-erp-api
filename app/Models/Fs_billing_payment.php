<?php

namespace App\Models;

class Fs_billing_payment extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'franchisee_id',
        'fs_billing_id',
        'payment_type',
        'payment_date',
        'remarks',
        'from_bank_id',
        'from_bank_name',
        'to_bank_id',
        'to_bank_name',
        'cheque_number',
        'cheque_date',
        'reference_number',
        'transaction_number',
        'payment_description',
        'term_day',
        'delivery_address',
        'paid_amount',
        'discount',
        'grand_total',
        'subtotal',
        'service_fee',
        'delivery_fee',
        'withholding_tax',
        'is_done',
        'deposit_date',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'fs_billing_payment';
    }

    /**
     * Get fs_billing_payment by ID
     */
    public function get_details_by_id($fs_billing_payment_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT CONCAT (first_name, ' ', last_name) FROM user WHERE id = fs_billing_payment.added_by) AS added_by_name
FROM fs_billing_payment
WHERE fs_billing_payment.is_deleted = 0
    AND fs_billing_payment.id = ?
EOT;
        $binds = [$fs_billing_payment_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all fs_billing_payment
     */
    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT CONCAT (first_name, ' ', last_name) FROM user WHERE id = fs_billing_payment.added_by) AS added_by_name,
    (SELECT name FROM bank WHERE id = fs_billing_payment.to_bank_id) as to_bank_name
FROM fs_billing_payment
WHERE is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all fs_billing_payment by franchisee_sale_id
     */
    public function get_details_by_franchisee_sales_id($franchisee_sale_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT CONCAT (first_name, ' ', last_name) FROM user WHERE id = fs_billing_payment.added_by) AS added_by_name
FROM fs_billing_payment
WHERE fs_billing_payment.is_deleted = 0
    AND fs_billing_payment.franchisee_sale_id = ?
EOT;
        $binds = [$franchisee_sale_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Search
     */
    public function search($franchisee_id, $franchisee_sale_id, $payment_method, $payment_date_from, $payment_date_to, $from_bank_id, $cheque_number, $cheque_date_from, $cheque_date_to, $reference_number, $transaction_number, $branch_name)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT CONCAT (first_name, ' ', last_name) FROM user WHERE id = fs_billing_payment.added_by) AS added_by_name,
    (SELECT name FROM branch WHERE id = (SELECT branch_id FROM franchisee WHERE id = fs_billing_payment.franchisee_id)) as branch_name,
    (SELECT name FROM bank WHERE id = fs_billing_payment.from_bank_id) as from_bank_name,
    (SELECT name FROM bank WHERE id = fs_billing_payment.to_bank_id) as to_bank_name
FROM fs_billing_payment
WHERE fs_billing_payment.is_deleted = 0
EOT;
        $binds = [];

        if ($franchisee_id) {
            $sql .= ' AND fs_billing_payment.franchisee_id = ?';
            $binds[] = $franchisee_id;
        }

        if ($franchisee_sale_id) {
            $sql .= ' AND fs_billing_payment.franchisee_sale_id = ?';
            $binds[] = $franchisee_sale_id;
        }

        if ($payment_method) {
            $sql .= ' AND fs_billing_payment.payment_method = ?';
            $binds[] = $payment_method;
        }

        if ($payment_date_from) {
            $sql .= ' AND fs_billing_payment.payment_date >= ?';
            $binds[] = $payment_date_from;
        }

        if ($payment_date_to) {
            $sql .= ' AND fs_billing_payment.payment_date <= ?';
            $binds[] = $payment_date_to;
        }

        if ($from_bank_id) {
            $sql .= ' AND fs_billing_payment.from_bank_id LIKE ?';
            $binds[] = '%' . $from_bank_id . '%';
        }

        if ($cheque_number) {
            $sql .= ' AND fs_billing_payment.cheque_number LIKE ?';
            $binds[] = '%' . $cheque_number . '%';
        }

        if ($cheque_date_from) {
            $sql .= ' AND fs_billing_payment.cheque_date >= ?';
            $binds[] = $cheque_date_from;
        }

        if ($cheque_date_to) {
            $sql .= ' AND fs_billing_payment.cheque_date <= ?';
            $binds[] = $cheque_date_to;
        }

        if ($reference_number) {
            $sql .= ' AND fs_billing_payment.reference_number LIKE ?';
            $binds[] = '%' . $reference_number . '%';
        }

        if ($transaction_number) {
            $sql .= ' AND fs_billing_payment.transaction_number LIKE ?';
            $binds[] = '%' . $transaction_number . '%';
        }

        if ($branch_name) {
            $sql .= ' AND (SELECT name FROM branch WHERE id = (SELECT branch_id FROM franchisee WHERE id = fs_billing_payment.franchisee_id)) LIKE ?';
            $binds[] = '%' . $branch_name . '%';
        }
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /*
    * Get payment by franchisee sale billing id
    */
    public function get_payment_by_franchisee_sale_billing_id($franchisee_sale_billing_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM fs_billing_payment
WHERE fs_billing_payment.is_deleted = 0
    AND fs_billing_payment.fs_billing_id = ?
EOT;
        $binds = [$franchisee_sale_billing_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get payments with billing details
     */
    public function get_payment_with_billing_details($franchisee_id, $franchisee_name, $branch_id, $type, $date_from, $date_to, $payment_status)
    {
        $database = \Config\Database::connect();

        switch ($type) {
            case "royalty_fee":
                $fees = "IFNULL(franchisee_sale_billing.royalty_fee_net_of_vat, 0.00) AS royalty_fee, '0.00' AS marketing_fee";
                break;
            case "marketing_fee":
                $fees = "'0.00' AS royalty_fee, IFNULL(franchisee_sale_billing.s_marketing_fee_net_of_vat, 0.00) AS marketing_fee";
                break;
            default:
                $fees = "IFNULL(franchisee_sale_billing.royalty_fee_net_of_vat, 0.00) AS royalty_fee, IFNULL(franchisee_sale_billing.s_marketing_fee_net_of_vat, 0.00) AS marketing_fee";
                break;
        }

        $sql = <<<EOT
SELECT franchisee_sale_billing.id, fs_billing_payment.payment_date, franchisee.name AS franchisee_name, branch.name AS branch_name, 'Billing' AS doc_type,
    $fees,
    "0.00" AS sales_invoice,
    fs_billing_payment.deposit_date,
    bank.name AS deposit_to,
    fs_billing_payment.cheque_number,
    fs_billing_payment.cheque_date,
    fs_billing_payment.reference_number,
    fs_billing_payment.payment_type AS pay_mode
FROM franchisee_sale_billing
LEFT JOIN fs_billing_payment
    ON fs_billing_payment.fs_billing_id = franchisee_sale_billing.id
LEFT JOIN franchisee
    ON franchisee.id = franchisee_sale_billing.franchisee_id
LEFT JOIN branch
    ON branch.id = franchisee_sale_billing.branch_id
LEFT JOIN bank
    ON bank.id = fs_billing_payment.to_bank_id
WHERE franchisee_sale_billing.is_deleted = 0
    AND fs_billing_payment.is_deleted = 0
EOT;
        $binds = [];

        if ($franchisee_id) {
            $sql .= ' AND franchisee_sale_billing.franchisee_id = ?';
            $binds[] = $franchisee_id;
        }

        if ($franchisee_name) {
            $sql .= ' AND franchisee.name LIKE ?';
            $binds[] = "%" . $franchisee_name . "%";
        }

        if ($branch_id) {
            $sql .= ' AND franchisee_sale_billing.branch_id = ?';
            $binds[] = $branch_id;
        }

        if ($date_from) {
            $sql .= ' AND fs_billing_payment.payment_date >= ?';
            $binds[] = $date_from;
        }

        if ($date_to) {
            $sql .= ' AND fs_billing_payment.payment_date <= ?';
            $binds[] = $date_to;
        }

        if ($payment_status) {
            if ($payment_status == 'paid') {
                $sql .= ' AND franchisee_sale_billing.payment_status = "closed_bill"';
            } else {
                $sql .= ' AND franchisee_sale_billing.payment_status = "open_bill"';
            }
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}