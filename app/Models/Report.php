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
    public function get_expense($expense_type = null, $date_from = null, $date_to = null, $payment_status = null)
    {
        $database = \Config\Database::connect();
    
        $binds = [];

        $sql = <<<EOT
SELECT expense.expense_date AS expense_date, expense.particulars AS particulars, expense.doc_no AS doc_no, expense.total AS expense_total, expense.type AS expense_type, expense.payment_status AS payment_status, expense.reference_no AS reference_no, expense.paid_amount AS paid_amount
FROM (
    SELECT supplies_expense.supplies_expense_date AS expense_date, 'Supplies Expense' AS particulars, supplies_expense.id AS doc_no, supplies_expense.grand_total AS total, expense_type.name AS type, CASE WHEN supplies_expense.grand_total = supplies_expense.paid_amount THEN 'fully paid' WHEN supplies_expense.grand_total < supplies_expense.paid_amount THEN 'over paid' WHEN supplies_expense.paid_amount > 0 AND supplies_expense.grand_total > supplies_expense.paid_amount THEN 'partially paid' ELSE 'unpaid' END AS payment_status, supplies_expense.doc_no AS reference_no, supplies_expense.paid_amount AS paid_amount
    FROM supplies_expense
    LEFT JOIN expense_type ON expense_type.id = supplies_expense.type

    UNION

    SELECT project_expense.project_expense_date AS expense_date, 'Project Expense' AS particulars, project_expense.id AS doc_no, project_expense.grand_total AS total, expense_type.name AS type, CASE WHEN project_expense.grand_total = project_expense.paid_amount THEN 'fully paid' WHEN project_expense.grand_total < project_expense.paid_amount THEN 'over paid' WHEN project_expense.paid_amount > 0 AND project_expense.grand_total > project_expense.paid_amount THEN 'partially paid' ELSE 'unpaid' END AS payment_status, project_expense.id AS reference_no, project_expense.paid_amount AS paid_amount
    FROM project_expense
    LEFT JOIN expense_type ON expense_type.id = project_expense.expense_type_id
) expense
EOT;

        $sql .= " WHERE expense.type IS NOT NULL AND expense.expense_date IS NOT NULL ";

        if ($expense_type) {
            $sql .= " AND expense.type = ?";
            $binds[] = $expense_type;
        }

        if ($date_from) {
            $sql .= " AND expense.expense_date >= ?";
            $binds[] = $date_from;
        }

        if ($date_to) {
            $sql .= " AND expense.expense_date <= ?";
            $binds[] = $date_to;
        }

        if ($payment_status) {
            $sql .= " AND expense.payment_status = ?";
            $binds[] = $payment_status;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : [];
    }

    /**
     * Get expense
     */
    public function get_project_sales($project_id = null, $date_from = null, $date_to = null, $customer_id = null)
    {
        $database = \Config\Database::connect();
    
        $binds = [];

        $sql = <<<EOT
SELECT project.name AS name, project.start_date AS start_date, customer.name AS customer_name, project.grand_total AS amount, project.paid_amount AS paid_amount, project.grand_total - project.paid_amount AS receivable, SUM(IFNULL(project_expense.grand_total, 0)) AS project_expense, project.paid_amount - SUM(IFNULL(project_expense.grand_total, 0)) AS total_sales
FROM project
LEFT JOIN customer ON customer.id = project.customer_id
LEFT JOIN project_expense ON project_expense.project_id = project.id
EOT;

        $sql .= " WHERE 1 ";

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
     * Get payables aging
     */
    public function get_payables_aging($supplier_id = null, $expense_type = null)
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
SELECT supplier_id, supplier_name, GROUP_CONCAT(cur) AS cur, GROUP_CONCAT(one_to_thirthy) AS one_to_thirthy, GROUP_CONCAT(thirtyone_to_sixty) AS thirtyone_to_sixty, GROUP_CONCAT(sixty_to_ninety) AS sixty_to_ninety, GROUP_CONCAT(above_ninety) AS above_ninety, SUM(total) AS total, SUM(total_paid) AS total_paid
FROM (
  SELECT supplier.id AS supplier_id, supplies_expense.id, supplier.trade_name AS supplier_name,
    CASE
    WHEN (supplies_expense.grand_total > supplies_expense.paid_amount AND DATEDIFF(CURDATE(), supplies_expense.due_date) <= 30) THEN GROUP_CONCAT(CONCAT("Supplies Expense ",supplies_expense.id,'-',supplies_expense.grand_total))END AS cur,
    CASE
    WHEN supplies_expense.grand_total > supplies_expense.paid_amount AND DATEDIFF(CURDATE(), supplies_expense.due_date) > 30 AND DATEDIFF(CURDATE(), supplies_expense.due_date) <= 60  THEN CONCAT("Supplies Expense ",supplies_expense.id,'-',supplies_expense.grand_total) END AS one_to_thirthy,
    CASE
    WHEN supplies_expense.grand_total > supplies_expense.paid_amount AND DATEDIFF(CURDATE(), supplies_expense.due_date) > 60 AND DATEDIFF(CURDATE(), supplies_expense.due_date) <= 90  THEN CONCAT("Supplies Expense ",supplies_expense.id,'-',supplies_expense.grand_total) END AS thirtyone_to_sixty,
    CASE
    WHEN supplies_expense.grand_total > supplies_expense.paid_amount AND DATEDIFF(CURDATE(), supplies_expense.due_date) > 90 AND DATEDIFF(CURDATE(), supplies_expense.due_date) <= 120  THEN CONCAT("Supplies Expense ",supplies_expense.id,'-',supplies_expense.grand_total) END AS sixty_to_ninety,
    CASE
    WHEN supplies_expense.grand_total > supplies_expense.paid_amount AND DATEDIFF(CURDATE(), supplies_expense.due_date) > 120 THEN CONCAT("Supplies Expense ",supplies_expense.id,'-',supplies_expense.grand_total) END AS above_ninety,
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
SELECT customer_id, customer_name, GROUP_CONCAT(cur) AS cur, GROUP_CONCAT(one_to_thirthy) AS one_to_thirthy, GROUP_CONCAT(thirtyone_to_sixty) AS thirtyone_to_sixty, GROUP_CONCAT(sixty_to_ninety) AS sixty_to_ninety, GROUP_CONCAT(above_ninety) AS above_ninety, SUM(total) AS total, SUM(total_paid) AS total_paid
FROM (
  SELECT customer.id AS customer_id, project_invoice.id, customer.name AS customer_name,
    CASE
    WHEN (project_invoice.grand_total > project_invoice.paid_amount AND DATEDIFF(CURDATE(), project_invoice.due_date) <= 30) THEN GROUP_CONCAT(CONCAT("Supplies Expense ",project_invoice.id,'-',project_invoice.grand_total))END AS cur,
    CASE
    WHEN project_invoice.grand_total > project_invoice.paid_amount AND DATEDIFF(CURDATE(), project_invoice.due_date) > 30 AND DATEDIFF(CURDATE(), project_invoice.due_date) <= 60  THEN CONCAT("Supplies Expense ",project_invoice.id,'-',project_invoice.grand_total) END AS one_to_thirthy,
    CASE
    WHEN project_invoice.grand_total > project_invoice.paid_amount AND DATEDIFF(CURDATE(), project_invoice.due_date) > 60 AND DATEDIFF(CURDATE(), project_invoice.due_date) <= 90  THEN CONCAT("Supplies Expense ",project_invoice.id,'-',project_invoice.grand_total) END AS thirtyone_to_sixty,
    CASE
    WHEN project_invoice.grand_total > project_invoice.paid_amount AND DATEDIFF(CURDATE(), project_invoice.due_date) > 90 AND DATEDIFF(CURDATE(), project_invoice.due_date) <= 120  THEN CONCAT("Supplies Expense ",project_invoice.id,'-',project_invoice.grand_total) END AS sixty_to_ninety,
    CASE
    WHEN project_invoice.grand_total > project_invoice.paid_amount AND DATEDIFF(CURDATE(), project_invoice.due_date) > 120 THEN CONCAT("Supplies Expense ",project_invoice.id,'-',project_invoice.grand_total) END AS above_ninety,
    project_invoice.grand_total as total,
    project_invoice.paid_amount as total_paid,
    project.id AS project_id
  FROM project_invoice
  LEFT JOIN project ON project.id = project_invoice.project_id
  LEFT JOIN customer ON customer.id = project.customer_id
  WHERE project_invoice.is_deleted = 0
    AND customer.is_deleted = 0
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

GROUP BY data.customer_id;
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

GROUP BY project_invoice.id;
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