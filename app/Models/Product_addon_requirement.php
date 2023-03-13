<?php

namespace App\Models;

class Product_addon_requirement extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'addon_id',
        'product_item_id',
        'item_id',
        'qty',
        'unit',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'product_addon_requirement';
    }

    public function get_optional_items($addon_id)
    {
        $database = \Config\Database::connect();

        $sql = <<<EOT
SELECT product_addon_requirement.*,
    ingredient_item.name AS ingredient_to_replace,
    item.name AS new_ingredient
FROM product_addon_requirement
LEFT JOIN item AS ingredient_item ON ingredient_item.id = product_addon_requirement.product_item_id
LEFT JOIN item ON item.id = product_addon_requirement.item_id
WHERE product_addon_requirement.is_deleted = 0
    AND product_addon_requirement.addon_id = ?
EOT;

        $binds = [$addon_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

}