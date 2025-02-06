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
            ['db' => 'slug', 'dt' => 'slug']
        ];

        $table = '(
            SELECT
                p.id,
                p.name,
                p.date_created,
                p.date_updated,
                p.slug,
                GROUP_CONCAT(m.name ORDER BY m.name ASC SEPARATOR "#|@") as market_names,
                GROUP_CONCAT(m.color ORDER BY m.name ASC SEPARATOR "|") as market_colors,
                CONCAT("|", GROUP_CONCAT(m.id ORDER BY m.name ASC SEPARATOR "|"), "|") as market_ids,
                (SELECT COUNT(DISTINCT bp.booklet_id) FROM st_booklet_product bp WHERE bp.product_id = p.id) as total_booklets,
                (SELECT COUNT(*) FROM st_subproduct sp WHERE sp.product_id = p.id) as total_references
            FROM ' . $this->table . ' p
            INNER JOIN st_market_product mp ON mp.product_id = p.id
            INNER JOIN st_market m ON m.id = mp.market_id   
            GROUP BY p.id         
        ) temp';

        return $this->datatablesSimple($table, 'id', $columns);
    }

    public function save($data) {
        $query = 'INSERT INTO ' . $this->table . ' (name, slug) '
            . 'VALUES (:name, :slug)';
        $this->query($query, $data);

        return $this->getLastInsertId();
    }

    public function update($data) {
        $query = 'UPDATE ' . $this->table . ' SET 
            name = :name,
            slug = :slug
            WHERE id = :id';
        $this->query($query, $data);
    }

    public function getByMarketId($marketId) {
        $sql = "SELECT p.id, p.name, p.date_updated
                FROM " . $this->table . " p 
                INNER JOIN st_market_product mp ON mp.product_id = p.id AND mp.market_id = :marketId
                ORDER BY 
                    CASE WHEN p.id = (SELECT value FROM st_param WHERE id = 'EMPTY_PRODUCT') THEN 0 ELSE 1 END, 
                    p.name ASC";
        return $this->fetchAll($sql, compact('marketId'));
    }

    public function getProducts() {
        $sql = "SELECT p.id, p.name
                FROM " . $this->table . " p
                INNER JOIN st_subproduct s ON s.product_id = p.id
                GROUP BY p.id
                ORDER BY p.name ASC";
        return $this->fetchAll($sql);
    }
}
