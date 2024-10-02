<?php

declare(strict_types=1);

namespace App\Dao;

class MarketAreaDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'st_market_area');
    }

    public function save($data) {
        $query = 'INSERT INTO ' . $this->table . ' (market_id, area_id) '
            . 'VALUES (:market_id, :area_id)';
        $this->query($query, $data);
    }

    public function clear($marketId) {
        $query = 'DELETE FROM ' . $this->table . ' WHERE market_id = :marketId';
        $this->query($query, compact('marketId'));
    }

    public function getAreasByMarketId($marketId) {
        $sql = "SELECT area_id FROM st_market_area WHERE market_id = :marketId";
        return array_column($this->fetchAll($sql, compact('marketId')), 'area_id');
    }

}
