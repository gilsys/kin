<?php

declare(strict_types=1);

namespace App\Dao;

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
}
