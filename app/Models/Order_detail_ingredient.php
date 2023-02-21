<?php

namespace App\Models;

class Order_detail_ingredient extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'order_detail_id',
        'product_id',
        'item_id',
        'qty',
        'unit',
        'added_by',
        'added_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'order_detail_ingredient';
    }

}