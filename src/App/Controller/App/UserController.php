<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Constant\App\MenuSection;
use App\Constant\StaticListTable;
use App\Constant\UserProfile;
use App\Constant\UserStatus;
use App\Controller\App\BaseController;
use App\Dao\ClientDAO;
use App\Dao\LogDAO;
use App\Dao\MarketDAO;
use App\Dao\StaticListDAO;
use App\Dao\StepDAO;
use App\Dao\UserDAO;
use App\Dao\UserProfileDAO;
use App\Dao\UserStatusDAO;
use App\Exception\AuthException;
use App\Exception\CustomException;
use App\Service\AuthService;
use App\Service\LogService;
use App\Util\CommonUtils;
use App\Util\FileUtils;
use App\Util\ResponseUtils;
use DateInterval;
use DatePeriod;
use DateTime;
use DateTimeZone;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use function mb_substr;

class UserController extends BaseController {

    const ENTITY_SINGULAR = 'user';
    const ENTITY_PLURAL = 'users';
    const MENU = MenuSection::MenuUsers;
    const MENU_DATA = MenuSection::MenuUserData;
    const MENU_LOGS = MenuSection::MenuUserLogs;

    public function getDAO() {
        return new UserDAO($this->get('pdo'));
    }

    public function getNameForLogs($id) {
        return $this->getDAO()->getFullname($id);
    }

    /**
     * Página de listado de clientes
     */
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $this->prepareList();

        // Carga selectores para filtros
        $userStatusDAO = new UserStatusDAO($this->get('pdo'));
        $marketDAO = new MarketDAO($this->get('pdo'));

        $data['data'] = [
            'userStatus' => $userStatusDAO->getForSelect('id', 'name', 'custom_order'),
            'markets' => $marketDAO->getForSelect(),
        ];

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    /**
     * Obtiene los datos para mostrar la datatable
     */
    public function datatable(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        return ResponseUtils::withJson($response, $this->getDAO()->getRemoteDatatable([UserProfile::Administrator, UserProfile::User]));
    }

    /**
     * Prepara el formulario de crear/editar usuario
     */
    public function form(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $data = $this->prepareForm($args);

        // Obtenemos los valores a mostrar en los desplegables
        $userProfileDAO = new UserProfileDAO($this->get('pdo'));
        $data['js'][] = '/js/project/user.header.js';

        $data['menu'] = static::MENU_DATA;
        $data['data']['userProfiles'] = $userProfileDAO->getAll('custom_order');

        $marketDAO = new MarketDAO($this->get('pdo'));
        $data['data']['markets'] = $marketDAO->getForSelect();


        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    /**
     * Página de listado de logs
     */
    public function logs(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $data['breadcumb'] = [__('app.user.logs')];
        $data['title'] = implode(' > ', $data['breadcumb']);

        // Indica elemento del menú principal activo
        $data['menu'] = static::MENU_LOGS;
        $data['entity'] = static::ENTITY_SINGULAR;
        $data['breadcumb'] = [__('app.users.title'), $data['title']];

        // Carga los flash messages para mostrarlos en pantalla
        $data['messages'] = $this->get('flash')->getMessages();

        // Define la vista a utilizar
        $data['view'] = 'user/logs';

        // Javascripts a incluir
        $data['js'] = ['/assets/plugins/custom/datatables/datatables.bundle.js', '/js/datatables.custom.js', '/js/project/user_logs.datatable.js', '/js/project/user.header.js'];
        $data['css'] = ['/assets/plugins/custom/datatables/datatables.bundle.css'];

        $data['data']['userId'] = $args['id'];
        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    /**
     * Obtiene los datos para mostrar la datatable de logs de autentificación
     */
    public function logsAuthDatatable(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $logDAO = new LogDAO($this->get('pdo'));
        return ResponseUtils::withJson($response, $logDAO->getUserAuthLogsRemoteDatatable($args['id'], $this->get('i18n')->getCurrentLang()));
    }

    /**
     * Obtiene los datos para mostrar la datatable de logs generales
     */
    public function logsDatatable(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $logDAO = new LogDAO($this->get('pdo'));
        return ResponseUtils::withJson($response, $logDAO->getUserLogsRemoteDatatable($args['id'], $this->get('i18n')->getCurrentLang()));
    }

    public function savePrevalidate($request, $response, $args) {
        $formData = parent::savePrevalidate($request, $response, $args);

        $userDAO = new UserDAO($this->get('pdo'));
        if (!$this->get('security')->isAdmin()) {
            if (empty($formData['id']) && $formData['user_profile_id'] != UserProfile::User) {
                throw new AuthException();
            } else if (!empty($formData['id']) && $userDAO->getSingleField($formData['id'], 'user_profile_id') != $formData['user_profile_id']) {
                throw new AuthException();
            }
        }
        return $formData;
    }

    public function savePreSave($request, $response, $args, &$formData) {
        $id = empty($formData['id']) ? null : $formData['id'];
        $userDAO = new UserDAO($this->get('pdo'));
        // Verifica que el usuario no esté desactivado
        if (!empty($id) && $userDAO->getSingleField($id, 'user_status_id') == UserStatus::Deleted) {
            throw new CustomException(__('app.error.save_deleted', [__('table.user_status.' . UserStatus::Deleted)]));
        }

        // Verifica que no exista otro usuario con el mismo nickname
        if (empty($id) && $userDAO->existsNickname($formData['nickname'], $id)) {
            throw new CustomException(__('app.error.save_deleted', [__('app.controller.user.error.nickname_exists')]));
        }



        // Verifica los requisitos de los passwords
        if ((empty($id) || !empty($formData['password'])) && empty($formData['send_email_password']) && !empty($this->get('params')->getParam('PASSWORD_COMPLEX')) && !CommonUtils::checkPasswordRequirements($formData['password'])) {
            throw new CustomException(__('app.error.save_deleted', [__('app.controller.user.error.password')]));
        }

        // Merge para no perder campos de personal_information no incluidos al formulario
        if (!empty($id)) {
            $oldUser = $userDAO->getById($id, false);
            $formData['personal_information'] = array_merge(json_decode($oldUser['personal_information'], true), $formData['personal_information']);
        }

        // Convierte los datos personales en JSON
        $formData['personal_information'] = json_encode($formData['personal_information']);
        $formData['user_status_id'] = empty($formData['user_status_id']) ? UserStatus::Disabled : $formData['user_status_id'];


        if ($formData['user_profile_id'] == UserProfile::Administrator) {
            $formData['market_id'] = null;
        }
    }

    public function savePersist($request, $response, $args, &$formData) {
        $isNew = empty($formData['id']);
        $sendEmailPassword = !empty($formData['send_email_password']);
        if ($sendEmailPassword) {
            unset($formData['send_email_password']);
            $formData['password'] = CommonUtils::generateRandomToken($formData['nickname']);
        }

        parent::savePersist($request, $response, $args, $formData);
        $userDAO = new UserDAO($this->get('pdo'));

        $authService = new AuthService($this->get('pdo'), $this->get('session'), $this->get('params'), $this->get('flash'), $this->get('renderer'));

        if ($isNew && $sendEmailPassword) {
            $authService->sendEmailPassword($userDAO->getById($formData['id']));
        }

        $directory = $this->get('params')->getParam('FOLDER_PRIVATE');
        $file = FileUtils::uploadFile($request, 'user', $directory, $formData['id'], 'avatar', false, true, null, true);

        if (!empty($file)) {
            $userDAO->updateSingleFieldEncryptedJSON($formData['id'], 'personal_information', 'avatar', $file);
        } else if (FileUtils::checkRemoveEmptyFile($request, 'avatar')) {
            $userDAO->updateSingleFieldEncryptedJSON($formData['id'], 'personal_information', 'avatar', '');
        }
        $userDAO->updateSingleField($formData['id'], 'color', $formData['color']);
        if ($formData['id'] == $this->get('session')['user']['id']) {
            $authService->reload();
        }
    }

    /**
     * Cambia el estado de un usuario
     */
    public function status(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $userDAO = new UserDAO($this->get('pdo'));

        $userDAO->updateSingleField($args['id'], 'user_status_id', $args['userStatusId']);

        // En caso de forzar que el usuario esté borrado, actualizar también la fecha de borrado (date_deleted), en cualquier otro estado, borrar la fecha
        $userDAO->updateSingleField($args['id'], 'date_deleted', $args['userStatusId'] == UserStatus::Deleted ? date('Y-m-d H:i:s') : null);

        LogService::save($this, 'app.log.action.user.status', [$userDAO->getFullname($args['id']), __('table.user_status.' . $args['userStatusId'])], 'st_user', $args['id']);
        return ResponseUtils::withJson($response, ['success' => 1]);
    }

    /**
     * Envía email para introducir la contraseña de un usuario nuevo
     */
    public function emailPassword(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $id = $args['id'];

        $this->get('logger')->addInfo("Send email for generate password for user " . $id);

        try {
            $this->get('pdo')->beginTransaction();
            $userDAO = new UserDAO($this->get('pdo'));
            $user = $userDAO->getById($id);

            // Verificar que el usuario no ha introducido la contraseña
            if (!empty($user['last_login'])) {
                $this->get('flash')->addMessage('danger', __('app.error.user_already_has_password'));
                return $response->withStatus(302)->withHeader('Location', '/app/' . static::ENTITY_SINGULAR . '/form/' . $id);
            }

            $authService = new AuthService($this->get('pdo'), $this->get('session'), $this->get('params'), $this->get('flash'), $this->get('renderer'));
            $authService->sendEmailPassword($user);
            $this->get('pdo')->commit();
        } catch (\Exception $e) {
            $this->get('pdo')->rollback();
            $this->get('logger')->addError($e);
            $this->get('flash')->addMessage('danger', __('app.error.email'));
            return $response->withStatus(302)->withHeader('Location', '/app/' . static::ENTITY_SINGULAR . '/form/' . $id);
        }
        $this->get('flash')->addMessage('success', __('app.controller.email_ok'));
        return $response->withStatus(302)->withHeader('Location', '/app/' . static::ENTITY_SINGULAR . '/form/' . $id);
    }

    /**
     * Guarda los datos del usuario
     */
    public function updateAuth(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $this->get('logger')->addInfo("Update auth");

        $formData = CommonUtils::getSanitizedData($request);
        $id = $formData['id'];

        try {
            $this->get('pdo')->beginTransaction();
            $userDAO = new UserDAO($this->get('pdo'));

            // Verifica que el usuario no esté desactivado
            if (!empty($id) && $userDAO->getSingleField($id, 'user_status_id') == UserStatus::Deleted) {
                $this->get('flash')->addMessage('danger', __('app.error.save_deleted', [__('table.user_status.' . UserStatus::Deleted)]));
                return $response->withStatus(302)->withHeader('Location', '/app/' . static::ENTITY_SINGULAR . '/form/' . $id);
            }

            // Verifica que no exista otro usuario con el mismo nickname
            if ($userDAO->existsNickname($formData['nickname'], $id)) {
                $this->get('flash')->addMessage('danger', __('app.controller.user.error.nickname_exists'));
                return $response->withStatus(302)->withHeader('Location', '/app/' . static::ENTITY_SINGULAR . '/form/' . $id);
            }

            // Verifica los requisitos de los passwords
            if (empty($id) || empty($formData['password']) || (!empty($this->get('params')->getParam('PASSWORD_COMPLEX')) && !CommonUtils::checkPasswordRequirements($formData['password']))) {
                $this->get('flash')->addMessage('danger', __('app.controller.user.error.password'));
                return $response->withStatus(302)->withHeader('Location', '/app/' . static::ENTITY_SINGULAR . '/form/' . $id);
            }

            $userDAO->updateAuth($id, ['nickname' => $formData['nickname'], 'password' => $formData['password']]);

            if ($id == $this->get('session')['user']['id']) {
                $authService = new AuthService($this->get('pdo'), $this->get('session'));
                $authService->reload();
            }

            LogService::save($this, 'app.log.action.user.update_auth', [$userDAO->getFullname($id)], 'st_user', $id);
            $this->get('pdo')->commit();
        } catch (\Exception $e) {
            $this->get('pdo')->rollback();
            $this->get('logger')->addError($e);
            $this->get('flash')->addMessage('danger', __('app.error.save'));
            return $response->withStatus(302)->withHeader('Location', '/app/' . static::ENTITY_SINGULAR . '/form/' . $id);
        }
        $this->get('flash')->addMessage('success', __('app.controller.save_ok'));
        return $response->withStatus(302)->withHeader('Location', '/app/' . static::ENTITY_SINGULAR . '/form/' . $id);
    }

    /**
     * Obtiene avatar del usuario o genera imagen con las iniciales
     */
    public function avatar(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $userDAO = new UserDAO($this->get('pdo'));
        $user = $userDAO->getById($args['id']);
        if (empty($user)) {
            throw new AuthException();
        }

        $avatar = !empty($user['avatar']) ? FileUtils::streamFile($this, $response, 'user', 'FOLDER_PRIVATE', $user['avatar'], $args['id'], 'avatar') : null;

        if (!empty($avatar)) {
            return $avatar;
        }

        $text = strtoupper(mb_substr(trim($user['name']), 0, 1) . mb_substr(trim($user['surnames']), 0, 1));

        $color = $userDAO->getSingleField($user['id'], 'color');
        return FileUtils::generateAvatar($response, $text, $color);
    }

    /**
     * Valida si existe algún usuario con el nickname especificado
     */
    public function checkNickname(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $formData = $request->getParsedBody();
        $userDAO = new UserDAO($this->get('pdo'));
        $id = !empty($args['id']) ? $args['id'] : null;
        if($id == 'c') {
            $id = $this->get('security')->getUserId();
        }
        $result = ($userDAO->existsNickname($formData['nickname'], $id)) ? 1 : 0;
        return ResponseUtils::withJson($response, $result);
    }

    /**
     * Valida el password actual
     */
    public function checkCurrentPassword(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $formData = $request->getParsedBody();
        $userDAO = new UserDAO($this->get('pdo'));
        $result = $userDAO->checkCurrentPassword($this->get('security')->getUserId(), $formData['password']) ? 1 : 0;
        return ResponseUtils::withJson($response, $result);
    }

    /**
     * Obtiene los trabajadores y los devuelve por JSON
     */
    public function selector(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $userDAO = new UserDAO($this->get('pdo'));
        $result = $userDAO->getForSelectByProfileWithStatus(UserProfile::User, true);
        return ResponseUtils::withJson($response, $result);
    }
}
