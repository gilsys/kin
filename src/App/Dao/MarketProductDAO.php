<?php

declare(strict_types=1);

namespace App\Dao;

class MarketProductDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'st_market_product');
    }

    public function save($data) {
        $query = 'INSERT INTO ' . $this->table . ' (market_id, product_id) '
            . 'VALUES (:market_id, :product_id)';
        $this->query($query, $data);
    }

    public function clear($productId) {
        $query = 'DELETE FROM ' . $this->table . ' WHERE product_id = :productId';
        $this->query($query, compact('productId'));
    }

    public function getMarketsByProductId($productId) {
        $sql = "SELECT market_id FROM " . $this->table . " WHERE product_id = :productId";
        return array_column($this->fetchAll($sql, compact('productId')), 'market_id');
    }

}
