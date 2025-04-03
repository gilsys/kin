<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Constant\App\FormSaveMode;
use App\Dao\FileDAO;
use App\Exception\AuthException;
use App\Exception\CustomException;
use App\Service\LogService;
use App\Util\CommonUtils;
use App\Util\FileUtils;
use App\Util\ResponseUtils;
use Defuse\Crypto\File;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class BaseController {

    const ENTITY_SINGULAR = '';
    const ENTITY_PLURAL = '';
    const MENU = '';

    private $container;

    // constructor receives container instance
    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    abstract public function getDAO();

    abstract public function getNameForLogs($entity);

    public function get($element) {
        return $this->container->get($element);
    }

    public function preparePage($title, $view) {
        $data['title'] = $title;
        $data['menu'] = $data['menu'] = static::MENU;
        $data['breadcumb'] = [$data['title']];
        $data['messages'] = $this->get('flash')->getMessages();
        $data['view'] = $view;
        return $data;
    }

    public function prepareList() {
        $this->get('logger')->addInfo(static::ENTITY_SINGULAR . ' - get list');

        // Indica elemento del menú principal activo
        $data['menu'] = static::MENU;
        $data['breadcumb'] = [ucfirst(__('app.entity.' . static::ENTITY_PLURAL)), __('app.common.list_of', __('app.entity.' . static::ENTITY_PLURAL))];
        $data['title'] = implode(' > ', $data['breadcumb']);

        // Define la vista a utilizar
        $data['view'] = static::ENTITY_SINGULAR . '/list';

        // Carga los flash messages para mostrarlos en pantalla
        $data['messages'] = $this->get('flash')->getMessages();
        $data['entity'] = static::ENTITY_SINGULAR;

        // Javascripts a incluir
        $data['js'] = ['/assets/plugins/custom/datatables/datatables.bundle.js', '/js/datatables.custom.js', '/js/project/' . static::ENTITY_SINGULAR . '.datatable.js'];
        $data['css'] = ['/assets/plugins/custom/datatables/datatables.bundle.css'];
        return $data;
    }

    public function prepareForm($args) {
        // Obtenemos los valores a mostrar en los desplegables        
        $data['data']['id'] = !empty($args['id']) ? $args['id'] : null;

        // Indica elemento del menú principal activo
        $data['menu'] = static::MENU;
        $data['entity'] = static::ENTITY_SINGULAR;
        $data['breadcumb'] = [ucfirst(__('app.entity.' . static::ENTITY_PLURAL)), empty($data['data']['id']) ? __('app.common.form.add', __('app.entity.' . static::ENTITY_SINGULAR)) : __('app.common.form.update', __('app.entity.' . static::ENTITY_SINGULAR))];
        $data['title'] = implode(' > ', $data['breadcumb']);

        // Define la vista a utilizar
        $data['view'] = static::ENTITY_SINGULAR . '/form';

        // Javascripts a incluir
        $data['js'] = ['/js/project/' . static::ENTITY_SINGULAR . '.form.js', '/js/uppy.custom.js'];

        // Carga los flash messages para mostrarlos en pantalla
        $data['messages'] = $this->get('flash')->getMessages();

        return $data;
    }

    public function deletePreDelete($request, $response, $args, &$formData) {
    }

    public function deletePostDelete($request, $response, $args, &$formData) {
    }

    /**
     * Eliminar registro
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $formData = CommonUtils::getSanitizedData($request);
        $id = $formData['id'];
        $redirectUrl = $this->getRedirectUrlList($formData);
        $this->get('logger')->addInfo("Delete " . static::ENTITY_SINGULAR . " - id: " . $id);
        try {
            $this->get('pdo')->beginTransaction();
            $nameForLogs = $this->getNameForLogs($id);
            $skipDelete = $this->deletePreDelete($request, $response, $args, $formData);

            if(empty($skipDelete)) {
                $dao = $this->getDAO();
                $dao->deleteById($id);
            }

            $this->deletePostDelete($request, $response, $args, $formData);
            LogService::save($this, 'app.log.action.delete', [ucfirst(__('app.entity.' . static::ENTITY_PLURAL)), $nameForLogs], $this->getDAO()->getTable(), $id);
            $this->get('pdo')->commit();
        } catch (\Exception $e) {
            $this->get('pdo')->rollback();
            $this->get('logger')->addError($e);
            $this->get('flash')->addMessage('danger', __('app.error.delete_dependencies'));
            return $response->withStatus(302)->withHeader('Location', $redirectUrl);
        }

        $this->get('flash')->addMessage('success', __('app.controller.delete_ok'));
        return $response->withStatus(302)->withHeader('Location', $redirectUrl);
    }

    /**
     * Restaurar registro
     */
    public function restore(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $formData = CommonUtils::getSanitizedData($request);
        $id = $formData['id'];
        $redirectUrl = $this->getRedirectUrlList($formData);
        $this->get('logger')->addInfo("Restore " . static::ENTITY_SINGULAR . " - id: " . $id);
        try {
            $this->get('pdo')->beginTransaction();
            $this->getDAO()->updateSingleField($id, 'date_deleted', null);
            $nameForLogs = $this->getNameForLogs($id);
            LogService::save($this, 'app.log.action.restore', [ucfirst(__('app.entity.' . static::ENTITY_PLURAL)), $nameForLogs], $this->getDAO()->getTable(), $id);
            $this->get('pdo')->commit();
        } catch (\Exception $e) {
            $this->get('pdo')->rollback();
            $this->get('logger')->addError($e);
            $this->get('flash')->addMessage('danger', __('app.error.restore'));
            return $response->withStatus(302)->withHeader('Location', $redirectUrl);
        }

        $this->get('flash')->addMessage('success', __('app.controller.restore_ok'));
        return $response->withStatus(302)->withHeader('Location', $redirectUrl);
    }

    /**
     * Obtiene los datos para mostrar la datatable
     */
    public function datatable(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        return ResponseUtils::withJson($response, $this->getDAO()->getRemoteDatatable());
    }

    /**
     * Obtiene los datos del cliente y los devuelve por JSON
     */
    public function load(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $dao = $this->getDAO();
        if (method_exists($dao, 'getFullById')) {
            $data = $this->getDAO()->getFullById($args['id']);
        } else {
            $data = $this->getDAO()->getById($args['id']);
        }
        if (empty($data)) {
            throw new AuthException();
        }
        return ResponseUtils::withJson($response, $data);
    }

    public function savePrevalidate($request, $response, $args) {
        $formData = CommonUtils::getSanitizedData($request);
        $id = !empty($formData['id']) ? $formData['id'] : null;
        if (empty($id)) {
            $this->get('logger')->addInfo("Insert new " . static::ENTITY_SINGULAR);
        } else {
            $this->get('logger')->addInfo("Update " . static::ENTITY_SINGULAR . " - id: " . $id);
        }
        return $formData;
    }

    public function savePreSave($request, $response, $args, &$formData) {
    }

    public function savePersist($request, $response, $args, &$formData) {
        $dao = $this->getDAO();
        if (empty($formData['id'])) {
            $formData['id'] = $dao->save($formData);
            LogService::save($this, 'app.log.action.save', [ucfirst(__('app.entity.' . static::ENTITY_PLURAL)), $this->getNameForLogs($formData['id'])], $this->getDAO()->getTable(), $formData['id']);
        } else {
            $dao->update($formData);
            LogService::save($this, 'app.log.action.update', [ucfirst(__('app.entity.' . static::ENTITY_PLURAL)), $this->getNameForLogs($formData['id'])], $this->getDAO()->getTable(), $formData['id']);
        }
        return $formData['id'];
    }

    public function savePostSave($request, $response, $args, &$formData) {
        $this->get('flash')->addMessage('success', __('app.controller.save_ok'));

        // Dependiendo de la selección del usuario, se redirige a una pantalla u otra
        switch ($args['mode']) {
            case FormSaveMode::SaveAndContinue:
                return $response->withStatus(302)->withHeader('Location', $this->getRedirectUrlForm($formData, false));
            case FormSaveMode::SaveAndNew:
                return $response->withStatus(302)->withHeader('Location', $this->getRedirectUrlForm($formData, true));
            default:
                return $response->withStatus(302)->withHeader('Location', $this->getRedirectUrlList($formData));
        }
    }

    /**
     * Guarda los datos del usuario
     */
    public function save(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $formData = $this->savePrevalidate($request, $response, $args);
        try {
            $this->get('pdo')->beginTransaction();
            $this->savePreSave($request, $response, $args, $formData);
            $this->savePersist($request, $response, $args, $formData);
            $this->get('pdo')->commit();
        } catch (\Exception $e) {
            $this->get('pdo')->rollback();
            $this->get('logger')->addError($e);
            $errorMsg = ($e instanceof CustomException) ? $e->getMessage() : __('app.error.save');
            $this->get('flash')->addMessage('danger', $errorMsg);
            return $response->withStatus(302)->withHeader('Location', $this->getRedirectUrlList($formData));
        }

        return $this->savePostSave($request, $response, $args, $formData);
    }

    public function saveFile($request, $id, $formField, $field, $fileType, $dao = null) {
        $dao = !empty($dao) ? $dao : $this->getDAO();
        $fileDAO = new FileDAO($this->get('pdo'));
        $oldFileId = $dao->getSingleField($id, $field);
        $fileId = !empty($oldFileId) ? $oldFileId : $fileDAO->getNext();

        $directory = $this->get('params')->getParam('FOLDER_PRIVATE');
        $file = FileUtils::uploadFile($request, $fileType, $directory, $fileId, $formField, false, true, null, false, 'file');

        if (!empty($file)) {
            if (empty($oldFileId)) {
                $fileId = $fileDAO->save(['file_type_id' => $fileType, 'file' => $file]);
                $dao->updateSingleField($id, $field, $fileId);
            } else {
                $fileDAO->updateSingleField($fileId, 'file', $file);
                $dao->updateDateUpdated($id);
            }
        }
    }

    public function getFile($response, $id, $field, $dao = null) {
        $dao = !empty($dao) ? $dao : $this->getDAO();
        $fileId = $dao->getSingleField($id, $field);
        if (empty($fileId)) {
            throw new \Exception(__('app.error.file_not_found'), 404);
        }

        return $this->getFileById($response, $fileId);
    }

    public function getFileById($response, $fileId, $field = 'file') {
        $fileDAO = new FileDAO($this->get('pdo'));
        $file = $fileDAO->getById($fileId);

        $response = FileUtils::streamFile($this, $response, $file['file_type_id'], 'FOLDER_PRIVATE', $file['file'], $file['id'], $field, false);

        if (!empty($response)) {
            return $response;
        }
        throw new \Exception(__('app.error.file_not_found'), 404);
    }

    public function getRedirectUrlList($formData) {
        return '/app/' . static::ENTITY_PLURAL;
    }

    public function getRedirectUrlForm($formData, $new) {
        $url = '/app/' . static::ENTITY_SINGULAR . '/form';
        if (!$new && !empty($formData['id'])) {
            $url .= '/' . $formData['id'];
        }
        return $url;
    }
}
