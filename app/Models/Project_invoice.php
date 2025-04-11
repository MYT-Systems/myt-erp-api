<?php

namespace App\Models;

class Project_invoice extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'project_id',
        'invoice_date',
        'invoice_no',
        'project_date',
        'due_date',
        'address',
        'company',
        'remarks',
        'subtotal',
        'vat_twelve',
        'vat_net',
        'wht',
        'is_wht',
        'wht_percent',
        'service_fee',
        'delivery_fee',
        'grand_total',
        'vat_type',
        'balance',
        'paid_amount',
        'payment_status',
        'status',
        'discount',
        'fully_paid_on',
        'is_closed',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'project_invoice';
    }

    /**
     * Get project_invoice by ID
     */
    public function get_details_by_id($project_invoice_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT project_invoice.*,
    (SELECT name FROM project WHERE project.id = project_invoice.project_id) AS project_name,
    (SELECT grand_total FROM project WHERE project.id = project_invoice.project_id) AS project_amount,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE user.id = project_invoice.added_by) AS added_by_name
FROM project_invoice
LEFT JOIN project_invoice_payment ON project_invoice_payment.project_invoice_id = project_invoice.id
LEFT JOIN project ON project.id = project_invoice.project_id
WHERE project_invoice.is_deleted = 0
    AND project_invoice.id = ?
    AND project.is_deleted = 0
GROUP BY project_invoice.id
EOT;
        $binds = [$project_invoice_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get project_invoice by ID
     */
    public function get_details_by_project_id($project_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT project_invoice.*, project_invoice.balance AS invoice_balance,
    (SELECT CONCAT(first_name, ' ', last_name) FROM user WHERE user.id = project_invoice.added_by) AS added_by_name, project_invoice_item_name.name AS project_invoice_item_name, SUM(project_invoice_payment.paid_amount) AS paid_amount
FROM project_invoice
LEFT JOIN project ON project.id = project_invoice.project_id
LEFT JOIN project_invoice_payment ON project_invoice_payment.project_invoice_id = project_invoice.id
LEFT JOIN (
SELECT GROUP_CONCAT(DISTINCT project_invoice_item.item_name ORDER BY project_invoice_item.id ASC SEPARATOR ', ') AS name, project_invoice_item.project_invoice_id AS project_invoice_id
FROM project_invoice_item
WHERE project_invoice_item.is_deleted = 0
GROUP BY project_invoice_item.project_invoice_id
) AS project_invoice_item_name ON project_invoice_item_name.project_invoice_id = project_invoice.id
WHERE project_invoice.is_deleted = 0
    AND project.id = ?
    AND project.is_deleted = 0
GROUP BY project_invoice.id
ORDER BY project_invoice.id ASC
EOT;
        $binds = [$project_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get the last invoice_no for the current year
     */
    public function get_last_invoice_no_by_year()
    {
        $database = \Config\Database::connect();
        $currentYear = date('Y') . '-%'; // Format YYYY-

    $sql = <<<EOT
SELECT invoice_no
FROM project_invoice
WHERE is_deleted = 0 AND invoice_no LIKE ?
ORDER BY invoice_no DESC
LIMIT 1
EOT;

        $query = $database->query($sql, [$currentYear]);
        return $query ? $query->getRowArray() : false;
    }


    /**
     * Get all project_invoice
     */
    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM project WHERE project.id = project_invoice.project_id) AS project_name,
    (SELECT CONCAT(first_name, ' ', last_name) FROM employee WHERE employee.id = project_invoice.added_by) AS added_by_name
FROM project_invoice
LEFT JOIN project ON project.id = project_invoice.project_id
WHERE project_invoice.is_deleted = 0
AND project.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

     /**
     * Search
     */
    public function search($project_invoice_id = null, $project_id = null, $invoice_date = null, $address = null, $company = null, $remarks = null, $payment_status = null, $status = null, $fully_paid_on = null, $anything = null, $date_from = null, $date_to = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT 
    project_invoice.*,
    project.name AS project_name,
    CONCAT(adder.first_name, ' ', adder.last_name) AS added_by_name,
    IF (
        project_invoice.is_closed = 1, 
        'closed_bill', 
        IF (
            project_invoice.paid_amount > project_invoice.grand_total, 
            'overpaid', 
            project_invoice.payment_status
        )
    ) AS payment_status,
    project.grand_total AS project_amount,
    (
        SELECT MAX(payment_date) 
        FROM project_invoice_payment 
        WHERE project_invoice_payment.project_invoice_id = project_invoice.id
    ) AS payment_date,
    (
        SELECT MAX(deposit_date) 
        FROM project_invoice_payment 
        WHERE project_invoice_payment.project_invoice_id = project_invoice.id
    ) AS deposit_date
FROM 
    project_invoice
LEFT JOIN 
    project ON project.id = project_invoice.project_id
LEFT JOIN 
    employee AS adder ON adder.id = project_invoice.added_by
WHERE 
    project_invoice.is_deleted = 0
    AND project.is_deleted = 0
EOT;

        $binds = [];

        if ($project_invoice_id) {
            $sql .= ' AND project_invoice.id = ?';
            $binds[] = $project_invoice_id;
        }

        if ($company) {
            $sql .= ' AND project_invoice.company = ?';
            $binds[] = $company;
        }

        if ($project_id) {
            $sql .= ' AND project_invoice.project_id = ?';
            $binds[] = $project_id;
        }

        if ($payment_status === 'overpaid') {
            $sql .= ' AND project_invoice.paid_amount > project_invoice.grand_total';
            $sql .= ' AND (project_invoice.is_closed = 0 OR project_invoice.is_closed IS NULL)';
        } elseif ($payment_status) {
            $sql .= ' AND project_invoice.payment_status = ?';
            $binds[] = $payment_status;
        } elseif (empty($payment_status)) {
            $sql .= ' AND project_invoice.status = "pending"';
        }

        if ($fully_paid_on) {
            $sql .= ' AND project_invoice.fully_paid_on = ?';
            $binds[] = $fully_paid_on;
        }

        if ($anything) {
            $sql .= ' AND (
                project_invoice.company LIKE ? OR 
                project_invoice.invoice_no LIKE ? OR 
                project.name LIKE ?
            )';

            // Correctly bind placeholders with wildcards
            $wildcard = "%$anything%";
            $new_binds = array_fill(0, 3, $wildcard);
            $binds = array_merge($binds, $new_binds);
        }

        if ($date_from) {
            $date_from = date('Y-m-d 00:00:00', strtotime($date_from));
            $sql .= ' AND (SELECT MAX(deposit_date) 
                    FROM project_invoice_payment 
                    WHERE project_invoice_payment.project_invoice_id = project_invoice.id) >= ?';
            $binds[] = $date_from;
        }

        if ($date_to) {
            $date_to = date('Y-m-d 23:59:59', strtotime($date_to));
            $sql .= ' AND (SELECT MAX(deposit_date) 
                    FROM project_invoice_payment 
                    WHERE project_invoice_payment.project_invoice_id = project_invoice.id) <= ?';
            $binds[] = $date_to;
        }

        try {
            $query = $database->query($sql, $binds);
            return $query ? $query->getResultArray() : false;
        } catch (\mysqli_sql_exception $e) {
            log_message('error', $e->getMessage());
            return false;
        }
    }

    /**
     * Get project_invoice by invoice_numbers
     */
    public function get_invoices_by_invoice_numbers(array $invoice_numbers)
    {
        $database = \Config\Database::connect();

        if (empty($invoice_numbers)) {
            return []; 
        }

        $invoice_numbers = array_values($invoice_numbers);

        $placeholders = implode(',', array_fill(0, count($invoice_numbers), '?'));

        $sql = <<<EOT
    SELECT project_invoice.invoice_no, project_invoice.invoice_date, project_invoice.due_date,
        project.name AS project_name,
        (SELECT MAX(payment_date) 
            FROM project_invoice_payment 
            WHERE project_invoice_payment.project_invoice_id = project_invoice.id) AS payment_date
    FROM project_invoice
    LEFT JOIN project ON project.id = project_invoice.project_id
    WHERE project_invoice.is_deleted = 0
    AND project.is_deleted = 0
    AND project_invoice.invoice_no IN ($placeholders)
    EOT;

        $query = $database->query($sql, $invoice_numbers);

        return $query ? $query->getResultArray() : [];
    }

    /**
     * Get project_invoice summary by ID
     */
    public function get_summary_by_id($project_id = null){
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT project_invoice.*,
    project.name AS project_name,
    CONCAT(adder.first_name, ' ', adder.last_name) AS added_by_name,
    IF (project_invoice.is_closed = 1, 'closed_bill', 
        IF (project_invoice.paid_amount > project_invoice.grand_total, 'overpaid', project_invoice.payment_status)
    ) AS payment_status,
    project.grand_total AS project_amount,
    (SELECT MAX(payment_date) 
     FROM project_invoice_payment 
     WHERE project_invoice_payment.project_invoice_id = project_invoice.id) AS payment_date
FROM project_invoice
LEFT JOIN project ON project.id = project_invoice.project_id
LEFT JOIN employee AS adder ON adder.id = project_invoice.added_by
WHERE project_invoice.is_deleted = 0
AND project.is_deleted = 0
EOT;

        $binds = [];

        if ($project_id) {
            $sql .= ' AND project_invoice.project_id = ?';
            $binds[] = $project_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /*
     *search_project_invoice_item
    */
    public function search_project_invoice_item($project_name, $item_id, $sales_date_from, $sales_date_to)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT  item.name AS item_name,
        project_invoice_item.item_id,
        project_invoice_item.item_unit_id,
        item_unit.inventory_unit,
        item_unit.breakdown_unit,
        project_invoice.id AS project_invoice_id,
        project_invoice.sales_date,
        project_invoice.sales_invoice_no AS invoice_no,
        project.name AS project_name,
        seller_branch.name AS seller_branch_name,
        buyer_branch.name AS buyer_branch_name,
        project_invoice_item.qty AS total_quantity,
        project_invoice_item.subtotal AS total_subtotal,
        project_invoice_item.price AS average_price,
        project_invoice_item.discount AS total_discount
FROM project_invoice_item
LEFT JOIN project_invoice ON project_invoice.id = project_invoice_item.project_invoice_id
LEFT JOIN project ON project.id = project_invoice.project_id
LEFT JOIN branch AS seller_branch ON seller_branch.id = project_invoice.seller_branch_id
LEFT JOIN branch AS buyer_branch ON buyer_branch.id = project_invoice.buyer_branch_id
LEFT JOIN item ON item.id = project_invoice_item.item_id
LEFT JOIN item_unit ON item_unit.id = project_invoice_item.item_unit_id
WHERE project_invoice_item.is_deleted = 0
EOT;
        $binds = [];

        if ($project_name) {
            $sql .= '  AND project.name LIKE ?';
            $binds[] = '%' . $project_name . '%';
        }

        if ($item_id) {
            $sql .= ' AND project_invoice_item.item_id = ?';
            $binds[] = $item_id;
        }

        if ($sales_date_from) {
            $sql .= ' AND project_invoice.sales_date >= ?';
            $binds[] = $sales_date_from;
        }

        if ($sales_date_to) {
            $sql .= ' AND project_invoice.sales_date <= ?';
            $binds[] = $sales_date_to;
        }

        // $sql .= ' GROUP BY project_invoice_item.item_id, project_invoice_item.item_unit_id';

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}