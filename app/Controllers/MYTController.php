<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\RESTful\ResourceController;

// Model Declaration
use App\Models\User;
use App\Models\Branch;
use App\Models\Webapp_log;
use App\Models\Webapp_response;

class MYTController extends ResourceController
{
    use ResponseTrait;
    protected $helpers = ['form', 'url', 'text'];

    protected $api_key;

    protected $user_key;

    protected $validation;

    protected $webapp_log_id;

    protected $requested_by;

    protected $user;

    protected $status;

    protected $method;

    protected $errorMessage;

    protected $db;
    
    protected function _validation_check($rule_group, $custom_rules = null)
    {
        $this->validation = \Config\Services::validation();
        $rules = [];
        foreach($rule_group as $rule) {
            $current_rule = $this->validation->getRuleGroup($rule);
            $rules = array_merge($rules, $current_rule);
        }

        if (isset($custom_rules)) {
            $rules = array_merge($rules, $custom_rules);
        }

        if (!$this->validate($rules)) {
            $errors = $this->validator->getErrors();
            return $this->fail($errors, 400);
        }
        return true;
    }

    protected function _verify_client()
    {
        if (defined($this->api_key)) {
            return constant($this->api_key);
        } else {
            return false;
        }
    }

    protected function _api_verification($controller, $method)
    {
        $this->requested_by = $this->request->getVar('requester') ?? 0;
        $userModel = new User();
        $where = [
            'pin' => $this->requested_by,
            'api_key' => $this->user_key ? : "",
            'token' => $this->request->getVar('token') ? : "",
            'is_deleted' => 0
        ];

        $user = $userModel->select('', $where, 1);
        // var_dump($where); die();
        $this->requested_by = $user ? $user['id'] : 0;

        $webappLogModel = new Webapp_log();
        $webappResponseModel = new Webapp_response();
        $values = [
            'controller' => $controller,
            'method' => $method,
            'ip_address' => $this->request->getServer('REMOTE_ADDR'),
            'data_received' => json_encode($this->request->getVar()),
            'requested_by' => $this->requested_by,
            'requested_on' => date('Y-m-d H:i:s'),
        ];

        if (!$insertID = $webappLogModel->insert($values)) {
            $response = $this->failServerError('Server Error.');
            $webappResponseModel->record_response($insertID, $response);
            return $response;
        }

        $this->webapp_log_id = $insertID;
        
        if (!$this->_user_is_authorized() AND $controller != "login") {
            $response = $this->failUnauthorized('API key or token not authorized.');
            $webappResponseModel->record_response($insertID, $response);
            return $response;
        }

        if (!$this->_verify_client()) {
            $response = $this->failUnauthorized('Invalid API key.');
            $webappResponseModel->record_response($insertID, $response);
            return $response;
        }
        return true;
    }

    protected function _user_is_authorized()
    {
        $token = $this->request->getVar('token');
       
        $userModel = new User();
        $where = [
            'id' => $this->requested_by,
            'api_key' => $this->user_key ? : "",
            'token' => $token ? : "",
            'is_deleted' => 0
        ];
        
        // var_dump($where); die();

        if (!$this->user = $userModel->select('', $where, 1))
            return false;

        return true;
    }

    protected function _verify_requester($token)
    {
        $userModel = new User();

        $date_today = new \DateTime();
        $webappResponseModel = new Webapp_response();

        if (empty($token) && empty($this->requested_by)) {
            $response = $this->failUnauthorized('Invalid Auth token');
            return $response;
        } else {
            //Check requester token and token expiry validity
            $where = [
                'id' => $this->requested_by,
                'token' => $token,
                'is_deleted' => 0
            ];

            if (!$requester = $userModel->select('', $where, 1)) {
                $response = $this->failUnauthorized('Token Expired');
                return $response;
            } else {
                $token_expiry = new \DateTime($requester['token_expiry']);

                if (empty($requester)) {
                    $response = $this->failUnauthorized('Invalid requester');
                    return $response;
                } else if ($requester['token'] !== $token) {
                    $response = $this->failUnauthorized('Invalid Auth token');
                    return $response;
                } else if ($date_today > $token_expiry) {
                    $response = $this->failUnauthorized('Token Expired');

                    $values = [
                        'token' => null,
                        'token_expiry' => null,
                        'updated_on' => date('Y-m-d H:i:s'),
                        'updated_by' => $this->requested_by
                    ];
                    $userModel->update($this->requested_by, $values);

                    $webappResponseModel->record_response($this->webapp_log_id, $response);
                    return $response;
                }

                return true;
            }
        }
    }

    protected function _store_request($data, $method, $user_id)
    {
        $requestModel = new Request();

        $values = [
            'data_sent' => json_encode($data),
            'method' => $method,
            'user_id' => $user_id,
            'requested_on' => date('Y-m-d H:i:s')
        ];

        return $requestModel->insert($values);
    }

    /**
     * Used for token randomizer
     */
    protected function _generate_token($length)
    {
        $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $size = strlen($chars);
        $token = '';
        for($i = 0; $i < $length; $i++) {
            $str = $chars[rand(0, $size - 1)];
            $token .= $str;
        }
        return $token;
    }

    /**
     * Used for uploading an attachment
     */
    protected function _attempt_upload_file($model = null, $path = 'uploads/', $extra_data = [])
    {
        $upload_path = FCPATH . $path;
        // create upload directory if not exists
        if (!is_dir($upload_path)) {
            mkdir($upload_path, 0777, true);
        }

        $file = $this->request->getFile('file');

        // file upload error
        if (!$file || $file->getError() == 4) {
            return false;
        }

        $file_name = $file->getName();
        $temp = explode('.', $file_name);
        $new_file_name = $temp[0] . '_' . time() . '.' . $temp[1];

        // move file to upload directory
        if ($file->move($upload_path, $new_file_name)) {
            $name = $this->request->getVar('name');
            $data = [
                'added_by'  => $this->requested_by,
                'added_on'  => date('Y-m-d H:i:s'),
                'name'      => $new_file_name,
                'file_url'  => base_url($path . $new_file_name),
            ];

            $data = array_merge($data, $extra_data);

            if ($model->insert($data)) {
                $response = $this->respond([
                    'status'  => 200,
                    'error'   => false,
                    'message' => 'File uploaded successfully',
                    'data'    => [
                        'file_name' => $new_file_name,
                        'file_url'  => base_url($path . $new_file_name),
                        ]
                ]);
            } else {
                $response = $this->respond([
                    'status' => 500,
                    'error'  => true,
                    'message' => 'Failed to upload file',
                    'data' => []
                ]);
            }
        }

        return $response;
    }

    /**
     * Used for uploading an attachment as base64
     */
    protected function _attempt_upload_file_base64($model = null, $extra_data = [])
    {
        if($files = $this->request->getFileMultiple('file')) {
            foreach($files AS $file) {
                // file upload error
                if (!$file || $file->getError() == 4) {
                    return false;
                }
                
                // convert the uploaded file into base64
                $base64 = base64_encode(file_get_contents($file->getTempName()));
                $base64_file = 'data:' . $file->getMimeType() . ';base64,' . $base64;

                $data = [
                    'name'     => $file->getName(),
                    'base_64'  => $base64_file,
                    'added_by' => $this->requested_by,
                    'added_on' => date('Y-m-d H:i:s'),
                ];

                $data = array_merge($data, $extra_data);

                if ($model->insert($data)) {
                    $response = $this->respond([
                        'status'  => 200,
                        'error'   => false,
                        'message' => 'File uploaded successfully'
                    ]);
                } else {
                    $response = $this->respond([
                        'status' => 500,
                        'error'  => true,
                        'message' => 'Failed to upload file',
                        'data' => []
                    ]);
                }
            }
        } else {
            $file = $this->request->getFile('file');

            // file upload error
            if (!$file || $file->getError() == 4) {
                return false;
            }
            
            // convert the uploaded file into base64
            $base64 = base64_encode(file_get_contents($file->getTempName()));
            $base64_file = 'data:' . $file->getMimeType() . ';base64,' . $base64;

            $data = [
                'name'     => $file->getName(),
                'base_64'  => $base64_file,
                'added_by' => $this->requested_by,
                'added_on' => date('Y-m-d H:i:s'),
            ];

            $data = array_merge($data, $extra_data);

            if ($model->insert($data)) {
                $response = $this->respond([
                    'status'  => 200,
                    'error'   => false,
                    'message' => 'File uploaded successfully'
                ]);
            } else {
                $response = $this->respond([
                    'status' => 500,
                    'error'  => true,
                    'message' => 'Failed to upload file',
                    'data' => []
                ]);
            }
        }
        return $response;
    }

    /**
     * Check if something is existing in a table
     */
    protected function _is_existing($model, $where)
    {
        $webappResponseModel = new Webapp_response();
        $where = array_merge($where, ['is_deleted' => 0]);
        if ($is_existing = $model->select('', $where, 1)) {
            $response = $this->respond([
                'status' => 200,
                'error'  => false,
                'message' => 'Data already exists',
                'existing_data' => $is_existing
            ]);

            $webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        return false;
    }

    protected function _jpeg_to_base64($field_name)
    {
        $file = $this->request->getFile($field_name);

        // file upload error
        if (!$file || $file->getError() == 4) {
            return false;
        }
        
        // convert the uploaded file into base64
        $base64 = base64_encode(file_get_contents($file->getTempName()));
        $base64_file = 'data:' . $file->getMimeType() . ';base64,' . $base64;
        return $base64_file;
    }

    /**
     * Convert base 64 to image
     */
    protected function _base64_to_jpeg($base64_string, $output_file)
    {
        // open the output file for writing
        $ifp = fopen( $output_file, 'wb' ); 
    
        // split the string on commas
        // $data[ 0 ] == "data:image/png;base64"
        // $data[ 1 ] == <actual base64 string>
        $data = explode( ',', $base64_string );
    
        // we could add validation here with ensuring count( $data ) > 1
        fwrite( $ifp, base64_decode( $data[ 1 ] ) );
    
        // clean up the file resource
        fclose( $ifp ); 
    
        return $output_file; 
    }

    /**
     * Save to json file
     */
    protected function _write_json($field_name, $data)
    {
        $bytes = random_bytes(5);
        $unique_id = bin2hex($bytes);

        $order_name = $field_name . "_" . $unique_id . ".json";
        $upload_path = FCPATH . 'public/' . $field_name . '/' . $order_name;

        $data = json_encode($data);
        return file_put_contents($upload_path, $data) ? $order_name : false;
    }

    /**
     * Save JSON file to server
     */
    protected function _upload_json($field_name)
    {
        $upload_path = FCPATH . 'public/' . $field_name . '/';

        if (!file_exists($upload_path)) {
            mkdir($upload_path, 0777, true);
        }

        $json_file = $this->request->getFile($field_name);
        $extension = $json_file->getExtension();
        $filename = $this->_create_json_filename() . '.' . $extension;

        if (!$json_file->hasMoved()) {
            $filepath = '../../public/' . $field_name . '/';
            $json_file->store($filepath, $filename);
        }

        if ($this->_file_is_saved($field_name, $filename))
            return $filename;
        else
            return false;
    }

    /**
     * Save JSON file to server
     */
    protected function _upload_multiple($field_name)
    {
        $upload_path = FCPATH . 'public/' . $field_name . '/';

        if (!file_exists($upload_path))
            mkdir($upload_path, 0777, true);

        $unsaved_files = [];
        $saved_files = [];

        $files = $this->request->getFiles();
        foreach ($files[$field_name] as $file) {
            $filename = $file->getClientName();
    
            if (!$file->hasMoved()) {
                $filepath = '../../public/' . $field_name . '/';
                $file->store($filepath, $filename);
            }

            if (!$this->_file_is_saved($field_name, $filename))
                $unsaved_files[] = $filename;
            else
                $saved_files[] = $filename;
        }

        return [
            'saved_files' => $saved_files,
            'unsaved_files' => $unsaved_files,
            'upload_path' => $upload_path
        ];
    }

    /**
     * Check if file is saved
     */
    protected function _file_is_saved($field_name, $filename)
    {
        $upload_path = FCPATH . 'public/' . $field_name . '/';
        $files = array_diff(scandir($upload_path), array('.', '..'));
        $files = array_values($files);

        foreach ($files as $file) { 
            if ($file == $filename)
                return true;
        }
        return false;
    }

    /**
     * Generate JSON filename
     */
    protected function _create_json_filename()
    {
        $order_name = "file_" . time();
        return $order_name;
    }
}
