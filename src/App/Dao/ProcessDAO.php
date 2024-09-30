<?php

declare(strict_types=1);

namespace App\Dao;

use App\Util\CommonUtils;

class ProcessDAO extends BaseDAO
{

    public function __construct($connection)
    {
        parent::__construct($connection, 'st_process');
    }

    public function getRemoteDatatable($clientId = null)
    {
        $extraSQL = '';
        if ($clientId != null) {
            $extraSQL .= ' WHERE p.client_id = ' . $clientId . ' ';
        }
        // Columnas a tratar en el datatable
        $columns = [
            ['db' => 'id', 'dt' => 'id'],
            ['db' => 'name', 'dt' => 'name'],
            ['db' => 'description', 'dt' => 'description'],
            ['db' => 'process_type_id', 'dt' => 'process_type_id', 'exact' => true],
            ['db' => 'process_type', 'dt' => 'process_type'],
            ['db' => 'process_type_color', 'dt' => 'process_type_color', 'exact' => true],
            ['db' => 'process_status_id', 'dt' => 'process_status_id', 'exact' => true],
            ['db' => 'total_tasks', 'dt' => 'total_tasks', 'exact' => true],
            ['db' => 'process_status', 'dt' => 'process_status'],
            ['db' => 'process_status_color', 'dt' => 'process_status_color', 'exact' => true],
            ['db' => 'client_id', 'dt' => 'client_id', 'exact' => true],
            ['db' => 'client_entity', 'dt' => 'client_entity'],
            ['db' => 'creator_user_id', 'dt' => 'creator_user_id', 'exact' => true],
            ['db' => 'creator_fullname', 'dt' => 'creator_fullname'],
            ['db' => 'creator_color', 'dt' => 'creator_color'],
            // Los campos de fecha deben ser formateados
            [
                'db' => 'date_start', 'dt' => 'date_start', 'date' => true,
                'formatter' => function ($d, $row) {
                    return CommonUtils::convertDate($d);
                }
            ],
            [
                'db' => 'date_end', 'dt' => 'date_end', 'date' => true,
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

        $table = '(
        SELECT
            p.id,
            p.name,
            p.description,
            p.date_start,
            p.date_end,
            p.client_id,
            JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(c.information, "' . AES_KEY . '"), "$.entity")) AS client_entity,
            p.process_type_id,
            p.process_status_id,
            (SELECT COUNT(*) FROM st_task t WHERE process_id = p.id) as total_tasks,
            p.creator_user_id,
            TRIM(CONCAT(JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(u.personal_information, "' . AES_KEY . '"), "$.name")), " " , JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(u.personal_information, "' . AES_KEY . '"), "$.surnames")))) AS creator_fullname,
            u.color AS creator_color, 
            p.date_created,
            p.date_updated,
            pt.name AS process_type,
            pt.color AS process_type_color,
            ps.name AS process_status,
            ps.color AS process_status_color
        FROM
            ' . $this->table . ' p
            INNER JOIN `st_process_type` pt ON pt.id = p.process_type_id     
            INNER JOIN `st_process_status` ps ON ps.id = p.process_status_id     
            INNER JOIN `st_client` c ON c.id = p.client_id    
            INNER JOIN `st_user` u ON u.id = p.creator_user_id 
             ' . $extraSQL . '

) temp';

        return $this->datatablesSimple($table, 'id', $columns);
    }

    public function getFullById($id)
    {
        $sql = "SELECT 
                p.id, 
                p.client_id, 
                p.process_type_id, 
                p.process_status_id, 
                (SELECT COUNT(*) FROM st_task t WHERE process_id = p.id) as total_tasks,
                p.creator_user_id, 
                TRIM(CONCAT(JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(u.personal_information, :AES_KEY), '$.name')), ' ' , JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(u.personal_information, :AES_KEY), '$.surnames')))) AS fullname,
                p.name, 
                p.description, 
                p.date_created, 
                p.date_updated,
                p.date_start,
                p.date_end
                FROM " . $this->table . " p 
                INNER JOIN `st_user` u ON u.id = p.creator_user_id 
                WHERE p.id = :id";

        return $this->fetchRecord($sql, ['id' => $id, 'AES_KEY' => AES_KEY]);
    }

    public function save($data)
    {
        $query = 'INSERT INTO ' . $this->table . ' (name, client_id, process_type_id, process_status_id, description, creator_user_id, date_start, date_end) '
            . 'VALUES (:name, :client_id, :process_type_id, :process_status_id, :description, :creator_user_id, ' . CommonUtils::datetimeOrNull($data, 'date_start') . ', ' . CommonUtils::datetimeOrNull($data, 'date_end') . ' )';
        $this->query($query, $data);

        return $this->getLastInsertId();
    }

    public function update($data)
    {
        $query = 'UPDATE ' . $this->table . ' SET '
            . ' name = :name,'
            . ' client_id = :client_id,'
            . ' process_type_id = :process_type_id,'
            . ' process_status_id = :process_status_id,'
            . ' description = :description,'
            . ' date_start = ' . CommonUtils::datetimeOrNull($data, 'date_start') . ', '
            . ' date_end = ' . CommonUtils::datetimeOrNull($data, 'date_end')
            . ' WHERE id = :id';
        $this->query($query, $data);
    }
}
