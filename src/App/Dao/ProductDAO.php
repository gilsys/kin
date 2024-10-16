<?php

declare(strict_types=1);

namespace App\Dao;

use App\Util\CommonUtils;

class ProductDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'st_product');
    }

    public function getRemoteDatatable() {
        // Columnas a tratar en el datatable
        $columns = [
            ['db' => 'id', 'dt' => 'id'],
            ['db' => 'name', 'dt' => 'name'],
            ['db' => 'area_name', 'dt' => 'area_name'],
            ['db' => 'area_color', 'dt' => 'area_color', 'exact' => true],
            ['db' => 'total_booklets', 'dt' => 'total_booklets', 'exact' => true],
            ['db' => 'area_id', 'dt' => 'area_id', 'exact' => true],
            [
                'db' => 'date_created',
                'dt' => 'date_created',
                'date' => true,
                'formatter' => function ($d, $row) {
                    return CommonUtils::convertDate($d);
                }
            ],
            [
                'db' => 'date_updated',
                'dt' => 'date_updated',
                'date' => true,
                'formatter' => function ($d, $row) {
                    return CommonUtils::convertDate($d);
                }
            ]

        ];

        $table = '(
            SELECT
                p.id,
                p.name,
                p.date_created,
                p.date_updated,
                a.name as area_name,
                a.color as area_color,
                a.id as area_id,
                (SELECT COUNT(*) FROM st_booklet_product bp WHERE bp.product_id = p.id) as total_booklets
            FROM
                ' . $this->table . ' p
                INNER JOIN st_area a ON p.area_id = a.id            
        ) temp';


        return $this->datatablesSimple($table, 'id', $columns);
    }

    public function save($data) {
        $query = 'INSERT INTO ' . $this->table . ' (name, area_id) '
            . 'VALUES (:name, :area_id)';
        $this->query($query, $data);

        return $this->getLastInsertId();
    }

    public function update($data) {
        $query = 'UPDATE ' . $this->table . ' SET 
            name = :name,
            area_id = :area_id
            WHERE id = :id';
        $this->query($query, $data);
    }

    public function getByMarketId($marketId) {
        $sql = "SELECT p.id, p.name, p.date_updated
                FROM " . $this->table . " p 
                INNER JOIN st_market_area ma ON ma.area_id = p.area_id AND ma.market_id = :marketId
                ORDER BY p.name ASC";
        $result = [];
        foreach ($this->fetchAll($sql, compact('marketId')) as $row) {
            $result[$row['id']] = $row;
        }
        return $result;
    }
}
