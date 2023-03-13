<?php

namespace App\Models;

class Transaction extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
    ];

    public function __construct()
    {
        $this->table = 'branch';
    }

    /**
     * Get all receivables from franchisee sale billing, franchisee sale, franchisee
     */
    public function get_all_receivables($branch_id, $date_from, $date_to, $type = null) {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT type, id, branch_id, franchisee_id, SUM(balance) AS balance, SUM(paid_amount) AS paid_amount, SUM(grand_total) AS grand_total, added_on, is_deleted
FROM
(
    (
    SELECT
        'franchisee_sale_billing' AS type,
        franchisee_sale_billing.id,
        franchisee_sale_billing.branch_id,
        franchisee_sale_billing.franchisee_id,
        franchisee_sale_billing.balance AS balance,
        franchisee_sale_billing.paid_amount AS paid_amount,
        franchisee_sale_billing.total_sale AS grand_total,
        franchisee_sale_billing.month AS month,
        franchisee_sale_billing.added_on,
        franchisee_sale_billing.is_deleted,
        ( SELECT franchisee.name FROM franchisee WHERE franchisee.id = franchisee_sale_billing.franchisee_id ) AS franchisee_name
    FROM franchisee_sale_billing
    WHERE franchisee_sale_billing.balance > 0
    )

    UNION ALL

    (
    SELECT
        'franchisee_sale' AS type,
        franchisee_sale.id,
        franchisee_sale.buyer_branch_id AS branch_id,
        franchisee_sale.franchisee_id,
        franchisee_sale.balance AS balance,
        franchisee_sale.paid_amount AS paid_amount,
        franchisee_sale.grand_total AS grand_total,
        franchisee_sale.added_on,
        NULL as month,
        franchisee_sale.is_deleted,
        ( SELECT franchisee.name FROM franchisee WHERE franchisee.id = franchisee_sale.franchisee_id ) AS franchisee_name
    FROM franchisee_sale
    WHERE franchisee_sale.balance > 0
        AND franchisee_sale.fs_status IN ('invoiced')
    )

    UNION ALL

    (
    SELECT
        'franchisee' AS type,
        franchisee.id,
        franchisee.branch_id branch_id,
        franchisee.id AS franchisee_id,
        franchisee.balance AS balance,
        franchisee.paid_amount AS paid_amount,
        franchisee.grand_total AS grand_total,
        franchisee.added_on,
        NULL as month,
        franchisee.is_deleted,
        franchisee.name AS franchisee_name
    FROM franchisee
    WHERE franchisee.balance > 0
    )
) AS receivables
WHERE receivables.is_deleted = 0
EOT;

        $binds = []; 

        if ($branch_id) {
            $sql .= ' AND receivables.branch_id = ?';
            $binds[] = $branch_id;
        }

        if ($date_from) {
            $sql .= ' AND receivables.added_on >= ?';
            $binds[] = $date_from;
        }

        if ($date_to) {
            $sql .= ' AND receivables.added_on <= ?';
            $binds[] = $date_to;
        }

        if ($type) {
            $sql .= ' AND receivables.type = ?';
            $binds[] = $type;
        }

        $sql .= ' GROUP BY branch_id, type';

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all receivables from franchisee sale billing, franchisee sale, franchisee by branch id
     */
    public function get_all_receivables_by_branch_id($branch_id, $type) {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM
(
    (
    SELECT
        'franchisee_sale_billing' AS type,
        franchisee_sale_billing.id,
        franchisee_sale_billing.branch_id,
        franchisee_sale_billing.franchisee_id,
        SUM(franchisee_sale_billing.balance) AS balance,
        SUM(franchisee_sale_billing.paid_amount) AS paid_amount,
        SUM(franchisee_sale_billing.total_sale) AS grand_total,
        franchisee_sale_billing.added_on,
        franchisee_sale_billing.month,
        franchisee_sale_billing.is_deleted,
        ( SELECT franchisee.name FROM franchisee WHERE franchisee.id = franchisee_sale_billing.franchisee_id ) AS franchisee_name,
        ( SELECT name FROM branch WHERE branch.id = franchisee_sale_billing.branch_id ) AS franchised_branch_name
    FROM franchisee_sale_billing
    WHERE franchisee_sale_billing.balance > 0
    GROUP BY id
    )

    UNION ALL

    (
    SELECT
        'franchisee_sale' AS type,
        franchisee_sale.id,
        franchisee_sale.buyer_branch_id AS branch_id,
        franchisee_sale.franchisee_id,
        SUM(franchisee_sale.balance) AS balance,
        SUM(franchisee_sale.paid_amount) AS paid_amount,
        SUM(franchisee_sale.grand_total) AS grand_total,
        franchisee_sale.added_on,
        NULL as month,
        franchisee_sale.is_deleted,
        ( SELECT franchisee.name FROM franchisee WHERE franchisee.id = franchisee_sale.franchisee_id ) AS franchisee_name,
        ( SELECT name FROM branch WHERE branch.id = (SELECT branch_id FROM franchisee WHERE franchisee.id = franchisee_sale.franchisee_id) ) AS franchised_branch_name
    FROM franchisee_sale
    WHERE franchisee_sale.balance > 0
        AND franchisee_sale.fs_status IN ('invoiced')
    GROUP BY id
    )

    UNION ALL

    (
    SELECT
        'franchisee' AS type,
        franchisee.id,
        franchisee.branch_id branch_id,
        franchisee.id AS franchisee_id,
        SUM(franchisee.balance) AS balance,
        SUM(franchisee.paid_amount) AS paid_amount,
        SUM(franchisee.grand_total) AS grand_total,
        franchisee.added_on,
        NULL as month,
        franchisee.is_deleted,
        franchisee.name AS franchisee_name,
        ( SELECT name FROM branch WHERE branch.id = franchisee.branch_id ) AS franchised_branch_name
    FROM franchisee
    WHERE franchisee.balance > 0
    GROUP BY id
    )
) AS receivables
WHERE receivables.is_deleted = 0
EOT;

        $binds = []; 

        if ($branch_id) {
            $sql .= ' AND receivables.branch_id = ?';
            $binds[] = $branch_id;
        }

        if ($type) {
            $sql .= ' AND receivables.type = ?';
            $binds[] = $type;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all payments from franchisee_payment, franchisee_sale_payment, fs_billing_payment by branch id
     */
    public function get_all_filtered_payments($branch_id, $deposited_to, $date_from, $date_to, $franchisee_id, $franchisee_name, $payment_status, $payment_mode, $type, $is_done) {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM
(
    (
    SELECT
        'franchisee_payment' AS type,
        franchisee_payment.id,
        franchisee_id AS payable_id,
        franchisee_payment.branch_id,
        franchisee_payment.franchisee_id,
        (SELECT grand_total FROM franchisee WHERE franchisee.id = franchisee_payment.franchisee_id) AS amount,
        franchisee_payment.amount AS paid_amount,
        franchisee_payment.added_on,
        franchisee_payment.is_deleted,
        franchisee_payment.to_bank_id,
        ( SELECT franchisee.name FROM franchisee WHERE franchisee.id = franchisee_payment.franchisee_id ) AS franchisee_name,
        ( SELECT name FROM branch WHERE branch.id = franchisee_payment.branch_id ) AS franchised_branch_name,
        franchisee_payment.invoice_no AS invoice_no,
        NULL AS doc_no,
        ( SELECT franchisee.grand_total FROM franchisee WHERE franchisee.id = franchisee_payment.franchisee_id ) AS grand_total,
        ( SELECT franchisee.balance FROM franchisee WHERE franchisee.id = franchisee_payment.franchisee_id ) AS balance,
        franchisee_payment.payment_method AS payment_mode,
        ( SELECT name FROM bank WHERE bank.id = franchisee_payment.to_bank_id ) AS deposit_to,
        NULL AS received_to,
        NULL AS received_from,
        franchisee_payment.is_done AS is_done,
        franchisee_payment.cheque_number AS check_no,
        franchisee_payment.cheque_date AS check_date,
        franchisee_payment.reference_number AS ref_no,
        franchisee_payment.payment_date as payment_date,
        franchisee_payment.deposit_date as deposit_date,
        CONCAT('Franchisee - ', franchisee_payment.franchisee_id) AS payment_for,
        franchisee_payment.remarks
    FROM franchisee_payment
    WHERE franchisee_payment.amount > 0
    GROUP BY id
    )

    UNION ALL

    (
    SELECT
        'franchisee_sale_payment' AS type,
        franchisee_sale_payment.id,
        franchisee_sale_id AS payable_id,
        (SELECT franchisee_sale.buyer_branch_id FROM franchisee_sale WHERE franchisee_sale.id = franchisee_sale_payment.franchisee_id) AS branch_id,
        franchisee_sale_payment.franchisee_id,
        franchisee_sale_payment.grand_total AS amount,
        franchisee_sale_payment.paid_amount AS paid_amount,
        franchisee_sale_payment.added_on,
        franchisee_sale_payment.is_deleted,
        franchisee_sale_payment.to_bank_id,
        ( SELECT franchisee.name FROM franchisee WHERE franchisee.id = franchisee_sale_payment.franchisee_id ) AS franchisee_name,
        ( SELECT name FROM branch WHERE branch.id = (SELECT franchisee_sale.buyer_branch_id FROM franchisee_sale WHERE franchisee_sale.id = franchisee_sale_payment.franchisee_id) ) AS franchised_branch_name,
        franchisee_sale_payment.invoice_no AS invoice_no,
        NULL AS doc_no,
        ( SELECT franchisee_sale.grand_total FROM franchisee_sale WHERE franchisee_sale.id = franchisee_sale_payment.franchisee_id ) AS grand_total,
        ( SELECT franchisee_sale.balance FROM franchisee_sale WHERE franchisee_sale.id = franchisee_sale_payment.franchisee_id ) AS balance,
        franchisee_sale_payment.payment_type AS payment_mode,
        ( SELECT name FROM bank WHERE bank.id = franchisee_sale_payment.to_bank_id ) AS deposit_to,
        NULL AS received_to,
        NULL AS received_from,
        franchisee_sale_payment.is_done AS is_done,
        franchisee_sale_payment.cheque_number AS check_no,
        franchisee_sale_payment.cheque_date AS check_date,
        franchisee_sale_payment.reference_number AS ref_no,
        franchisee_sale_payment.payment_date,
        franchisee_sale_payment.deposit_date,
        CONCAT('FI No. ', franchisee_sale_payment.franchisee_sale_id) AS payment_for,
        franchisee_sale_payment.remarks
    FROM franchisee_sale_payment
    WHERE franchisee_sale_payment.paid_amount > 0
    GROUP BY id
    )

    UNION ALL

    (
    SELECT
        'fs_billing_payment' AS type,
        fs_billing_payment.id,
        fs_billing_id AS payable_id,
        (SELECT franchisee.branch_id FROM franchisee WHERE franchisee.id = fs_billing_payment.franchisee_id) AS branch_id,
        fs_billing_payment.franchisee_id,
        fs_billing_payment.grand_total AS amount,
        fs_billing_payment.paid_amount AS paid_amount,
        fs_billing_payment.added_on,
        fs_billing_payment.is_deleted,
        fs_billing_payment.to_bank_id,
        ( SELECT franchisee.name FROM franchisee WHERE franchisee.id = fs_billing_payment.franchisee_id ) AS franchisee_name,
        ( SELECT name FROM branch WHERE branch.id = (SELECT franchisee.branch_id FROM franchisee WHERE franchisee.id = fs_billing_payment.franchisee_id) ) AS franchised_branch_name,
        NULL AS invoice_no,
        NULL AS doc_no,
        ( SELECT franchisee_sale_billing.total_sale FROM franchisee_sale_billing WHERE franchisee_sale_billing.id = fs_billing_payment.fs_billing_id ) AS grand_total,
        ( SELECT franchisee_sale_billing.balance FROM franchisee_sale_billing WHERE franchisee_sale_billing.id = fs_billing_payment.fs_billing_id ) AS balance,
        fs_billing_payment.payment_type AS payment_mode,
        ( SELECT name FROM bank WHERE bank.id = fs_billing_payment.to_bank_id ) AS deposit_to,
        NULL AS received_to,
        NULL AS received_from,
        fs_billing_payment.is_done AS is_done,
        fs_billing_payment.cheque_number AS check_no,
        fs_billing_payment.cheque_date AS check_date,
        fs_billing_payment.reference_number AS ref_no,
        fs_billing_payment.payment_date as payment_date,
        fs_billing_payment.deposit_date as deposit_date,
        CONCAT('FS Billing - ', MONTHNAME(franchisee_sale_billing.month)) AS payment_for,
        fs_billing_payment.remarks
    FROM fs_billing_payment
    LEFT JOIN franchisee_sale_billing ON franchisee_sale_billing.id = fs_billing_payment.fs_billing_id
    WHERE fs_billing_payment.paid_amount > 0
    GROUP BY fs_billing_payment.id
    )
) AS payments
WHERE payments.is_deleted = 0
EOT;

        $binds = []; 

        if ($branch_id) {
            $sql .= ' AND payments.branch_id = ?';
            $binds[] = $branch_id;
        }

        if ($deposited_to) {
            $sql .= ' AND payments.to_bank_id = ?';
            $binds[] = $deposited_to;
        }

        if ($date_from) {
            $sql .= ' AND payments.added_on >= ?';
            $binds[] = $date_from;
        }

        if ($date_to) {
            $sql .= ' AND payments.added_on <= ?';
            $binds[] = $date_to;
        }

        if ($franchisee_id) {
            $sql .= ' AND payments.franchisee_id = ?';
            $binds[] = $franchisee_id;
        }

        if ($franchisee_name) {
            $sql .= ' AND payments.franchisee_name LIKE ?';
            $binds[] = '%' . $franchisee_name . '%';
        }

        if ($type) {
            $sql .= ' AND payments.type = ?';
            $binds[] = $type;
        }

        if ($payment_mode) {
            $sql .= ' AND payments.payment_mode = ?';
            $binds[] = $payment_mode;
        }

        if ($is_done || $is_done === '0') {
            $sql .= ' AND payments.is_done = ?';
            $binds[] = $is_done;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}
