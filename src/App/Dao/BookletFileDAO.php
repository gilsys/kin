<?php

declare(strict_types=1);

namespace App\Dao;

class BookletFileDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'st_booklet_file');
    }

    public function save($data) {
        $query = 'INSERT INTO ' . $this->table . ' (booklet_id, file_id) '
            . 'VALUES (:booklet_id, :file_id)';
        $this->query($query, $data);
    }

    public function clear($bookletId) {
        $query = 'DELETE FROM ' . $this->table . ' WHERE booklet_id = :bookletId';
        $this->query($query, compact('bookletId'));
    }

    public function deleteByFileId($fileId) {
        $query = 'DELETE FROM ' . $this->table . ' WHERE file_id = :fileId';
        $this->query($query, compact('fileId'));
    }

    public function getFilesByBookletId($bookletId) {
        $sql = "SELECT f.id, f.date_created AS date, f.file_type_id
                FROM " . $this->table . " bf
                INNER JOIN st_file f ON f.id = bf.file_id
                WHERE bf.booklet_id = :bookletId
                ORDER BY f.date_created DESC";
        return $this->fetchAll($sql, compact('bookletId'));
    }

    public function getByFileId($fileId) {
        $sql = "SELECT * FROM " . $this->table . " WHERE file_id = :fileId";
        return $this->fetchRecord($sql, compact('fileId'));
    }
}
