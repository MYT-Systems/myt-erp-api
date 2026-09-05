<?php

namespace App\Models;

class Bank_fund_transaction extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'bank_id',
        'type',
        'txn_date',
        'amount',
        'remarks',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted',
    ];

    public function __construct()
    {
        $this->table = 'bank_fund_transaction';
    }
}
