<?php

declare(strict_types=1);

namespace App\Dao;

class ParamDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'st_param');
    }
    
    public function getById($id) {
        $value = parent::getById($id);
        return $value['value'];
    }

    public function getConstantsLike($id) {
        $sql = "SELECT * FROM " . $this->table . " where id like :id ORDER BY id ASC";
        $results = $this->fetchAll($sql, ['id' => $id . '%']);
        
        $r = [];
        foreach ($results as $result) {
            $r[$result['id']] = $result['value'];
        }
        return $r;
    }

    public function update($id, $value) {
        $query = 'UPDATE ' . $this->table . ' SET value = :value WHERE id = :id';
        $this->query($query, ['value' => $value, 'id' => $id]);
    }

}


