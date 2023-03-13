<?php

namespace App\Models;

class Initial_inventory extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'branch_id',
        'date',
        'user_id',
        'inventory_id',
        'item_id',
        'qty',
        'delivered_qty',
        'total_qty',
        'unit',
        'added_on',
        'added_by',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'initial_inventory';
    }
    
}