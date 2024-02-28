<?php

namespace App\Models;

class Project_type_name extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'name'
    ];

    public function __construct()
    {
        $this->table = 'project_type_name';
    }
}