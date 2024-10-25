<?php

declare(strict_types=1);

namespace App\Dao;

use App\Util\CommonUtils;

class SubProductDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'st_subproduct');
    }

    public function getRemoteDatatable($language) {
        // Columnas a tratar en el datatable
        $columns = [
            ['db' => 'id', 'dt' => 'id'],
            ['db' => 'name', 'dt' => 'name'],
            ['db' => 'format', 'dt' => 'format'],
            ['db' => 'reference', 'dt' => 'reference', 'exact' => true],
            ['db' => 'product_id', 'dt' => 'product_id', 'exact' => true],
            ['db' => 'product_name', 'dt' => 'product_name', 'exact' => true],
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
                sp.id,
                JSON_UNQUOTE(JSON_EXTRACT(sp.name, "$.' . $language . '")) AS name,
                JSON_UNQUOTE(JSON_EXTRACT(sp.format, "$.' . $language . '")) AS format,
                sp.reference,
                sp.product_id,
                sp.date_created,
                sp.date_updated,
                p.name as product_name
            FROM
                ' . $this->table . ' sp
                INNER JOIN st_product p ON sp.product_id = p.id            
        ) temp';


        return $this->datatablesSimple($table, 'id', $columns);
    }

    public function getById($id) {
        $record = parent::getById($id);
        return $this->getJsonFieldsValue($record, ['name', 'format']);
    }

    public function save($data) {
        $query = 'INSERT INTO ' . $this->table . ' (name, format, reference, product_id) '
            . 'VALUES (:name, :format, :reference, :product_id)';
        $this->query($query, $data);

        return $this->getLastInsertId();
    }

    public function update($data) {
        $query = 'UPDATE ' . $this->table . ' SET 
            name = :name,
            format = :format,
            reference = :reference,
            product_id = :product_id
            WHERE id = :id';
        $this->query($query, $data);
    }

    public function getSubProducts($language) {
        $sql = 'SELECT s.id, JSON_UNQUOTE(JSON_EXTRACT(s.name, "$.' . $language . '")) AS name, s.product_id
                FROM ' . $this->table . ' s
                ORDER BY s.product_id ASC, s.name ASC';
        return $this->fetchAll($sql);
    }
}
