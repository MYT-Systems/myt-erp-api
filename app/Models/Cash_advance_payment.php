<?php

namespace App\Models;

class Cash_advance_payment extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        "cash_advance_id",
        "employee_id",
        "paid_amount",
        "paid_on",
        "added_on",
        "added_by",
        "updated_on",
        "updated_by",
        "is_deleted",
    ];

    public function __construct()
    {
        $this->table = 'cash_advance_payment';
    }

}