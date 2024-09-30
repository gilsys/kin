<?php

declare(strict_types=1);

namespace App\Dao;

class AuAdavCardsDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'au_adav_cards');
    }

    public function getByUri($uri) {
        $sql = 'SELECT CAST(carddata AS CHAR(10000) CHARACTER SET utf8) 
        FROM ' . $this->table . ' WHERE uri like :uri';
        return $this->fetchOneField($sql, ['uri' => '%' . $uri . '%']);
    }
}
