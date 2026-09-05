<?php

namespace App\Models;

class Bank_recon_balance extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'bank_id', 'txn_date', 'type', 'amount', 'balance',
        'source_type', 'source_id', 'is_deleted',
    ];

    public function __construct()
    {
        $this->table = 'bank_recon_balance';
    }

    /**
     * Delete and re-insert all bank_recon_balance rows for a given bank,
     * recomputing the running balance from beginning_bal forward.
     */
    public function rebuild($bank_id)
    {
        if (!$bank_id) return false;

        $db = \Config\Database::connect();
        $db->query("DELETE FROM bank_recon_balance WHERE bank_id = ?", [$bank_id]);

        $sql = <<<EOT
INSERT INTO bank_recon_balance (bank_id, txn_date, type, amount, balance, source_type, source_id)
WITH txns AS (
    SELECT to_bank_id AS bank_id, deposit_date AS txn_date,
           'Credit' AS type, paid_amount AS amount,
           'invoice_payment' AS source_type, id AS source_id, 1 AS ord
    FROM project_invoice_payment
    WHERE is_deleted = 0 AND to_bank_id = ?

    UNION ALL

    SELECT bank_from, payment_date, 'Debit', amount,
           'se_bank_slip', id, 2
    FROM se_bank_slip
    WHERE is_deleted = 0 AND bank_from = ?

    UNION ALL

    SELECT CAST(pcd.`from` AS UNSIGNED), pcd.date, 'Debit', pcd.amount,
           'petty_cash', pcd.id, 3
    FROM petty_cash_detail pcd
    JOIN petty_cash pc ON pc.id = pcd.petty_cash_id
    WHERE pcd.is_deleted = 0
      AND pc.is_deleted = 0
      AND pcd.type = 'in'
      AND pcd.`from` REGEXP '^[0-9]+$'
      AND CAST(pcd.`from` AS UNSIGNED) = ?

    UNION ALL

    SELECT bank_from_id, transaction_date, 'Debit', amount,
           'bank_transfer_out', id, 4
    FROM bank_transfer
    WHERE is_deleted = 0 AND bank_from_id = ?

    UNION ALL

    SELECT bank_to_id, transaction_date, 'Credit', amount,
           'bank_transfer_in', id, 5
    FROM bank_transfer
    WHERE is_deleted = 0 AND bank_to_id = ?

    UNION ALL

    SELECT bank_id, txn_date,
           IF(type = 'interest', 'Credit', 'Debit'),
           amount, 'monthly_adjustment', id, 6
    FROM bank_monthly_adjustment
    WHERE is_deleted = 0 AND bank_id = ?

    UNION ALL

    SELECT bank_id, txn_date,
           IF(type = 'deposit', 'Credit', 'Debit'),
           amount, 'fund_transaction', id, 7
    FROM bank_fund_transaction
    WHERE is_deleted = 0 AND bank_id = ?
)
SELECT
    t.bank_id,
    t.txn_date,
    t.type,
    t.amount,
    b.beginning_bal + SUM(IF(t.type = 'Credit', t.amount, -t.amount))
        OVER (
            PARTITION BY t.bank_id
            ORDER BY t.txn_date, t.ord, t.source_id
            ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
        ) AS balance,
    t.source_type,
    t.source_id
FROM txns t
JOIN bank b ON b.id = t.bank_id AND b.is_deleted = 0
ORDER BY t.bank_id, t.txn_date, t.ord, t.source_id;
EOT;

        return $db->query($sql, [$bank_id, $bank_id, $bank_id, $bank_id, $bank_id, $bank_id, $bank_id]);
    }
}
