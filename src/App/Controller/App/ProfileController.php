<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Constant\App\MenuSection;
use App\Dao\UserDAO;
use App\Service\AuthService;
use App\Service\LogService;
use App\Util\CommonUtils;
use App\Util\FileUtils;
use App\Util\ResponseUtils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ProfileController extends BaseController {

    public function getDAO() {
    }

    public function getNameForLogs($entity) {
    }

    /**
     * Prepara el formulario de crear/editar usuario
     */
    public function form(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data['title'] = __('app.profile');

        // Indica elemento del menú principal activo
        $data['menu'] = MenuSection::MenuProfile;
        $data['breadcumb'] = [$data['title']];

        // Carga los flash messages para mostrarlos en pantalla
        $data['messages'] = $this->get('flash')->getMessages();

        // Define la vista a utilizar
        $data['view'] = 'profile/form';

        // Javascripts a incluir
        $data['js'] = ['/js/project/profile.form.js'];

        // Obtenemos los valores a mostrar en los desplegables
        $data['data'] = [
            'userId' => $this->get('session')['user']['id']
        ];

        $data['data']['id'] = $data['data']['userId'];

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    /**
     * Obtiene los datos del usuario y los devuelve por JSON
     */
    public function load(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $userDAO = new UserDAO($this->get('pdo'));
        $user = $userDAO->getFullById($this->get('session')['user']['id']);
        return ResponseUtils::withJson($response, $user);
    }

    /**
     * Guarda los datos del usuario
     */
    public function save(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $this->get('logger')->addInfo("Insert or save user");
        $userDAO = new UserDAO($this->get('pdo'));
        $authService = new AuthService($this->get('pdo'), $this->get('session'), $this->get('params'), $this->get('flash'), $this->get('renderer'));

        $formData = CommonUtils::getSanitizedData($request);

        $id = $this->get('session')['user']['id'];

        try {
            $this->get('pdo')->beginTransaction();

            // Verifica los requisitos de los passwords
            if (!empty($formData['password']) && !empty($this->get('params')->getParam('PASSWORD_COMPLEX')) && !CommonUtils::checkPasswordRequirements($formData['password'])) {
                $this->get('flash')->addMessage('danger', __('app.controller.user.error.password'));
                return $response->withStatus(302)->withHeader('Location', '/app/profile');
            }

            // Verificar que el password actual es correcto
            if (!empty($formData['password']) && !$userDAO->checkCurrentPassword($id, $formData['password_current'])) {
                $this->get('flash')->addMessage('danger', __('app.controller.user.error.password_current'));
                return $response->withStatus(302)->withHeader('Location', '/app/profile');
            }

            // Merge para no perder campos de personal_information no incluidos al formulario
            $oldUser = $userDAO->getById($id, false);
            $formData['personal_information'] = array_merge(json_decode($oldUser['personal_information'], true), $formData['personal_information']);

            // Convierte los datos personales en JSON
            $formData['personal_information'] = json_encode($formData['personal_information']);
            $formData['user_profile_id'] = $oldUser['user_profile_id'];
            $formData['user_status_id'] = $oldUser['user_status_id'];
            $formData['market_id'] = $oldUser['market_id'];

            if (!empty($formData['nickname'])) {
                $userDAO->updateAuth($id, ['nickname' => $formData['nickname'], 'password' => $formData['password']]);
                unset($formData['nickname']);
                unset($formData['password']);
                unset($formData['password_current']);
            }

            $userDAO->update($formData);
            LogService::save($this, 'app.log.action.user.update', [$userDAO->getFullname($id)], 'st_user', $id);

            $directory = $this->get('params')->getParam('FOLDER_PRIVATE');
            $file = FileUtils::uploadFile($request, 'user', $directory, $id, 'avatar', false, true, null, true);

            if (!empty($file)) {
                $userDAO->updateSingleFieldEncryptedJSON($id, 'personal_information', 'avatar', $file);
            } else if (FileUtils::checkRemoveEmptyFile($request, 'avatar')) {
                $userDAO->updateSingleFieldEncryptedJSON($id, 'personal_information', 'avatar', '');
            }

            $authService->reload();
            $this->get('pdo')->commit();
        } catch (\Exception $e) {
            $this->get('pdo')->rollback();
            $this->get('logger')->addError($e);
            $this->get('flash')->addMessage('danger', __('app.error.save'));
            return $response->withStatus(302)->withHeader('Location', '/app/profile');
        }

        $this->get('flash')->addMessage('success', __('app.controller.save_ok'));
        return $response->withStatus(302)->withHeader('Location', '/app/profile');
    }
}
