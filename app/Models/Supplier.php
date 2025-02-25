<?php

namespace App\Models;

class Supplier extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'trade_name',
        'trade_address',
        'bir_name',
        'bir_address',
        'bir_number',
        'tin',
        'terms',
        'requirements',
        'phone_no',
        'email',
        'contact_person',
        'bank_primary',
        'primary_account_no',
        'primary_account_name',
        'bank_alternate',
        'alternate_account_no',
        'alternate_account_name',
        'payee',
        'vat_type',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'supplier';
    }
    
    /**
     * Get supplier details by ID
     */
    public function get_details_by_id($supplier_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM supplier
WHERE supplier.is_deleted = 0
    AND supplier.id = ?
EOT;
        $binds = [$supplier_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all suppliers
     */
    public function get_all_supplier()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM supplier
WHERE supplier.is_deleted = 0
ORDER BY supplier.trade_name ASC
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get supplier details by supplier name
     */
    public function get_details_by_supplier_name($supplier_name)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM supplier
WHERE supplier.is_deleted = 0
    AND supplier.name = ?
EOT;
        $binds = [$supplier_name];
        $query = $database->query($sql, $binds);

        return !$query->getResult() ? false : $query->getResult()[0];
    }

    /**
     * Get suppliers based on supplier name, contact_person, phone_no, tin_no, bir_no, email
     */
    public function search($trade_name, $trade_address, $bir_name, $bir_number, $bir_address, $tin, $terms, $requirements, $phone_no, $email, $contact_person, $bank_primary, $primary_account_no, $primary_account_name, $bank_alternate, $alternate_account_no, $alternate_account_name)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM supplier
WHERE supplier.is_deleted = 0
EOT;
        $binds = [];

        if ($trade_name) {
            $sql       .= " AND supplier.trade_name REGEXP ?";
            $trade_name = str_replace(' ', '|', $trade_name);
            $binds[]    = $trade_name;
        }

        if ($trade_address) {
            $sql          .= " AND trade_address REGEXP ?";
            $trade_address = str_replace(' ', '|', $trade_address);
            $binds[]       = $trade_address;
        }

        if ($bir_name) {
            $sql     .= " AND bir_name REGEXP ?";
            $bir_name = str_replace(' ', '|', $bir_name);
            $binds[]  = $bir_name;
        }

        if ($bir_address) {
            $sql        .= " AND bir_address REGEXP ?";
            $bir_address = str_replace(' ', '|', $bir_address);
            $binds[]     = $bir_address;
        }

        if ($tin) {
            $sql .= " AND tin = ?";
            $binds[] = $tin;
        }

        if ($terms) {
            $sql .= " AND terms = ?";
            $binds[] = $terms;
        }

        if ($requirements) {
            $sql         .= " AND requirements REGEXP ?";
            $requirements = str_replace(' ', '|', $requirements);
            $binds[]      = $requirements;
        }

        if ($phone_no) {
            $sql .= " AND phone_no = ?";
            $binds[] = $phone_no;
        }

        if ($email) {
            $sql .= " AND email = ?";
            $binds[] = $email;
        }

        if ($contact_person) {
            $sql           .= " AND contact_person REGEXP ?";
            $contact_person = str_replace(' ', '|', $contact_person);
            $binds[]        = $contact_person;
        }

        if ($bank_primary) {
            $sql         .= " AND bank_primary REGEXP ?";
            $bank_primary = str_replace(' ', '|', $bank_primary);
            $binds[]      = $bank_primary;
        }

        if ($primary_account_no) {
            $sql              .= " AND primary_account_no REGEXP ?";
            $primary_account_no = str_replace(' ', '|', $primary_account_no);
            $binds[]           = $primary_account_no;
        }

        if ($primary_account_name) {
            $sql               .= " AND primary_account_name REGEXP ?";
            $primary_account_name = str_replace(' ', '|', $primary_account_name);
            $binds[]            = $primary_account_name;
        }
        
        if ($bank_alternate) {
            $sql           .= " AND bank_alternate REGEXP ?";
            $bank_alternate = str_replace(' ', '|', $bank_alternate);
            $binds[]        = $bank_alternate;
        }

        if ($alternate_account_no) {
            $sql                .= " AND alternate_account_no REGEXP ?";
            $alternate_account_no = str_replace(' ', '|', $alternate_account_no);
            $binds[]             = $alternate_account_no;
        }

        if ($alternate_account_name) {
            $sql                 .= " AND alternate_account_name REGEXP ?";
            $alternate_account_name = str_replace(' ', '|', $alternate_account_name);
            $binds[]              = $alternate_account_name;
        }

        $sql .= " ORDER BY supplier.trade_name ASC";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

}