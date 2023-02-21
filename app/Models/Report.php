<?php

namespace App\Models;

class Report extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
    ];

    public function __construct()
    {
        $this->table = 'receive';
    }

    /**
     * Get all the payables of owner based on receives and supplies receive
     */
    public function get_receive_payables($supplier_id, $vendor_id, $date_from, $date_to, $payable, $paid) {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM
(
    (
    SELECT
        'receive' as type,
        receive.id as id,
        receive.supplier_id as supplier_id,
        receive.vendor_id as vendor_id,
        receive.purchase_date as date,
        receive.grand_total as amount,
        receive.paid_amount as paid_amount,
        receive.balance as balance,
        receive.remarks as remarks,
        receive.invoice_no as invoice_no,
        receive.dr_no as dr_no,
        receive.added_by as added_by,
        receive.added_on as added_on,
        receive.updated_by as updated_by,
        receive.updated_on as updated_on,
        receive.is_deleted as is_deleted,
        (SELECT supplier.trade_name FROM supplier WHERE supplier.id = receive.supplier_id) as supplier_name,
        (SELECT vendor.trade_name FROM vendor WHERE vendor.id = receive.vendor_id) as vendor_name
    FROM receive
    WHERE receive.is_deleted = 0
    )

    UNION ALL

    (
    SELECT
        'supplies_receive' as type,
        supplies_receive.id as id,
        supplies_receive.supplier_id as supplier_id,
        supplies_receive.vendor_id as vendor_id,
        supplies_receive.purchase_date as date,
        supplies_receive.grand_total as amount,
        supplies_receive.paid_amount as paid_amount,
        supplies_receive.balance as balance,
        supplies_receive.remarks as remarks,
        supplies_receive.invoice_no as invoice_no,
        supplies_receive.dr_no as dr_no,
        supplies_receive.added_by as added_by,
        supplies_receive.added_on as added_on,
        supplies_receive.updated_by as updated_by,
        supplies_receive.updated_on as updated_on,
        supplies_receive.is_deleted as is_deleted,
        (SELECT supplier.trade_name FROM supplier WHERE supplier.id = supplies_receive.supplier_id) as supplier_name,
        (SELECT vendor.trade_name FROM vendor WHERE vendor.id = supplies_receive.vendor_id) as vendor_name
    FROM supplies_receive
    WHERE supplies_receive.is_deleted = 0
    )
) AS receivables
WHERE receivables.is_deleted = 0
EOT;

        $binds = []; 

        if ($supplier_id) {
            $sql .= " AND receivables.supplier_id = ?";
            $binds[] = $supplier_id;
        }

        if ($vendor_id) {
            $sql .= " AND receivables.vendor_id = ?";
            $binds[] = $vendor_id;
        }

        if ($date_from) {
            $sql .= " AND receivables.date >= ?";
            $binds[] = $date_from;
        }

        if ($date_to) {
            $sql .= " AND receivables.date <= ?";
            $binds[] = $date_to;
        }

        switch($payable) {
            case 'all':
                break;
            case '0':
                $sql .= " AND receivables.balance <= 0";
                break;
            case '1':
                $sql .= " AND receivables.balance > 0";
                break;
        }

        if ($paid) {
            $sql .= " AND receivables.balance = 0";
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all franchisee sales filterable by franchisee, branch, date from, date to, and payment status
     */
    public function get_franchisee_sales($franchisee_id, $date_from, $date_to, $buyer_branch_id, $seller_branch_id, $payment_status) {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM
(
    (
    SELECT
        'franchisee_sale' as type,
        franchisee_sale.id as id,
        franchisee_sale.franchisee_id as franchisee_id,
        franchisee_sale.buyer_branch_id as buyer_branch_id,
        franchisee_sale.seller_branch_id as seller_branch_id,
        franchisee_sale.sales_date as date,
        franchisee_sale.grand_total as amount,
        franchisee_sale.paid_amount as paid_amount,
        franchisee_sale.balance as balance,
        franchisee_sale.remarks as remarks,
        franchisee_sale.added_by as added_by,
        franchisee_sale.added_on as added_on,
        franchisee_sale.updated_by as updated_by,
        franchisee_sale.updated_on as updated_on,
        franchisee_sale.is_deleted as is_deleted,
        (SELECT franchisee.name FROM franchisee WHERE franchisee.id = franchisee_sale.franchisee_id) as franchisee_name,
        (SELECT branch.name FROM branch WHERE branch.id = franchisee_sale.buyer_branch_id) as buyer_branch_name,
        (SELECT branch.name FROM branch WHERE branch.id = franchisee_sale.seller_branch_id) as seller_branch_name
    FROM franchisee_sale
    )
) AS franchisee_sales
WHERE franchisee_sales.is_deleted = 0
EOT;

        $binds = []; 

        if ($franchisee_id) {
            $sql .= " AND franchisee_sales.franchisee_id = ?";
            $binds[] = $franchisee_id;
        }

        if ($buyer_branch_id) {
            $sql .= " AND franchisee_sales.buyer_branch_id = ?";
            $binds[] = $buyer_branch_id;
        }

        if ($seller_branch_id) {
            $sql .= " AND franchisee_sales.seller_branch_id = ?";
            $binds[] = $seller_branch_id;
        }

        if ($date_from) {
            $sql .= " AND franchisee_sales.date >= ?";
            $binds[] = $date_from;
        }

        if ($date_to) {
            $sql .= " AND franchisee_sales.date <= ?";
            $binds[] = $date_to;
        }

        if ($payment_status == 'open_bill') {
            $sql .= " AND franchisee_sales.balance = 0";
        } else if ($payment_status == 'closed_bill') {
            $sql .= " AND franchisee_sales.balance > 0";
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /*
    * Get all payments done by franchisee, filterable by franchisee, branch, date from, date to, and payment type
    */
    public function get_franchisee_branch_payments($franchisee_id, $date_from, $date_to, $branch_id, $payment_type) {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM
(
    (SELECT 
        'Franchisee Payment' as type,
        franchisee_payment.id as id,
        franchisee_payment.franchisee_id as franchisee_id,
        franchisee_payment.payment_method as payment_method,
        franchisee_payment.payment_date as date,
        franchisee_payment.amount as amount,
        franchisee_payment.remarks as remarks,
        franchisee_payment.added_by as added_by,
        franchisee_payment.added_on as added_on,
        franchisee_payment.updated_by as updated_by,
        franchisee_payment.updated_on as updated_on,
        franchisee_payment.is_deleted as is_deleted,
        (SELECT franchisee.name FROM franchisee WHERE franchisee.id = franchisee_payment.franchisee_id) as franchisee_name,
        (SELECT branch.name FROM branch WHERE branch.id = (SELECT franchisee.branch_id FROM franchisee WHERE franchisee.id = franchisee_payment.franchisee_id)) as branch_name,
        (SELECT franchisee.balance FROM franchisee WHERE franchisee.id = franchisee_payment.franchisee_id) as balance,
        (SELECT franchisee.paid_amount FROM franchisee WHERE franchisee.id = franchisee_payment.franchisee_id) as paid_amount
    FROM franchisee_payment
    WHERE franchisee_payment.is_deleted = 0    
    )

    UNION ALL

    (SELECT
        'Franchisee Sale Billing Payment' as type,
        fs_billing_payment.id as id,
        fs_billing_payment.franchisee_id as franchisee_id,
        fs_billing_payment.payment_type as payment_method,
        fs_billing_payment.payment_date as date,
        fs_billing_payment.grand_total as amount,
        fs_billing_payment.remarks as remarks,
        fs_billing_payment.added_by as added_by,
        fs_billing_payment.added_on as added_on,
        fs_billing_payment.updated_by as updated_by,
        fs_billing_payment.updated_on as updated_on,
        fs_billing_payment.is_deleted as is_deleted,
        (SELECT franchisee.name FROM franchisee WHERE franchisee.id = fs_billing_payment.franchisee_id) as franchisee_name,
        (SELECT branch.name FROM branch WHERE branch.id = (SELECT franchisee.branch_id FROM franchisee WHERE franchisee.id = fs_billing_payment.franchisee_id)) as buyer_branch_name,
        (SELECT franchisee_sale_billing.balance FROM franchisee_sale_billing WHERE franchisee_sale_billing.id = fs_billing_payment.fs_billing_id) as balance,
        (SELECT franchisee_sale_billing.paid_amount FROM franchisee_sale_billing WHERE franchisee_sale_billing.id = fs_billing_payment.fs_billing_id) as paid_amount
    FROM fs_billing_payment
    WHERE fs_billing_payment.is_deleted = 0
    )

    UNION ALL

    (SELECT
        'Franchisee Sale Payment' as type,
        Franchisee_sale_payment.id as id,
        Franchisee_sale_payment.franchisee_id as franchisee_id,
        Franchisee_sale_payment.payment_type as payment_method,
        Franchisee_sale_payment.payment_date as date,
        Franchisee_sale_payment.grand_total as amount,
        Franchisee_sale_payment.remarks as remarks,
        Franchisee_sale_payment.added_by as added_by,
        Franchisee_sale_payment.added_on as added_on,
        Franchisee_sale_payment.updated_by as updated_by,
        Franchisee_sale_payment.updated_on as updated_on,
        Franchisee_sale_payment.is_deleted as is_deleted,
        (SELECT franchisee.name FROM franchisee WHERE franchisee.id = Franchisee_sale_payment.franchisee_id) as franchisee_name,
        (SELECT branch.name FROM branch WHERE branch.id = (SELECT franchisee.branch_id FROM franchisee WHERE franchisee.id = Franchisee_sale_payment.franchisee_id)) as buyer_branch_name,  
        (SELECT franchisee_sale.balance FROM franchisee_sale WHERE franchisee_sale.id = Franchisee_sale_payment.franchisee_sale_id) as balance,
        (SELECT franchisee_sale.paid_amount FROM franchisee_sale WHERE franchisee_sale.id = Franchisee_sale_payment.franchisee_sale_id) as paid_amount
    FROM Franchisee_sale_payment
    WHERE Franchisee_sale_payment.is_deleted = 0
    )
) AS franchisee_payments
WHERE franchisee_payments.is_deleted = 0
EOT;

        $binds = [];

        if ($franchisee_id) {
            $sql .= " AND franchisee_payments.franchisee_id = ?";
            $binds[] = $franchisee_id;
        }

        if ($branch_id) {
            $sql .= " AND franchisee_payments.buyer_branch_id = ?";
            $binds[] = $branch_id;
        }

        if ($date_from) {
            $sql .= " AND franchisee_payments.date >= ?";
            $binds[] = $date_from;
        }

        if ($date_to) {
            $sql .= " AND franchisee_payments.date <= ?";
            $binds[] = $date_to;
        }

        if ($payment_type) {
            $sql .= " AND franchisee_payments.payment_method = ?";
            $binds[] = $payment_type;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get dash reports
     */
    public function get_dash_reports()
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
SELECT 
    (SELECT COUNT(*) 
    FROM purchase 
    WHERE purchase.is_deleted = 0
        AND purchase.status = 'for_approval') AS purchase_for_approval,
    (SELECT COUNT(*)
    FROM purchase
    WHERE purchase.is_deleted = 0
        AND purchase.order_status = 'incomplete') AS incomplete_purchase,
    (SELECT COUNT(*)
    FROM purchase
    WHERE purchase.is_deleted = 0
        AND purchase.order_status = 'complete') AS complete_purchase,
    (SELECT COUNT(*)
    FROM purchase
    WHERE purchase.is_deleted = 0
        AND purchase.order_status = 'pending') AS pending_purchase,
    (SELECT COUNT(*)
    FROM supplies_expense
    WHERE supplies_expense.is_deleted = 0
        AND supplies_expense.status = 'for_approval') AS supplies_expense_for_approval,
    (SELECT COUNT(*)
    FROM supplies_expense
    WHERE supplies_expense.is_deleted = 0
        AND supplies_expense.status = 'complete') AS complete_supplies_expense,
    (SELECT COUNT(*)
    FROM supplies_expense
    WHERE supplies_expense.is_deleted = 0
        AND supplies_expense.status = 'incomplete') AS supplies_expense_incomplete,
    (SELECT COUNT(*)
    FROM supplies_expense
    WHERE supplies_expense.is_deleted = 0
        AND supplies_expense.status = 'pending') AS supplies_expense_pending,
    (SELECT COUNT(*)
    FROM franchisee_payment
    WHERE franchisee_payment.is_deleted = 0
        AND franchisee_payment.is_done = 0) AS franchisee_payment_not_done,
    (SELECT COUNT(*)
    FROM franchisee_payment
    WHERE franchisee_payment.is_deleted = 0
        AND franchisee_payment.is_done = 1) AS franchisee_payment_done,
    (SELECT COUNT(*)
    FROM franchisee_sale
    WHERE franchisee_sale.is_deleted = 0
        AND franchisee_sale.payment_status = 'open_bill') AS franchisee_sale_open_bill,
    (SELECT COUNT(*)
    FROM franchisee_sale
    WHERE franchisee_sale.is_deleted = 0
        AND franchisee_sale.payment_status = 'closed_bill') AS franchisee_sale_closed_bill,
    (SELECT COUNT(*)
    FROM receive
    WHERE receive.is_deleted = 0
        AND receive.balance > 0) AS receive_open_bill,
    (SELECT COUNT(*)
    FROM receive
    WHERE receive.is_deleted = 0
        AND receive.balance <= 0) AS receive_closed_bill,
    (SELECT COUNT(*)
    FROM supplies_receive
    WHERE supplies_receive.is_deleted = 0
        AND supplies_receive.balance > 0) AS supplies_receive_open_bill,
    (SELECT COUNT(*)
    FROM supplies_receive
    WHERE supplies_receive.is_deleted = 0
        AND supplies_receive.balance <= 0) AS supplies_receive_closed_bill,
    (SELECT COUNT(*)
    FROM purchase
    WHERE purchase.is_deleted = 0
       AND purchase.with_payment = 1
       AND purchase.id NOT IN (
        SELECT receive.po_id
        FROM receive
        WHERE receive.is_deleted = 0
        )) AS purchase_with_payment_not_received,
    (SELECT COUNT(*)
    FROM inventory
    WHERE inventory.is_deleted = 0
        AND inventory.current_qty < 0) AS inventory_negative_qty
FROM
    DUAL
EOT;
        
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get adjustments for approval
     */
    public function get_adjustments_for_approval()
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
SELECT
    COUNT(*) AS count,
    user.type as user_type,
    branch.name AS branch_name
FROM adjustment
LEFT JOIN branch ON branch.id = adjustment.branch_id
LEFT JOIN user ON user.id = adjustment.added_by
WHERE adjustment.is_deleted = 0
    AND adjustment.status = 'pending'
GROUP BY branch.id, user.type
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : [];
    }

    /**
     * Get low stocks per branch
     */
    public function get_low_stocks_per_branch()
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
SELECT
    COUNT(*) AS count,
    branch.name AS branch_name
FROM inventory
LEFT JOIN branch ON branch.id = inventory.branch_id
WHERE inventory.is_deleted = 0
    AND inventory.current_qty < inventory.min
GROUP BY branch.id
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : [];
    }

    /**
     * Get all pending requests
     */
    public function get_all_pending_requests()
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
SELECT
    request.id,
    from_branch.name AS from_branch_name,
    to_branch.name AS to_branch_name
FROM request
LEFT JOIN branch AS from_branch ON from_branch.id = request.branch_from
LEFT JOIN branch AS to_branch ON to_branch.id = request.branch_to
WHERE request.is_deleted = 0
    AND request.status = 'pending'
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : [];
    }

    /**
     * Get all number of unprocess franchisee sales
     */
    public function get_all_unprocess_franchisee_sales()
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
SELECT
    COUNT(*) AS count,
    franchisee.id AS franchisee_id,
    franchisee.name AS franchisee_name
FROM franchisee_sale
LEFT JOIN franchisee ON franchisee.id = franchisee_sale.franchisee_id
WHERE franchisee_sale.is_deleted = 0
    AND franchisee_sale.fs_status = 'quoted'
EOT;
        
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : [];
    }

    /**
     * Get all for approval transfers
     */
    public function get_all_for_approval_transfers()
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
SELECT
    transfer.id,
    transfer.transfer_number,
    from_branch.name AS from_branch_name,
    to_branch.name AS to_branch_name
FROM transfer
LEFT JOIN branch AS from_branch ON from_branch.id = transfer.branch_from
LEFT JOIN branch AS to_branch ON to_branch.id = transfer.branch_to
WHERE transfer.is_deleted = 0
    AND transfer.transfer_status = 'pending'
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : [];
    }

    
    /**
     * Get all expired franchisee contracts
     */
    public function get_expired_contracts()
    {
        $database = \Config\Database::connect();
        $date_now = date('Y-m-d');
        $sql = <<<EOT
SELECT
    franchisee.id AS franchisee_id,
    franchisee.name AS franchisee_name,
    franchisee.contract_start AS contract_start,
    franchisee.contract_end AS contract_end
FROM franchisee
WHERE franchisee.is_deleted = 0
    AND franchisee.contract_end < '$date_now'
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : [];
    }

    /**
     * Get franchisee sales based on date
     */
}