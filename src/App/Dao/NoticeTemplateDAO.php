<?php

declare(strict_types=1);

namespace App\Dao;

use App\Util\CommonUtils;

class NoticeTemplateDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'st_notice_template');
    }

    public function getRemoteDatatable($statusEventGroupId) {
        // Columnas a tratar en el datatable
        $columns = [
            ['db' => 'id', 'dt' => 'id'],
            ['db' => 'code', 'dt' => 'code'],
            ['db' => 'name', 'dt' => 'name'],
            ['db' => 'status_event', 'dt' => 'status_event'],
            ['db' => 'status_event_color', 'dt' => 'status_event_color'],
            ['db' => 'status_event_group', 'dt' => 'status_event_group'],
            ['db' => 'status_event_group_color', 'dt' => 'status_event_group_color'],
            // Los campos de fecha deben ser formateados
            ['db' => 'date_created', 'dt' => 'date_created', 'date' => true,
                'formatter' => function ($d, $row) {
                    return CommonUtils::convertDate($d);
                }
            ],
            ['db' => 'status_event_id', 'dt' => 'status_event_id', 'exact' => true],
            ['db' => 'status_event_group_id', 'dt' => 'status_event_group_id', 'exact' => true],
            ['db' => 'editable', 'dt' => 'editable', 'exact' => true]
        ];

        $table = '(
            SELECT 
              nt.id,
              nt.code, 
              nt.name,
              se.name as status_event,
              se.color as status_event_color,
              seg.name as status_event_group,
              seg.color as status_event_group_color,
              nt.status_event_id,
              se.status_event_group_id,
              nt.date_created,
              nt.date_updated,
              se.editable
            FROM ' . $this->table . ' nt
            INNER JOIN st_status_event se on se.id = nt.status_event_id
            INNER JOIN st_status_event_group seg on seg.id = se.status_event_group_id
            WHERE hidden = 0
            AND seg.id = ' . intval($statusEventGroupId) . '
         ) temp';

        return $this->datatablesSimple($table, 'id', $columns);
    }

    public function save($data) {
        $query = 'INSERT INTO ' . $this->table . ' (name, email_subject, email_content, content, status_event_id, code) VALUES ' .
                '(:name, :email_subject, :email_content, :content, :status_event_id, :code)';
        $this->query($query, $data);
        return $this->getLastInsertId();
    }

    public function update($data) {
        $query = 'UPDATE ' . $this->table . ' SET name = :name, email_subject = :email_subject, content = :content, email_content = :email_content, status_event_id = :status_event_id, code = :code WHERE id = :id';
        $this->query($query, $data);
    }

    public function getFullById($id) {
        $sql = "SELECT nt.*, se.editable
                FROM `" . $this->getTable() . "` nt
                LEFT JOIN st_status_event se on se.id = nt.status_event_id
                WHERE nt.id = :id";

        return $this->fetchRecord($sql, compact('id'));
    }

    public function getForSelectByStatusEventId($statusEventId) {
        $sql = "SELECT id, name FROM " . $this->table . " WHERE status_event_id = :status_event_id ORDER BY id ASC";
        return $this->fetchAll($sql, ['status_event_id' => $statusEventId]);
    }

    public function getNoticeTypeClientForSelect($statusEventIds) {
        $sql = 'SELECT 
            nt.id, nt.name
            FROM ' . $this->table . ' nt
            WHERE FIND_IN_SET(nt.status_event_id, :status_event_ids)';
        return $this->fetchAll($sql, ['status_event_ids' => implode(',', $statusEventIds)]);
    }

}
