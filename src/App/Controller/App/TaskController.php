<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Constant\App\MenuSection;
use App\Constant\StaticListTable;
use App\Constant\UserProfile;
use App\Dao\TaskDAO;
use App\Dao\StaticListDAO;
use App\Dao\UserDAO;
use App\Dao\ProcessDAO;
use App\Dao\TagDAO;
use App\Dao\TaskTagDAO;
use App\Dao\LogDAO;
use App\Service\LogService;
use App\Exception\AuthException;
use App\Service\TagService;
use App\Util\ResponseUtils;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use App\Service\WebhookService;
use App\Util\CommonUtils;

class TaskController extends BaseController {

    const ENTITY_SINGULAR = 'task';
    const ENTITY_PLURAL = 'tasks';
    const MENU = MenuSection::MenuTasks;

    public function getDAO() {
        return new TaskDAO($this->get('pdo'));
    }


    public function getNameForLogs($id) {
        return $this->getDAO()->getSingleField($id, 'id');
    }


    /**
     * Página de listado de procesos
     */
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $this->prepareList();

        // Carga selectores para filtros
        $tagDAO = new TagDAO($this->get('pdo'));
        $processDAO = new ProcessDAO($this->get('pdo'));

        $taskTypeEntity = StaticListTable::getEntity(StaticListTable::TaskType);
        $taskTypeDAO = new StaticListDAO($this->get('pdo'), 'st_' . $taskTypeEntity);

        $taskStatusEntity = StaticListTable::getEntity(StaticListTable::TaskStatus);
        $taskStatusDAO = new StaticListDAO($this->get('pdo'), 'st_' . $taskStatusEntity);

        $taskPaymentStatusEntity = StaticListTable::getEntity(StaticListTable::TaskPaymentStatus);
        $taskPaymentStatusDAO = new StaticListDAO($this->get('pdo'), 'st_' . $taskPaymentStatusEntity);
        $userDAO = new UserDAO($this->get('pdo'));

        $data['data'] = [
            'tags' => $tagDAO->getForSelect('id', 'name'),
            'processes' => $processDAO->getForSelect(),
            'taskTypes' => $taskTypeDAO->getAll(),
            'taskStatuses' => $taskStatusDAO->getAll(),
            'taskPaymentStatuses' => $taskPaymentStatusDAO->getAll(),
            'taskCreator' => $userDAO->getForSelectFullname(),
        ];


        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    /**
     * Prepara el formulario de crear/editar proceso
     */
    public function form(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {

        $data = $this->prepareForm($args);

        isset($args['processId']) ? $data['data']['processId'] = $args['processId'] : null;

        // Obtenemos los valores a mostrar en los desplegables

        $processDAO = new ProcessDAO($this->get('pdo'));

        $taskTypeEntity = StaticListTable::getEntity(StaticListTable::TaskType);
        $taskTypeDAO = new StaticListDAO($this->get('pdo'), 'st_' . $taskTypeEntity);

        $taskStatusEntity = StaticListTable::getEntity(StaticListTable::TaskStatus);
        $taskStatusDAO = new StaticListDAO($this->get('pdo'), 'st_' . $taskStatusEntity);

        $taskPaymentStatusEntity = StaticListTable::getEntity(StaticListTable::TaskPaymentStatus);
        $taskPaymentStatusDAO = new StaticListDAO($this->get('pdo'), 'st_' . $taskPaymentStatusEntity);

        $data['data']['taskTypes'] = $taskTypeDAO->getAll();
        $data['data']['taskStatuses'] = $taskStatusDAO->getAll();
        $data['data']['taskPaymentStatuses'] = $taskPaymentStatusDAO->getAll();
        $data['data']['processes'] = $processDAO->getForSelect();

        if (!empty($data['data']['id'])) {
            $data = $this->prepareTaskData($data['data']['id'], $data);

            $extraJs = ['/assets/plugins/custom/datatables/datatables.bundle.js', '/js/datatables.custom.js', '/js/project/log.datatable.js'];
            $data['js'] = array_merge($data['js'], $extraJs);
        }

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    public function load(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $taskTagDAO = new TaskTagDAO($this->get('pdo'));

        $data = $this->getDAO()->getFullById($args['id']);
        $this->get('security')->checkUser($data['creator_user_id']);

        $data['tags'] = $taskTagDAO->getTagsByTaskId($args['id']);
        $data['suggestedTags'] = $taskTagDAO->getTagsWithSameProcessTask($args['id']);

        if (empty($data)) {
            throw new AuthException();
        }

        return ResponseUtils::withJson($response, $data);
    }


    public function getTagsForTaskWhitelist(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $taskTagDAO = new TaskTagDAO($this->get('pdo'));
        return ResponseUtils::withJson($response, $taskTagDAO->getTagsForProcess($args['processId']));
    }

    /**
     * Obtiene los datos para mostrar la datatable de tareas
     */
    public function datatable(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $tasksDAO = new TaskDAO($this->get('pdo'));
        $processId = isset($args['processId']) ? $args['processId'] : null;
        $userId = $this->get('session')['user']['user_profile_id'] != UserProfile::Administrator ? $this->get('session')['user']['id'] : null;
        return ResponseUtils::withJson($response, $tasksDAO->getRemoteDatatable($processId, $userId));
    }

    /**
     * Obtiene los datos para mostrar la datatable de logs generales
     */
    public function logsDatatable(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $logDAO = new LogDAO($this->get('pdo'));
        return ResponseUtils::withJson($response, $logDAO->getLogsRemoteDatatable('st_' . self::ENTITY_SINGULAR, $args['id'], $this->get('i18n')->getCurrentLang()));
    }

    public function savePreSave($request, $response, $args, &$formData) {
        $tagService = new TagService($this->get('pdo'));
        $tagDAO = new TagDAO($this->get('pdo'));

        $formData['tags'] = $tagService->extractTagsFromInput($formData['tags']);
        $tagService->checkAndSaveNewTags($formData['tags']);

        unset($formData['task_is_extra']);
        if ($this->get('session')['user']['user_profile_id'] != UserProfile::Administrator) {
            unset($formData['payment_status_id']);
        }

        $formData['payment_status_id'] = !empty($formData['payment_status_id']) ? $formData['payment_status_id'] : null;
        $formData['date_task'] = !empty($formData['date_task']) ? $formData['date_task'] : null;
        $formData['hours'] = !empty($formData['hours']) ? $formData['hours'] : null;

        if (empty($formData['id'])) {
            $formData['creator_user_id'] = $this->get('session')['user']['id'];
            $formData['newTags'] = $tagDAO->getExistingTags($formData['tags']);
        } else {
            $formData['newTags'] = $tagDAO->getExistingTagsNotInTask($formData['tags'], $formData['id']);
        }
    }

    public function savePersist($request, $response, $args, &$formData) {
        $tagService = new TagService($this->get('pdo'));
        $webhookService = new WebhookService($this->get('pdo'), $this->get('session'), $this->get('params'));
        $taskDAO = $this->getDAO();

        $id = !empty($formData['id']) ? $formData['id'] : null;

        $newTags = $formData['newTags'];
        $inputTags = $formData['tags'];
        unset($formData['newTags']);
        unset($formData['tags']);

        if (empty($id)) {
            $id = $taskDAO->save($formData);
            LogService::save($this, 'app.log.action.save', [ucfirst(__('app.entity.' . static::ENTITY_PLURAL)), $this->getNameForLogs($id)], $this->getDAO()->getTable(), $id);
        } else {
            $taskDAO->update($formData);
            LogService::save($this, 'app.log.action.update', [ucfirst(__('app.entity.' . static::ENTITY_PLURAL)), $this->getNameForLogs($id)], $this->getDAO()->getTable(), $id);
        }

        $tagService->saveTagsToTask($newTags, $id);
        $deletedTags = $tagService->compareInputTagsToExistingInTask($inputTags, $id);
        $tagService->deleteRemovedTagsFromTask($deletedTags, $id);
        if (empty($formData['id'])) {
            $formData['id'] = $id;
            $webhookService->triggerZapierWebhook($this->getTaskInformation($formData), $this->get('params')->getParam('ZAPIER.TASK_CREATE'));
        } else {
            $webhookService->triggerZapierWebhook($this->getTaskInformation($formData), $this->get('params')->getParam('ZAPIER.TASK_UPDATE'));
        }
        return $id;
    }

    public function deletePreDelete($request, $response, $args, &$formData) {
        $taskTagDAO = new TaskTagDAO($this->get('pdo'));
        $tagDAO = new TagDAO($this->get('pdo'));
        $taskTagDAO->deleteTaskTagByTaskId($formData['id']);
        $tagDAO->deleteUselessTags();
    }

    public function deletePostDelete($request, $response, $args, &$formData) {
        $webhookService = new WebhookService($this->get('pdo'), $this->get('session'), $this->get('params'));
        $webhookService->triggerZapierWebhook($formData, $this->get('params')->getParam('ZAPIER.TASK_DELETE'));
    }

    private function getTaskInformation($task) {
        $taskDAO = $this->getDAO();
        $processDAO = new ProcessDAO($this->get('pdo'));
        $userDAO = new UserDAO($this->get('pdo'));
        $tagDAO = new TagDAO($this->get('pdo'));

        $taskStatusEntity = StaticListTable::getEntity(StaticListTable::TaskStatus);
        $taskStatusDAO = new StaticListDAO($this->get('pdo'), 'st_' . $taskStatusEntity);

        $taskTypeEntity = StaticListTable::getEntity(StaticListTable::TaskType);
        $taskTypeDAO = new StaticListDAO($this->get('pdo'), 'st_' . $taskTypeEntity);

        $taskPaymentStatusEntity = StaticListTable::getEntity(StaticListTable::TaskPaymentStatus);
        $taskPaymentStatusDAO = new StaticListDAO($this->get('pdo'), 'st_' . $taskPaymentStatusEntity);

        $data = [
            'id' => $task['id'],
            'name' => $tagDAO->getTagNameByTaskAsString($task['id']),
            'process' => $processDAO->getSingleField($task['process_id'], 'name'),
            'taskStatus' => $taskStatusDAO->getSingleField($task['task_status_id'], 'name'),
            'taskType' => $taskTypeDAO->getSingleField($task['task_type_id'], 'name'),
            'author' => $userDAO->getFullname($taskDAO->getSingleField($task['id'], 'creator_user_id')),
            'paymentStatus' => $task['payment_status_id'] != null ? $taskPaymentStatusDAO->getSingleField($task['payment_status_id'], 'name') : null,
            'description' => $task['description'],
            'hours' => $task['hours'],
            'date_task' => $task['date_task'],
            'date_created' => CommonUtils::convertDate($taskDAO->getSingleField($task['id'], 'date_created'), 'd/m/Y '),
        ];
        return $data;
    }

    private function prepareTaskData($id, $data) {
        $taskDAO = $this->getDAO();
        $data['data']['task'] = $taskDAO->getById($id);
        if (empty($data['data']['task'])) {
            throw new Exception(__('app.error.file_not_found'), 404);
        }

        if (!empty($data['data']['task']['description'])) {
            $data['breadcumb'][] = trim($data['data']['task']['description']);
        } else {
            $data['breadcumb'][] = __('app.entity.task') . " " . trim(strval($data['data']['task']['id']));
        }

        return $data;
    }
}
