<?php

namespace App\Models;

class SE_check_slip_attachment extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'se_check_slip_id',
        'file_name',
        'file_path',
        'file_url',
        'mime',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'se_check_slip_attachment';
    }

    /**
     * Get se_check_slip_attachment details by receive ID
     */
    public function get_details_by_se_check_slip_id($se_check_slip_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT se_check_slip_attachment.*
FROM se_check_slip_attachment
WHERE se_check_slip_attachment.is_deleted = 0
EOT;
        $binds = [];
        if (isset($se_check_slip_id)) {
            $sql .= " AND se_check_slip_id = ?";
            $binds[] = $se_check_slip_id;
        }
        
        $sql .= " GROUP BY se_check_slip_attachment.id";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get se_check_slip_attachment details by ID
     */
    public function get_details_by_id($se_check_slip_attachment_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT se_check_slip_attachment.*
FROM se_check_slip_attachment
WHERE se_check_slip_attachment.is_deleted = 0
EOT;
        $binds = [];
        if (isset($se_check_slip_attachment_id)) {
            $sql .= " AND se_check_slip_attachment.id = ?";
            $binds[] = $se_check_slip_attachment_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all se_check_slip_attachments
     */
    public function get_all_se_check_slip_attachment()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT se_check_slip_attachment.*
FROM se_check_slip_attachment
WHERE se_check_slip_attachment.is_deleted = 0
ORDER BY se_check_slip_attachment.name ASC
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get se_check_slip_attachments based on se_check_slip_attachment name, contact_person, phone_no, tin_no, bir_no, email
     */
    public function search($name = null, $template_name = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT se_check_slip_attachment.*
FROM se_check_slip_attachment
WHERE se_check_slip_attachment.is_deleted = 0
EOT;
        $binds = [];

        if ($name) {
            $sql .= " AND se_check_slip_attachment.file_name REGEXP ?";
            $name    = str_replace(' ', '|', $name);
            $binds[] = $name;
        }

        $sql .= " ORDER BY se_check_slip_attachment.file_name ASC";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

}