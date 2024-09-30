<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Constant\App\MenuSection;
use App\Constant\StaticListTable;
use App\Dao\ProcessDAO;
use App\Dao\StaticListDAO;
use App\Dao\UserDAO;
use App\Dao\FileDAO;
use App\Dao\TaskDAO;
use App\Util\FileUtils;
use App\Constant\FileType;
use App\Constant\TaskStatus;
use App\Exception\AuthException;
use App\Constant\UserProfile;
use App\Service\LogService;
use App\Dao\FileProcessDAO;
use App\Dao\ProcessTypeDAO;
use App\Dao\LogDAO;
use App\Service\TagService;
use App\Dao\TagDAO;
use App\Dao\TaskTagDAO;
use App\Util\CommonUtils;
use App\Util\ResponseUtils;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ProcessController extends BaseController {

    const ENTITY_SINGULAR = 'process';
    const ENTITY_PLURAL = 'processes';
    const MENU = MenuSection::MenuProcess;

    private $fileIds = [];
    private $deleteFileIds = [];

    public function getDAO() {
        return new ProcessDAO($this->get('pdo'));
    }


    public function getNameForLogs($id) {
        return $this->getDAO()->getSingleField($id, 'name');
    }


    /**
     * Página de listado de procesos
     */
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $this->prepareList();

        // Carga selectores para filtros
        $processTypeEntity = StaticListTable::getEntity(StaticListTable::ProcessType);
        $processTypeDAO = new StaticListDAO($this->get('pdo'), 'st_' . $processTypeEntity);

        $processStatusEntity = StaticListTable::getEntity(StaticListTable::ProcessStatus);
        $processStatusDAO = new StaticListDAO($this->get('pdo'), 'st_' . $processStatusEntity);
        $userDAO = new UserDAO($this->get('pdo'));

        $data['data'] = [
            'processTypes' => $processTypeDAO->getAll(),
            'processStatuses' => $processStatusDAO->getAll(),
            'processCreator' => $userDAO->getForSelectFullname(),
        ];

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    /**
     * Prepara el formulario de crear/editar proceso
     */
    public function form(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $data = $this->prepareForm($args);

        isset($args['clientId']) ? $data['data']['clientId'] = $args['clientId'] : null;

        // Obtenemos los valores a mostrar en los desplegables
        $processTypeEntity = StaticListTable::getEntity(StaticListTable::ProcessType);
        $processTypeDAO = new StaticListDAO($this->get('pdo'), 'st_' . $processTypeEntity);

        $processStatusEntity = StaticListTable::getEntity(StaticListTable::ProcessStatus);
        $processStatusDAO = new StaticListDAO($this->get('pdo'), 'st_' . $processStatusEntity);

        $data['data']['processTypes'] = $processTypeDAO->getAll();
        $data['data']['processStatuses'] = $processStatusDAO->getAll();

        if (!empty($data['data']['id'])) {


            // Carga selectores para filtros

            $tagDAO = new TagDAO($this->get('pdo'));

            $taskTypeEntity = StaticListTable::getEntity(StaticListTable::TaskType);
            $taskTypeDAO = new StaticListDAO($this->get('pdo'), 'st_' . $taskTypeEntity);

            $taskStatusEntity = StaticListTable::getEntity(StaticListTable::TaskStatus);
            $taskStatusDAO = new StaticListDAO($this->get('pdo'), 'st_' . $taskStatusEntity);

            $taskPaymentStatusEntity = StaticListTable::getEntity(StaticListTable::TaskPaymentStatus);
            $taskPaymentStatusDAO = new StaticListDAO($this->get('pdo'), 'st_' . $taskPaymentStatusEntity);
            $userDAO = new UserDAO($this->get('pdo'));

            $data['data']['tags'] = $tagDAO->getTagsByProcessId($data['data']['id']);
            $data['data']['taskTypes'] = $taskTypeDAO->getAll();        
            $data['data']['taskStatuses'] = $taskStatusDAO->getAll();        
            $data['data']['taskPaymentStatuses'] = $taskPaymentStatusDAO->getAll();        
            $data['data']['taskCreator'] = $userDAO->getForSelectFullname();


            $data = $this->prepareProcessData($data['data']['id'], $data);

            $extraJs = ['/assets/plugins/custom/datatables/datatables.bundle.js', '/js/datatables.custom.js', '/js/project/task.datatable.js', '/js/project/log.datatable.js'];
            $data['js'] = array_merge($data['js'], $extraJs);

            $data['css'][] = '/assets/plugins/custom/datatables/datatables.bundle.css';

            $data['processId'] = $data['data']['id'];
        }

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    public function load(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $dao = $this->getDAO();
        $data = $this->getDAO()->getFullById($args['id']);
        $fileProcessDAO = new FileProcessDAO($this->get('pdo'));
        $data['files'] = $fileProcessDAO->getFilesByProcessId($args['id']);

        if (empty($data)) {
            throw new AuthException();
        }
        return ResponseUtils::withJson($response, $data);
    }

    /**
     * Obtiene los datos para mostrar la datatable de logs generales
     */
    public function logsDatatable(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $logDAO = new LogDAO($this->get('pdo'));
        return ResponseUtils::withJson($response, $logDAO->getLogsRemoteDatatable('st_' . self::ENTITY_SINGULAR, $args['id'], $this->get('i18n')->getCurrentLang()));
    }

    public function savePreSave($request, $response, $args, &$formData) {
        if (empty($formData['id'])) {
            $this->get('security')->checkAdmin();
            if ($this->get('session')['user']['user_profile_id'] != UserProfile::Administrator) {
                throw new AuthException();
            }
            $formData['creator_user_id'] = $this->get('session')['user']['id'];
        } else {
            unset($formData['mt-task_length']);

            if (isset($formData['files'])) {
                unset($formData['files']);
            }
            $this->fileIds = !empty($formData['file_ids']) ? explode(',', $formData['file_ids']) : [];
            unset($formData['file_ids']);

            $this->deleteFileIds = !empty($formData['delete_file_ids']) ? explode(',', $formData['delete_file_ids']) : [];
            unset($formData['delete_file_ids']);
        }
    }

    public function savePersist($request, $response, $args, &$formData) {
        $dao = $this->getDAO();
        if (empty($formData['id'])) {
            $formData['id'] = $dao->save($formData);
            $this->createDefaultTasks($formData);
            LogService::save($this, 'app.log.action.save', [ucfirst(__('app.entity.' . static::ENTITY_PLURAL)), $this->getNameForLogs($formData['id'])], $this->getDAO()->getTable(), $formData['id']);
        } else {
            $dao->update($formData);
            LogService::save($this, 'app.log.action.update', [ucfirst(__('app.entity.' . static::ENTITY_PLURAL)), $this->getNameForLogs($formData['id'])], $this->getDAO()->getTable(), $formData['id']);
        }
        return $formData['id'];
    }

    public function savePostSave($request, $response, $args, &$formData) {
        $fileProcessDAO = new FileProcessDAO($this->get('pdo'));
        foreach ($this->fileIds as $fileId) {
            $fileProcessDAO->save($formData['id'], $fileId);
        }

        foreach ($this->deleteFileIds as $fileId) {
            $fileProcessDAO->deleteByFileId($fileId);
        }

        return parent::savePostSave($request, $response, $args, $formData);
    }

    private function prepareProcessData($id, $data) {
        $processDAO = $this->getDAO();
        $data['data']['process'] = $processDAO->getById($id);
        if (empty($data['data']['process'])) {
            throw new Exception(__('app.error.file_not_found'), 404);
        }

        $data['breadcumb'][] = trim($data['data']['process']['name']);
        return $data;
    }

    private function createDefaultTasks($formData) {
        $processTypeDAO = new ProcessTypeDAO($this->get('pdo'));
        $defaultTasks = $processTypeDAO->getSingleField($formData['process_type_id'], 'json_data');

        if (empty($defaultTasks)) return;
        $taskDAO = new TaskDAO($this->get('pdo'));
        $tagDAO = new TagDAO($this->get('pdo'));
        $taskTagDAO = new TaskTagDAO($this->get('pdo'));
        $tagService = new TagService($this->get('pdo'));

        $defaultTasks = json_decode($defaultTasks, true);
        $names = array_map(function ($defaultTask) {
            return $defaultTask['name'];
        }, $defaultTasks);
        $tagService->checkAndSaveNewTags($names);

        $taskData = [
            'process_id' => $formData['id'],
            'task_status_id' => TaskStatus::Pending,
            'payment_status_id' => null,
            'description' => null,
            'hours' => null,
            'date_task' => null,
            'creator_user_id' => $this->get('session')['user']['id'],
        ];
        foreach ($defaultTasks as $task) {
            $tagId = $tagDAO->getSingleField($task['name'], 'id', 'name');
            $taskData['task_type_id'] = $task['type'];
            $taskId = $taskDAO->save($taskData);
            $taskTagDAO->save(['task_id' => $taskId, 'tag_id' => $tagId]);
            LogService::save($this, 'app.log.action.save', [ucfirst(__('app.entity.tasks')), $taskId], $taskDAO->getTable(), $taskId);
        }
    }

    public function uploadFile(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $directory = $this->get('params')->getParam('FOLDER_PRIVATE');
        $fileDAO = new FileDAO($this->get('pdo'));
        $id = $fileDAO->getNextAutoincrement();
        $fileName = FileUtils::uploadFile($request, 'process', $directory, $id, 'process-file');
        $fileData = ['file_type_id' => FileType::FileProcess, 'file' => $fileName];
        $fileDAO->save($fileData);
        return ResponseUtils::withJson($response, ['file_id' => $id]);
    }

    public function processFile(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $fileDAO = new FileDAO($this->get('pdo'));
        $file = $fileDAO->getById($args['id']);
        if (empty($file)) {
            throw new \Exception(__('file_not_found'));
        }
        $response = FileUtils::streamFile($this, $response, 'process', 'FOLDER_PRIVATE', $file['file'], $args['id'], 'process-file');
        if (!empty($response)) {
            return $response;
        }
        throw new \Exception(__('app.error.file_not_found'), 404);
    }

    public function downloadFile(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $fileDAO = new FileDAO($this->get('pdo'));
        $file = $fileDAO->getById($args['id']);
        $processFile = FileUtils::streamFile($this, $response, 'process', 'FOLDER_PRIVATE', $file['file'], $file['id'], 'process-file', false, FileUtils::FILENAME_HASH, true);
        if (!empty($processFile)) {
            return $processFile;
        }
        throw new \Exception(__('app.error.file_not_found'), 404);
    }

    public function deleteFile(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $formData = CommonUtils::getSanitizedData($request);
        $fileId = $formData['id'];
        $fileProcessDAO = new FileProcessDAO($this->get('pdo'));
        $fileProcessDAO->deleteByFileId($fileId);
        return ResponseUtils::withJson($response, ['file_id' => $fileId]);
    }
}
