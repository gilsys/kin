<?php

declare(strict_types=1);

namespace App\Dao;

use App\Constant\UserStatus;
use App\Util\CommonUtils;

class UserDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'st_user');
    }

    public function getById($id, $extractPersonalInformation = true) {
        $sql = "SELECT u.*, AES_DECRYPT(u.personal_information, :AES_KEY) AS personal_information, p.color as user_profile_color 
                FROM " . $this->table . " u
                INNER JOIN st_user_profile p on p.id = u.user_profile_id
                WHERE u.id = :id";

        $result = $this->fetchRecord($sql, ['id' => $id, 'AES_KEY' => AES_KEY]);

        if (empty($result)) {
            return $result;
        }
        if ($extractPersonalInformation) {
            return $this->extractPersonalInformation($result);
        }
        return $result;
    }

    public function getForSelectByProfileWithStatus($userProfileId, $onlyEnabled = false, $userIds = null) {
        $where = "";
        $data = ['userProfileId' => $userProfileId, 'AES_KEY' => AES_KEY];
        if ($onlyEnabled) {
            $where .= " AND (user_status_id = :user_status_id ";
            $data['user_status_id'] = UserStatus::Validated;

            if (!empty($userIds)) {
                $userIds = is_array($userIds) ? $userIds : [$userIds];
                $where .= " OR FIND_IN_SET(u.id, :user_ids) ";
                $data['user_ids'] = implode(',', $userIds);
            }

            $where .= ") ";
        }

        $sql = 'SELECT u.id, u.user_status_id, u.date_updated,
                JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(u.personal_information, :AES_KEY), "$.name")) as name,
                JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(u.personal_information, :AES_KEY), "$.surnames")) as surnames
                FROM ' . $this->table . ' u
                WHERE user_profile_id = :userProfileId ' . $where . '
                GROUP BY u.id
                ORDER BY name ASC, surnames ASC';
        return $this->fetchAll($sql, $data);
    }

    public function getFullname($id) {
        $sql = 'SELECT 
                TRIM(CONCAT(JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(personal_information, :AES_KEY), "$.name")), " " , JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(personal_information, :AES_KEY), "$.surnames")))) as fullname
                FROM ' . $this->table . '
                WHERE id = :id';
        return $this->fetchOneField($sql, ['id' => $id, 'AES_KEY' => AES_KEY]);
    }

    public function getForSelectFullname() {
        $sql = 'SELECT 
                id,
                TRIM(CONCAT(JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(personal_information, :AES_KEY), "$.name")), " " , JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(personal_information, :AES_KEY), "$.surnames")))) as fullname
                FROM ' . $this->table;
        return $this->fetchAll($sql, ['AES_KEY' => AES_KEY]);
    }

    public function getFullById($id) {
        $sql = 'SELECT u.*, 
                AES_DECRYPT(u.personal_information, :AES_KEY) AS personal_information, 
                p.name as user_profile, 
                p.color as user_profile_color, 
                us.color as user_status_color,
                "en" AS language
                FROM ' . $this->table . ' u 
                LEFT JOIN st_user_profile p on p.id = u.user_profile_id
                LEFT JOIN st_user_status us on us.id = u.user_status_id
                WHERE u.id = :id';

        $result = $this->fetchRecord($sql, ['id' => $id, 'AES_KEY' => AES_KEY]);
        if (empty($result)) {
            return $result;
        }
        return $this->extractPersonalInformation($result);
    }

    public function getRemoteDatatable($profilesToShow) {
        // Columnas a tratar en el datatable
        $columns = [
            ['db' => 'id', 'dt' => 'id'],
            ['db' => 'profile', 'dt' => 'profile'],
            ['db' => 'status', 'dt' => 'status'],
            ['db' => 'email', 'dt' => 'email'],
            ['db' => 'name', 'dt' => 'name'],
            ['db' => 'nickname', 'dt' => 'nickname'],
            ['db' => 'surnames', 'dt' => 'surnames'],
            ['db' => 'phone', 'dt' => 'phone'],
            ['db' => 'address', 'dt' => 'address'],
            // Los campos de fecha deben ser formateados
            [
                'db' => 'date_created',
                'dt' => 'date_created',
                'date' => true,
                'formatter' => function ($d, $row) {
                    return CommonUtils::convertDate($d);
                }
            ],
            [
                'db' => 'last_login',
                'dt' => 'last_login',
                'date' => true,
                'formatter' => function ($d, $row) {
                    return CommonUtils::convertDate($d);
                }
            ],
            ['db' => 'user_profile_color', 'dt' => 'user_profile_color', 'exact' => true],
            ['db' => 'user_status_color', 'dt' => 'user_status_color', 'exact' => true],
            ['db' => 'user_profile_id', 'dt' => 'user_profile_id', 'exact' => true],
            ['db' => 'user_status_id', 'dt' => 'user_status_id', 'exact' => true],
            ['db' => 'market_id', 'dt' => 'market_id', 'exact' => true],
            ['db' => 'market_name', 'dt' => 'market_name'],
            ['db' => 'market_color', 'dt' => 'market_color', 'exact' => true],
            ['db' => 'date_updated', 'dt' => 'date_updated'],
            ['db' => 'wp_id', 'dt' => 'wp_id', 'exact' => true],

        ];

        // Trabajamos con campos JSON, extraemos la información directamente y eliminamos las comillas
        // Si no se ha filtrado por estado borrado, no mostrar borrados
        $showDeleted = !empty($_POST['columns'][12]['search']['value']) && $_POST['columns'][12]['search']['value'] == UserStatus::Deleted;
        $userStatusSql = 'WHERE u.user_status_id ' . ($showDeleted ? '=' : '!=') . ' "' . UserStatus::Deleted . '"';
        $table = '(
    SELECT 
      u.id,
      p.name as profile,
      us.name as status,
      JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(u.personal_information, "' . AES_KEY . '"), "$.email")) as email,
      JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(u.personal_information, "' . AES_KEY . '"), "$.name")) as name,
      JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(u.personal_information, "' . AES_KEY . '"), "$.surnames")) as surnames,
      u.nickname,
      JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(u.personal_information, "' . AES_KEY . '"), "$.phone1")) as phone,
      JSON_UNQUOTE(JSON_EXTRACT(AES_DECRYPT(u.personal_information, "' . AES_KEY . '"), "$.address")) as address,
      u.date_created,
      u.date_updated,
      u.last_login,
      u.user_profile_id,
      u.user_status_id,
      m.name as market_name,
      m.id as market_id,
      m.color as market_color,
      p.color as user_profile_color,
      us.color as user_status_color,
      u.wp_id
    FROM ' . $this->table . ' u
    INNER JOIN st_user_profile p ON u.user_profile_id = p.id and p.id IN (\'' . implode('\',\'', $profilesToShow) . '\')
    INNER JOIN st_user_status us ON u.user_status_id = us.id
    LEFT JOIN st_market m ON u.market_id = m.id
    ' . $userStatusSql . '
    GROUP BY u.id
    ) temp';

        return $this->datatablesSimple($table, 'id', $columns);
    }

    public function save($data) {
        $data['password'] = CommonUtils::getPasswordEncrypted($data['password'], $this->getNextAutoincrement());
        $query = 'INSERT INTO ' . $this->table . ' (nickname, personal_information, password, user_status_id, user_profile_id, color, market_id) '
            . 'VALUES (:nickname, AES_ENCRYPT(:personal_information, :AES_KEY), :password, :user_status_id, :user_profile_id, :color, :market_id)';

        $data['AES_KEY'] = AES_KEY;
        $this->query($query, $data);

        $id = $this->getLastInsertId();
        $this->updateWithRandomToken($id);
        return $id;
    }

    public function updatePassword($id, $password) {
        $password = CommonUtils::getPasswordEncrypted($password, $id);
        $query = 'UPDATE ' . $this->table . ' SET password = :password WHERE id = :id';
        $this->query($query, compact('id', 'password'));
    }

    public function checkLOPD($id) {
        $query = 'UPDATE ' . $this->table . ' SET date_lopd_accepted = NOW() WHERE id = :id';
        $this->query($query, compact('id'));
    }

    public function updateAuth($id, $data) {
        $password = CommonUtils::getPasswordEncrypted($data['password'], $id);
        $nickname = $data['nickname'];
        $query = 'UPDATE ' . $this->table . ' SET password = :password, nickname = :nickname WHERE id = :id';
        $this->query($query, compact('id', 'password', 'nickname'));
    }

    public function updateProfile($data) {
        $sqlExtra = '';
        if (!empty($data['password'])) {
            $data['password'] = CommonUtils::getPasswordEncrypted($data['password'], $data['id']);
            $sqlExtra = ', password = :password';
        } else {
            unset($data['password']);
        }

        $query = 'UPDATE ' . $this->table . ' SET '
            . ' personal_information = AES_ENCRYPT(:personal_information, :AES_KEY) ' . $sqlExtra
            . ' WHERE id = :id';

        $data['AES_KEY'] = AES_KEY;
        $this->query($query, $data);
    }

    public function update($data) {
        $sqlExtra = '';
        if (!empty($data['password'])) {
            $data['password'] = CommonUtils::getPasswordEncrypted($data['password'], $data['id']);
            $sqlExtra .= ', password = :password';
        } else {
            unset($data['password']);
        }

        if (!empty($data['color'])) {
            $sqlExtra .= ', color = :color';
        }

        $query = 'UPDATE ' . $this->table . ' SET 
            personal_information = AES_ENCRYPT(:personal_information, :AES_KEY), 
            user_profile_id = :user_profile_id,
            market_id = :market_id, 
            user_status_id = :user_status_id' . $sqlExtra . '
            WHERE id = :id';

        $data['AES_KEY'] = AES_KEY;
        $this->query($query, $data);
    }

    /**
     * La información personal se incluye en un campo "personal_information" en formato JSON
     * Se extrae y se añade como campos al array.
     * @param type $result
     * @return type
     */
    private function extractPersonalInformation($result) {
        $result = array_merge($result, json_decode($result['personal_information'], true));
        unset($result['personal_information']);
        return $result;
    }

    public function getByEmail($email, $mergePersonalInformation = true) {
        $sql = "SELECT u.*, AES_DECRYPT(u.personal_information, :AES_KEY) AS personal_information, p.color as user_profile_color 
                FROM " . $this->table . " u
                INNER JOIN st_user_profile p on p.id = u.user_profile_id
                WHERE JSON_EXTRACT(AES_DECRYPT(u.personal_information, :AES_KEY), '$.email') = :email";
        $result = $this->fetchRecord($sql, ['email' => $email, 'AES_KEY' => AES_KEY]);
        if (empty($result)) {
            return $result;
        }
        if ($mergePersonalInformation) {
            return $this->extractPersonalInformation($result);
        } else {
            return $result;
        }
    }

    public function getByLogin($login, $mergePersonalInformation = true) {
        $sql = "SELECT u.*, AES_DECRYPT(u.personal_information, :AES_KEY) AS personal_information, p.color as user_profile_color 
                FROM " . $this->table . " u
                INNER JOIN st_user_profile p on p.id = u.user_profile_id
                WHERE u.nickname = :login AND u.user_status_id != :userStatusDeleted";
        $result = $this->fetchRecord($sql, ['login' => $login, 'AES_KEY' => AES_KEY, 'userStatusDeleted' => UserStatus::Deleted]);
        if (empty($result)) {
            return $result;
        }
        if ($mergePersonalInformation) {
            return $this->extractPersonalInformation($result);
        } else {
            return $result;
        }
    }

    public function getByToken($token) {
        $sql = "SELECT u.*, AES_DECRYPT(u.personal_information, :AES_KEY) AS personal_information, p.color as user_profile_color 
                FROM " . $this->table . " u
                INNER JOIN st_user_profile p on p.id = u.user_profile_id
                WHERE token = :token";
        $result = $this->fetchRecord($sql, ['token' => $token, 'AES_KEY' => AES_KEY]);
        if (empty($result)) {
            return $result;
        }
        return $this->extractPersonalInformation($result);
    }

    public function existsEmail($email, $userId = null) {
        $data = ['email' => $email];
        $sqlUserId = '';
        if (!empty($userId)) {
            $sqlUserId = " AND id != :id";
            $data['id'] = $userId;
        }

        $sql = "SELECT id FROM " . $this->table . " WHERE JSON_EXTRACT(AES_DECRYPT(personal_information, :AES_KEY), '$.email') = :email" . $sqlUserId;
        $data['AES_KEY'] = AES_KEY;
        $result = $this->fetchRecord($sql, $data);
        return !empty($result);
    }

    public function existsNickname($nickname, $userId = null) {
        $data = compact('nickname');
        $sqlUserId = '';
        if (!empty($userId)) {
            $sqlUserId = " AND id != :id";
            $data['id'] = $userId;
        }

        $sql = "SELECT id FROM " . $this->table . " WHERE nickname = :nickname" . $sqlUserId;
        $result = $this->fetchRecord($sql, $data);
        return !empty($result);
    }

    public function loginSuccess($id) {
        $this->query("update " . $this->table . " set last_login = NOW(), failed_logins=0 WHERE id = :id", ['id' => $id]);
    }

    public function lockUser($id) {
        $this->query("update " . $this->table . " set failed_logins=failed_logins+1, user_status_id=:locked WHERE id = :id", ['id' => $id, 'locked' => UserStatus::Disabled]);
    }

    public function loginFailed($id) {
        $this->query("update " . $this->table . " set failed_logins=failed_logins+1 WHERE id = :id", ['id' => $id]);
    }

    public function updateWithRandomToken($id) {
        do {
            $token = CommonUtils::generateRandomToken($id);
        } while (!empty($this->getByToken($token)));
        $this->updateSingleField($id, 'token', $token);
    }

    public function updateWithRandomChangePasswordToken($id) {
        do {
            $token = CommonUtils::generateRandomToken($id);
        } while (!empty($this->getByChangePasswordToken($token)));
        $this->query("update " . $this->table . " set change_password_token=:token, change_password_time=:time WHERE id = :id", ['id' => $id, 'token' => $token, 'time' => time()]);
        return $token;
    }

    public function getByChangePasswordToken($token) {
        $sql = "SELECT * FROM " . $this->table . " WHERE change_password_token = :token";
        $result = $this->fetchRecord($sql, ['token' => $token]);
        if (empty($result)) {
            return $result;
        }
        return $this->extractPersonalInformation($result);
    }

    public function getByPin($pin, $userId = null) {
        $data = ['pin' => $pin, 'AES_KEY' => AES_KEY];
        $sqlUserId = '';
        if (!empty($userId)) {
            $sqlUserId = " AND id = :id";
            $data['id'] = $userId;
        }

        $sql = "SELECT *, AES_DECRYPT(personal_information, :AES_KEY) AS personal_information FROM " . $this->table . " WHERE JSON_EXTRACT(AES_DECRYPT(personal_information, :AES_KEY), '$.pin.pin') = :pin" . $sqlUserId;
        $result = $this->fetchRecord($sql, $data);
        if (empty($result)) {
            return $result;
        }
        return $this->extractPersonalInformation($result);
    }

    public function checkCurrentPassword($id, $currentPassword) {
        $password = $this->getSingleField($id, 'password');
        return ($password == CommonUtils::getPasswordEncrypted($currentPassword, $id));
    }
}
