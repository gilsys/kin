<?php

declare(strict_types=1);

namespace App\Dao;

use App\Constant\International;
use App\Constant\SubProductStatus;
use App\Util\CommonUtils;

class SubProductDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'st_subproduct');
    }

    public function getFullById($id, $language = null, $international = null) {
        if (empty($language)) {
            return $this->getById($id);
        }
        $sql = 'SELECT
                JSON_UNQUOTE(JSON_EXTRACT(sp.name, "$.' . $language . '")) AS name,
                ' . (!is_null($international) && $international == International::NoCode ? 'null' : 'JSON_UNQUOTE(JSON_EXTRACT(sp.reference, "$.' . (!is_null($international) ? ($international == International::InternationalCode ? 'en' : 'es') : $language) . '"))') . ' AS reference
            FROM
                ' . $this->table . ' sp            
            WHERE id = :id';
        return $this->fetchRecord($sql, compact('id'));
    }

    public function getRemoteDatatable($language) {
        // Columnas a tratar en el datatable
        $columns = [
            ['db' => 'id', 'dt' => 'id'],
            ['db' => 'name', 'dt' => 'name'],
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
            ],
            ['db' => 'subproduct_status', 'dt' => 'subproduct_status', 'exact' => true]
        ];

        $showDeleted = !empty($_POST['columns'][8]['search']['value']) && $_POST['columns'][8]['search']['value'] == SubProductStatus::Deleted;
        $subProductStatusSql = ' AND sp.date_deleted ' . ($showDeleted ? 'IS NOT NULL' : 'IS NULL');

        $table = '(
            SELECT
                sp.id,
                JSON_UNQUOTE(JSON_EXTRACT(sp.name, "$.' . $language . '")) AS name,
                JSON_UNQUOTE(JSON_EXTRACT(sp.reference, "$.' . $language . '")) AS reference,
                sp.product_id,
                sp.date_created,
                sp.date_updated,
                p.name as product_name,
                IF(sp.date_deleted IS NULL, "' . SubProductStatus::Enabled . '", "' . SubProductStatus::Deleted . '") AS subproduct_status
            FROM
                ' . $this->table . ' sp
                INNER JOIN st_product p ON sp.product_id = p.id       
            WHERE p.date_deleted IS NULL' . $subProductStatusSql . '     
        ) temp';


        return $this->datatablesSimple($table, 'id', $columns);
    }

    public function getById($id) {
        $record = parent::getById($id);
        return $this->getJsonFieldsValue($record, ['name', 'reference']);
    }

    public function save($data) {
        $query = 'INSERT INTO ' . $this->table . ' (name, reference, product_id) '
            . 'VALUES (:name, :reference, :product_id)';
        $this->query($query, $data);

        return $this->getLastInsertId();
    }

    public function update($data) {
        $query = 'UPDATE ' . $this->table . ' SET 
            name = :name,            
            reference = :reference,
            product_id = :product_id
            WHERE id = :id';
        $this->query($query, $data);
    }

    public function getSubProducts($language, $selectedIds = [], $marketId = null, $customCreatorUserId = null, $international = null) {
        $data = [];

        $whereSql = "";
        if (!empty($marketId)) {
            $whereSql .= "(p.id IN (SELECT mp.product_id FROM st_market_product mp WHERE mp.market_id = :marketId))";
            $data['marketId'] = $marketId;
        }

        if (!empty($customCreatorUserId)) {
            if (!empty($whereSql)) {
                $whereSql .= " OR ";
            }

            $whereSql .= "(p.parent_product_id IS NOT NULL AND p.creator_user_id = :customCreatorUserId)";
            $data['customCreatorUserId'] = $customCreatorUserId;
        }

        if (empty($whereSql)) {
            $whereSql = "1 = 1";
        }

        $whereSqlSelected = '';
        if (!empty($selectedIds)) {
            $whereSqlSelected .= ' OR FIND_IN_SET(s.id, :selectedIds)';
            $data['selectedIds'] = implode(',', $selectedIds);
        }

        $sql = 'SELECT s.id, JSON_UNQUOTE(JSON_EXTRACT(s.name, "$.' . $language . '")) AS name, p.id AS product_id,
                ' . (!is_null($international) && $international == International::NoCode ? 'null' : 'JSON_UNQUOTE(JSON_EXTRACT(s.reference, "$.' . (!is_null($international) ? ($international == International::InternationalCode ? 'en' : 'es') : $language) . '"))') . ' AS reference
                FROM ' . $this->table . ' s
                INNER JOIN st_product p ON (p.parent_product_id IS NULL AND p.id = s.product_id) OR (p.parent_product_id IS NOT NULL AND p.parent_product_id = s.product_id)
                WHERE ((p.date_deleted IS NULL AND s.date_deleted IS NULL) AND (' . $whereSql . '))' . $whereSqlSelected . '
                ORDER BY s.product_id ASC, s.name ASC';
        return $this->fetchAll($sql, $data);
    }
}
