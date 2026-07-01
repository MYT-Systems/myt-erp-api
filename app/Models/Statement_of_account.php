<?php

namespace App\Models;

class Statement_of_account extends MYTModel
{
    public function __construct()
    {
        $this->table = 'project_invoice';
    }

    /**
     * Get all projects that have at least one non-cancelled invoice
     */
    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT project.id AS id, project.name AS project_name, customer.name AS customer_name, project.company AS company
FROM project
LEFT JOIN customer ON customer.id = project.customer_id
WHERE project.is_deleted = 0
    AND EXISTS (
        SELECT 1 FROM project_invoice
        WHERE project_invoice.project_id = project.id
            AND project_invoice.is_deleted = 0
            AND project_invoice.status != 'cancelled_invoice'
    )
ORDER BY project.name ASC
EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get project and client details for the statement header
     */
    public function get_project_details($project_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT project.id AS id, project.name AS project_name, project.address AS project_address,
    customer.name AS client_name, customer.address AS customer_address
FROM project
LEFT JOIN customer ON customer.id = project.customer_id
WHERE project.is_deleted = 0
    AND project.id = ?
EOT;
        $query = $database->query($sql, [$project_id]);
        return $query ? $query->getRowArray() : false;
    }

    /**
     * Get combined ledger: invoice line-items (debit) and payments (credit), ordered by date
     */
    public function get_ledger($project_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT * FROM (
    SELECT
        project_invoice.invoice_date      AS txn_date,
        project_invoice.invoice_no        AS billing_no,
        project_invoice_item.item_name    AS description,
        project_invoice_item.billed_amount AS debit,
        0                                 AS credit,
        project_invoice.id                AS invoice_sort_id,
        project_invoice_item.id           AS item_sort_id,
        1                                 AS sort_order
    FROM project_invoice_item
    JOIN project_invoice ON project_invoice.id = project_invoice_item.project_invoice_id
    WHERE project_invoice.project_id = ?
        AND project_invoice.is_deleted = 0
        AND project_invoice_item.is_deleted = 0
        AND project_invoice.status != 'cancelled_invoice'

    UNION ALL

    SELECT
        COALESCE(project_invoice_payment.deposit_date, project_invoice_payment.payment_date) AS txn_date,
        project_invoice.invoice_no AS billing_no,
        CONCAT('Payment - ', COALESCE(
            NULLIF(project_invoice_payment.remarks, ''),
            NULLIF(project_invoice_payment.payment_description, '0'),
            'N/A'
        )) AS description,
        0 AS debit,
        project_invoice_payment.paid_amount AS credit,
        project_invoice_payment.project_invoice_id AS invoice_sort_id,
        project_invoice_payment.id AS item_sort_id,
        2 AS sort_order
    FROM project_invoice_payment
    LEFT JOIN project_invoice ON project_invoice.id = project_invoice_payment.project_invoice_id
    WHERE project_invoice_payment.project_id = ?
        AND project_invoice_payment.is_deleted = 0
) AS ledger
ORDER BY txn_date ASC, invoice_sort_id ASC, sort_order ASC, item_sort_id ASC
EOT;
        $query = $database->query($sql, [$project_id, $project_id]);
        return $query ? $query->getResultArray() : [];
    }

    /**
     * Get current outstanding balance for a project, independent of any date filter
     */
    public function get_current_balance($project_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT SUM(balance) AS current_balance
FROM project_invoice
WHERE project_id = ?
    AND is_deleted = 0
    AND status != 'cancelled_invoice'
EOT;
        $query = $database->query($sql, [$project_id]);
        $result = $query ? $query->getRowArray() : null;
        return ($result && $result['current_balance'] !== null) ? (float) $result['current_balance'] : 0.0;
    }
}
