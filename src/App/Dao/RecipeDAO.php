<?php

declare(strict_types=1);

namespace App\Dao;

use App\Constant\UserProfile;
use App\Util\CommonUtils;

class RecipeDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'st_recipe');
    }

    public function getFullById($id) {
        $sql = 'SELECT r.*,
                CONCAT(
                    JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(u.personal_information, "' . AES_KEY . '"), "$.name")), 
                    " ", 
                    JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(u.personal_information, "' . AES_KEY . '"), "$.surnames"))
                ) as creator_name
                FROM `' . $this->getTable() . '` r
                INNER JOIN
                    st_user u ON r.creator_user_id = u.id                
                WHERE r.id = :id';
        $record = $this->fetchRecord($sql, compact('id'));
        $record['json_data'] = !empty($record['json_data']) ? json_decode($record['json_data'], true) : [];
        return $record;
    }

    public function save($data) {
        $query = 'INSERT INTO ' . $this->table . ' (name, qr_language_id, main_language_id, recipe_layout_id, creator_user_id, json_data) '
            . 'VALUES (:name, :qr_language_id, :main_language_id, :recipe_layout_id, :creator_user_id, :json_data)';
        $this->query($query, $data);

        return $this->getLastInsertId();
    }

    public function update($data) {
        $query = 'UPDATE ' . $this->table . ' SET 
            name = :name,
            qr_language_id = :qr_language_id,
            main_language_id = :main_language_id,
            recipe_layout_id = :recipe_layout_id,
            json_data = :json_data
            WHERE id = :id';
        $this->query($query, $data);
    }


    public function getRemoteDatatable($userId = null) {
        // Columnas a tratar en el datatable
        $columns = [
            ['db' => 'id', 'dt' => 'id'],
            ['db' => 'name', 'dt' => 'name'],
            ['db' => 'creator_name', 'dt' => 'creator_name'],
            ['db' => 'creator_user_id', 'dt' => 'creator_user_id', 'exact' => true],
            ['db' => 'qr_language_id', 'dt' => 'qr_language_id', 'exact' => true],
            ['db' => 'qr_language', 'dt' => 'qr_language'],
            ['db' => 'qr_language_color', 'dt' => 'qr_language_color', 'exact' => true],
            ['db' => 'main_language_id', 'dt' => 'main_language_id', 'exact' => true],
            ['db' => 'main_language', 'dt' => 'main_language'],
            ['db' => 'main_language_color', 'dt' => 'main_language_color', 'exact' => true],
            ['db' => 'editable', 'dt' => 'editable', 'exact' => true],
            ['db' => 'last_file_id', 'dt' => 'last_file_id', 'exact' => true],
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

        $whereSql = '';
        if (!empty($userId)) {
            $whereSql .= ' AND (r.creator_user_id = ' . intval($userId) . ' OR u.user_profile_id = "' . UserProfile::Administrator . '")';
        }

        $table = '(
            SELECT
                r.id,
                r.name,
                r.date_created,
                r.date_updated,
                l1.name as main_language,
                l1.color as main_language_color,
                l2.name as qr_language,
                l2.color as qr_language_color,
                r.main_language_id,
                r.qr_language_id,
                r.creator_user_id,
                CONCAT(
                    JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(u.personal_information, "' . AES_KEY . '"), "$.name")), 
                    " ", 
                    JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(u.personal_information, "' . AES_KEY . '"), "$.surnames"))
                ) as creator_name,
                ' . (!empty($userId) ? 'IF(r.creator_user_id = ' . $userId . ', 1, 0)' : 1) . ' AS editable,
                (SELECT rf.file_id FROM st_recipe_file rf WHERE rf.recipe_id = r.id ORDER BY rf.file_id DESC LIMIT 1) AS last_file_id
            FROM
                ' . $this->table . ' r
            INNER JOIN
                st_user u ON r.creator_user_id = u.id
            INNER JOIN
                st_language l1 ON r.main_language_id = l1.id  
            INNER JOIN
                st_language l2 ON r.qr_language_id = l2.id  
            WHERE 1 = 1' . $whereSql . '  
        ) temp';


        return $this->datatablesSimple($table, 'id', $columns);
    }

    public function duplicate($id, $creatorUserId, $copyNameText) {
        $originalName = $this->getSingleField($id, 'name');
        $name = $originalName . ' (' . $copyNameText . ')';

        $i = 1;
        while (!empty($this->getByNameCreatorUserId($name, $creatorUserId))) {
            $i++;
            $name = $originalName . ' (' . $copyNameText . ' #' . $i . ')';
        }

        $query = 'INSERT INTO ' . $this->table . ' (name, main_language_id, qr_language_id, creator_user_id, recipe_layout_id, json_data) 
                SELECT :name, main_language_id, qr_language_id, :creatorUserId, recipe_layout_id, json_data FROM ' . $this->table . ' WHERE id = :id';
        $this->query($query, compact('id', 'creatorUserId', 'name'));
        $newId = $this->getLastInsertId();
        return $newId;
    }

    public function getByNameCreatorUserId($name, $creatorUserId) {
        $sql = "SELECT * FROM `" . $this->getTable() . "` WHERE name = :name AND creator_user_id = :creatorUserId";
        return $this->fetchRecord($sql, compact('name', 'creatorUserId'));
    }
}
