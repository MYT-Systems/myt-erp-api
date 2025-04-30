<?php

namespace App\Models;

class Supplies_expense extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'due_date',
        'branch_id',
        'supplier_id',
        'vendor_id',
        'forwarder_id',
        'expense_type_id',
        'supplies_expense_date',
        'type',
        'delivery_address',
        'branch_name',
        'delivery_date',
        'doc_no',
        'grand_total',
        'paid_amount',
        'balance',
        'remarks',
        'payment_method',
        'requisitioner',
        'status',
        'order_status',
        'prepared_by',
        'prepared_on',
        'authorized_by',
        'authorized_on',
        'recommended_by',
        'recommended_on',
        'approved_by',
        'approved_on',
        'disapproved_by',
        'disapproved_on',
        'sent_by',
        'sent_on',
        'printed_by',
        'printed_on',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted',
        'with_payment',
    ];

    public function __construct()
    {
        $this->table = 'supplies_expense';
    }

    /**
     * Get supplies_expense details by ID
     */
    public function get_details_by_id($supplies_expense_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT supplies_expense.*,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = supplies_expense.prepared_by) AS prepared_by_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = supplies_expense.approved_by) AS approved_by_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = supplies_expense.printed_by) AS printed_by_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = supplies_expense.disapproved_by) AS disapproved_by_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = supplies_expense.added_by) AS added_by_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = supplies_expense.updated_by) AS updated_by_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = supplies_expense.requisitioner) AS requisitioner_name,
    (SELECT supplier.trade_name FROM supplier WHERE supplier.id = supplies_expense.supplier_id) AS supplier_trade_name,
    (SELECT forwarder.name FROM forwarder WHERE forwarder.id = supplies_expense.forwarder_id) AS forwarder_name,
    (SELECT expense_type.name FROM expense_type WHERE expense_type.id = supplies_expense.type) AS expense_name,
    (SELECT vendor.trade_name FROM vendor WHERE vendor.id = supplies_expense.vendor_id) AS vendor_trade_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = supplies_expense.requisitioner) AS requisitioner_name,
    (SELECT supplier.email FROM supplier WHERE supplier.id = supplies_expense.supplier_id) AS supplier_email,
    (SELECT vendor.email FROM vendor WHERE vendor.id = supplies_expense.vendor_id) AS vendor_email
FROM supplies_expense
WHERE supplies_expense.id = ?
AND supplies_expense.is_deleted = 0
EOT;
        $binds = [$supplies_expense_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all supplies_expenses.
     */
    public function get_all_supplies_expense($supplier_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT supplies_expense.*, 
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = supplies_expense.prepared_by) AS prepared_by_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = supplies_expense.approved_by) AS approved_by_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = supplies_expense.printed_by) AS printed_by_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = supplies_expense.disapproved_by) AS disapproved_by_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = supplies_expense.added_by) AS added_by_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = supplies_expense.updated_by) AS updated_by_name,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) FROM user WHERE user.id = supplies_expense.requisitioner) AS requester_name,
    (SELECT supplier.trade_name FROM supplier WHERE supplier.id = supplies_expense.supplier_id) AS supplier_trade_name,
    (SELECT forwarder.name FROM forwarder WHERE forwarder.id = supplies_expense.forwarder_id) AS forwarder_name,
    (SELECT expense_type.name FROM expense_type WHERE expense_type.id = supplies_expense.type) AS expense_name,
    (SELECT vendor.trade_name FROM vendor WHERE vendor.id = supplies_expense.vendor_id) AS vendor_trade_name
FROM supplies_expense
WHERE supplies_expense.is_deleted = 0 
AND supplies_expense.status = 'approved'
EOT;

        $binds = [];

        if ($supplier_id) {
            $sql .= " AND supplies_expense.supplier_id = ?";
            $binds[] = $supplier_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get receive details by ID
     */
    public function get_expense_breakdown_by_day($date_from = null, $date_to = null, $type = "None")
    {
        if($type === "" || $type === NULL) {
            $type = "None";
        }
        $database = \Config\Database::connect();
        $sql      = <<<EOT
SELECT 
    calendar.date AS expense_date,
    COALESCE(SUM(pc.amount), 0) AS total_expense_per_day,
    COALESCE(expense_type.name, "None") AS pc_expense_type
FROM (
    SELECT ? + INTERVAL n DAY AS date, "$type" AS expense_type
    FROM (
        SELECT 
            a.N + b.N * 10 + c.N * 100 AS n, "$type" AS expense_type
        FROM
            (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS a,
            (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS b,
            (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS c
        ) AS numbers
    WHERE ? + INTERVAL n DAY <= ?
) AS calendar
LEFT JOIN (
    SELECT petty_cash_detail.date AS date, petty_cash_detail.type AS type, petty_cash_detail.amount AS amount, IFNULL(petty_cash_detail.out_type, "$type") AS expense_type
    FROM petty_cash_detail
    WHERE petty_cash_detail.is_deleted = 0

    UNION ALL

    SELECT supplies_expense.supplies_expense_date AS date, 'out' AS type, supplies_expense.grand_total AS amount, IFNULL(supplies_expense.type, "$type") AS expense_type
    FROM supplies_expense
    WHERE supplies_expense.is_deleted = 0

    UNION ALL

    SELECT receive.receive_date AS date, 'out' AS type, receive.grand_total AS amount, "Supplies" AS expense_type
    FROM receive
    WHERE receive.is_deleted = 0
) AS pc ON calendar.date = pc.date AND pc.type = 'out'
LEFT JOIN expense_type ON expense_type.id = IFNULL(pc.expense_type, "$type")
EOT;

        $binds = [$date_from, $date_from, $date_to];

if($type !== "None" && $type !== NULL) {
        $sql      .= <<<EOT

WHERE pc.expense_type = ?
EOT;
    $binds[] = $type;
}

        $sql      .= <<<EOT

GROUP BY calendar.date
ORDER BY calendar.date;
EOT;

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get supplies_expenses based on supplies_expense name, contact_person, phone_no, tin_no, bir_no, email
     */
    public function search($supplier_id, $vendor_id, $type, $forwarder_id, $expense_type_id, $supplies_expense_date, $delivery_date, $delivery_address, $branch_name, $remarks, $purpose, $requisitioner, $status, $order_status, $se_date_from, $se_date_to, $delivery_date_from, $delivery_date_to, $limit_by, $anything) 
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT supplies_expense.*,
    CONCAT(preparer.first_name, ' ', preparer.last_name) AS prepared_by_name,
    CONCAT(approver.first_name, ' ', approver.last_name) AS approved_by_name,
    CONCAT(printer.first_name, ' ', printer.last_name) AS printed_by_name,
    CONCAT(disapprover.first_name, ' ', disapprover.last_name) AS disapproved_by_name,
    CONCAT(adder.first_name, ' ', adder.last_name) AS added_by_name,
    CONCAT(updater.first_name, ' ', updater.last_name) AS updated_by_name,
    CONCAT(requester.first_name, ' ', requester.last_name) AS requester_name,
    supplier.trade_name AS supplier_trade_name,
    supplier.email AS supplier_email,
    forwarder.name AS forwarder_name,
    expense_type.name AS expense_name,
    vendor.trade_name AS vendor_trade_name,
    CONCAT(requisitioner.first_name, ' ', requisitioner.last_name) AS requisitioner_name,
    IF(supplies_expense_payment.total_payment >= supplies_expense.grand_total, 1, 0) AS can_be_paid
FROM supplies_expense
    LEFT JOIN user AS preparer ON preparer.id = supplies_expense.prepared_by
    LEFT JOIN user AS approver ON approver.id = supplies_expense.approved_by
    LEFT JOIN user AS printer ON printer.id = supplies_expense.printed_by
    LEFT JOIN user AS disapprover ON disapprover.id = supplies_expense.disapproved_by
    LEFT JOIN user AS adder ON adder.id = supplies_expense.added_by
    LEFT JOIN user AS updater ON updater.id = supplies_expense.updated_by
    LEFT JOIN user AS requester ON requester.id = supplies_expense.requisitioner
    LEFT JOIN supplier ON supplier.id = supplies_expense.supplier_id
    LEFT JOIN forwarder ON forwarder.id = supplies_expense.forwarder_id
    LEFT JOIN expense_type ON expense_type.id = supplies_expense.type
    LEFT JOIN vendor ON vendor.id = supplies_expense.vendor_id
    LEFT JOIN user AS requisitioner ON requisitioner.id = supplies_expense.requisitioner
    LEFT JOIN supplies_expense_payment ON supplies_expense_payment.supplies_expense_id = supplies_expense.id
WHERE supplies_expense.is_deleted = 0
EOT;
        $binds = [];

        if ($supplier_id) {
            $sql .= ' AND supplies_expense.supplier_id = ?';
            $binds[] = $supplier_id;
        }

        if ($vendor_id) {
            $sql .= ' AND supplies_expense.vendor_id = ?';
            $binds[] = $vendor_id;
        }

        if ($type) {
            $sql .= ' AND supplies_expense.type = ?';
            $binds[] = $type;
        }

        if ($forwarder_id) {
            $sql .= ' AND supplies_expense.forwarder_id = ?';
            $binds[] = $forwarder_id;
        }

        if ($expense_type_id) {
            $sql .= ' AND supplies_expense.expense_type_id = ?';
            $binds[] = $expense_type_id;
        }

        if ($supplies_expense_date) {
            $sql .= ' AND supplies_expense.supplies_expense_date = ?';
            $binds[] = $supplies_expense_date;
        }

        if ($delivery_date) {
            $sql .= ' AND supplies_expense.delivery_date = ?';
            $binds[] = $delivery_date;
        }

        if ($delivery_address) {
            $sql .= ' AND supplies_expense.delivery_address LIKE ?';
            $binds[] = $delivery_address;
        }

        if ($branch_name) {
            $sql .= ' AND supplies_expense.branch_name LIKE ?';
            $binds[] = $branch_name;
        }

        if ($remarks) {
            $sql .= ' AND supplies_expense.remarks LIKE ?';
            $binds[] = '%' . $remarks . '%';
        }

        if ($purpose) {
            $sql .= ' AND supplies_expense.purpose LIKE ?';
            $binds[] = '%' . $purpose . '%';
        }

        if ($requisitioner) {
            $sql .= ' AND supplies_expense.requisitioner = ?';
            $binds[] = $requisitioner;
        }
    
        if ($status) {
            if ($status == 'for_approval') {
                $sql .= ' AND supplies_expense.status = ? AND supplies_expense.order_status = "pending"';
                // $binds[] = 'pending';
                $binds[] = 'for_approval';
            } elseif ($status == 'approved') {
                $sql .= ' AND supplies_expense.status = ? AND supplies_expense.order_status IN ("pending", "incomplete")';
                // $binds[] = 'sent';
                $binds[] = 'approved';
            } elseif ($status == 'disapproved') {
                $sql .= ' AND supplies_expense.status = ?';
                // $binds[] = 'sent';
                $binds[] = 'disapproved';
            } elseif ($status == 'all') {
                $sql .= ' AND (
                    (supplies_expense.status = "for_approval" AND (supplies_expense.approved_by IS NULL OR supplies_expense.approved_by = "")) 
                    OR supplies_expense.status = "approved" 
                    OR supplies_expense.status = "disapproved" 
                    OR (supplies_expense.status = "approved" AND supplies_expense.order_status IN ("complete", "incomplete", "pending"))
                )';
            }
        }

        if ($order_status && !$anything) {
            if ($order_status === 'complete') {
                    $sql .= ' AND supplies_expense.order_status = ?';
                    $binds[] = $order_status;
                    
                    // Use date filter if available
                    if (!empty($se_date_from) && !empty($se_date_to)) {
                        $sql .= ' AND supplies_expense.supplies_expense_date BETWEEN ? AND ?';
                        $binds[] = $se_date_from;
                        $binds[] = $se_date_to;
                    } else {
                        // Default to current month if no date filter is provided
                        $sql .= ' AND MONTH(supplies_expense.supplies_expense_date) = MONTH(CURRENT_DATE())
                                  AND YEAR(supplies_expense.supplies_expense_date) = YEAR(CURRENT_DATE())';
                    }
            } else {
            $sql .= ' AND supplies_expense.order_status = ?';
            $binds[] = $order_status;
            }
        }

        if ($se_date_from) {
            $sql .= 'AND DATE(supplies_expense.supplies_expense_date) >= ?';
            $binds[] = $se_date_from;
        }

        if ($se_date_to) {
            $sql .= 'AND DATE(supplies_expense.supplies_expense_date) <= ?';
            $binds[] = $se_date_to;
        }

        if ($anything) {
            $sql .= ' AND (supplies_expense.delivery_address LIKE ? OR supplies_expense.branch_name LIKE ? OR supplies_expense.doc_no LIKE ? OR supplies_expense.remarks LIKE ? OR supplies_expense.requisitioner LIKE ? OR supplier.trade_name LIKE ? OR forwarder.name LIKE ? OR expense_type.name LIKE ? OR vendor.trade_name LIKE ? OR preparer.first_name LIKE ? OR preparer.last_name LIKE ? OR approver.first_name LIKE ? OR approver.last_name LIKE ? OR printer.first_name LIKE ? OR printer.last_name LIKE ? OR disapprover.first_name LIKE ? OR disapprover.last_name LIKE ? OR adder.first_name LIKE ? OR adder.last_name LIKE ? OR updater.first_name LIKE ? OR updater.last_name LIKE ? OR requester.first_name LIKE ? OR requester.last_name LIKE ?)';
            $new_binds = [];
            for ($i = 0; $i < 22; $i++) {
                $new_binds[] = '%' . $anything . '%';
            }
            $binds = array_merge($binds, $new_binds);
        }

        $sql .= ' ORDER BY supplies_expense.added_on DESC';

        if ($limit_by) {
            $sql .= ' LIMIT ?';
            $binds[] = $limit_by;
        }
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get commission based on transaction_type_id, branch_id
     */
    public function get_commission($transaction_type_id = null, $branch_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT commission
FROM supplies_expense
WHERE supplies_expense.is_deleted = 0
    AND supplies_expense.transaction_type_id = ?
    AND supplies_expense.branch_id = ?
EOT;
        $binds = [$transaction_type_id, $branch_id];

        $query = $database->query($sql, $binds);
        return $query ? (float) $query->getResultArray()[0]['commission'] : false;
    }

    /**
     * Get all payments for a specific supplies_expense
     */
    public function get_all_payment_by_se($supplies_expense_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
(
    SELECT 
        'bank' AS payment_type,
        se_bank_slip.id,
        NULL AS check_date,
        se_bank_slip.payment_date,
        se_bank_slip.bank_from,
        se_bank_slip.from_account_no,
        se_bank_slip.from_account_name,
        se_bank_slip.bank_to,
        se_bank_slip.to_account_no,
        se_bank_slip.to_account_name,
        se_bank_slip.transaction_fee,
        se_bank_slip.reference_no AS payment_ref_no,
        se_bank_slip.amount,
        se_bank_slip.supplier_id,
        se_bank_slip.vendor_id,
        se_bank_slip.payee,
        se_bank_slip.particulars,
        se_bank_slip.acknowledged_by,
        se_bank_slip.approved_by,
        se_bank_slip.approved_on,
        se_bank_slip.disapproved_by,
        se_bank_slip.disapproved_on,
        se_bank_slip.completed_by,
        se_bank_slip.completed_on,
        se_bank_slip.printed_by,
        se_bank_slip.printed_on,
        se_bank_slip.status
    FROM se_bank_slip
    LEFT JOIN se_bank_entry ON se_bank_slip.id = se_bank_entry.se_bank_slip_id
    WHERE se_bank_slip.is_deleted = 0 
      AND se_bank_entry.se_id = ?
)
UNION
(
    SELECT 
        'gcash' AS payment_type,
        se_gcash_slip.id,
        NULL AS check_date,
        se_gcash_slip.payment_date,
        NULL AS bank_from,
        NULL AS from_account_no,
        se_gcash_slip.account_name AS from_account_name,
        NULL AS bank_to,
        se_gcash_slip.account_no AS to_account_no,
        se_gcash_slip.account_name AS to_account_name,
        0.00 AS transaction_fee,
        se_gcash_slip.reference_no AS payment_ref_no,
        se_gcash_slip.amount,
        se_gcash_slip.supplier_id,
        se_gcash_slip.vendor_id,
        se_gcash_slip.payee,
        se_gcash_slip.particulars,
        se_gcash_slip.acknowledged_by,
        se_gcash_slip.approved_by,
        se_gcash_slip.approved_on,
        se_gcash_slip.disapproved_by,
        se_gcash_slip.disapproved_on,
        se_gcash_slip.completed_by,
        se_gcash_slip.completed_on,
        se_gcash_slip.printed_by,
        se_gcash_slip.printed_on,
        se_gcash_slip.status
    FROM se_gcash_slip
    LEFT JOIN se_gcash_entry ON se_gcash_slip.id = se_gcash_entry.se_gcash_slip_id
    WHERE se_gcash_slip.is_deleted = 0
      AND se_gcash_entry.se_id = ?
)
UNION
(
    SELECT 
        'check' AS payment_type,
        se_check_slip.id,
        se_check_slip.check_date,
        se_check_slip.issued_date AS payment_date,
        se_check_slip.bank_id AS bank_from,
        NULL AS from_account_no,
        NULL AS from_account_name,
        NULL AS bank_to,
        NULL AS to_account_no,
        NULL AS to_account_name,
        0.00 AS transaction_fee,
        se_check_slip.check_no AS payment_ref_no,
        se_check_slip.amount,
        se_check_slip.supplier_id,
        se_check_slip.vendor_id,
        se_check_slip.payee,
        se_check_slip.particulars,
        se_check_slip.acknowledged_by,
        se_check_slip.approved_by,
        se_check_slip.approved_on,
        se_check_slip.disapproved_by,
        se_check_slip.disapproved_on,
        se_check_slip.completed_by,
        se_check_slip.completed_on,
        se_check_slip.printed_by,
        se_check_slip.printed_on,
        se_check_slip.status
    FROM se_check_slip
    LEFT JOIN se_check_entry ON se_check_slip.id = se_check_entry.se_check_slip_id
    WHERE se_check_slip.is_deleted = 0
      AND se_check_entry.se_id = ?
)
EOT;

        $binds = [$supplies_expense_id, $supplies_expense_id, $supplies_expense_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

}