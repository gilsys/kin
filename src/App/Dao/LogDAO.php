<?php

declare(strict_types=1);

namespace App\Dao;

use App\Util\CommonUtils;
use Exception;
use function __;

class LogDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'st_log');
    }

    public function getUserAuthLogsRemoteDatatable($userId, $lang) {
        if (!is_numeric($userId) || strlen($lang) != 2) {
            throw new Exception(__('app.error.invalid_parameters'));
        }


        // Columnas a tratar en el datatable
        $columns = [
            ['db' => 'translation_id', 'dt' => 'translation_id'],
            ['db' => 'message', 'dt' => 'message'],
            ['db' => 'ip', 'dt' => 'ip'],
            ['db' => 'device', 'dt' => 'device'],
            ['db' => 'user_agent', 'dt' => 'user_agent'],
            ['db' => 'variables', 'dt' => 'variables'],
            // Los campos de fecha deben ser formateados
            ['db' => 'date_created', 'dt' => 'date_created', 'date' => true,
                'formatter' => function ($d, $row) {
                    return CommonUtils::convertDate($d);
                }
            ]
        ];

        $translationDAO = new TranslationDAO($this->connection);

        $table = '(
            SELECT
              l.id,
              l.translation_id,
              l.variables,
              t.' . $lang . ' as message,
              l.date_created,
              JSON_UNQUOTE(JSON_EXTRACT(variables, "$.ip")) as ip,
              JSON_UNQUOTE(JSON_EXTRACT(variables, "$.device")) as device,
              JSON_UNQUOTE(JSON_EXTRACT(variables, "$.userAgent")) as user_agent
            FROM ' . $this->table . ' l
            INNER JOIN (' . $translationDAO->getAllTranslationsSql() . ') t on t.id = l.translation_id
            WHERE `table` = "auth" AND user_id = "' . $userId . '"
         ) temp';

        return $this->datatablesSimple($table, 'id', $columns);
    }

    public function getUserLogsRemoteDatatable($userId, $lang) {
        if (!is_numeric($userId) || strlen($lang) != 2) {
            throw new Exception(__('app.error.invalid_parameters'));
        }

        // Columnas a tratar en el datatable
        $columns = [
            ['db' => 'translation_id', 'dt' => 'translation_id'],
            ['db' => 'message', 'dt' => 'message',
                'formatter' => function ($d, $row) {
                    return __($row['translation_id'], json_decode($row['variables'], true));
                }
            ],
            ['db' => 'variables', 'dt' => 'variables'],
            // Los campos de fecha deben ser formateados
            ['db' => 'date_created', 'dt' => 'date_created', 'date' => true,
                'formatter' => function ($d, $row) {
                    return CommonUtils::convertDate($d);
                }
            ]
        ];

        $translationDAO = new TranslationDAO($this->connection);

        $table = '(
            SELECT 
              l.id,
              l.translation_id,
              t.' . $lang . ' as message,
              l.variables,
              l.date_created              
            FROM ' . $this->table . ' l
            INNER JOIN (' . $translationDAO->getAllTranslationsSql() . ') t on t.id = l.translation_id
            WHERE `table` <> "auth" AND user_id = "' . $userId . '"
         ) temp';

        return $this->datatablesSimple($table, 'id', $columns);
    }

    public function getLogsRemoteDatatable($table, $id, $lang) {
        if (!is_numeric($id) || strlen($lang) != 2) {
            throw new Exception(__('app.error.invalid_parameters'));
        }

        $data = ['AES_KEY' => AES_KEY];

        // Columnas a tratar en el datatable
        $columns = [
            ['db' => 'translation_id', 'dt' => 'translation_id'],
            ['db' => 'user_fullname', 'dt' => 'user_fullname'],
            ['db' => 'user_id', 'dt' => 'user_id'],
            ['db' => 'message', 'dt' => 'message',
                'formatter' => function ($d, $row) {
                    return __($row['translation_id'], json_decode($row['variables'], true));
                }
            ],
            ['db' => 'variables', 'dt' => 'variables'],
            // Los campos de fecha deben ser formateados
            ['db' => 'date_created', 'dt' => 'date_created', 'date' => true,
                'formatter' => function ($d, $row) {
                    return CommonUtils::convertDate($d);
                }
            ]
        ];

        $translationDAO = new TranslationDAO($this->connection);

        $table = '(
            SELECT 
              l.id,
              l.translation_id,
              TRIM(CONCAT(JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(u.personal_information, "' . AES_KEY . '"), "$.name")), " " , JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(u.personal_information, "' . AES_KEY . '"), "$.surnames")))) as user_fullname,
              l.user_id,
              t.' . $lang . ' as message,
              l.variables,
              l.date_created              
            FROM ' . $this->table . ' l
            INNER JOIN (' . $translationDAO->getAllTranslationsSql() . ') t on t.id = l.translation_id
            INNER JOIN st_user u on u.id = l.user_id
            WHERE l.table = "' . $table . '" AND l.table_id = ' . $id . '
         ) temp';

        return $this->datatablesSimple($table, 'id', $columns);
    }

    public function save($data) {
        $query = 'INSERT INTO `' . $this->table . '` (`user_id`, `translation_id`, `variables`, `table`, `table_id`) VALUES (:user_id, :translation_id, :variables, :table, :table_id)';
        $this->query($query, $data);
        return $this->getLastInsertId();
    }

}
