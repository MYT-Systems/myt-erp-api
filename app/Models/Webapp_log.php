<?php

namespace App\Models;

class Webapp_log extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'controller',
        'method',
        'ip_address',
        'data_received',
        'requested_by',
        'requested_on',
    ];
    
    public function __construct()
    {
        $this->table = 'webapp_log';
    }
}

?>