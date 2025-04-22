<?php

namespace App\Controllers;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Webapp_response;

class Employees extends MYTController
{

    public function __construct()
    {
        // Headers for API
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];
       
        $this->_load_essentials();
    }

    /**
     * Get employees
     */
    public function get_employee()
    {
        if (($response = $this->_api_verification('employees', 'get_employee')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $employee_id = $this->request->getVar('employee_id') ? : null;
        $employee    = $employee_id ? $this->employeeModel->get_details_by_id($employee_id) : null;

        if (!$employee) {
            $response = $this->failNotFound('No employee found');
        } else {
            $response = $this->respond([
                'data'   => $employee,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get employees
     */
    public function get_all_employee()
    {
        if (($response = $this->_api_verification('employees', 'get_all_employee')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $employees = $this->employeeModel->get_all_employees();

        if (!$employees) {
            $response = $this->failNotFound('No employee found');
        } else {
            $response = $this->respond([
                'data'   => $employees,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create employee
     */
    public function create()
    {
        if (($response = $this->_api_verification('employees', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $username = $this->request->getVar('username');
        if ($response = $this->_is_existing($this->employeeModel, ['username' => $username]))
            return $response;
  
  
        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$employee_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create employee.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'response'    => 'Employee created successfully.',
                'status'      => 'success',
                'employee_id' => $employee_id
            ]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update employee
     */


    public function update($id = null)
    {
        if (($response = $this->_api_verification('employees', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = [
            'id' => $this->request->getVar('employee_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$employee = $this->employeeModel->select('', $where, 1)) {
            $response = $this->failNotFound('Employee not found');
        } elseif (!$this->_attempt_update($employee['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update employee.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Employee updated successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Change password
     */
    public function change_password($id = null)
    {
        if (($response = $this->_api_verification('employees', 'change_password')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        if (($response = $this->_validation_check(['password_update'])) !== true) {
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $employee_id = $this->request->getVar('employee_id') ? : null;
        $employee_id = isset($employee_id) ? $employee_id : $this->requested_by;

        $where = ['id' => $employee_id, 'is_deleted' => 0];
        $values = [
            'password'       => password_hash($this->request->getVar('password'), PASSWORD_BCRYPT),
            'password_reset' => date('Y-m-d H:i:s'),
            'updated_by'     => $this->requested_by,
            'updated_on'     => date('Y-m-d H:i:s')
        ];

        if (!$employee = $this->employeeModel->select('', $where, 1))
            $response = $this->failNotFound('Employee not found');
        elseif (!$this->employeeModel->update($employee_id, $values))
            $response = $this->fail('Server error');
        else
            $response = $this->respond(['response' => 'Password changed successfully']);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete employees
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('employees', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = [
            'id' => $this->request->getVar('employee_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$employee = $this->employeeModel->select('', $where, 1)) {
            $response = $this->failNotFound('Employee not found');
        } elseif (!$this->_attempt_delete($employee['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete employee.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Employee deleted successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search employees based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('employees', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $username = $this->request->getVar('username') ? : null;
        $name     = $this->request->getVar('name') ? : null;
        $email    = $this->request->getVar('email') ? : null;
        $status   = $this->request->getVar('status') ? : null;
        $type     = $this->request->getVar('type') ? : null;


        if (!$employees = $this->employeeModel->search($username, $name, $email, $status, $type)) {
            $response = $this->failNotFound('No employee found');
        } else {
            $response            = [];
            $response['data']    = $employees;
            $response['status']  = 'success';
            $response            = $this->respond($response);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt to create employee
    */
    protected function _attempt_create()
    {
        $base64 = $this->_jpeg_to_base64("profile_picture");
        $base64 = $base64 ? : null;
        
        $values = [
            "username" => $this->request->getVar("username"),
            "password" => password_hash($this->request->getVar('password'), PASSWORD_BCRYPT),
            "last_name" => $this->request->getVar("last_name"),
            "first_name" => $this->request->getVar("first_name"),
            "middle_name" => $this->request->getVar("middle_name") ? : null,
            "suffix" => $this->request->getVar("suffix") ? : null,
            'type' => $this->request->getVar("type"),
            "contact_no" => $this->request->getVar("contact_no"),
            "address" => $this->request->getVar("address"),
            "email" => $this->request->getVar("email"),
            "gender" => $this->request->getVar("gender"),
            "birthdate" => $this->request->getVar("birthdate"),
            "civil_status" => $this->request->getVar("civil_status"),
            "nationality" => $this->request->getVar("nationality"),
            "religion" => $this->request->getVar("religion"),
            "remarks" => $this->request->getVar("remarks") ? : null,
            "profile_picture" => $base64,
            "sss" => $this->request->getVar("sss") ? : 0.00,
            "hdmf" => $this->request->getVar("hdmf") ? : 0.00,
            "philhealth" => $this->request->getVar("philhealth") ? : 0.00,
            "employment_status" => $this->request->getVar("employment_status"),
            "salary_type" => $this->request->getVar("salary_type"),
            "salary" => $this->request->getVar("salary"),
            "daily_allowance" => $this->request->getVar("daily_allowance") ? : 0.00,
            "communication_allowance" => $this->request->getVar("communication_allowance") ? : 0.00,
            "transportation_allowance" => $this->request->getVar("transportation_allowance") ? : 0.00,
            "food_allowance" => $this->request->getVar("food_allowance") ? : 0.00,
            "hmo_allowance" => $this->request->getVar("hmo_allowance") ? : 0.00,
            "tech_allowance" => $this->request->getVar("tech_allowance") ? : 0.00,
            "ops_allowance" => $this->request->getVar("ops_allowance") ? : 0.00,
            "special_allowance" => $this->request->getVar("special_allowance") ? : 0.00,
            "status" => "active",
            'added_by' => $this->requested_by,
            'added_on' => date('Y-m-d H:i:s')
        ];

        if (!$employee_id = $this->employeeModel->insert($values))
            return false;

        return $employee_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($employee_id)
    {
        $base64 = $this->_jpeg_to_base64("profile_picture");
        $base64 = $base64 ? : null;

        $values = [
            "username" => $this->request->getVar("username"),
            "password" => password_hash($this->request->getVar('password'), PASSWORD_BCRYPT),
            "last_name" => $this->request->getVar("last_name"),
            "first_name" => $this->request->getVar("first_name"),
            "middle_name" => $this->request->getVar("middle_name") ? : null,
            "suffix" => $this->request->getVar("suffix") ? : null,
            'type' => $this->request->getVar("type"),
            "contact_no" => $this->request->getVar("contact_no"),
            "address" => $this->request->getVar("address"),
            "email" => $this->request->getVar("email"),
            "gender" => $this->request->getVar("gender"),
            "birthdate" => $this->request->getVar("birthdate"),
            "civil_status" => $this->request->getVar("civil_status"),
            "nationality" => $this->request->getVar("nationality"),
            "religion" => $this->request->getVar("religion"),
            "remarks" => $this->request->getVar("remarks") ? : null,
            "profile_picture" => $base64,
            "sss" => $this->request->getVar("sss") ? : 0.00,
            "hdmf" => $this->request->getVar("hdmf") ? : 0.00,
            "philhealth" => $this->request->getVar("philhealth") ? : 0.00,
            "employment_status" => $this->request->getVar("employment_status"),
            "salary_type" => $this->request->getVar("salary_type"),
            "salary" => $this->request->getVar("salary"),
            "daily_allowance" => $this->request->getVar("daily_allowance") ? : 0.00,
            "communication_allowance" => $this->request->getVar("communication_allowance") ? : 0.00,
            "transportation_allowance" => $this->request->getVar("transportation_allowance") ? : 0.00,
            "food_allowance" => $this->request->getVar("food_allowance") ? : 0.00,
            "hmo_allowance" => $this->request->getVar("hmo_allowance") ? : 0.00,
            "tech_allowance" => $this->request->getVar("tech_allowance") ? : 0.00,
            "ops_allowance" => $this->request->getVar("ops_allowance") ? : 0.00,
            "special_allowance" => $this->request->getVar("special_allowance") ? : 0.00,
            "status" => $this->request->getVar("status"),
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->employeeModel->update($employee_id, $values))
            return false;

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($employee_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->employeeModel->update($employee_id, $values))
            return false;

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->branchModel = new Branch();
        $this->employeeModel = new Employee();
        $this->webappResponseModel = new Webapp_response();
    }
}
