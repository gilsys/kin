<?php

declare(strict_types=1);

namespace App\Dao;

use App\Util\CommonUtils;

class BookletDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'st_booklet');
    }

    public function getFullById($id) {
        $sql = 'SELECT b.*, 
                m.name as market_name, 
                CONCAT(
                    JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(u.personal_information, "' . AES_KEY . '"), "$.name")), 
                    " ", 
                    JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(u.personal_information, "' . AES_KEY . '"), "$.surnames"))
                ) as creator_name
                FROM `' . $this->getTable() . '` b
                INNER JOIN
                    st_market m ON b.market_id = m.id
                INNER JOIN
                    st_user u ON b.creator_user_id = u.id                
                WHERE b.id = :id';

        return $this->fetchRecord($sql, compact('id'));
    }

    public function getRemoteDatatable() {
        // Columnas a tratar en el datatable
        $columns = [
            ['db' => 'id', 'dt' => 'id'],
            ['db' => 'name', 'dt' => 'name'],
            ['db' => 'creator_name', 'dt' => 'creator_name'],
            ['db' => 'creator_user_id', 'dt' => 'creator_user_id', 'exact' => true],
            ['db' => 'market_color', 'dt' => 'market_color', 'exact' => true],
            ['db' => 'market_name', 'dt' => 'market_name'],
            ['db' => 'market_id', 'dt' => 'market_id', 'exact' => true],
            ['db' => 'qr_language_id', 'dt' => 'qr_language_id', 'exact' => true],
            ['db' => 'qr_language', 'dt' => 'qr_language'],
            ['db' => 'qr_language_color', 'dt' => 'qr_language_color', 'exact' => true],
            ['db' => 'main_language_id', 'dt' => 'main_language_id', 'exact' => true],
            ['db' => 'main_language', 'dt' => 'main_language'],
            ['db' => 'main_language_color', 'dt' => 'main_language_color', 'exact' => true],
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
                b.id,
                b.name,
                b.date_created,
                b.date_updated,
                l1.name as main_language,
                l1.color as main_language_color,
                l2.name as qr_language,
                l2.color as qr_language_color,
                b.main_language_id,
                b.qr_language_id,
                b.creator_user_id,
                m.name as market_name,
                m.color as market_color,
                m.id as market_id,
                CONCAT(
                    JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(u.personal_information, "' . AES_KEY . '"), "$.name")), 
                    " ", 
                    JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(u.personal_information, "' . AES_KEY . '"), "$.surnames"))
                ) as creator_name
            FROM
                ' . $this->table . ' b
            INNER JOIN
                st_market m ON b.market_id = m.id
            INNER JOIN
                st_user u ON b.creator_user_id = u.id
            INNER JOIN
                st_language l1 ON b.main_language_id = l1.id  
            INNER JOIN
                st_language l2 ON b.qr_language_id = l2.id    
        ) temp';


        return $this->datatablesSimple($table, 'id', $columns);
    }

    public function save($data) {
        $query = 'INSERT INTO ' . $this->table . ' (name, qr_language_id, main_language_id, page2_booklet_layout_id, page3_booklet_layout_id, page4_booklet_layout_id, market_id, creator_user_id) '
            . 'VALUES (:name, :qr_language_id, :main_language_id, :page2_booklet_layout_id, :page3_booklet_layout_id, :page4_booklet_layout_id, :market_id, :creator_user_id)';
        $this->query($query, $data);

        return $this->getLastInsertId();
    }

    public function update($data) {
        $query = 'UPDATE ' . $this->table . ' SET 
            name = :name,
            qr_language_id = :qr_language_id,
            main_language_id = :main_language_id,
            market_id = :market_id,
            page2_booklet_layout_id = :page2_booklet_layout_id,
            page3_booklet_layout_id = :page3_booklet_layout_id,
            page4_booklet_layout_id = :page4_booklet_layout_id
            WHERE id = :id';
        $this->query($query, $data);
    }

    public function getBookletImages($bookletId, $lang) {
        $query = "SELECT
                    bp.page, 
                    bp.custom_order, 
                    p.id as product_id, 
                    f.id as image_id,
                    display_mode,
                    f.file,
                    p.slug
                FROM 
                    st_booklet_product bp
                INNER JOIN 
                    st_booklet b ON b.id = bp.booklet_id
                INNER JOIN 
                    st_product p ON p.id = bp.product_id
                LEFT JOIN 
                    st_file f ON f.id = 
                            CASE 
                                WHEN bp.display_mode = 2 THEN p.image_" . $lang . "_2
                                WHEN bp.display_mode = 3 THEN p.image_" . $lang . "_3
                                WHEN bp.display_mode = 6 THEN p.image_" . $lang . "_6
                            END                    
                WHERE 
                    bp.booklet_id = :bookletId
                ORDER BY 
                    bp.page ASC, 
                    bp.custom_order ASC";

        return $this->fetchAll($query, compact('bookletId'));
    }
}
