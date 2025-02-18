<?php

namespace App\Controllers;

class Payrolls extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Search payrolls
     */
    public function search()
    {
        if (($response = $this->_api_verification('payrolls', 'search')) !== true)
            return $response;

        $employee_id = $this->request->getVar('employee_id') ? : null;
        $date_from = $this->request->getVar('date_from') ? : null;
        $date_to = $this->request->getVar('date_to') ? : null;

        if (!$payrolls = $this->payrollModel->search($employee_id, $date_from, $date_to)) {
            $response = $this->failNotFound('No payrolls found');
        } else {
            $response = $this->respond([
                'data'     => $payrolls,
                'status'   => 'success',
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function compute_payroll()
    {
        if (($response = $this->_api_verification('payrolls', 'compute_payroll')) !== true)
            return $response;

        $employee_id = $this->request->getVar('employee_id');
        $date_from = $this->request->getVar('date_from');
        $date_to = $this->request->getVar('date_to');

        if (!$employee = $this->employeeModel->get_details_by_id($employee_id)) {
            $response = $this->fail('Employee not found.');

            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $employee = $employee ? $employee[0] : null;

        if (!$timesheet = $this->attendanceEntryModel->get_work_minutes_by_employee($employee_id, $date_from, $date_to)) {
            $response = $this->fail('No timesheet found.');

            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        if (!$wastage_items = $this->wastageItemModel->get_cost_per_employee($employee_id, $date_from, $date_to))
            $wastage_items = ['total_cost' => 0.00];

        if (!$ds_deductions = $this->dsDeductionModel->get_cost_per_employee($employee_id, $date_from, $date_to))
            $ds_deductions = ['total_cost' => 0.00];

        $total_working_hours = round($timesheet['total_worked_minutes'] / 60, 3);
        $total_deductions = $employee['philhealth'] + $employee['sss'] + $employee['hdmf'] + $wastage_items['total_cost'];
        $total_additions =  $employee['daily_allowance'] + $employee['communication_allowance'] + $employee['transportation_allowance'] + $employee['food_allowance'] + $employee['hmo_allowance'] + $employee['tech_allowance'] + $employee['ops_allowance'] + $employee['special_allowance'];

        $data = [
            'employee_name' => $employee['name'],
            'date_from' => $date_from,
            'date_to' => $date_to,
            'rate' => $employee['salary'],
            'total_working_hours' => $total_working_hours,
            'basic_pay' => round($employee['salary'] * $total_working_hours, 2),
            'deductions' => [
                'philhealth' => $employee['philhealth'],
                'sss' => $employee['sss'],
                'hdmf' => $employee['hdmf'],
                'wastage' => $wastage_items['total_cost'],
                'shortage' => $ds_deductions['total_cost']
            ],
            'additions' => [
                'daily_allowance' => $employee['daily_allowance'],
                'communication_allowance' => $employee['communication_allowance'],
                'transportation_allowance' => $employee['transportation_allowance'],
                'food_allowance' => $employee['food_allowance'],
                'hmo_allowance' => $employee['hmo_allowance'],
                'tech_allowance' => $employee['tech_allowance'],
                'ops_allowance' => $employee['ops_allowance'],
                'special_allowance' => $employee['special_allowance']
            ],
            'summary' => [
                'basic_pay' => round($employee['salary'] * $total_working_hours, 2),
                'total_deduction' => round($total_deductions, 2),
                'total_additions' => round($total_additions, 2)
            ]
        ];

        $response = $this->respond($data);
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function create()
    {
        if (($response = $this->_api_verification('payrolls', 'add')) !== true)
            return $response;

        $cash_advance = $this->request->getVar('cash_advance') ? : 0.00;

        $data = [
            'employee_id' => $this->request->getVar('employee_id'),
            'date_from' => $this->request->getVar('date_from'),
            'date_to' => $this->request->getVar('date_to'),
            'release_date' => $this->request->getVar('release_date'),
            'rate' => $this->request->getVar('rate') ? : 0.00,
            'total_working_hours' => $this->request->getVar('total_working_hours'),
            'basic_pay' => $this->request->getVar('basic_pay') ? : 0.00,
            'philhealth' => $this->request->getVar('philhealth') ? : 0.00,
            'sss' => $this->request->getVar('sss') ? : 0.00,
            'hdmf' => $this->request->getVar('hdmf') ? : 0.00,
            'cash_advance' => $this->request->getVar('cash_advance') ? : 0.00,
            'wastage' => $this->request->getVar('wastage') ? : 0.00,
            'shortage' => $this->request->getVar('shortage') ? : 0.00,
            'daily_allowance' => $this->request->getVar('daily_allowance') ? : 0.00,
            'communication_allowance' => $this->request->getVar('communication_allowance') ? : 0.00,
            'transportation_allowance' => $this->request->getVar('transportation_allowance') ? : 0.00,
            'food_allowance' => $this->request->getVar('food_allowance') ? : 0.00,
            'hmo_allowance' => $this->request->getVar('hmo_allowance') ? : 0.00,
            'tech_allowance' => $this->request->getVar('tech_allowance') ? : 0.00,
            'ops_allowance' => $this->request->getVar('ops_allowance') ? : 0.00,
            'special_allowance' => $this->request->getVar('special_allowance') ? : 0.00,
            'total_deduction' => $this->request->getVar('total_deductions') ? : 0.00,
            'total_additions' => $this->request->getVar('total_additions') ? : 0.00,
            'grand_total' => $this->request->getVar('grand_total') ? : 0.00,
            'remarks' => $this->request->getVar('remarks') ? : null,
            'added_by' => $this->requested_by,
            'added_on' => date('Y-m-d H:i:s')
        ];
        
        $this->db = db_connect();
        $this->db->transBegin();

        if (!$this->payrollModel->insert($data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to generate payroll. Please try again.');
        } elseif ($cash_advance > 0 AND !$this->_save_cash_advance_payment($payroll_id)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to save cash advance.');
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Payroll generated.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    protected function _save_cash_advance_payment($payroll_id)
    {
        $employee_id = $this->request->getVar('employee_id');
        if (!$cash_advance = $this->cashAdvanceModel->search(null, 'approved', null, null, true, $employee_id)) {
            return false;
        }
        $cash_advance = $cash_advance[0];

        $values = [
            'payroll_id' => $payroll_id,
            'employee_id' => $employee_id,
            'cash_advance_id' => $cash_advance['id'],
            'paid_amount' => $this->request->getVar('cash_advance') ? : 0.00,
            'paid_on' => $this->request->getVar('release_date'),
            'added_by' => $this->requested_by,
            'added_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->cashAdvancePaymentModel->insert($values))
            return false;
        return true;
    }

    public function delete()
    {
        if (($response = $this->_api_verification('payrolls', 'delete')) !== true)
        return $response;

        $payroll_id = $this->request->getVar('payroll_id') ? : 0.00;
        $condition = ['id' => $payroll_id, 'is_deleted' => 0];
        $data = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        $this->db = db_connect();
        $this->db->transBegin();

        if (!$payroll_details = $this->payrollModel->select('', $condition, 1)) {
            $response = $this->failNotFound('Payroll does not exist.');
        } elseif (!$this->payrollModel->update($condition, $data)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to generate payroll. Please try again.');
        } elseif (!$this->_delete_cash_advance_payment($payroll_id)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to save cash advance.');
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Payroll generated.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    protected function _delete_cash_advance_payment($payroll_id)
    {
        $condition = ['payroll_id' => $payroll_id, 'is_deleted' => 0];
        if (!$this->cashAdvancePaymentModel->select('', $condition)) return true;

        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->cashAdvancePaymentModel->custom_update($condition, $values))
            return false;
        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->attendanceEntryModel = model('App\Models\Attendance_entry');
        $this->dsDeductionModel = model('Daily_sale_employee_deduction');
        $this->payrollModel = model('App\Models\Payroll');
        $this->employeeModel = model('App\Models\Employee');
        $this->cashAdvanceModel = model('App\Models\Cash_advance');
        $this->cashAdvancePaymentModel = model('App\Models\Cash_advance_payment');
        $this->wastageItemModel = model('App\Models\Wastage_item');
        $this->webappResponseModel = model('App\Models\Webapp_response');
    }
}
