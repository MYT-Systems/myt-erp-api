<?php

namespace App\Models;
use CodeIgniter\Model;

class MYTModel extends Model
{
    public $table = NULL;

	/**
	 * Parameter database will be passed if there
	 * is no need to create a database connection
	 */
    public function select($columns = NULL, $conditions = NULL, $limit = NULL, $order = NULL, $offset = NULL, $db = NULL)
	{
		$database = $db ? $db : \Config\Database::connect();
        $builder = $database->table($this->table);

		if (!empty($columns)) {
			$builder->select($columns);
		}

		if (!empty($order)) {
			$builder->orderBy($order);
		}

        if (!empty($conditions)) {
			$query = $builder->getWhere($conditions);
		} else {
            if (isset($limit) and isset($offset))
                $query = $builder->get($limit, $offset);
            else
                $query = $builder->get();
        }

		if (!$query or empty($query->getResultArray())) {
			return FALSE;
		} else {
			if ($limit !== 1) {
				return $query->getResultArray();
			} else {
                $result = $query->getResultArray();
				return $result[0];
			}
		}
	}

	public function custom_update($conditions, $values, $db = null)
	{
		if (!isset($db))
        	$database = \Config\Database::connect();
		else
			$database = $db;
		$builder = $database->table($this->table);

		if (!$builder->update($values, $conditions))
			$return_value = false;
		else
			$return_value = true;

		return $return_value;
	}
}