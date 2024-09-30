<?php

declare(strict_types=1);

namespace App\Dao;

class TranslationDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'st_translation');
    }

    public function save($id, $translations) {
        $translations['id'] = $id;
        $query = 'INSERT INTO `' . $this->getTable() . '` (id, ca, es, en) VALUES (:id, :ca, :es, :en)';
        $this->query($query, $translations);
        return $this->getLastInsertId();
    }

    public function getTranslations($startWith = [], $notStartWith = []) {
        $data = [];
        $sql = 'SELECT * FROM (' . $this->getAllTranslationsSql() . ') t WHERE 1 = 1';

        if (!empty($startWith)) {
            foreach ($startWith as $k => $v) {
                $sql .= ' AND id LIKE :start_' . $k;
                $data['start_' . $k] = $v . '%';
            }
        }

        if (!empty($notStartWith)) {
            foreach ($notStartWith as $k => $v) {
                $sql .= ' AND id NOT LIKE :not_start_' . $k;
                $data['not_start_' . $k] = $v . '%';
            }
        }

        $sql .= ' ORDER BY id asc';

        return $this->fetchAll($sql, $data);
    }

    public function getAllTranslationsSql() {
        return 'SELECT * FROM st_translation_custom tc UNION ALL SELECT * FROM st_translation WHERE id NOT IN (SELECT id FROM st_translation_custom)';
    }

    public function getByIdLanguage($id, $language) {
        $sql = 'SELECT ' . $language . ' FROM (' . $this->getAllTranslationsSql() . ') t WHERE t.id = :id';
        return $this->fetchOneField($sql, compact('id'));
    }

}
