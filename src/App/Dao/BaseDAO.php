<?php

declare(strict_types=1);

namespace App\Dao;

use App\Constant\GenericStatus;
use App\Util\SlimUtils;
use DateInterval;
use DatePeriod;
use DateTime;
use Exception;

include 'SSP.php';

class BaseDAO {

    public $table;
    public $connection;
    private $cache = [];

    public function __construct($connection, $table) {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function query($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        try {
            $result = $stmt->execute($params);
        } catch (Exception $e) {
            SlimUtils::getLogger()->addError($sql);
            SlimUtils::getLogger()->addError(print_r($params, true));
            throw $e;
        }
        return $result;
    }

    public function fetchAll($sql, $params = null) {
        $stmt = $this->connection->prepare($sql);
        try {
            $stmt->execute($params);
        } catch (Exception $e) {
            SlimUtils::getLogger()->addError($sql);
            SlimUtils::getLogger()->addError(print_r($params, true));
            throw $e;
        }
        return $stmt->fetchAll();
    }

    public function fetchOneField($sql, $params = null) {
        $result = $this->fetchRecord($sql, $params);
        return empty($result) ? null : reset($result);
    }

    public function fetchRecord($sql, $params = null) {
        $stmt = $this->connection->prepare($sql);
        try {
            $stmt->execute($params);
        } catch (Exception $e) {
            SlimUtils::getLogger()->addError($sql);
            SlimUtils::getLogger()->addError(print_r($params, true));
            throw $e;
        }
        return $stmt->fetch();
    }

    public function getEmptyModel() {
        $sql = "SELECT column_name, column_default FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = :table_name order by ordinal_position";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['table_name' => $this->getTable()]);
        $tableFieldsArray = $stmt->fetchAll();
        $tableFields = [];
        foreach ($tableFieldsArray as $field) {
            $tableFields[$field['column_name']] = $field['column_default'];
        }
        return $tableFields;
    }

    public function getSingleField($key, $fieldName, $fieldCondition = null, $cache = false) {
        if ($cache) {
            $cacheKey = $key . '@' . $fieldName . '@' . $fieldCondition;
            if (!empty($this->cache[$cacheKey])) {
                return $this->cache[$cacheKey];
            }
        }

        if (empty($fieldCondition)) {
            $fieldCondition = 'id';
        }
        $sql = "SELECT `" . $fieldName . "` FROM `" . $this->getTable() . "` WHERE " . $fieldCondition . " = :key";
        $result = $this->fetchRecord($sql, compact('key'));

        if (!empty($result)) {
            $result = reset($result);
        }
        if ($cache) {
            $this->cache[$cacheKey] = $result;
        }
        return $result;
    }

    public function getJsonPropertyInFieldEncrypted($key, $fieldName, $jsonField, $fieldCondition = null) {
        $decryptedField = $this->getSingleFieldEncrypted($key, $fieldName, $fieldCondition);
        $json = json_decode($decryptedField, true);
        return !empty($json[$jsonField]) ? $json[$jsonField] : null;
    }

    public function getSingleFieldEncrypted($key, $fieldName, $fieldCondition = null) {
        if (empty($fieldCondition)) {
            $fieldCondition = 'id';
        }
        $sql = "SELECT AES_DECRYPT(`" . $fieldName . "`, :AES_KEY) AS encrypted_data FROM `" . $this->getTable() . "` WHERE " . $fieldCondition . " = :key";
        $result = $this->fetchRecord($sql, ['AES_KEY' => AES_KEY, 'key' => $key]);
        if (empty($result)) {
            return $result;
        }
        return reset($result);
    }

    public function getBySingleFieldEncrypted($key, $fieldName, $jsonField, $fieldCondition) {
        $sql = 'SELECT ' . $fieldName . ' FROM ' . $this->table . ' WHERE JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(' . $jsonField . ', :AES_KEY), "$.' . $fieldCondition . '")) = :key';
        $result = $this->fetchRecord($sql, ['AES_KEY' => AES_KEY, 'key' => $key]);
        if (empty($result)) {
            return $result;
        }
        return reset($result);
    }

    public function updateDateUpdated($id) {
        $sql = "update `" . $this->getTable() . "` set date_updated = NOW() WHERE id = :id";
        $this->query($sql, ['id' => $id]);
    }

    public function updateSingleField($id, $fieldName, $fieldValue, $allowedValues = null, $keyField = 'id') {
        if (!empty($allowedValues) && !in_array($fieldValue, $allowedValues)) {
            throw new Exception(__('app.error.invalid_value'));
        }

        $sql = "update `" . $this->getTable() . "` set " . $fieldName . " = :fieldValue WHERE " . $keyField . " = :id";
        $this->query($sql, ['id' => $id, 'fieldValue' => $fieldValue]);
    }

    public function updateSingleEncryptedField($id, $fieldName, $fieldValue) {
        $sql = "update `" . $this->getTable() . "` set " . $fieldName . " =  AES_ENCRYPT(:fieldValue, :AES_KEY) WHERE id = :id";
        $this->query($sql, ['id' => $id, 'fieldValue' => $fieldValue, 'AES_KEY' => AES_KEY]);
    }

    public function updateSingleFieldEncryptedJSON($id, $fieldName, $jsonKey, $fieldValue) {
        $encryptedData = $this->getSingleFieldEncrypted($id, $fieldName);

        if (!empty($encryptedData)) {
            $json = json_decode($encryptedData, true);
        }
        $json[$jsonKey] = $fieldValue;

        $this->updateSingleEncryptedField($id, $fieldName, json_encode($json));
    }

    public function getById($id) {
        $sql = "SELECT * FROM `" . $this->getTable() . "` WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['id' => $id]);
        $record = $stmt->fetch();
        return $record;
    }

    public function getByField($fieldName, $value) {
        $sql = "SELECT * FROM `" . $this->getTable() . "` WHERE " . $fieldName . " = :value";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(compact('value'));
        return $stmt->fetch();
    }

    public function getAllByField($fieldName, $value) {
        $sql = "SELECT * FROM `" . $this->getTable() . "` WHERE " . $fieldName . " = :value";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(compact('value'));
        return $stmt->fetchAll();
    }

    public function getForSelectWithStatus($id = 'id', $name = 'name', $orderBy = 'id', $onlyEnabled = false) {
        $where = "";
        $data = [];
        if ($onlyEnabled) {
            $where .= " WHERE status = :status ";
            $data ['status'] = GenericStatus::Enabled;
        }
        $sql = "SELECT " . $id . ", " . $name . ", status FROM " . $this->table . $where . " ORDER BY " . $orderBy . " ASC";
        return $this->fetchAll($sql, $data);
    }

    public function getForSelect($id = 'id', $name = 'name', $orderBy = 'id', $exclude = null) {
        $excludeSql = '';
        $params = [];
        if (!empty($exclude)) {
            $excludeSql = ' WHERE NOT FIND_IN_SET(' . $id . ', :excludeIds)';
            $params = ['excludeIds' => implode(',', $exclude)];
        }
        $sql = "SELECT " . $id . ", " . $name . " FROM " . $this->table . $excludeSql . " ORDER BY " . $orderBy . " ASC";
        return $this->fetchAll($sql, $params);
    }

    public function getAll($orderBy = 'id') {
        $sql = "SELECT * FROM " . $this->table . " ORDER BY ' . $orderBy . ' ASC";
        return $this->fetchAll($sql);
    }

    public function getAllAssoc($onlyColumn = null) {
        $results = [];
        foreach ($this->getAll() as $result) {
            if (!empty($onlyColumn)) {
                $results[$result['id']] = $result[$onlyColumn];
            } else {
                $results[$result['id']] = $result;
            }
        }
        return $results;
    }

    public function getTable() {
        return $this->table;
    }

    public function deleteById($id, $hasCustomOrder = false) {
        if ($hasCustomOrder) {
            $customOrder = $this->getSingleField($id, 'custom_order');
            $query = 'UPDATE ' . $this->table . ' SET custom_order = custom_order - 1 WHERE custom_order >= :customOrder';
            $this->query($query, compact('customOrder'));
        }
        $query = 'DELETE FROM `' . $this->getTable() . '` WHERE id = :id';
        $this->query($query, ['id' => $id]);
    }

    public function getNext($fieldName = null) {
        if (empty($fieldName)) {
            return $this->getNextAutoincrement();
        }
        $sql = "SELECT MAX(`" . $fieldName . "`) FROM `" . $this->getTable() . "`";
        $result = $this->fetchRecord($sql);
        if (empty($result)) {
            return 1;
        }
        return intval(reset($result)) + 1;
    }

    public function getNextAutoincrement() {
        $sql = "SHOW TABLE STATUS LIKE :table";
        $result = $this->fetchRecord($sql, ['table' => $this->getTable()]);
        if (empty($result)) {
            return 1;
        }
        return $result["Auto_increment"];
    }

    public function setNextAutoincrement($value) {
        $sql = "ALTER TABLE " . $this->getTable() . " AUTO_INCREMENT = " . intval($value);
        $this->query($sql);
    }

    public function getLastInsertId() {
        return $this->connection->lastInsertId();
    }

    public function datatablesSimple($table, $primaryKey, $columns, $defaultOrder = false, $defaultDir = 'asc') {
        $request = &$_POST;

        // Fuerza un orden específico
        if (!empty($defaultOrder)) {
            foreach ($request['columns'] as $columnNum => $column) {
                if ($column['data'] == $defaultOrder) {
                    $request['order'][0] = ['column' => $columnNum, 'dir' => $defaultDir];
                    $request['columns'][$columnNum]['orderable'] = true;
                    break;
                }
            }
        }
        return SSP::simple($request, $this->getConnection(), $table, $primaryKey, $columns);
    }

    public function datatablesComplex($table, $primaryKey, $columns, $whereResult = null, $whereAll = null) {
        return SSP::complex($_POST, $this->getConnection(), $table, $primaryKey, $columns, $whereResult, $whereAll);
    }

    public function datatableEmptyColumns(&$columns, $fields) {
        foreach ($fields as $field) {
            foreach ($columns as &$column) {
                if ($column['db'] == $field || $column['dt'] == $field) {
                    $column['formatter'] = function ($d, $row) {
                        return null;
                    };
                    continue;
                }
            }
        }
    }

    public function deleteAll() {
        $query = 'DELETE FROM `' . $this->getTable() . '`';
        $this->query($query);
    }

    public function checkExists($value, $field, $id = null) {
        $data = compact('value');
        $sqlId = "";
        if (!empty($id)) {
            $sqlId = " AND id != :id";
            $data['id'] = $id;
        }

        $sql = "SELECT id FROM " . $this->table . " WHERE " . $field . " = :value" . $sqlId;
        $result = $this->fetchRecord($sql, $data);
        return !empty($result);
    }

    public function up($id) {
        $current = $this->getById($id);
        $sql = 'UPDATE ' . $this->table . ' SET custom_order = custom_order+1 WHERE custom_order = :custom_order';
        $this->query($sql, ['custom_order' => intval($current['custom_order']) - 1]);

        $sql = 'UPDATE ' . $this->table . ' SET custom_order = custom_order-1 WHERE id = :id';
        $this->query($sql, compact('id'));
    }

    public function down($id) {
        $current = $this->getById($id);
        $sql = 'UPDATE ' . $this->table . ' SET custom_order = custom_order-1 WHERE custom_order = :custom_order';
        $this->query($sql, ['custom_order' => intval($current['custom_order']) + 1]);

        $sql = 'UPDATE ' . $this->table . ' SET custom_order = custom_order+1 WHERE id = :id';
        $this->query($sql, compact('id'));
    }

    public function getLastMonthsSql($fieldName, &$data, $months) {
        if (is_null($months)) {
            return '';
        }

        $data['months'] = $months;

        return ' AND (' . $fieldName . ' >= (DATE_FORMAT(NOW(),"%Y-%m-01") - INTERVAL :months MONTH) AND ' . $fieldName . ' <= CURRENT_DATE)';
    }

    public function getMonthSql($fieldName, &$data, $month = null, $year = null) {
        $data['month'] = !empty($month) ? $month : date('m');
        $data['year'] = !empty($year) ? $year : date('Y');

        return ' MONTH(' . $fieldName . ') = :month AND YEAR(' . $fieldName . ') = :year';
    }

    public function getLastMonthsTableSql($months) {
        $start = new DateTime(date('Y-m-01', strtotime(date('Y-m-01') . ' -' . $months . ' months')));
        $end = new DateTime(date('Y-m-d'));
        $interval = DateInterval::createFromDateString('1 month');
        $period = new DatePeriod($start, $interval, $end);
        $result = '';
        foreach ($period as $dt) {
            if (!empty($result)) {
                $result .= ' UNION ';
            }
            $result .= ' SELECT STR_TO_DATE("' . $dt->format('Y-m-d') . '", "%Y-%m-%d") AS date';
        }

        return 'SELECT MONTH(months.date) AS month, YEAR(months.date) AS year FROM (' . $result . ') months';
    }

    public function getMonthsTableSql($year) {
        $start = new DateTime(date($year . '-01-01'));
        $end = new DateTime(date($year . '-12-31'));

        $interval = DateInterval::createFromDateString('1 month');
        $period = new DatePeriod($start, $interval, $end);
        $result = '';
        foreach ($period as $dt) {
            if (!empty($result)) {
                $result .= ' UNION ';
            }
            $result .= ' SELECT STR_TO_DATE("' . $dt->format('Y-m-d') . '", "%Y-%m-%d") AS date';
        }

        return 'SELECT MONTH(months.date) AS month, YEAR(months.date) AS year FROM (' . $result . ') months';
    }

    public function getDaysTableSql($dateStart, $dateEnd) {
        $start = new DateTime($dateStart);
        $end = (new DateTime($dateEnd))->add(new DateInterval('P1D'));

        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod($start, $interval, $end);
        $result = '';
        foreach ($period as $dt) {
            if (!empty($result)) {
                $result .= ' UNION ';
            }
            $result .= ' SELECT STR_TO_DATE("' . $dt->format('Y-m-d') . '", "%Y-%m-%d") AS date';
        }

        return 'SELECT days.date AS date FROM (' . $result . ') days';
    }

    public function getJsonFieldsValue($record, $jsonFields) {
        foreach ($jsonFields as $jsonField) {
            $jsonValue = !empty($record[$jsonField]) ? json_decode($record[$jsonField], true) : null;
            if (!empty($jsonValue) && is_array($jsonValue)) {
                $record[$jsonField] = $jsonValue;
            }
        }

        return $record;
    }

}
