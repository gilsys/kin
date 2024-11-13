<?php

declare(strict_types=1);

namespace App\Dao;

class BookletProductDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'st_booklet_product');
    }

    public function save($data) {
        $query = 'INSERT INTO ' . $this->table . ' (booklet_id, product_id, page, custom_order, display_mode) '
            . 'VALUES (:booklet_id, :product_id, :page, :custom_order, :display_mode)';
        $this->query($query, $data);
    }

    public function clear($bookletId) {
        $query = 'DELETE FROM ' . $this->table . ' WHERE booklet_id = :bookletId';
        $this->query($query, compact('bookletId'));
    }

    public function getByBookletId($bookletId) {
        $sql = 'SELECT * FROM `' . $this->getTable() . '` WHERE booklet_id = :bookletId ORDER BY page ASC, custom_order ASC';
        return $this->fetchAll($sql, compact('bookletId'));
    }
}
