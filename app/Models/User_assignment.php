<?php

namespace App\Models;

class User_assignment extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'employee_id',
        'user_id',
        'assigned_on',
        'ended_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'user_assignment';
    }
    
}