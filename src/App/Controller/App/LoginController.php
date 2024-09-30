<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Controller\App\BaseController;
use App\Dao\UserDAO;
use App\Exception\AuthException;
use App\Service\AuthService;
use App\Service\LogService;
use App\Service\PinService;
use App\Service\RecaptchaService;
use App\Util\CommonUtils;
use App\Util\RequestUtils;
use App\Util\ResponseUtils;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class LoginController extends BaseController {

    public function getDAO() {
    }

    public function getNameForLogs($entity) {
    }

    /**
     * Página de login
     */
    public function form(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        if (!empty($this->get('session')['user'])) {
            return $response->withStatus(302)->withHeader('Location', '/app/index');
        }
        $data['title'] = __('app.controller.login.title');
        $data['messages'] = $this->get('flash')->getMessages();

        // Define la vista a utilizar
        $data['view'] = 'login/form';

        // Javascripts a incluir
        $data['js'] = ['/js/project/login.form.js'];
        $data['css'] = ['/css/project/login.css'];
        return $this->get('renderer')->render($response, "login.phtml", $data);
    }

    /**
     * Acción de login
     */
    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $authService = new AuthService($this->get('pdo'), $this->get('session'), $this->get('params'), $this->get('flash'));
        $login = RequestUtils::getParam($request, 'login', 'POST');
        $password = RequestUtils::getParam($request, 'password', 'POST');

        $result = $authService->login($login, $password);
        return $response->withStatus(302)->withHeader('Location', '/app/public/login');
    }

    /**
     * Enviar pin para el cambio de contraseña
     */
    public function forgotLogin(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $formData = CommonUtils::getSanitizedData($request);

        $recaptchaSecretKey = $this->get('params')->getParam('RECAPTCHA_SECRET_KEY');
        if (!empty($recaptchaSecretKey)) {
            $recaptchaService = new RecaptchaService($recaptchaSecretKey);
            if (!$recaptchaService->check($formData['g-recaptcha-response'])) {
                return ResponseUtils::withJson($response, ['error' => __("controller.login.recaptcha_not_valid")]);
            }
        }

        $login = $formData['login'];
        $this->get('logger')->addInfo("Request change password for user with login $login");

        $userDAO = new UserDAO($this->get('pdo'));
        $user = $userDAO->getByLogin($login);
        if (empty($user)) {
            return ResponseUtils::withJson($response, ['error' => __("controller.login.user_not_exists")]);
        }

        try {
            $this->get('pdo')->beginTransaction();
            $pinService = new PinService($this->get('pdo'), $this->get('session'), $this->get('params'), $this->get('renderer'));
            $pinService->sendEmail($user['id']);

            LogService::saveAuth($this, 'app.log.action.forgot_password.sent', $user['id']);
            $this->get('pdo')->commit();
        } catch (Exception $e) {
            $this->get('pdo')->rollback();
            $this->get('logger')->addError($e);
            return ResponseUtils::withJson($response, ['error' => __("controller.login.error.send_pin_email")]);
        }

        return ResponseUtils::withJson($response, ['success' => 1]);
    }

    /**
     * Comprobar pin para el cambio de contraseña
     */
    public function forgotPin(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $formData = CommonUtils::getSanitizedData($request);

        $login = $formData['login'];
        $this->get('logger')->addInfo("Check pin for user with login $login");

        $userDAO = new UserDAO($this->get('pdo'));
        $user = $userDAO->getByLogin($login);
        if (empty($user)) {
            throw new AuthException();
        }

        $pinService = new PinService($this->get('pdo'), $this->get('session'), $this->get('params'));
        if (!$pinService->check(strtoupper($formData['pin']), $user['id'])) {
            LogService::saveAuth($this, 'app.log.action.forgot_password.pin_invalid', $user['id']);
            return ResponseUtils::withJson($response, ['error' => __("app.controller.common.pin_not_valid")]);
        }

        return ResponseUtils::withJson($response, ['success' => 1]);
    }

    /**
     * Cambiar la contraseña
     */
    public function forgotPassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $formData = CommonUtils::getSanitizedData($request);

        $login = $formData['login'];
        $this->get('logger')->addInfo("Change password for user with login $login");

        $userDAO = new UserDAO($this->get('pdo'));
        $user = $userDAO->getByLogin($login);
        if (empty($user)) {
            throw new AuthException();
        }

        $pinService = new PinService($this->get('pdo'), $this->get('session'), $this->get('params'));
        if (!$pinService->check(strtoupper($formData['pin']), $user['id'])) {
            LogService::saveAuth($this, 'app.log.action.forgot_password.pin_invalid', $user['id']);
            return ResponseUtils::withJson($response, ['error' => __("app.controller.common.pin_not_valid")]);
        }

        // Verifica los requisitos de los passwords
        if (!empty($this->get('params')->getParam('PASSWORD_COMPLEX')) && !CommonUtils::checkPasswordRequirements($formData['password'])) {
            return ResponseUtils::withJson($response, ['error' => __('app.controller.login.error.password')]);
        }

        LogService::saveAuth($this, 'app.log.action.forgot_password.success', $user['id']);

        $userDAO->updatePassword($user['id'], $formData['password']);
        $userDAO->updateSingleFieldEncryptedJSON($user['id'], 'personal_information', 'pin', '');

        $this->get('flash')->addMessage('success', __("controller.login.change_password_ok"));
        return ResponseUtils::withJson($response, ['success' => 1]);
    }

    /**
     * Página para introducir la contraseña
     */
    public function enterPassword(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        if (empty($args['token'])) {
            $this->get('flash')->addMessage('danger', __('controller.login.url_not_valid'));
            return $response->withStatus(302)->withHeader('Location', '/');
        }

        $authService = new AuthService($this->get('pdo'), $this->get('session'));
        if (!empty($this->get('session')['user'])) {
            $authService->logout();
            return $response->withStatus(302)->withHeader('Location', '/app/public/enter_password/' . $args['token']);
        }

        try {
            $user = $authService->getUserFromEncryptedUserToken($args['token'], intval($this->get('params')->getParam('PASSWORD_TOKEN_CADUCITY')));
        } catch (Exception $e) {
            $this->get('flash')->addMessage('danger', $e->getMessage());
            return $response->withStatus(302)->withHeader('Location', '/');
        }

        $data['data']['token'] = $args['token'];

        $data['title'] = __('app.controller.login.title');
        $data['messages'] = $this->get('flash')->getMessages();

        // Define la vista a utilizar
        $data['view'] = 'login/enter_password';

        // Javascripts a incluir
        $data['js'] = ['/js/project/login.enter_password.form.js'];
        $data['css'] = ['/css/project/login.css'];
        return $this->get('renderer')->render($response, "login.phtml", $data);
    }

    /**
     * Introducir la contraseña
     */
    public function enterPasswordPost(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $formData = CommonUtils::getSanitizedData($request);

        if (empty($formData['token'])) {
            throw new AuthException();
            return $response->withStatus(302)->withHeader('Location', '/');
        }

        try {
            $authService = new AuthService($this->get('pdo'), $this->get('session'));
            $user = $authService->getUserFromEncryptedUserToken($formData['token'], intval($this->get('params')->getParam('PASSWORD_TOKEN_CADUCITY')));

            $this->get('logger')->addInfo("Enter password for user with login " . $user['nickname']);

            // Verifica los requisitos de los passwords
            if (!empty($this->get('params')->getParam('PASSWORD_COMPLEX')) && !CommonUtils::checkPasswordRequirements($formData['password'])) {
                $this->get('flash')->addMessage('danger', __('app.controller.login.error.password'));
                return $response->withStatus(302)->withHeader('Location', '/app/public/enter_password/' . $formData['token']);
            }

            $userDAO = new UserDAO($this->get('pdo'));
            $userDAO->updatePassword($user['id'], $formData['password']);
            $userDAO->updateWithRandomToken($user['id']);

            LogService::saveAuth($this, 'app.log.action.enter_password.success', $user['id']);
        } catch (Exception $e) {
            $this->get('flash')->addMessage('danger', $e->getMessage());
            return $response->withStatus(302)->withHeader('Location', '/');
        }

        $this->get('flash')->addMessage('success', __("controller.login.enter_password_ok"));
        return $response->withStatus(302)->withHeader('Location', '/');
    }

    /**
     * Cierre de sesión
     */
    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $authService = new AuthService();
        if (!empty($this->get('session')['user'])) {
            LogService::saveAuth($this, 'app.log.action.auth.logout', $this->get('session')['user']['id']);
        }
        $authService->logout();
        return $response->withStatus(302)->withHeader('Location', '/app/public/login');
    }

}
