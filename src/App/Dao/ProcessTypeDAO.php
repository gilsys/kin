<?php

declare(strict_types=1);

namespace App\Dao;

use App\Util\CommonUtils;

class ProcessTypeDAO extends BaseDAO
{

    public function __construct($connection)
    {
        parent::__construct($connection, 'st_process_type');
    }

    public function getRemoteDatatable()
    {

        // Columnas a tratar en el datatable
        $columns = [
            ['db' => 'id', 'dt' => 'id', 'exact' => true],
            ['db' => 'name', 'dt' => 'name'],
            ['db' => 'custom_order', 'dt' => 'custom_order'],
            ['db' => 'color', 'dt' => 'color'],
            // Los campos de fecha deben ser formateados
            [
                'db' => 'date_created', 'dt' => 'date_created', 'date' => true,
                'formatter' => function ($d, $row) {
                    return CommonUtils::convertDate($d);
                }
            ],
            [
                'db' => 'date_updated', 'dt' => 'date_updated', 'date' => true,
                'formatter' => function ($d, $row) {
                    return CommonUtils::convertDate($d);
                }
            ]
        ];

        $table = '(
        SELECT
            pt.id,
            pt.name,
            pt.custom_order,
            pt.color,
            pt.date_created,
            pt.date_updated
        FROM
            ' . $this->table . ' pt 
        ) temp';

        return $this->datatablesSimple($table, 'id', $columns);
    }

    public function save($data)
    {
        $query = 'INSERT INTO ' . $this->table . ' (name, json_data, color, custom_order) '
            . 'VALUES (:name, :json_data, :color, :custom_order)';
        $this->query($query, $data);

        return $this->getLastInsertId();
    }

    public function update($data)
    {
        $query = 'UPDATE ' . $this->table . ' SET '
            . ' name = :name,'
            . ' json_data = :json_data,'
            . ' color = :color '
            . ' WHERE id = :id';
        $this->query($query, $data);
    }

    public function count() {
        $sql = "SELECT COUNT(*) FROM " . $this->getTable();
        $result = $this->fetchRecord($sql);
        if (empty($result)) {
            return $result;
        }
        return reset($result);
    }
}
