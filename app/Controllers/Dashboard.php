<?php

namespace App\Controllers;

class Dashboard extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get dashboard details for mobile app
     */
    public function mobile()
    {
        if (($response = $this->_api_verification('dashboard', 'mobile')) !== true)
            return $response;

        if ($branches = $this->branchGroupModel->get_branches_per_supervisor($this->requested_by)) {
            $branches = array_map(function ($datum) {
                return $datum['branch_id'];
            }, $branches);
        }
        $branches = $branches ? : null;
        
        $adjustments = $this->adjustmentModel->search(null, $branches, null, null, null, 'pending', null, null, null, null, null, 'store');
        $requests = $this->requestModel->get_by_status('for_approval', $branches);
        $expenses = $this->expenseModel->get_by_status('pending', $branches);
        $transfers = $this->transferModel->get_by_status('on_hold', $branches);
        $branches = $this->branchModel->get_details_by_id($branches);
        $branches_open = 0;
        $branches_close = 0;
        foreach ($branches as $branch) {
            if ($branch['is_open'])
                $branches_open += 1;
            else
                $branches_close += 1;
        }

        $data = [
            'status' => 'success',
            'requests' => $requests ? count($requests) : 0,
            'transfers' => $transfers ? count($transfers) : 0,
            'adjustments' => $adjustments ? count($adjustments) : 0,
            'branches_open' => $branches_open,
            'branches_close' => $branches_close,
            'expenses' => $expenses ? count($expenses) : 0
        ];

        $response = $this->respond($data);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->adjustmentModel = model('App\Models\Adjustment');
        $this->requestModel = model('App\Models\Request');
        $this->expenseModel = model('App\Models\Expense');
        $this->branchModel = model('App\Models\Branch');
        $this->transferModel = model('App\Models\Transfer');
        $this->webappResponseModel = model('App\Models\Webapp_response');
    }
}
