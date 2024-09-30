<?php

declare(strict_types=1);

namespace App\Dao;

class StatusEventGroupDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'st_status_event_group');
    }

    public function getForSelectWithStatusEvents($eventId = null) {
        $result = $this->getAll();
        foreach ($result as $i => &$row) {
            $params = ['status_event_group_id' => $row['id']];
            $idSql = "";
            if (!empty($eventId)) {
                $idSql = " OR id = :id";
                $params['id'] = $eventId;
            }

            $sql = "SELECT id, name, has_content FROM st_status_event WHERE status_event_group_id = :status_event_group_id AND (editable = 1" . $idSql . ") ORDER BY id ASC";
            $statusEvents = $this->fetchAll($sql, $params);
            if (!empty($statusEvents)) {
                $row['status_events'] = $statusEvents;
            } else {
                unset($result[$i]);
            }
        }

        return $result;
    }

}
