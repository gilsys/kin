<?php

declare(strict_types=1);

namespace App\Service;

use App\Constant\StatusEvent;
use App\Constant\UserStatus;
use App\Dao\UserDAO;
use App\Util\CommonUtils;
use Blocktrail\CryptoJSAES\CryptoJSAES;
use Exception;

class AuthService extends BaseService {

    private $params;
    private $session;
    private $flash;
    private $renderer;

    public function __construct($pdo = null, $session = null, $params = null, $flash = null, $renderer = null) {
        parent::__construct($pdo);
        $this->session = $session;
        $this->params = $params;
        $this->flash = $flash;
        $this->renderer = $renderer;
    }

    /**
     * Recarga el usuario de la base de datos a sessión
     */
    public function reload($user = null) {
        $userDAO = new UserDAO($this->pdo);
        if (empty($user)) {
            $user = $userDAO->getById($this->session['user']['id']);
            $this->session['user'] = $user;
        }
    }

    public function checkCurrentPassword($userId, $password) {
        $userDAO = new UserDAO($this->pdo);
        $user = $userDAO->getById($userId, false);
        return $user['password'] == CommonUtils::getPasswordEncrypted($password, $userId);
    }

    /**
     * Identifica al usuario
     * @param type $login
     * @param type $password
     * @param type $rememberCookie
     * @param type $profiles
     * @return boolean
     * @throws Exception
     */
    public function login($login, $password, $rememberCookie = null, $profiles = null) {
        $userDAO = new UserDAO($this->pdo);

        $user = $userDAO->getByLogin($login);

        try {
            if (empty($user)) {
                throw new Exception(__('service.auth.user_not_valid'));
            }

            if ($user['user_status_id'] == UserStatus::Disabled) {
                throw new Exception(__('service.auth.user_locked'));
            }


            if ($user['password'] == CommonUtils::getPasswordEncrypted($password, $user['id'])) {

                if (!empty($profiles) && !in_array($user['user_profile_id'], $profiles)) {
                    throw new Exception(__('service.auth.insufficient_permissions'));
                }

                // Update "intentos_realizados" and "fecha ultimo acceso"
                LogService::saveAuth($this, 'app.log.action.auth.success', $user['id']);
                $userDAO->loginSuccess($user['id']);

                if (!empty($rememberCookie)) {
                    $this->setRememberCookie($user);
                } else {
                    $this->deleteRememberCookie();
                }
            } else if ($user['failed_logins'] + 1 >= intval($this->params->getParam('LOGIN.MAX_ERRORS'))) {
                LogService::saveAuth($this, 'app.log.action.auth.user_locked', $user['id']);
                $userDAO->lockUser($user['id']);
                throw new Exception(__('service.auth.user_locked'));
            } else {
                LogService::saveAuth($this, 'app.log.action.auth.login_failed', $user['id']);
                $userDAO->loginFailed($user['id']);
                throw new Exception(__('service.auth.user_not_valid'));
            }
        } catch (Exception $e) {
            $this->flash->addMessage('danger', $e->getMessage());
            return false;
        }
        $this->session['user'] = $user;
        $this->reload($user);
        return true;
    }

    /**
     * Asigna la cookie de larga duración de sesión
     * @param type $user
     */
    public function setRememberCookie($user) {
        setcookie("remember_login", $this->generateEncryptedUserToken($user), time() + (10 * 365 * 24 * 60 * 60), '/');
    }

    public function generateEncryptedUserToken($user, $caducity = false) {
        if ($caducity) {
            return md5(PASSWORD_SALT . $user['id'] . time()) . ':' . $user['token'] . ':' . time();
        } else {
            return md5(PASSWORD_SALT . $user['id']) . ':' . $user['token'];
        }
    }

    public function getUserFromEncryptedUserToken($encryptedUserToken, $caducityMinutes = null, $validUserStatus = [UserStatus::Validated]) {
        $exploded = explode(":", $encryptedUserToken);
        $userIdMd5Salted = $exploded[0];
        $token = $exploded[1];

        $userDAO = new UserDAO($this->pdo);
        $user = $userDAO->getByToken($token);

        if (empty($user)) {
            throw new Exception(__('service.auth.token_not_valid'));
        }

        // Si se usa caducidad
        if (count($exploded) == 3) {
            $time = $exploded[2];
            $expectedUserIdMd5Salted = md5(PASSWORD_SALT . $user['id'] . $time);
            if (!empty($caducityMinutes) && ($time + ($caducityMinutes * 60)) < time()) {
                // Token caducado
                throw new Exception(__('service.auth.token_timed_out'));
            }
        } else {
            $expectedUserIdMd5Salted = md5(PASSWORD_SALT . $user['id']);
        }

        if ($userIdMd5Salted == $expectedUserIdMd5Salted && in_array($user['user_status_id'], $validUserStatus)) {
            return $user;
        }
        throw new Exception(__('service.auth.token_not_valid'));
    }

    /**
     * Carga la cookie de larga duración de sesión
     */
    public function checkRememberCookie() {
        $session = $this->session;
        if (empty($this->session['user'])) {
            if (!empty($_COOKIE['remember_login'])) {

                try {
                    $user = $this->getUserFromEncryptedUserToken($_COOKIE['remember_login']);
                    if (!empty($user)) {
                        $this->session['user'] = $user;
                    }
                } catch (Exception $e) {
                    return false;
                }
            }
        }
    }

    /**
     * Realiza un autologin a través de un token
     */
    public function checkAutologinToken($token) {
        $session = $this->session;
        if (empty($this->session['user'])) {
            if (!empty($token)) {

                try {
                    $user = $this->getUserFromEncryptedUserToken($token);
                    if (!empty($user)) {
                        $this->session['user'] = $user;
                    }
                } catch (Exception $e) {
                    return false;
                }
            }
        }
    }

    /**
     * Elimina la cookie de larga duración de sesión
     */
    public function deleteRememberCookie() {
        if (isset($_COOKIE['remember_login'])) {
            unset($_COOKIE['remember_login']);
            setcookie('remember_login', '', time() - 3600, '/');
        }
    }

    public function logout($destroySession = true) {
        $this->deleteRememberCookie();

        if ($destroySession) {
            session_destroy();
        } else {
            foreach (array_keys($_SESSION) as $key) {
                unset($_SESSION[$key]);
            }
        }
    }

    /**
     * De forma opcional se pueden enviar parámetros para que sean encriptados
     * @param type $user
     * @param type $params
     * @return type
     */
    public function generateUniqueToken($params = null) {
        $h = gmdate('U');
        $t = md5(PASSWORD_SALT . $h);
        $out = ['h' => $h, 't' => $t];

        if (!empty($params)) {
            $encrypted = CryptoJSAES::encrypt(serialize($params), PASSWORD_SALT);
            $out['p'] = CommonUtils::base64urlEncode($encrypted);
        }

        return $out;
    }

    public function checkUniqueToken($h, $t, $salt = null, $tokenTimeout = 60) {
        if (empty($salt)) {
            $salt = PASSWORD_SALT;
        }
        $expectedHash = md5($salt . $h);
        if ($expectedHash != $t) {
            return false;
        }
        // Timeout de vida del token
        if ($tokenTimeout > 0 && gmdate('U') - $h > $tokenTimeout) {
            return false;
        }
        return true;
    }

    public function decodeUniqueToken($h, $t, $p, $tokenTimeout = 60) {
        if (!$this->checkUniqueToken($h, $t, null, $tokenTimeout)) {
            return false;
        }

        $functionParams = [];

        if (!empty($p)) {
            $decrypted = CryptoJSAES::decrypt(CommonUtils::base64urlDecode($p), PASSWORD_SALT);
            $functionParams = unserialize($decrypted);
        }
        return $functionParams;
    }

    /**
     * Identifica al usuario directamente a través de su ID
     * @param type id
     * @return boolean
     * @throws Exception
     */
    public function authByUserId($userId) {
        $userDAO = new UserDAO($this->pdo);
        $user = $userDAO->getById($userId);

        try {
            if (empty($user)) {
                throw new Exception(__('service.auth.user_not_valid'));
            }

            if ($user['user_status_id'] == UserStatus::Disabled) {
                throw new Exception(__('service.auth.user_locked'));
            }

            // Update "intentos_realizados" and "fecha ultimo acceso"
            $userDAO->loginSuccess($user['id']);
            $this->reload($user);
        } catch (Exception $e) {
            $this->flash->addMessage('danger', $e->getMessage());
            return false;
        }
        $this->session['user'] = $user;
        return true;
    }

    public function sendEmailPassword($user) {
        $token = $this->generateEncryptedUserToken($user, true);
        $url = $this->params->getParam('SITE.URL') . '/app/public/enter_password/' . $token;

        $data = [
            'user' => $user,
            'url' => [
                'url' => $url,
                'caducity' => round(intval($this->params->getParam('PASSWORD_TOKEN_CADUCITY')) / 60)
            ]
        ];

        $noticeService = new NoticeService($this->pdo, $this->session, $this->params, $this->renderer);
        $noticeService->sendEmail(StatusEvent::EmailRegistration, $user['email'], $data);
        LogService::saveAuth($this, 'app.log.action.user.email_password_sent', $user['id']);

        return true;
    }

}
