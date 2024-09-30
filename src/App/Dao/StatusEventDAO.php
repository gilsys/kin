<?php

declare(strict_types=1);

namespace App\Dao;

class StatusEventDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'st_status_event');
    }

    public function getEditableForSelect() {        
        return $this->fetchAll('SELECT id, name FROM ' . $this->table . ' WHERE editable = 1');
    }

    public function getByNoticeTemplateId($noticeTemplateId) {
        $sql = "SELECT se.*
                FROM " . $this->table . " se 
                INNER JOIN st_notice_template nt ON se.id = nt.status_event_id AND nt.id = :noticeTemplateId";
        return $this->fetchRecord($sql, compact('noticeTemplateId'));
    }

}
