<?php

declare(strict_types=1);

namespace App\Dao;

class StatusEventVariableDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'st_status_event_variable');
    }

    public function getAllWithStatusEvents() {
        $sql = "SELECT sev.*, GROUP_CONCAT(DISTINCT sevs.status_event_id ORDER BY sevs.status_event_id ASC SEPARATOR '#') as status_events 
                FROM " . $this->table . " sev 
                LEFT JOIN st_status_event_variables sevs ON sev.id = sevs.status_event_variable_id
                WHERE sevs.status_event_variable_id IS NOT NULL OR sev.common = 1
                GROUP BY sev.id
                ORDER BY sev.custom_order ASC";
        return $this->fetchAll($sql);
    }

    public function getByStatusEventId($statusEventId) {
        $sql = "SELECT sev.*
                FROM " . $this->table . " sev 
                LEFT JOIN st_status_event_variables sevs ON sev.id = sevs.status_event_variable_id AND sevs.status_event_id = :status_event_id
                WHERE sevs.status_event_variable_id IS NOT NULL OR sev.common = 1
                ORDER BY sev.custom_order ASC";
        return $this->fetchAll($sql, ['status_event_id' => $statusEventId]);
    }

}


