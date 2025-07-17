<?php

declare(strict_types=1);

namespace App\Dao;

use App\Constant\ProductStatus;
use App\Util\CommonUtils;

class ProductDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'st_product');
    }

    public function getRemoteDatatable($customProduct = false, $creatorUserId = null, $marketId = null) {
        // Columnas a tratar en el datatable
        $columns = [
            ['db' => 'id', 'dt' => 'id'],
            ['db' => 'name', 'dt' => 'name'],
            ['db' => 'total_booklets', 'dt' => 'total_booklets', 'exact' => true],
            ['db' => 'total_references', 'dt' => 'total_references'],
            ['db' => 'market_names', 'dt' => 'market_names', 'formatter' => function ($d, $row) {
                return !empty($d) ? explode('#|@', $d) : [];
            }],
            ['db' => 'market_colors', 'dt' => 'market_colors', 'formatter' => function ($d, $row) {
                return !empty($d) ? explode('|', $d) : [];
            }],
            ['db' => 'market_ids', 'dt' => 'market_ids'],
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
            ['db' => 'slug', 'dt' => 'slug'],
            ['db' => 'product_status', 'dt' => 'product_status', 'exact' => true],
            ['db' => 'parent_product_id', 'dt' => 'parent_product_id', 'exact' => true],
            ['db' => 'subtitle_es', 'dt' => 'subtitle_es'],
            ['db' => 'subtitle_en', 'dt' => 'subtitle_en'],
            ['db' => 'subtitle_fr', 'dt' => 'subtitle_fr'],
            ['db' => 'periodicity_es', 'dt' => 'periodicity_es'],
            ['db' => 'periodicity_en', 'dt' => 'periodicity_en'],
            ['db' => 'periodicity_fr', 'dt' => 'periodicity_fr']
        ];

        $whereSql = ' AND p.parent_product_id ' . ($customProduct ? 'IS NOT NULL' : 'IS NULL');
        if (!empty($creatorUserId)) {
            $whereSql .= ' AND p.creator_user_id = ' . intval($creatorUserId);
        }

        if (!empty($marketId)) {
            $whereSql .= ' AND p.id != (SELECT value FROM st_param WHERE id = "EMPTY_PRODUCT") AND mp.market_id = ' . intval($marketId);
        }

        $showDeleted = !empty($_POST['columns'][8]['search']['value']) && $_POST['columns'][8]['search']['value'] == ProductStatus::Deleted;
        $productStatusSql = ' AND p.date_deleted ' . ($showDeleted ? 'IS NOT NULL' : 'IS NULL');

        $table = '(
            SELECT
                p.id,
                p.name,
                p.date_created,
                p.date_updated,
                p.slug,
                p.subtitle_es,
                p.subtitle_en,
                p.subtitle_fr,
                p.periodicity_es,
                p.periodicity_en,
                p.periodicity_fr,
                GROUP_CONCAT(m.name ORDER BY m.name ASC SEPARATOR "#|@") as market_names,
                GROUP_CONCAT(m.color ORDER BY m.name ASC SEPARATOR "|") as market_colors,
                CONCAT("|", GROUP_CONCAT(m.id ORDER BY m.name ASC SEPARATOR "|"), "|") as market_ids,
                (SELECT COUNT(DISTINCT bp.booklet_id) FROM st_booklet_product bp WHERE bp.product_id = p.id) as total_booklets,
                (SELECT COUNT(*) FROM st_subproduct sp WHERE sp.product_id = p.id AND sp.date_deleted IS NULL) as total_references,
                IF(p.date_deleted IS NULL, "' . ProductStatus::Enabled . '", "' . ProductStatus::Deleted . '") AS product_status,
                p.parent_product_id
            FROM ' . $this->table . ' p
            LEFT JOIN st_market_product mp ON mp.product_id = p.id
            LEFT JOIN st_market m ON m.id = mp.market_id
            WHERE 1 = 1' . $whereSql . $productStatusSql . '
            GROUP BY p.id         
        ) temp';

        return $this->datatablesSimple($table, 'id', $columns);
    }

    public function save($data) {
        $query = 'INSERT INTO ' . $this->table . ' (name, slug, subtitle_es, subtitle_en, subtitle_fr, periodicity_es, periodicity_en, periodicity_fr, creator_user_id) '
            . 'VALUES (:name, :slug, :subtitle_es, :subtitle_en, :subtitle_fr, :periodicity_es, :periodicity_en, :periodicity_fr, :creator_user_id)';
        $this->query($query, $data);

        return $this->getLastInsertId();
    }

    public function update($data) {
        $query = 'UPDATE ' . $this->table . ' SET 
        name = :name,
        slug = :slug,
        subtitle_es = :subtitle_es,
        subtitle_en = :subtitle_en,
        subtitle_fr = :subtitle_fr,
        periodicity_es = :periodicity_es,
        periodicity_en = :periodicity_en,
        periodicity_fr = :periodicity_fr
        WHERE id = :id';
        $this->query($query, $data);
    }

    public function saveCustom($data) {
        $query = 'INSERT INTO ' . $this->table . ' (
                    subtitle_es, periodicity_es, image_es_2, image_es_3, image_es_6, logo_es, photo_es, zip_es,
                    subtitle_en, periodicity_en, image_en_2, image_en_3, image_en_6, logo_en, photo_en, zip_en,
                    subtitle_fr, periodicity_fr, image_fr_2, image_fr_3, image_fr_6, logo_fr, photo_fr, zip_fr,
                    parent_product_id, name, slug, subtitle_custom, periodicity_custom, creator_user_id
                ) SELECT 
                    subtitle_es, periodicity_es, image_es_2, image_es_3, image_es_6, logo_es, photo_es, zip_es,
                    subtitle_en, periodicity_en, image_en_2, image_en_3, image_en_6, logo_en, photo_en, zip_en,
                    subtitle_fr, periodicity_fr, image_fr_2, image_fr_3, image_fr_6, logo_fr, photo_fr, zip_fr,
                    :parent_product_id, :name, :slug, :subtitle_custom, :periodicity_custom, :creator_user_id
                  FROM ' . $this->table . ' 
                  WHERE id = :parent_product_id';
        $this->query($query, $data);
        return $this->getLastInsertId();
    }

    public function updateCustom($data) {
        $query = 'UPDATE ' . $this->table . ' SET 
        name = :name,
        slug = :slug,
        subtitle_custom = :subtitle_custom,
        periodicity_custom = :periodicity_custom
        WHERE id = :id';
        $this->query($query, $data);
    }

    public function updateCustomByParentProductId($parentProductId) {
        $query = 'UPDATE ' . $this->table . ' AS t
        JOIN ' . $this->table . ' AS s ON s.id = :parentProductId
        SET
            t.subtitle_es = s.subtitle_es, t.periodicity_es = s.periodicity_es, t.image_es_2 = s.image_es_2, t.image_es_3 = s.image_es_3, t.image_es_6 = s.image_es_6, t.logo_es = s.logo_es, t.photo_es = s.photo_es, t.zip_es = s.zip_es,
            t.subtitle_en = s.subtitle_en, t.periodicity_en = s.periodicity_en, t.image_en_2 = s.image_en_2, t.image_en_3 = s.image_en_3, t.image_en_6 = s.image_en_6, t.logo_en = s.logo_en, t.photo_en = s.photo_en, t.zip_en = s.zip_en,
            t.subtitle_fr = s.subtitle_fr, t.periodicity_fr = s.periodicity_fr, t.image_fr_2 = s.image_fr_2, t.image_fr_3 = s.image_fr_3, t.image_fr_6 = s.image_fr_6, t.logo_fr = s.logo_fr, t.photo_fr = s.photo_fr, t.zip_fr = s.zip_fr,
            t.date_updated = NOW()
        WHERE t.parent_product_id = :parentProductId';
        $this->query($query, compact('parentProductId'));
    }

    public function getFullById($id, $language) {
        $sql = 'SELECT
                subtitle_' . $language . ' as subtitle,
                periodicity_' . $language . ' as periodicity,
                slug,
                logo_' . $language . ' as logo,
                photo_' . $language . ' as photo,
                fl.file as logo_file,
                fp.file as photo_file
            FROM
                ' . $this->table . ' p     
            LEFT JOIN st_file fl on fl.id = p.logo_' . $language . '            
            LEFT JOIN st_file fp on fp.id = p.photo_' . $language . '            
            WHERE p.id = :id';

        return $this->fetchRecord($sql, compact('id'));
    }

    public function getByMarketId($marketId, $bookletId = null, $customCreatorUserId = null) {
        $data = compact('marketId');

        $whereSqlSelected = "";
        if (!empty($bookletId)) {
            $whereSqlSelected .= " OR p.id IN (SELECT bp.product_id FROM st_booklet_product bp WHERE bp.booklet_id = :bookletId)";
            $data['bookletId'] = $bookletId;
        }

        $whereSqlCustom = "";
        if (!empty($customCreatorUserId)) {
            $whereSqlCustom .= " OR (p.parent_product_id IS NOT NULL AND p.creator_user_id = :customCreatorUserId)";
            $data['customCreatorUserId'] = $customCreatorUserId;
        }

        $sql = "SELECT p.id, p.name, p.date_updated, if(p.parent_product_id IS NOT NULL, 1, 0) AS is_custom
                FROM " . $this->table . " p 
                LEFT JOIN st_market_product mp ON mp.product_id = p.id
                WHERE (p.date_deleted IS NULL AND (mp.market_id = :marketId" . $whereSqlCustom . "))" . $whereSqlSelected . "
                ORDER BY 
                    CASE WHEN p.id = (SELECT value FROM st_param WHERE id = 'EMPTY_PRODUCT') THEN 0 ELSE 1 END, 
                    p.name ASC";
        return $this->fetchAll($sql, $data);
    }

    public function getProducts($selectedIds = [], $full = false, $marketId = null, $customCreatorUserId = null, $subproductSelectedIds = []) {
        $data = [];

        $fields = $full ? "p.id, p.name, IF(p.parent_product_id IS NOT NULL, p.subtitle_custom, p.subtitle_$full) as subtitle, IF(p.parent_product_id IS NOT NULL, p.periodicity_custom, p.periodicity_$full) as periodicity, IF(p.parent_product_id IS NOT NULL, 1, 0) AS is_custom, p.date_updated" : 'p.id, p.name';

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


        $whereSqlSelected = "";
        if (!empty($selectedIds)) {
            $whereSqlSelected .= " OR FIND_IN_SET(p.id, :selectedIds)";
            $data['selectedIds'] = implode(',', $selectedIds);
        }

        $whereSqlSubproductSelected = '';
        if (!empty($subproductSelectedIds)) {
            $whereSqlSubproductSelected .= ' OR FIND_IN_SET(s.id, :subproductSelectedIds)';
            $data['subproductSelectedIds'] = implode(',', $subproductSelectedIds);
        }

        $sql = "SELECT " . $fields . "
                FROM " . $this->table . " p
                INNER JOIN st_subproduct s ON (p.parent_product_id IS NULL AND p.id = s.product_id) OR (p.parent_product_id IS NOT NULL AND p.parent_product_id = s.product_id) AND (s.date_deleted IS NULL" . $whereSqlSubproductSelected . ")
                WHERE (p.date_deleted IS NULL AND (" . $whereSql . "))" . $whereSqlSelected . "
                GROUP BY p.id
                ORDER BY p.name ASC";
        return $this->fetchAll($sql, $data);
    }

    public function getForSelect($id = 'id', $name = 'name', $orderBy = 'id', $exclude = null, $deleted = false) {
        $excludeSql = ' AND parent_product_id IS NULL ';
        $params = [];
        if (!empty($exclude)) {
            $excludeSql .= ' AND NOT FIND_IN_SET(' . $id . ', :excludeIds)';
            $params = ['excludeIds' => implode(',', $exclude)];
        }
        if (!$deleted) {
            $excludeSql .= ' AND date_deleted IS NULL';
        }
        $sql = "SELECT " . $id . ", " . $name . " FROM " . $this->table . " WHERE 1 = 1" . $excludeSql . " ORDER BY " . $orderBy . " ASC";
        return $this->fetchAll($sql, $params);
    }
}
