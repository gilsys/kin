<?php

declare(strict_types=1);

namespace App\Dao;

use App\Constant\Color;
use App\Constant\UserProfile;
use App\Util\CommonUtils;

class RecipeDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'st_recipe');
    }

    public function getFullById($id) {
        $sql = 'SELECT r.*,
                m.name as market_name, 
                CONCAT(
                    JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(u.personal_information, "' . AES_KEY . '"), "$.name")), 
                    " ", 
                    JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(u.personal_information, "' . AES_KEY . '"), "$.surnames"))
                ) as creator_name
                FROM `' . $this->getTable() . '` r
                INNER JOIN
                    st_user u ON r.creator_user_id = u.id
                INNER JOIN
                    st_market m ON r.market_id = m.id            
                WHERE r.id = :id';
        $record = $this->fetchRecord($sql, compact('id'));
        $record['json_data'] = !empty($record['json_data']) ? json_decode($record['json_data'], true) : [];
        $this->processRecipeColor($record['json_data']);
        return $record;
    }

    private function processRecipeColor(&$array) {
        foreach ($array as &$value) {
            if (!empty($value['formdata']) && !empty($value['formdata']['title_bg_color']) && !array_key_exists('color_id', $value['formdata'])) {
                $value['formdata']['color_id'] = Color::Custom;
            }

            if (is_array($value)) {
                $this->processRecipeColor($value);
            }
        }
    }

    public function save($data) {
        $query = 'INSERT INTO ' . $this->table . ' (name, qr_language_id, main_language_id, market_id, creator_user_id, json_data, international) '
            . 'VALUES (:name, :qr_language_id, :main_language_id, :market_id, :creator_user_id, :json_data, :international)';
        $this->query($query, $data);

        return $this->getLastInsertId();
    }

    public function update($data) {
        $query = 'UPDATE ' . $this->table . ' SET 
            name = :name,
            qr_language_id = :qr_language_id,
            main_language_id = :main_language_id,
            market_id = :market_id,
            json_data = :json_data,
            international = :international
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
            ['db' => 'market_color', 'dt' => 'market_color', 'exact' => true],
            ['db' => 'market_name', 'dt' => 'market_name'],
            ['db' => 'market_id', 'dt' => 'market_id', 'exact' => true],
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
            ['db' => 'user_profile_color', 'dt' => 'user_profile_color', 'exact' => true],

        ];

        $whereSql = '';
        if (!empty($userId)) {
            $whereSql .= ' AND (r.creator_user_id = ' . intval($userId) . ' OR (u.user_profile_id = "' . UserProfile::Administrator . '" AND r.market_id = (SELECT u2.market_id FROM st_user u2 WHERE u2.id = ' . intval($userId) . ')))';
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
                (SELECT rf.file_id FROM st_recipe_file rf WHERE rf.recipe_id = r.id ORDER BY rf.file_id DESC LIMIT 1) AS last_file_id,
                m.name as market_name,
                m.color as market_color,
                m.id as market_id,
                p.color as user_profile_color
            FROM
                ' . $this->table . ' r
            INNER JOIN
                st_user u ON r.creator_user_id = u.id
            INNER JOIN
                st_language l1 ON r.main_language_id = l1.id  
            INNER JOIN
                st_language l2 ON r.qr_language_id = l2.id  
            INNER JOIN
                st_market m ON r.market_id = m.id
            INNER JOIN 
                st_user_profile p ON u.user_profile_id = p.id
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

        $query = 'INSERT INTO ' . $this->table . ' (name, main_language_id, qr_language_id, market_id, creator_user_id, json_data, international) 
                SELECT :name, main_language_id, qr_language_id, market_id, :creatorUserId, json_data, international FROM ' . $this->table . ' WHERE id = :id';
        $this->query($query, compact('id', 'creatorUserId', 'name'));
        $newId = $this->getLastInsertId();
        return $newId;
    }

    public function getByNameCreatorUserId($name, $creatorUserId) {
        $sql = "SELECT * FROM `" . $this->getTable() . "` WHERE name = :name AND creator_user_id = :creatorUserId";
        return $this->fetchRecord($sql, compact('name', 'creatorUserId'));
    }
}
