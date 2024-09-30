<?php

declare(strict_types=1);

namespace App\Dao;

use App\Util\CommonUtils;

class ClientDAO extends BaseDAO
{

    public function __construct($connection)
    {
        parent::__construct($connection, 'st_client');
    }

    public function getRemoteDatatable()
    {
        // Columnas a tratar en el datatable
        $columns = [
            ['db' => 'id', 'dt' => 'id'],
            ['db' => 'name', 'dt' => 'name'],
            ['db' => 'surnames', 'dt' => 'surnames'],
            ['db' => 'fullname', 'dt' => 'fullname'],
            ['db' => 'client_name', 'dt' => 'client_name'],
            ['db' => 'phone', 'dt' => 'phone'],
            ['db' => 'email', 'dt' => 'email'],
            ['db' => 'address', 'dt' => 'address'],
            ['db' => 'city_name', 'dt' => 'city_name'],
            ['db' => 'client_type', 'dt' => 'client_type'],
            ['db' => 'total_processes', 'dt' => 'total_processes'],
            // Los campos de fecha deben ser formateados
            [
                'db' => 'date_created', 'dt' => 'date_created', 'date' => true,
                'formatter' => function ($d, $row) {
                    return CommonUtils::convertDate($d);
                }
            ],
            ['db' => 'client_type_id', 'dt' => 'client_type_id', 'exact' => true],
            ['db' => 'client_type_color', 'dt' => 'client_type_color', 'exact' => true],
            ['db' => 'date_updated', 'dt' => 'date_updated'],
            ['db' => 'country_name', 'dt' => 'country_name']
        ];

        $table = '(
    SELECT *,
        TRIM(CONCAT(name, " ", surnames)) AS fullname,
        IF(entity IS NOT NULL AND entity != "", entity, CONCAT(name, " ", surnames)) AS client_name
    FROM (
        SELECT
            c.id,
            JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(c.information, "' . AES_KEY . '"), "$.name")) AS name,
            JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(c.information, "' . AES_KEY . '"), "$.surnames")) AS surnames,
            JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(c.information, "' . AES_KEY . '"), "$.entity")) AS entity,
            JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(c.information, "' . AES_KEY . '"), "$.phone")) AS phone,
            JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(c.information, "' . AES_KEY . '"), "$.email")) AS email,
            JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(c.information, "' . AES_KEY . '"), "$.address")) AS address,
            JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(c.information, "' . AES_KEY . '"), "$.city")) AS city_name,
            JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(c.information, "' . AES_KEY . '"), "$.province")) AS province_name,
            (SELECT COUNT(*) FROM st_process p WHERE client_id = c.id) as total_processes,
            co.name AS country_name,
            c.date_created,
            c.date_updated,
            c.client_type_id,
            ct.name AS client_type,
            ct.color AS client_type_color
        FROM 
            ' . $this->table . ' c 
            INNER JOIN `st_client_type` ct ON ct.id = c.client_type_id                        
            LEFT JOIN `st_country` co ON co.id = JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(c.information, "' . AES_KEY . '"), "$.country_id"))
    ) sub
) temp';

        return $this->datatablesSimple($table, 'id', $columns);
    }

    public function getFullForSelect($searchTerm = null, $id = null)
    {
        $data = ['AES_KEY' => AES_KEY];

        $whereSql = '';
        if (!empty($searchTerm)) {
            $whereSql .= ' AND entity LIKE :searchTerm ';
            $data['searchTerm'] = '%' . $searchTerm . '%';
        }
        if (!empty($id)) {
            $whereSql .= ' AND id = :id';
            $data['id'] = $id;
        }

        $sql = 'SELECT *, entity as client_name,
                IF(entity IS NOT NULL AND entity != "", entity, CONCAT(name, " ", surnames)) AS text
                FROM (
                    SELECT p.id,
                    TRIM(JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(p.information, :AES_KEY), "$.name"))) as name,
                    TRIM(JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(p.information, :AES_KEY), "$.surnames"))) as surnames,
                    TRIM(JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(p.information, :AES_KEY), "$.entity"))) as entity                  
                    FROM ' . $this->table . ' p
                    GROUP BY p.id
                ) t
                WHERE 1 = 1' . $whereSql . ' 
                ORDER BY client_name ';

        if (!empty($id)) {
            return $this->fetchRecord($sql, $data);
        } else {
            return $this->fetchAll($sql, $data);
        }
    }

    public function getFullById($id)
    {
        $sql = "SELECT p.id,
                AES_DECRYPT(p.information, :AES_KEY) AS information,  
                p.client_type_id,                
                p.date_created,
                p.date_updated
                FROM " . $this->table . " p                
                WHERE p.id = :id";

        $result = $this->fetchRecord($sql, ['id' => $id, 'AES_KEY' => AES_KEY]);
        if (empty($result)) {
            return $result;
        }
        return $this->extractPersonalInformation($result);
    }

    public function getBasicInformation($id)
    {
        return $this->getById($id);
    }

    public function getById($id, $extractPersonalInformation = true)
    {
        $sql = "SELECT p.id, AES_DECRYPT(p.information, :AES_KEY) AS information, co.name as country_name
                FROM " . $this->table . " p
                LEFT JOIN `st_country` co ON co.id = JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(p.information, '" . AES_KEY . "'), '$.country_id'))
                WHERE p.id = :id";

        $result = $this->fetchRecord($sql, ['id' => $id, 'AES_KEY' => AES_KEY]);
        if (empty($result)) {
            return $result;
        }
        if ($extractPersonalInformation) {
            return $this->extractPersonalInformation($result);
        }
        return $result;
    }

    public function getFullname($id)
    {
        $sql = 'SELECT 
                TRIM(CONCAT(JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(information, :AES_KEY), "$.name")), " " , JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(information, :AES_KEY), "$.surnames")))) as fullname
                FROM ' . $this->table . '
                WHERE id = :id';
        return $this->fetchOneField($sql, ['id' => $id, 'AES_KEY' => AES_KEY]);
    }

    public function getClientName($id)
    {
        $sql = 'SELECT p.id,                    
                TRIM(JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(p.information, :AES_KEY), "$.entity"))) as client_name
                FROM ' . $this->table . ' p
                WHERE id = :id';
        return $this->fetchOneField($sql, ['id' => $id, 'AES_KEY' => AES_KEY]);
    }

    public function save($data)
    {
        $query = 'INSERT INTO ' . $this->table . ' (information, client_type_id) '
            . 'VALUES (AES_ENCRYPT(:information, :AES_KEY), :client_type_id)';

        $data['AES_KEY'] = AES_KEY;
        $this->query($query, $data);

        return $this->getLastInsertId();
    }

    public function update($data)
    {
        $query = 'UPDATE ' . $this->table . ' SET '
            . ' information = AES_ENCRYPT(:information, :AES_KEY),'
            . ' client_type_id = :client_type_id'
            . ' WHERE id = :id';

        $data['AES_KEY'] = AES_KEY;
        $this->query($query, $data);
    }

    /**
     * La información personal se incluye en un campo "information" en formato JSON
     * Se extrae y se añade como campos al array.
     * @param type $result
     * @return type
     */
    private function extractPersonalInformation($result)
    {
        $result = array_merge($result, json_decode($result['information'], true));
        unset($result['information']);
        return $result;
    }
}
