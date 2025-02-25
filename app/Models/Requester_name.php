<?php

namespace App\Models;

class Requester_name extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'name'
    ];

    public function __construct()
    {
        $this->table = 'requester_name';
    }
}