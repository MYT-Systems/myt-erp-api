<?php

namespace App\Models;

class Customer extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'name',
        'company',
        'address',
        'contact_number',
        'email',
        'contact_person',
        'tin_no',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted',
    ];

    public function __construct()
    {
        $this->table = 'customer';
    }

    /**
     * Get customer by ID
     */
    public function get_customer_by_id($customer_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM customer
WHERE customer.is_deleted = 0
 AND customer.id = ?
EOT;
        $binds = [$customer_id];
       
        $query = $database->query($sql, $binds);
        return $query ? (float)$query->getResultArray()[0]['percentage'] : false;
    }

    /**
     * Get customer details by ID
     */
    public function get_details_by_id($customer_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM customer
WHERE customer.is_deleted = 0
    AND customer.id = ?
ORDER BY customer.name ASC
EOT;
        $binds = [$customer_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all customers
     */
    public function get_all_customer()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT customer.*
FROM customer
WHERE customer.is_deleted = 0
ORDER BY customer.name ASC
EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

/**
     * Get Customer
     */
    public function search($name = null, $company = null, $address = null, $contact_number = null, $email = null, $contact_person = null, $tin_no = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM customer
WHERE customer.is_deleted = 0
EOT;
        $binds = [];

        if ($name) {
            $sql    .= " AND customer.name REGEXP ?";
            $name    = str_replace(' ', '|', $name);
            $binds[] = $name;
        }

        if ($company) {
            $sql    .= " AND customer.name REGEXP ?";
            $company    = str_replace(' ', '|', $company);
            $binds[] = $company;
        }

        if ($address) {
            $sql    .= " AND customer.address REGEXP ?";
            $address = str_replace(' ', '|', $address);
            $binds[] = $address;
        }

        if ($contact_number) {
            $sql .= " AND customer.contact_number = ?";
            $binds[] = $contact_number;
        }

        if ($email) {
            $sql .= " AND customer.email = ?";
            $binds[] = $email;
        }
        if ($contact_person) {
            $sql .= " AND customer.contact_person = ?";
            $binds[] = $contact_person;
        }
          if ($tin_no) {
            $sql .= " AND customer.tin_no = ?";
            $binds[] = $tin_no;
        }

        $sql    .= " ORDER BY customer.name ASC";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}
