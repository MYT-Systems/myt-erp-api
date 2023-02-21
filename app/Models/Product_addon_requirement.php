<?php

namespace App\Models;

class Product_addon_requirement extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'addon_id',
        'product_item_id',
        'item_id',
        'qty',
        'unit',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'product_addon_requirement';
    }

}