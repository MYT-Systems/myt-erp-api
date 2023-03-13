<?php

namespace App\Models;

class Store_deposit_attachment extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'store_deposit_id',
        'base64',
        'added_on',
        'added_by',
        'updated_on',
        'updated_by',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'store_deposit_attachment';
    }

}