<?php

declare(strict_types=1);

namespace App\Dao;

use App\Service\TagService;
use App\Util\CommonUtils;

class TaskDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'st_task');
    }

    public function getRemoteDatatable($processId = null, $userId = null) {

        $condition = ' ';
        if (!empty($_POST['columns'][2]['search']['value'])) {
            $tags = preg_replace('/[^0-9,]/', '', $_POST['columns'][2]['search']['value']);
            $condition .= ' AND FIND_IN_SET(tag.id, "' .  $tags . '" ) ';
            $_POST['columns'][2]['search']['value'] = '';
        }

        $extraSQL = ' ';
        $extraSelect = ' ';
        $extraJoin = ' ';
        $extraColumns = null;
        if ($processId != null) {
            $extraSQL .= ' AND t.process_id = ' . $processId . ' ';
        }
        if ($userId) {
            $extraSQL .= ' AND t.creator_user_id = ' . $userId . ' ';
        } else {
            $extraSelect = '             
             t.payment_status_id,
             tps.name AS payment_status,
             tps.color AS payment_status_color, ';
            $extraJoin = ' LEFT JOIN `st_task_payment_status` tps ON tps.id = t.payment_status_id ';

            $extraColumns = [
                ['db' => 'payment_status_id', 'dt' => 'payment_status_id', 'exact' => true],
                ['db' => 'payment_status', 'dt' => 'payment_status'],
                ['db' => 'payment_status_color', 'dt' => 'payment_status_color', 'exact' => true],
            ];
        }

        // Columnas a tratar en el datatable
        $columns = [
            ['db' => 'id', 'dt' => 'id'],
            ['db' => 'tags', 'dt' => 'tags'],
            ['db' => 'description', 'dt' => 'description'],
            ['db' => 'hours', 'dt' => 'hours'],
            ['db' => 'process_id', 'dt' => 'process_id', 'exact' => true],
            ['db' => 'process_name', 'dt' => 'process_name'],
            ['db' => 'task_type_id', 'dt' => 'task_type_id', 'exact' => true],
            ['db' => 'task_type', 'dt' => 'task_type'],
            ['db' => 'task_type_color', 'dt' => 'task_type_color', 'exact' => true],
            ['db' => 'task_status_id', 'dt' => 'task_status_id', 'exact' => true],
            ['db' => 'task_status', 'dt' => 'task_status'],
            ['db' => 'task_status_color', 'dt' => 'task_status_color', 'exact' => true],
            ['db' => 'creator_user_id', 'dt' => 'creator_user_id', 'exact' => true],
            ['db' => 'creator_fullname', 'dt' => 'creator_fullname'],
            ['db' => 'creator_color', 'dt' => 'creator_color'],
            // Los campos de fecha deben ser formateados
            [
                'db' => 'date_task', 'dt' => 'date_task', 'date' => true,
                'formatter' => function ($d, $row) {
                    return CommonUtils::convertDate($d);
                }
            ],
            [
                'db' => 'date_created', 'dt' => 'date_created', 'date' => true,
                'formatter' => function ($d, $row) {
                    return CommonUtils::convertDate($d);
                }
            ],
            [
                'db' => 'date_updated', 'dt' => 'date_updated', 'date' => true,
                'formatter' => function ($d, $row) {
                    return CommonUtils::convertDate($d);
                }
            ]
        ];

        isset($extraColumns) ? $columns = array_merge($columns, $extraColumns) : null;

        $table = '(
        SELECT
            t.id,
            (SELECT GROUP_CONCAT(tag.name ORDER BY tag.name ASC SEPARATOR ", ") FROM st_task_tag ttag INNER JOIN st_tag tag ON ttag.tag_id = tag.id WHERE ttag.task_id = t.id ) as tags,
            t.description,
            t.date_task,
            t.hours,
            t.process_id,
            p.name AS process_name,
            t.task_type_id,
            t.task_status_id,
            t.creator_user_id,
            TRIM(CONCAT(JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(u.personal_information, "' . AES_KEY . '"), "$.name")), " " , JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(u.personal_information, "' . AES_KEY . '"), "$.surnames")))) AS creator_fullname,
            u.color AS creator_color, 
            t.date_created,
            t.date_updated,
            tt.name AS task_type,
            tt.color AS task_type_color,
             ' . $extraSelect . ' 
            ts.name AS task_status,
            ts.color AS task_status_color
        FROM
            ' . $this->table . ' t
            INNER JOIN `st_task_type` tt ON tt.id = t.task_type_id
            INNER JOIN `st_task_status` ts ON ts.id = t.task_status_id
            INNER JOIN `st_process` p ON p.id = t.process_id
            INNER JOIN `st_user` u ON u.id = t.creator_user_id 
            LEFT JOIN `st_task_tag` ttag ON ttag.task_id = t.id
            INNER JOIN `st_tag` tag ON tag.id = ttag.tag_id
            ' . $extraJoin . ' 
            WHERE 1 = 1 
             ' . $extraSQL . ' ' . $condition . ' 
            GROUP BY t.id          
        ) temp';

        return $this->datatablesSimple($table, 'id', $columns);
    }

    public function getFullById($id) {
        $sql = "SELECT 
                t.id, 
                (SELECT GROUP_CONCAT(tag.name ORDER BY tag.name ASC SEPARATOR ', ') FROM st_task_tag ttag INNER JOIN st_tag tag ON ttag.tag_id = tag.id WHERE ttag.task_id = t.id ) as tags,
                t.description, 
                t.process_id,
                t.task_type_id, 
                t.task_status_id, 
                t.payment_status_id, 
                t.creator_user_id, 
                TRIM(CONCAT(JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(u.personal_information, :AES_KEY), '$.name')), ' ' , JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(u.personal_information, :AES_KEY), '$.surnames')))) AS fullname,
                t.hours,
                t.date_task, 
                t.date_created, 
                t.date_updated 
                FROM " . $this->table . " t 
                INNER JOIN `st_user` u ON u.id = t.creator_user_id 
                WHERE t.id = :id";

        return $this->fetchRecord($sql, ['id' => $id, 'AES_KEY' => AES_KEY]);
    }

    public function save($data) {
        $query = 'INSERT INTO ' . $this->table . ' (process_id, task_type_id, task_status_id, payment_status_id, hours, description, creator_user_id, date_task) '
            . 'VALUES (:process_id, :task_type_id, :task_status_id, :payment_status_id, :hours, :description, :creator_user_id, ' . CommonUtils::datetimeOrNull($data, 'date_task') . ' )';
        $this->query($query, $data);

        return $this->getLastInsertId();
    }

    public function update($data) {
        $query = 'UPDATE ' . $this->table . ' SET '
            . ' process_id = :process_id,'
            . ' task_type_id = :task_type_id,'
            . ' task_status_id = :task_status_id,'
            . ' payment_status_id = :payment_status_id,'
            . ' hours = :hours,'
            . ' description = :description,'
            . ' date_task = ' . CommonUtils::datetimeOrNull($data, 'date_task')
            . ' WHERE id = :id';
        $this->query($query, $data);
    }
}
