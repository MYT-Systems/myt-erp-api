<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Branch_operation_log;

class Cron extends MYTController
{

    public function __construct()
    {
        $this->userModel = new User();
        $this->operationLogModel = new Branch_operation_log();
    }

    /**
     * Logout all users
     */
    public function logout_all_users()
    {
        $not_logged_out_users = $this->operationLogModel->get_user_not_logged_out();
        $not_logged_out_users = array_map(function($value) {
            return $value['user_id'];
        }, $not_logged_out_users);

        $this->userModel->log_out_all_users($not_logged_out_users);
        $this->operationLogModel->log_out_all_users();
    }
}
