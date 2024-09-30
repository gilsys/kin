<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Constant\App\FormSaveMode;
use App\Constant\App\MenuSection;
use App\Constant\StaticListTable;
use App\Dao\StaticListDAO;
use App\Exception\AuthException;
use App\Service\LogService;
use App\Util\CommonUtils;
use App\Util\ResponseUtils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class StaticListController extends BaseController {

    const ENTITY_SINGULAR = 'static_list';
    const ENTITY_PLURAL = 'static_lists';

    public function getDAO() {
    }


    public function getNameForLogs($id) {
    }

    /**
     * Página de listado
     */
    public function list(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $this->get('security')->checkStaticList($args['list']);
        $entity = StaticListTable::getEntity($args['list']);

        $data = $this->prepareList();

        $data['menu'] = MenuSection::getMaintenanceMenu($args['list']);
        $data['breadcumb'] = [__('app.entity.plural.' . $entity), __('app.common.list_of', lcfirst(__('app.entity.plural.' . $entity)))];
        $data['title'] = implode(' > ', $data['breadcumb']);

        $data['list'] = $args['list'];
        $data['entity'] = $entity;

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    /**
     * Obtiene los datos para mostrar la datatable
     */
    public function datatable(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $this->get('security')->checkStaticList($args['list']);
        // Validar que la lista existe
        $table = StaticListTable::getTable($args['list']);

        $staticListDAO = new StaticListDAO($this->get('pdo'), $table);
        return ResponseUtils::withJson($response, $staticListDAO->getRemoteDatatable($this->get('i18n')->getCurrentLang()));
    }

    /**
     * Prepara el formulario de crear/editar
     */
    public function form(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $this->get('security')->checkStaticList($args['list']);
        $entity = StaticListTable::getEntity($args['list']);

        $data = $this->prepareForm($args);

        $data['menu'] = MenuSection::getMaintenanceMenu($args['list']);
        $data['breadcumb'] = [__('app.entity.plural.' . $entity), empty($data['data']['id']) ? __('app.common.form.add', __('app.entity.' . $entity)) : __('app.common.form.update', __('app.entity.' . $entity))];
        $data['title'] = implode(' > ', $data['breadcumb']);
        $data['entity'] = $entity;
        $data['data']['list'] = $args['list'];

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    /**
     * Obtiene los datos y los devuelve por JSON
     */
    public function load(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $this->get('security')->checkStaticList($args['list']);
        $table = StaticListTable::getTable($args['list']);
        $staticListDAO = new StaticListDAO($this->get('pdo'), $table);
        $staticList = $staticListDAO->getById($args['id']);
        if (empty($staticList)) {
            throw new AuthException();
        }
        return ResponseUtils::withJson($response, $staticList);
    }

    /**
     * Guarda los datos
     */
    public function save(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $this->get('security')->checkStaticList($args['list']);
        $this->get('logger')->addInfo("Insert or update static list settings");
        $table = StaticListTable::getTable($args['list']);
        $entity = StaticListTable::getEntity($args['list']);

        $formData = CommonUtils::getSanitizedData($request);
        $id = !empty($formData['id']) ? $formData['id'] : null;

        try {
            $this->get('pdo')->beginTransaction();
            $staticListDAO = new StaticListDAO($this->get('pdo'), $table);
            if (empty($id)) {
                $formData['custom_order'] = $staticListDAO->count() + 1;
                $id = $staticListDAO->save($formData);

                LogService::save($this, 'app.log.action.save', [ucfirst(__('app.entity.' . $entity)), $staticListDAO->getSingleField($id, 'name')], $table, $id);
            } else {
                $staticListDAO->update($formData);
                LogService::save($this, 'app.log.action.update', [ucfirst(__('app.entity.' . $entity)), $staticListDAO->getSingleField($id, 'name')], $table, $id);
            }
            $this->get('pdo')->commit();
        } catch (\Exception $e) {
            $this->get('pdo')->rollback();
            $this->get('logger')->addError($e);
            $this->get('flash')->addMessage('danger', __('app.error.save'));
            return $response->withStatus(302)->withHeader('Location', '/app/static_lists/' . $args['list']);
        }
        $this->get('flash')->addMessage('success', __('app.controller.save_ok'));

        // Dependiendo de la selección del usuario, se redirige a una pantalla u otra
        switch ($args['mode']) {
            case FormSaveMode::SaveAndContinue:
                return $response->withStatus(302)->withHeader('Location', '/app/static_list/form/' . $args['list'] . '/' . $id);
            case FormSaveMode::SaveAndNew:
                return $response->withStatus(302)->withHeader('Location', '/app/static_list/form/' . $args['list']);
            default:
                return $response->withStatus(302)->withHeader('Location', '/app/static_lists/' . $args['list']);
        }
    }

    /**
     * Eliminar registro
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $formData = CommonUtils::getSanitizedData($request);
        try {
            $this->get('pdo')->beginTransaction();

            $this->get('security')->checkStaticList($args['list']);
            $table = StaticListTable::getTable($args['list']);
            $entity = StaticListTable::getEntity($args['list']);
            $staticListDAO = new StaticListDAO($this->get('pdo'), $table);

            LogService::save($this, 'app.log.action.delete', [ucfirst(__('app.entity.' . $entity)), $staticListDAO->getSingleField($formData['id'], 'name')], $table, $formData['id']);

            $staticListDAO->deleteById($formData['id']);
            $this->get('pdo')->commit();
        } catch (\Exception $e) {
            $this->get('pdo')->rollback();
            $this->get('logger')->addError($e);
            $this->get('flash')->addMessage('danger', __('app.error.delete_dependencies'));
            return $response->withStatus(302)->withHeader('Location', '/app/static_lists/' . $args['list']);
        }

        $this->get('flash')->addMessage('success', __('app.controller.delete_ok'));
        return $response->withStatus(302)->withHeader('Location', '/app/static_lists/' . $args['list']);
    }

    /**
     * Ordenación
     */
    public function order(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $this->get('security')->checkStaticList($args['list']);
        $table = StaticListTable::getTable($args['list']);
        $staticListDAO = new StaticListDAO($this->get('pdo'), $table);
        if ($args['direction'] == 1) {
            $staticListDAO->up($args['id']);
        } else {
            $staticListDAO->down($args['id']);
        }
        return ResponseUtils::withJson($response, ['success' => 1]);
    }
}
