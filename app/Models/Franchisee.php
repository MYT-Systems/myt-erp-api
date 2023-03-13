<?php

namespace App\Models;

class Franchisee extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'branch_id',
        'name',
        'type',
        'grand_total',
        'royalty_fee',
        'marketing_fee',
        'franchisee_fee',
        'franchisee_package',
        'paid_amount',
        'balance',
        'payment_status',
        'contract_start',
        'contract_end',
        'franchisee_contact_no',
        'franchised_on',
        'opening_start',
        'remarks',
        'contact_person',
        'contact_number',
        'address',
        'email',
        'package_type',
        'security_deposit',
        'taxes',
        'beginning_credit_limit',
        'current_credit_limit',
        'other_fee',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'franchisee';
    }

    /**
     * Get franchisee by ID
     */
    public function get_details_by_id($franchisee_id = null)
    {
        $database = \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
SELECT franchisee.*,
    branch.name AS branch_name,
    (SELECT IFNULL(sum(franchisee_sale.balance),0) 
        FROM franchisee_sale
        WHERE franchisee_sale.franchisee_id = franchisee.id
        AND franchisee_sale.is_deleted = 0
        AND franchisee_sale.fs_status IN ('processing', 'invoiced')
    ) AS payable_credit,
    IF (franchisee.contract_end < ?, 'expired', 'active') AS contract_status
FROM franchisee
LEFT JOIN branch ON branch.id = franchisee.branch_id
WHERE franchisee.is_deleted = 0
    AND franchisee.id = ?
EOT;
        $binds = [$date_now, $franchisee_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get payable credit base on franchisee name
     */
    public function get_payable_credit_by_franchisee_name($franchisee_name = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT
    SUM((SELECT IFNULL(sum(franchisee_sale.balance),0) 
        FROM franchisee_sale
        WHERE franchisee_sale.franchisee_id = franchisee.id
        AND franchisee_sale.is_deleted = 0
        AND franchisee_sale.fs_status IN ('processing', 'invoiced')
    )) AS payable_credit
FROM franchisee
WHERE franchisee.is_deleted = 0
    AND franchisee.name LIKE ?
EOT;
        $binds = ["%{$franchisee_name}%"];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get ramaining credit based on franchisee name
     */
    public function get_remaining_credit_by_franchisee_name($franchisee_name = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT SUM(franchisee.current_credit_limit) AS remaining_credit
FROM franchisee
WHERE franchisee.is_deleted = 0
    AND franchisee.name LIKE ?
EOT;
        $binds = ["%{$franchisee_name}%"];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all franchisee
     */
    public function get_all()
    {
        $database = \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');
        
        $sql = <<<EOT
SELECT franchisee.*,
    branch.name AS branch_name,
    (SELECT IFNULL(sum(franchisee_sale.balance),0) 
        FROM franchisee_sale
        WHERE franchisee_sale.franchisee_id = franchisee.id
        AND franchisee_sale.is_deleted = 0
        AND franchisee_sale.fs_status IN ('processing', 'invoiced')
    ) AS payable_credit,
    IF (franchisee.contract_end < ?, 'expired', 'active') AS contract_status
FROM franchisee
LEFT JOIN branch ON branch.id = franchisee.branch_id
WHERE franchisee.is_deleted = 0
EOT;
        $binds = [$date_now];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Search
     */
    public function search($branch_id, $name, $type, $franchisee_fee, $royalty_fee, $paid_amount, $payment_status, $franchised_on_from, $franchised_on_to, $opening_start, $remarks, $contact_person, $contact_number, $address, $email, $contract_status)
    {
        $database = \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
SELECT franchisee.*,
    branch.name AS branch_name,
    (SELECT IFNULL(sum(franchisee_sale.balance),0) 
        FROM franchisee_sale
        WHERE franchisee_sale.franchisee_id = franchisee.id
        AND franchisee_sale.is_deleted = 0
        AND franchisee_sale.fs_status IN ('processing', 'invoiced')
    ) AS payable_credit,
    IF (franchisee.contract_end < ?, 'expired', 'active') AS contract_status
FROM franchisee
LEFT JOIN branch ON branch.id = franchisee.branch_id
WHERE franchisee.is_deleted = 0
EOT;
        $binds = [$date_now];

        if ($branch_id) {
            $sql .= ' AND branch_id = ?';
            $binds[] = $branch_id;
        }

        if ($name) {
            $sql .= ' AND franchisee.name REGEXP ?';
            $name    = str_replace(' ', '|', $name);
            $binds[] = $name;
        }

        if ($type) {
            $sql .= ' AND type = ?';
            $binds[] = $type;
        }

        if ($franchisee_fee) {
            $sql .= ' AND franchisee_fee = ?';
            $binds[] = $franchisee_fee;
        }

        if ($royalty_fee) {
            $sql .= ' AND royalty_fee = ?';
            $binds[] = $royalty_fee;
        }

        if ($paid_amount) {
            $sql .= ' AND paid_amount = ?';
            $binds[] = $paid_amount;
        }

        if ($payment_status) {
            $sql .= ' AND payment_status = ?';
            $binds[] = $payment_status;
        }

        if ($franchised_on_from) {
            $sql .= ' AND franchised_on >= ?';
            $binds[] = $franchised_on_from;
        }

        if ($franchised_on_to) {
            $sql .= ' AND franchised_on <= ?';
            $binds[] = $franchised_on_to;
        }
        
        if ($opening_start) {
            $sql .= ' AND opening_start = ?';
            $binds[] = $opening_start;
        }

        if ($remarks) {
            $sql .= ' AND remarks REGEXP ?';
            $remarks = str_replace(' ', '|', $remarks);
            $binds[] = $remarks;
        }

        if ($contact_person) {
            $sql .= ' AND contact_person REGEXP ?';
            $contact_person = str_replace(' ', '|', $contact_person);
            $binds[] = $contact_person;
        }

        if ($contact_number) {
            $sql .= ' AND contact_number REGEXP ?';
            $contact_number = str_replace(' ', '|', $contact_number);
            $binds[] = $contact_number;
        }

        if ($address) {
            $sql .= ' AND address REGEXP ?';
            $address = str_replace(' ', '|', $address);
            $binds[] = $address;
        }

        if ($email) {
            $sql .= ' AND email REGEXP ?';
            $email = str_replace(' ', '|', $email);
            $binds[] = $email;
        }

        if ($contract_status) {
            $sql .= ' AND IF (franchisee.contract_end < ?, "expired", "active") = ?';
            $binds[] = $date_now;
            $binds[] = $contract_status;
        }

        $sql .= ' ORDER BY franchisee.name ASC';

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Update schedule by branch id
     */
    public function update_schedule_by_branch_id($branch_id, $values, $db)
    {
        $database = $db ? $db : \Config\Database::connect();
        $sql = <<<EOT
UPDATE franchisee
SET opening_start = ?, updated_by = ?, updated_on = ?
WHERE branch_id = ?
EOT;    
        $binds = [$values['opening_start'], $values['updated_by'], $values['updated_on'], $branch_id];

        return $this->query($sql, $binds);
    }
}