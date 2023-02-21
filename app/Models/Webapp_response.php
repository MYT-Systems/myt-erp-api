<?php

namespace App\Models;

class Webapp_response extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'webapp_log_id',
        'response',
        'responded_on'
    ];
    
    public function __construct()
    {
        $this->table = 'webapp_response';
    }

    public function record_response($webapp_log_id, $response, $is_api = false)
    {
        if ($is_api) {
            $values = [
                'webapp_log_id' => $webapp_log_id,
                'response' => json_encode($response),
                'responded_on' => date("Y-m-d H:i:s")
            ];
        } else {
            $converted_response = (array)$response;
            $prefix = chr(0).'*'.chr(0);
    
            $values = [
                'webapp_log_id' => $webapp_log_id,
                'response' => $converted_response[$prefix . 'body'],
                'responded_on' => date("Y-m-d H:i:s")
            ];
        }

        if (!$this->insert($values))
            return false;
        else
            return true;
    }
}

?>