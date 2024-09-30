<?php

declare(strict_types=1);

namespace App\Dao;

class StaticListDAO extends BaseDAO {

    public function __construct($connection, $table) {
        parent::__construct($connection, $table);
    }

    public function getRemoteDatatable($lang) {
        if (!ctype_alpha($lang)) {
            throw \Exception();
        }

        // Columnas a tratar en el datatable
        $columns = [
            ['db' => 'id', 'dt' => 'id'],
            ['db' => 'name', 'dt' => 'name'],
            ['db' => 'name_translated', 'dt' => 'name_translated'],
            ['db' => 'color', 'dt' => 'color'],
            ['db' => 'custom_order', 'dt' => 'custom_order']
        ];
        
        $translationDAO = new TranslationDAO($this->connection);

        $table = '(
            SELECT 
              sl.id,
              sl.name,
              t.`' . $lang . '` as name_translated,
              sl.color,
              sl.custom_order
            FROM ' . $this->table . ' sl
            LEFT JOIN (' . $translationDAO->getAllTranslationsSql() . ') t ON t.id = sl.name
         ) temp';
        return $this->datatablesSimple($table, 'id', $columns, 'custom_order');
    }
    
    public function save($data) {
        $query = 'INSERT INTO ' . $this->table . ' (name, color, custom_order) '
                . 'VALUES (:name, :color, :custom_order)';

        $this->query($query, $data);

        return $this->getLastInsertId();
    }

    public function update($data) {
        $extraSql = '';
        if (!empty($data['name'])) {
            $extraSql = ' name = :name, ';
        }
        $query = 'UPDATE ' . $this->table . ' SET ' . $extraSql . ' color = :color WHERE id = :id';
        $this->query($query, $data);
    }
    
    public function count() {
        $sql = "SELECT COUNT(*) FROM " . $this->getTable();
        $result = $this->fetchRecord($sql);
        if (empty($result)) {
            return $result;
        }
        return reset($result);
    }

}


