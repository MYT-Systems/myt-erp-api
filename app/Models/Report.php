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
    public function get_receive_payables($invoice_no, $supplier_id, $vendor_id, $date_from, $date_to, $payable, $paid) {
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

        if ($invoice_no) {
            $sql .= " AND (receivables.invoice_no LIKE ? OR receivables.dr_no LIKE ?)";
            $binds[] = "%" . $invoice_no . "%";
            $binds[] = "%" . $invoice_no . "%";
        }

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

   /**
 * Get expense
 */
public function get_expense($expense_type = null, $date_from = null, $date_to = null, $payment_status = null, $search_text = null)
{
    $database = \Config\Database::connect();

    $binds = [];

    $sql = <<<EOT
SELECT 
    supplies_expense.supplies_expense_date AS expense_date, 
    'Supplies Expense' AS particulars, 
    supplies_expense.id AS doc_no, 
    supplies_expense.grand_total AS expense_total, 
    expense_type.name AS expense_type,
    CASE 
        WHEN supplies_expense.balance = 0 THEN 'fully paid' 
        WHEN supplies_expense.balance > 0 THEN 'partially paid' 
        ELSE 'unpaid' 
    END AS payment_status, 
    supplies_expense.doc_no AS reference_no, 
    supplies_expense.paid_amount AS paid_amount,
    supplies_expense.balance AS balance,
    supplies_expense.remarks AS description,
    NULL AS invoice_no,
    expense_type.id AS expense_type_id
FROM supplies_expense
LEFT JOIN expense_type ON expense_type.id = supplies_expense.type
WHERE supplies_expense.is_deleted = 0
AND supplies_expense.status NOT IN ("pending", "for_approval", "disapproved", "deleted")
EOT;

    // Removed incorrect check for expense_type, now correctly checking for 'type' column
    $sql .= " AND supplies_expense.type IS NOT NULL AND supplies_expense.supplies_expense_date IS NOT NULL ";

    if ($expense_type) {
        $sql .= " AND supplies_expense.type = ?";
        $binds[] = $expense_type;
    }

    if ($date_from) {
        $sql .= " AND supplies_expense.supplies_expense_date >= ?";
        $binds[] = $date_from;
    }

    if ($date_to) {
        $sql .= " AND supplies_expense.supplies_expense_date <= ?";
        $binds[] = $date_to;
    }

    // Corrected the payment status filter
    if ($payment_status) {
        $sql .= " AND CASE 
                    WHEN supplies_expense.grand_total = supplies_receive.paid_amount THEN 'fully paid'
                    WHEN supplies_expense.grand_total < supplies_receive.paid_amount THEN 'over paid' 
                    WHEN supplies_expense.paid_amount > 0 AND supplies_expense.grand_total > supplies_receive.paid_amount THEN 'partially paid' 
                    ELSE 'unpaid' END = ?";
        $binds[] = $payment_status;
    }

    if ($search_text) {
        $sql .= " AND LOWER(supplies_expense.remarks) LIKE LOWER(?)";
        $binds[] = '%' . $search_text . '%'; // Adding % around the search_text for partial matching
    }

    $sql .= <<<EOT
ORDER BY supplies_expense.doc_no DESC
EOT;

    $query = $database->query($sql, $binds);
    return $query ? $query->getResultArray() : [];
}

    /**
     * Get expense
     */
    public function get_project_sales($project_id, $date_from, $date_to, $customer_id, $distributor_id, $anything, $payment_structure)
    {
        $database = \Config\Database::connect();
    
        $binds = [];

        $sql = <<<EOT
SELECT 
    project.id AS project_id,
    project.name AS name, 
    project.start_date AS start_date,
    distributor.name AS distributor_name, 
    customer.name AS customer_name, 
    
    (SELECT COALESCE(SUM(project_invoice.grand_total), 0) 
     FROM project_invoice 
     WHERE project_invoice.project_id = project.id 
       AND project_invoice.is_deleted = 0) AS amount, 

    COALESCE(project.paid_amount, 0) AS paid_amount, 

    ((SELECT COALESCE(SUM(project_invoice.grand_total), 0) 
      FROM project_invoice 
      WHERE project_invoice.project_id = project.id 
        AND project_invoice.is_deleted = 0) 
      - COALESCE(project.paid_amount, 0)) AS receivable, 

    (SELECT COALESCE(SUM(project_expense.grand_total), 0) 
     FROM project_expense 
     WHERE project_expense.project_id = project.id 
       AND project_expense.status = 'approved' 
       AND project_expense.is_deleted = 0) AS project_expense,

    (COALESCE(project.paid_amount, 0) - 
     (SELECT COALESCE(SUM(project_expense.grand_total), 0) 
      FROM project_expense 
      WHERE project_expense.project_id = project.id 
        AND project_expense.status = 'approved' 
        AND project_expense.is_deleted = 0)) AS total_sales
FROM project
LEFT JOIN customer ON customer.id = project.customer_id
LEFT JOIN distributor ON distributor.id = project.distributor_id
WHERE project.is_deleted = 0 
AND customer.is_deleted = 0
EOT;

        if ($project_id) {
            $sql .= " AND project.id = ?";
            $binds[] = $project_id;
        }

        if ($date_from) {
            $sql .= " AND project.added_on >= ?";
            $binds[] = $date_from;
        }

        if ($date_to) {
            $sql .= " AND project.added_on <= ?";
            $binds[] = $date_to;
        }

        if ($customer_id) {
            $sql .= " AND project.customer_id = ?";
            $binds[] = $customer_id;
        }

        if ($distributor_id) {
            $sql .= " AND project.distributor_id = ?";
            $binds[] = $distributor_id;
        }
        
        if ($anything) {
            $sql .= " AND project.name LIKE ?";
            $binds[] = '%' . $anything . '%';
        }
        
        if ($payment_structure && $payment_structure !== "All") {
            $sql .= " AND project.payment_structure = ?";
            $binds[] = $payment_structure;
        }

        $sql .= " GROUP BY project.id ORDER BY customer_name";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : [];
    }

    /**
     * Get expense
     */
    public function get_invoice_summary($project_id)
    {
        $database = \Config\Database::connect();
    
        $binds = [];

        $sql = <<<EOT
SELECT 
    project.id AS project_id,
    project.name AS name, 
    project.start_date AS start_date,
    distributor.name AS distributor_name, 
    customer.name AS customer_name, 
    
    (SELECT COALESCE(SUM(project_invoice.grand_total), 0) 
     FROM project_invoice 
     WHERE project_invoice.project_id = project.id 
       AND project_invoice.is_deleted = 0) AS amount, 

    COALESCE(project.paid_amount, 0) AS paid_amount, 

    ((SELECT COALESCE(SUM(project_invoice.grand_total), 0) 
      FROM project_invoice 
      WHERE project_invoice.project_id = project.id 
        AND project_invoice.is_deleted = 0) 
      - COALESCE(project.paid_amount, 0)) AS receivable, 

    (SELECT COALESCE(SUM(project_expense.grand_total), 0) 
     FROM project_expense 
     WHERE project_expense.project_id = project.id 
       AND project_expense.status = 'approved' 
       AND project_expense.is_deleted = 0) AS project_expense,

    (COALESCE(project.paid_amount, 0) - 
     (SELECT COALESCE(SUM(project_expense.grand_total), 0) 
      FROM project_expense 
      WHERE project_expense.project_id = project.id 
        AND project_expense.status = 'approved' 
        AND project_expense.is_deleted = 0)) AS total_sales
FROM project
LEFT JOIN customer ON customer.id = project.customer_id
LEFT JOIN distributor ON distributor.id = project.distributor_id
WHERE project.is_deleted = 0 
AND customer.is_deleted = 0
EOT;

        if ($project_id) {
            $sql .= " AND project.id = ?";
            $binds[] = $project_id;
        }

        $sql .= " GROUP BY project.id";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : [];
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
     * Get previous balance of a bank account
     */
    public function get_previous_balance($bank_id = null, $date_from = null)
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
SELECT 
    bank.id,
    bank.name,
    bank.current_bal,
    CASE 
        WHEN DATE(?) < DATE(bank.added_on) THEN 0
    	WHEN DATE(?) = DATE(bank.added_on) THEN bank.beginning_bal
        ELSE 
            GREATEST(
                0,  -- If the result is less than 0, return 0
                bank.current_bal - (
                    IFNULL(( 
                        SELECT SUM(project_invoice_payment.paid_amount)
                        FROM project_invoice_payment
                        WHERE project_invoice_payment.to_bank_id = bank.id
                        AND project_invoice_payment.is_deleted = 0
                        AND project_invoice_payment.payment_date >= ? 
                    ), 0)
                    -
                    IFNULL(( 
                        SELECT SUM(se_bank_slip.amount)
                        FROM se_bank_slip
                        LEFT JOIN se_bank_entry ON se_bank_entry.se_bank_slip_id = se_bank_slip.id
                        WHERE se_bank_slip.bank_from = bank.id
                        AND se_bank_slip.is_deleted = 0
                        AND se_bank_entry.is_deleted = 0
                        AND se_bank_slip.payment_date >= ?
                    ), 0)
                )
            )
    END AS previous_balance
FROM bank
WHERE bank.is_deleted = 0
AND bank.id = ?;
EOT;

        $query = $database->query($sql, [$date_from, $date_from, $date_from, $date_from, $bank_id]);
        return $query ? $query->getRowArray() : false;
    }

    /**
     * Get bank reconciliation
     */
    public function get_bank_reconciliation($bank_id = null, $date_from = null, $date_to = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT * FROM (
    SELECT 
        'Credit' AS type,
        project_invoice.id AS id,
        CONCAT('SALES INVOICE NO. ', project_invoice.invoice_no) AS reference_no,
        project_invoice_payment.deposit_date AS date,
        project_invoice_payment.paid_amount,
        bank.name AS bank_name,
        project_invoice_payment.to_bank_id AS bank_id
    FROM project_invoice_payment
    LEFT JOIN bank ON bank.id = project_invoice_payment.to_bank_id
    LEFT JOIN project_invoice ON project_invoice.id = project_invoice_payment.project_invoice_id
    WHERE project_invoice_payment.is_deleted = 0
    AND bank.is_deleted = 0

    UNION ALL

    SELECT 
        'Debit' AS type,
        supplies_expense.id as id,
        CONCAT('PURCHASE ORDER NO. ', se_bank_entry.se_id) AS reference_no,
        se_bank_slip.payment_date AS date,
        se_bank_slip.amount AS paid_amount,
        bank.name AS bank_name,
        se_bank_slip.bank_from AS bank_id
    FROM se_bank_slip
    LEFT JOIN bank ON bank.id = se_bank_slip.bank_from
    LEFT JOIN se_bank_entry ON se_bank_entry.se_bank_slip_id = se_bank_slip.id
    LEFT JOIN supplies_expense ON supplies_expense.id = se_bank_entry.se_id
    WHERE se_bank_slip.is_deleted = 0
    AND se_bank_entry.is_deleted = 0
    AND bank.is_deleted = 0
) AS temp
WHERE 1 = 1
EOT;

        $binds = [];

        if ($bank_id) {
            $sql .= " AND temp.bank_id = ?";
            $binds[] = $bank_id;
        }

        if ($date_from) {
            $sql .= " AND temp.date >= ?";
            $binds[] = $date_from;
        }

        if ($date_to) {
            $sql .= " AND temp.date <= ?";
            $binds[] = $date_to;
        }

        $sql .= " ORDER BY temp.date ASC";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get dash reports
     */
    public function get_dashboard_reports()
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
     * Get payables aging
     */
    public function get_payables_aging($supplier_id = null, $expense_type = null)
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
SELECT supplier_id, supplier_name, GROUP_CONCAT(cur SEPARATOR ',') AS cur, GROUP_CONCAT(one_to_thirty SEPARATOR ',') AS one_to_thirty, GROUP_CONCAT(thirtyone_to_sixty SEPARATOR ',') AS thirtyone_to_sixty, GROUP_CONCAT(sixtyone_to_ninety SEPARATOR ',') AS sixtyone_to_ninety, GROUP_CONCAT(above_ninety SEPARATOR ',') AS above_ninety, SUM(total) AS total, SUM(total_paid) AS total_paid
FROM (
  SELECT supplier.id AS supplier_id, supplies_expense.id, supplier.trade_name AS supplier_name,
    CASE
    WHEN (supplies_expense.grand_total > supplies_expense.paid_amount AND DATEDIFF(CURDATE(), IF(supplies_expense.due_date IS NULL, supplies_expense.supplies_expense_date, supplies_expense.due_date))- supplier.terms <= 0) THEN GROUP_CONCAT(CONCAT("Exp. ",supplies_expense.id,' - ',supplies_expense.grand_total))END AS cur,
    CASE
    WHEN supplies_expense.grand_total > supplies_expense.paid_amount AND DATEDIFF(CURDATE(), IF(supplies_expense.due_date IS NULL, supplies_expense.supplies_expense_date, supplies_expense.due_date)) - supplier.terms > 0 AND DATEDIFF(CURDATE(), IF(supplies_expense.due_date IS NULL, supplies_expense.supplies_expense_date, supplies_expense.due_date)) - supplier.terms <= 30  THEN CONCAT("Exp. ",supplies_expense.id,' - ',supplies_expense.grand_total) END AS one_to_thirty,
    CASE
    WHEN supplies_expense.grand_total > supplies_expense.paid_amount AND DATEDIFF(CURDATE(), IF(supplies_expense.due_date IS NULL, supplies_expense.supplies_expense_date, supplies_expense.due_date)) - supplier.terms > 30 AND DATEDIFF(CURDATE(), IF(supplies_expense.due_date IS NULL, supplies_expense.supplies_expense_date, supplies_expense.due_date)) - supplier.terms <= 60  THEN CONCAT("Exp. ",supplies_expense.id,' - ',supplies_expense.grand_total) END AS thirtyone_to_sixty,
    CASE
    WHEN supplies_expense.grand_total > supplies_expense.paid_amount AND DATEDIFF(CURDATE(), IF(supplies_expense.due_date IS NULL, supplies_expense.supplies_expense_date, supplies_expense.due_date)) - supplier.terms > 60 AND DATEDIFF(CURDATE(), IF(supplies_expense.due_date IS NULL, supplies_expense.supplies_expense_date, supplies_expense.due_date)) - supplier.terms <= 90  THEN CONCAT("Exp. ",supplies_expense.id,' - ',supplies_expense.grand_total) END AS sixtyone_to_ninety,
    CASE
    WHEN supplies_expense.grand_total > supplies_expense.paid_amount AND DATEDIFF(CURDATE(), IF(supplies_expense.due_date IS NULL, supplies_expense.supplies_expense_date, supplies_expense.due_date)) - supplier.terms > 90 THEN CONCAT("Exp. ",supplies_expense.id,' - ',supplies_expense.grand_total) END AS above_ninety,
    supplies_expense.grand_total as total,
    supplies_expense.paid_amount as total_paid,
    expense_type.id AS expense_type
  FROM supplies_expense
  LEFT JOIN supplier ON supplier.id = supplies_expense.supplier_id
  LEFT JOIN expense_type ON expense_type.id = supplies_expense.type
  WHERE supplies_expense.is_deleted = 0
    AND supplier.is_deleted = 0
  GROUP BY supplier.trade_name, supplies_expense.id
) AS data
WHERE data.total > data.total_paid
EOT;

        $binds = [];
    
        if ($supplier_id) {
            $sql .= " AND data.supplier_id = ?";
            $binds[] = $supplier_id;
        }

        if ($expense_type) {
            $sql .= " AND data.$expense_type = ?";
            $binds[] = $expense_type;
        }
        $sql .= <<<EOT

GROUP BY data.supplier_id;
EOT;

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : [];
    }

    /**
     * Get receivables aging
     */
    public function get_receivables_aging($customer_id = null, $project_id = null)
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
SELECT customer_id, customer_name, GROUP_CONCAT(cur SEPARATOR ',') AS cur, GROUP_CONCAT(one_to_thirty SEPARATOR ',') AS one_to_thirty, GROUP_CONCAT(thirtyone_to_sixty SEPARATOR ',') AS thirtyone_to_sixty, GROUP_CONCAT(sixtyone_to_ninety SEPARATOR ',') AS sixtyone_to_ninety, GROUP_CONCAT(above_ninety SEPARATOR ',') AS above_ninety, SUM(total) AS total, SUM(total_paid) AS total_paid
FROM (
  SELECT customer.id AS customer_id, project_invoice.id, customer.name AS customer_name,
    CASE
    WHEN (project_invoice.grand_total > project_invoice.paid_amount AND DATEDIFF(CURDATE(), IF(project_invoice.due_date IS NULL, project_invoice.invoice_date, project_invoice.due_date))- customer.terms <= 0) THEN GROUP_CONCAT(CONCAT("INV. ",project_invoice.invoice_no,'-(',project_invoice.grand_total, ')'))END AS cur,
    CASE
    WHEN project_invoice.grand_total > project_invoice.paid_amount AND DATEDIFF(CURDATE(), IF(project_invoice.due_date IS NULL, project_invoice.invoice_date, project_invoice.due_date)) - customer.terms > 0 AND DATEDIFF(CURDATE(), IF(project_invoice.due_date IS NULL, project_invoice.invoice_date, project_invoice.due_date)) - customer.terms <= 30  THEN CONCAT("INV. ",project_invoice.invoice_no,'-(',project_invoice.grand_total, ')') END AS one_to_thirty,
    CASE
    WHEN project_invoice.grand_total > project_invoice.paid_amount AND DATEDIFF(CURDATE(), IF(project_invoice.due_date IS NULL, project_invoice.invoice_date, project_invoice.due_date)) - customer.terms > 30 AND DATEDIFF(CURDATE(), IF(project_invoice.due_date IS NULL, project_invoice.invoice_date, project_invoice.due_date)) - customer.terms <= 60  THEN CONCAT("INV. ",project_invoice.invoice_no,'-(',project_invoice.grand_total, ')') END AS thirtyone_to_sixty,
    CASE
    WHEN project_invoice.grand_total > project_invoice.paid_amount AND DATEDIFF(CURDATE(), IF(project_invoice.due_date IS NULL, project_invoice.invoice_date, project_invoice.due_date)) - customer.terms > 60 AND DATEDIFF(CURDATE(), IF(project_invoice.due_date IS NULL, project_invoice.invoice_date, project_invoice.due_date)) - customer.terms <= 90  THEN CONCAT("INV. ",project_invoice.invoice_no,'-(',project_invoice.grand_total, ')') END AS sixtyone_to_ninety,
    CASE
    WHEN project_invoice.grand_total > project_invoice.paid_amount AND DATEDIFF(CURDATE(), IF(project_invoice.due_date IS NULL, project_invoice.invoice_date, project_invoice.due_date)) - customer.terms > 90 THEN CONCAT("INV. ",project_invoice.invoice_no,'-(',project_invoice.grand_total, ')') END AS above_ninety,
    project_invoice.grand_total as total,
    project_invoice.paid_amount as total_paid,
    project.id AS project_id
  FROM project_invoice
  LEFT JOIN project ON project.id = project_invoice.project_id
  LEFT JOIN customer ON customer.id = project.customer_id
  WHERE project_invoice.is_deleted = 0
    AND project.is_deleted = 0
    AND project.grand_total > project.paid_amount
  GROUP BY customer.name, project_invoice.id
) AS data
WHERE data.total > data.total_paid
EOT;

        $binds = [];
    
        if ($customer_id) {
            $sql .= " AND data.customer_id = ?";
            $binds[] = $customer_id;
        }

        if ($project_id) {
            $sql .= " AND data.project_id = ?";
            $binds[] = $project_id;
        }

        $sql .= <<<EOT

GROUP BY data.customer_id
ORDER BY customer_name;
EOT;

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : [];
    }

    /**
     * Get statement of account
     */
    public function get_statement_of_account($customer_id = null)
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
SELECT project.id AS project_id, project_invoice.invoice_date AS invoice_date, project_invoice.id AS project_invoice_id, project_invoice.grand_total AS total_amount, project_invoice.paid_amount AS total_paid, project_invoice.grand_total - project_invoice.paid_amount AS remaining_balance
FROM project_invoice
LEFT JOIN project ON project.id = project_invoice.project_id
LEFT JOIN customer ON customer.id = project.customer_id
WHERE project_invoice.grand_total > project_invoice.paid_amount
EOT;

        $binds = [];
    
        if ($customer_id) {
            $sql .= " AND project.customer_id = ?";
            $binds[] = $customer_id;
        }

        $sql .= <<<EOT

GROUP BY project_invoice.id
EOT;

        $query = $database->query($sql, $binds);
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
     * Get sales
     */
    public function get_sales($date_from, $date_to)
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
SELECT SUM(paid_amount) AS sales
FROM project_invoice_payment
WHERE is_deleted = 0
EOT;
        $binds = [];

        if($date_from && $date_to) {
            $sql .= <<<EOT

AND deposit_date BETWEEN ? AND ?
EOT;
            $binds[] = $date_from;
            $binds[] = $date_to;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray()[0]['sales'] : 0;
    }

    /**
 * Get expenses with optional date filtering, excluding project expenses
 */
public function get_expenses($date_from = null, $date_to = null)
{
    $database = \Config\Database::connect();
    
    $sql = <<<EOT
SELECT SUM(grand_total) AS expenses
FROM supplies_expense
WHERE is_deleted = 0
AND status IN ('approved', 'printed', 'sent')
{date_filter_supplies}
EOT;

    // Initialize bind parameters and date filter
    $binds = [];
    $date_filter_supplies = '';

    if ($date_from && $date_to) {
        // Add date filter if both date_from and date_to are provided
        $date_filter_supplies = "AND supplies_expense_date BETWEEN ? AND ?";
        $binds = array_merge($binds, [$date_from, $date_to]);
    }

    // Replace placeholder with the appropriate date filter
    $sql = str_replace('{date_filter_supplies}', $date_filter_supplies, $sql);

    $query = $database->query($sql, $binds);
    return $query ? $query->getResultArray()[0]['expenses'] : 0;
}



    /**
     * Get receivables
     */
    public function get_receivables($date_from, $date_to)
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
SELECT SUM(project_invoice.grand_total - project_invoice.paid_amount) AS receivables
FROM project_invoice
LEFT JOIN project ON project.id = project_invoice.project_id
WHERE project_invoice.is_deleted = 0
AND project.is_deleted = 0
AND project_invoice.status = 'sent'
EOT;
        $binds = [];
        if($date_from && $date_to) {
            $sql .= <<<EOT

AND project_invoice.invoice_date BETWEEN ? AND ?
EOT;
            $binds[] = $date_from;
            $binds[] = $date_to;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray()[0]['receivables'] : 0;
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

    public function get_financial_report($date_from, $date_to)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM (
    (SELECT 
        supplies_expense.supplies_expense_date AS date, 
        'Supplies Invoice' AS reference,
        expense_type.name COLLATE utf8mb4_general_ci AS account_type,
        supplies_expense.remarks COLLATE utf8mb4_general_ci AS description,
        NULL AS income,
        supplies_expense.grand_total AS expense
    FROM supplies_expense
    LEFT JOIN expense_type ON expense_type.id = supplies_expense.type
    WHERE supplies_expense.is_deleted = 0
    AND supplies_expense.status NOT IN ('pending', 'for_approval', 'disapproved', 'deleted'))

    UNION ALL

    (SELECT 
        project_expense.project_expense_date AS date, 
        'Project Invoice' AS reference,
        expense_type.name COLLATE utf8mb4_general_ci AS account_type,
        project_expense.remarks COLLATE utf8mb4_general_ci AS description,
        NULL AS income, 
        project_expense.grand_total AS expense
    FROM project_expense
    LEFT JOIN expense_type ON expense_type.id = project_expense.expense_type_id
    WHERE project_expense.is_deleted = 0
    AND project_expense.status = 'approved')

    UNION ALL

    (SELECT 
        project.project_date AS date,
        'Sales Invoice' AS reference,
        project.project_type COLLATE utf8mb4_general_ci AS account_type,
        '' AS description,
        project.grand_total AS income,
        NULL AS expense
    FROM project
    WHERE project.is_deleted = 0)
) AS report
WHERE 1
EOT;
        $binds = [];

        if($date_from && $date_to) {
            $sql .= <<<EOT

AND report.date BETWEEN ? AND ?
EOT;
            $binds[] = $date_from;
            $binds[] = $date_to;
        }
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : [];
    }

    /**
     * Get sales report
     */
    public function get_sales_report($date_from, $date_to, $year)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT 
    SUM(CASE WHEN MONTH(project_invoice_payment.payment_date) = 1 THEN project_invoice_payment.paid_amount ELSE 0 END) AS jan,
    SUM(CASE WHEN MONTH(project_invoice_payment.payment_date) = 2 THEN project_invoice_payment.paid_amount ELSE 0 END) AS feb,
    SUM(CASE WHEN MONTH(project_invoice_payment.payment_date) = 3 THEN project_invoice_payment.paid_amount ELSE 0 END) AS mar,
    SUM(CASE WHEN MONTH(project_invoice_payment.payment_date) = 4 THEN project_invoice_payment.paid_amount ELSE 0 END) AS apr,
    SUM(CASE WHEN MONTH(project_invoice_payment.payment_date) = 5 THEN project_invoice_payment.paid_amount ELSE 0 END) AS may,
    SUM(CASE WHEN MONTH(project_invoice_payment.payment_date) = 6 THEN project_invoice_payment.paid_amount ELSE 0 END) AS jun,
    SUM(CASE WHEN MONTH(project_invoice_payment.payment_date) = 7 THEN project_invoice_payment.paid_amount ELSE 0 END) AS jul,
    SUM(CASE WHEN MONTH(project_invoice_payment.payment_date) = 8 THEN project_invoice_payment.paid_amount ELSE 0 END) AS aug,
    SUM(CASE WHEN MONTH(project_invoice_payment.payment_date) = 9 THEN project_invoice_payment.paid_amount ELSE 0 END) AS sep,
    SUM(CASE WHEN MONTH(project_invoice_payment.payment_date) = 10 THEN project_invoice_payment.paid_amount ELSE 0 END) AS oct,
    SUM(CASE WHEN MONTH(project_invoice_payment.payment_date) = 11 THEN project_invoice_payment.paid_amount ELSE 0 END) AS nov,
    SUM(CASE WHEN MONTH(project_invoice_payment.payment_date) = 12 THEN project_invoice_payment.paid_amount ELSE 0 END) AS `dec`
FROM project_invoice_payment
WHERE project_invoice_payment.is_deleted = 0 
EOT;
        $binds = [];
        
        if ($year) {
            $sql .= <<<EOT

AND YEAR(project_invoice_payment.payment_date) = ? 
EOT;
            $binds[] = $year;
        }

        if ($date_from && $date_to) {
            $sql .= <<<EOT

AND project_invoice_payment.payment_date BETWEEN ? AND ?
EOT;
            $binds[] = $date_from;
            $binds[] = $date_to;
        }
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : [];
    }

    /**
     * Get expenses
     */
    public function get_expenses_report($date_from = null, $date_to = null, $year = null)
    {
        $database = \Config\Database::connect();

    $sql = <<<EOT
SELECT 
    doc_no,
    expense_type,
    jan,
    feb,
    mar,
    apr,
    may,
    jun,
    jul,
    aug,
    sep,
    oct,
    nov,
    `dec`,
    (jan + feb + mar + apr + may + jun + jul + aug + sep + oct + nov + `dec`) AS expense_total
FROM (
    -- Supplies Expense
    SELECT 
        supplies_expense.id AS doc_no,
        expense_type.name AS expense_type,
        CASE WHEN MONTH(supplies_expense.supplies_expense_date) = 1 THEN supplies_expense.grand_total ELSE 0 END AS jan,
        CASE WHEN MONTH(supplies_expense.supplies_expense_date) = 2 THEN supplies_expense.grand_total ELSE 0 END AS feb,
        CASE WHEN MONTH(supplies_expense.supplies_expense_date) = 3 THEN supplies_expense.grand_total ELSE 0 END AS mar,
        CASE WHEN MONTH(supplies_expense.supplies_expense_date) = 4 THEN supplies_expense.grand_total ELSE 0 END AS apr,
        CASE WHEN MONTH(supplies_expense.supplies_expense_date) = 5 THEN supplies_expense.grand_total ELSE 0 END AS may,
        CASE WHEN MONTH(supplies_expense.supplies_expense_date) = 6 THEN supplies_expense.grand_total ELSE 0 END AS jun,
        CASE WHEN MONTH(supplies_expense.supplies_expense_date) = 7 THEN supplies_expense.grand_total ELSE 0 END AS jul,
        CASE WHEN MONTH(supplies_expense.supplies_expense_date) = 8 THEN supplies_expense.grand_total ELSE 0 END AS aug,
        CASE WHEN MONTH(supplies_expense.supplies_expense_date) = 9 THEN supplies_expense.grand_total ELSE 0 END AS sep,
        CASE WHEN MONTH(supplies_expense.supplies_expense_date) = 10 THEN supplies_expense.grand_total ELSE 0 END AS oct,
        CASE WHEN MONTH(supplies_expense.supplies_expense_date) = 11 THEN supplies_expense.grand_total ELSE 0 END AS nov,
        CASE WHEN MONTH(supplies_expense.supplies_expense_date) = 12 THEN supplies_expense.grand_total ELSE 0 END AS `dec`
    FROM supplies_expense
    LEFT JOIN expense_type ON expense_type.id = supplies_expense.type
    WHERE supplies_expense.is_deleted = 0
    AND (
        (supplies_expense.status = 'approved' AND supplies_expense.order_status IN ('complete', 'pending', 'incomplete'))
        OR
        (supplies_expense.status = 'sent' AND supplies_expense.order_status IN ('complete', 'pending', 'incomplete'))
    )
EOT;

        $binds = [];

        if ($year) {
            $sql .= " AND YEAR(supplies_expense.supplies_expense_date) = ?";
            $binds[] = $year;
        }

        if ($date_from && $date_to) {
            $sql .= " AND supplies_expense.supplies_expense_date BETWEEN ? AND ?";
            $binds[] = $date_from;
            $binds[] = $date_to;
        }

    $sql .= <<<EOT

    UNION ALL

    -- Petty Cash
    SELECT 
        petty_cash_detail.id AS doc_no,
        expense_type.name AS expense_type,
        CASE WHEN MONTH(petty_cash_detail.date) = 1 THEN petty_cash_detail.amount ELSE 0 END AS jan,
        CASE WHEN MONTH(petty_cash_detail.date) = 2 THEN petty_cash_detail.amount ELSE 0 END AS feb,
        CASE WHEN MONTH(petty_cash_detail.date) = 3 THEN petty_cash_detail.amount ELSE 0 END AS mar,
        CASE WHEN MONTH(petty_cash_detail.date) = 4 THEN petty_cash_detail.amount ELSE 0 END AS apr,
        CASE WHEN MONTH(petty_cash_detail.date) = 5 THEN petty_cash_detail.amount ELSE 0 END AS may,
        CASE WHEN MONTH(petty_cash_detail.date) = 6 THEN petty_cash_detail.amount ELSE 0 END AS jun,
        CASE WHEN MONTH(petty_cash_detail.date) = 7 THEN petty_cash_detail.amount ELSE 0 END AS jul,
        CASE WHEN MONTH(petty_cash_detail.date) = 8 THEN petty_cash_detail.amount ELSE 0 END AS aug,
        CASE WHEN MONTH(petty_cash_detail.date) = 9 THEN petty_cash_detail.amount ELSE 0 END AS sep,
        CASE WHEN MONTH(petty_cash_detail.date) = 10 THEN petty_cash_detail.amount ELSE 0 END AS oct,
        CASE WHEN MONTH(petty_cash_detail.date) = 11 THEN petty_cash_detail.amount ELSE 0 END AS nov,
        CASE WHEN MONTH(petty_cash_detail.date) = 12 THEN petty_cash_detail.amount ELSE 0 END AS `dec`
    FROM petty_cash_detail
    LEFT JOIN expense_type ON expense_type.id = petty_cash_detail.out_type
    WHERE petty_cash_detail.is_deleted = 0
        AND petty_cash_detail.type = 'out'
EOT;

        if ($year) {
            $sql .= " AND YEAR(petty_cash_detail.date) = ?";
            $binds[] = $year;
        }

        if ($date_from && $date_to) {
            $sql .= " AND petty_cash_detail.date BETWEEN ? AND ?";
            $binds[] = $date_from;
            $binds[] = $date_to;
        }

        $sql .= ") AS detailed_expenses ORDER BY expense_type, doc_no";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : [];
    }
}