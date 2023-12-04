<?php

namespace App\Models;

class Distributor extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'name',
        'address',
        'contact_person',
        'contact_no',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'distributor';
    }

    /**
     * Get distributor details by ID
     */
    public function get_details_by_id($distributor_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT distributor.*
FROM distributor
WHERE distributor.id = ?
    AND distributor.is_deleted = 0
EOT;
        $binds = [$distributor_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get distributor that are in need of billing for a certain date
     * If no billing in the past 30 days then client must show
     */
    public function get_clients_to_bill($distributor_id = null, $billing_date = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT distributor_client.*, customer.name AS customer_name, project.name AS project_name, distributor_billing.grand_total
FROM distributor
    LEFT JOIN distributor_client ON distributor_client.distributor_id = distributor.id
    LEFT JOIN distributor_billing_entry ON distributor_billing_entry.distributor_client_id = distributor_client.id
    LEFT JOIN distributor_billing ON distributor_billing.id = distributor_billing_entry.distributor_billing_id
    LEFT JOIN project ON project.id = distributor_client.project_id
    LEFT JOIN customer ON customer.id = distributor_client.customer_id
WHERE (distributor_billing.billing_date IS NULL OR distributor_billing.billing_date < DATE_SUB(?, INTERVAL 30 DAY))
    AND (project.start_date < DATE_SUB(?, INTERVAL 30 DAY))
    AND (distributor_client.distributor_client_date < DATE_SUB(?, INTERVAL 30 DAY))
    AND distributor.is_deleted = 0
    AND distributor_client.is_deleted = 0
    AND (distributor_billing_entry.is_deleted = 0 OR distributor_billing_entry.is_deleted IS NULL)
    AND (distributor_billing.is_deleted = 0 OR distributor_billing.is_deleted IS NULL)
EOT;
    $binds = [$billing_date, $billing_date, $billing_date];

if($distributor_id) {
    $sql .= <<<EOT

    AND distributor.id = ?    
EOT;
        $binds[] = $distributor_id;
}

    $sql .= <<<EOT

GROUP BY distributor_client.id
EOT;

        // 2023-08-31


        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get distributor details by ID
     */
    public function filter_distributor_status($status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT distributor.*
FROM distributor
WHERE distributor.status = ?
    AND distributor.is_deleted = 0
EOT;
        $binds = [$status];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Filter by order status
     */
    public function filter_order_status($status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT distributor.*
FROM distributor
WHERE distributor.order_status = ?
    AND distributor.is_deleted = 0
EOT;
        $binds = [$status];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all distributors
     */
    public function get_all_distributor()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT distributor.*
FROM distributor
WHERE distributor.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get distributors based on distributor name, contact_person, phone_no, tin_no, bir_no, email
     * can_be_paid - used by add payment function, this avoid overpaying
     */
    public function search($name, $limit_by, $anything)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT distributor.*, 
FROM distributor
WHERE distributor.is_deleted = 0
EOT;
        $binds = [];

        if ($name) {
            $sql .= ' AND distributor.name = ?';
            $binds[] = $branch_id;
        }

        $sql .= ' ORDER BY distributor.added_on DESC';

        if ($limit_by) {
            $sql .= ' LIMIT ?';
            $binds[] = (int)$limit_by;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}