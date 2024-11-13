<?php

declare(strict_types=1);

namespace App\Dao;

class RecipeFileDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'st_recipe_file');
    }

    public function save($data) {
        $query = 'INSERT INTO ' . $this->table . ' (recipe_id, file_id) '
            . 'VALUES (:recipe_id, :file_id)';
        $this->query($query, $data);
    }

    public function clear($recipeId) {
        $query = 'DELETE FROM ' . $this->table . ' WHERE recipe_id = :recipeId';
        $this->query($query, compact('recipeId'));
    }

    public function deleteByFileId($fileId) {
        $query = 'DELETE FROM ' . $this->table . ' WHERE file_id = :fileId';
        $this->query($query, compact('fileId'));
    }

    public function getFilesByRecipeId($recipeId) {
        $sql = "SELECT f.id, f.date_created AS date
                FROM " . $this->table . " bf
                INNER JOIN st_file f ON f.id = bf.file_id
                WHERE bf.recipe_id = :recipeId
                ORDER BY f.date_created DESC";
        return $this->fetchAll($sql, compact('recipeId'));
    }

    public function getByFileId($fileId) {
        $sql = "SELECT * FROM " . $this->table . " WHERE file_id = :fileId";
        return $this->fetchRecord($sql, compact('fileId'));
    }
}
