<?php

declare(strict_types=1);

namespace App\Dao;

use App\Util\CommonUtils;

class MarketDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'st_market');
    }

    public function getRemoteDatatable() {
        // Columnas a tratar en el datatable
        $columns = [
            ['db' => 'id', 'dt' => 'id'],
            ['db' => 'name', 'dt' => 'name'],
            ['db' => 'color', 'dt' => 'color'],
            ['db' => 'main_language', 'dt' => 'main_language'],
            ['db' => 'main_language_color', 'dt' => 'main_language_color'],
            ['db' => 'main_language_id', 'dt' => 'main_language_id', 'exact' => true],
            ['db' => 'qr_language', 'dt' => 'qr_language'],
            ['db' => 'qr_language_color', 'dt' => 'qr_language_color'],
            ['db' => 'qr_language_id', 'dt' => 'qr_language_id', 'exact' => true],
            ['db' => 'total_users', 'dt' => 'total_users', 'exact' => true],
            ['db' => 'area_names', 'dt' => 'area_names'],
            ['db' => 'area_ids', 'dt' => 'area_ids'],

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
            m.id,
            m.name,
            m.color,
            m.main_language_id,            
            m.qr_language_id,
            l1.name as main_language,
            l1.color as main_language_color,
            l2.name as qr_language,
            l2.color as qr_language_color,
            (SELECT COUNT(*) FROM st_user u WHERE u.market_id = m.id) as total_users,
            GROUP_CONCAT(DISTINCT concat(a.name, "|", a.color) ORDER BY a.custom_order ASC SEPARATOR ", ") as area_names,
            CONCAT("|", GROUP_CONCAT(DISTINCT a.id SEPARATOR "|") , "|") as area_ids,
            m.date_created,
            m.date_updated

        FROM
            ' . $this->table . ' m
            INNER JOIN `st_language` l1 ON l1.id = m.main_language_id
            INNER JOIN `st_language` l2 ON l2.id = m.qr_language_id
            LEFT JOIN st_market_area ma ON ma.market_id = m.id
            LEFT JOIN st_area a ON a.id = ma.area_id
            GROUP BY m.id

        ) temp';

        return $this->datatablesSimple($table, 'id', $columns);
    }

    public function save($data) {
        $query = 'INSERT INTO ' . $this->table . ' (name, color, main_language_id, qr_language_id) '
            . 'VALUES (:name, :color, :main_language_id, :qr_language_id)';
        $this->query($query, $data);

        return $this->getLastInsertId();
    }

    public function update($data) {
        $query = 'UPDATE ' . $this->table . ' SET 
            name = :name,
            color = :color,
            main_language_id = :main_language_id,
            qr_language_id = :qr_language_id
            WHERE id = :id';
        $this->query($query, $data);
    }
}
