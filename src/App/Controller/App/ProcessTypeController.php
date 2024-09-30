<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Constant\App\MenuSection;
use App\Dao\ProcessTypeDAO;
use App\Constant\StaticListTable;
use App\Dao\StaticListDAO;
use Exception;
use App\Exception\AuthException;
use App\Util\ResponseUtils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ProcessTypeController extends BaseController {

    const ENTITY_SINGULAR = 'process_type';
    const ENTITY_PLURAL = 'process_types';
    const MENU = MenuSection::MenuProcessType;

    public function getDAO() {
        return new ProcessTypeDAO($this->get('pdo'));
    }


    public function getNameForLogs($id) {
        return $this->getDAO()->getSingleField($id, 'id');
    }


    /**
     * Página de listado de procesos
     */
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $this->prepareList();
        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    /**
     * Prepara el formulario de crear/editar proceso
     */
    public function form(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $data = $this->prepareForm($args);

        $taskTypeEntity = StaticListTable::getEntity(StaticListTable::TaskType);
        $taskTypeDAO = new StaticListDAO($this->get('pdo'), 'st_' . $taskTypeEntity);
        $data['data']['taskTypes'] = $taskTypeDAO->getAll();

        if (!empty($data['data']['id'])) {
            $data = $this->prepareProcessTypeData($data['data']['id'], $data);
        }

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    public function load(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {

        $data = $this->getDAO()->getById($args['id']);

        if (empty($data)) {
            throw new AuthException();
        }
        if (!empty($data['json_data'])) {
            $data['json_data'] = json_decode($data['json_data'], true);
        }
        return ResponseUtils::withJson($response, $data);
    }

    public function savePreSave($request, $response, $args, &$formData) {
        if (empty($formData['id'])) {
            $dao = $this->getDAO();
            $formData['custom_order'] = $dao->count() + 1;
        }
        if (empty($formData['json_data'])) {
            $formData['json_data'] = null;
        } else {
            $formData['json_data'] = json_encode($formData['json_data']);
        }
    }

    public function order(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $dao = $this->getDao();
        if ($args['direction'] == 1) {
            $dao->up($args['id']);
        } else {
            $dao->down($args['id']);
        }
        return ResponseUtils::withJson($response, ['success' => 1]);
    }

    private function prepareProcessTypeData($id, $data) {
        $data['data']['processType'] = $this->getDAO()->getById($id);
        if (empty($data['data']['processType'])) {
            throw new Exception(__('app.error.file_not_found'), 404);
        }

        $data['breadcumb'][] = trim($data['data']['processType']['name']);
        return $data;
    }
}
