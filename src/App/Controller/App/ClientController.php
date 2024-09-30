<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Constant\App\MenuSection;
use App\Constant\StaticListTable;
use App\Dao\ClientDAO;
use App\Dao\ProcessDAO;
use App\Dao\UserDAO;
use App\Dao\StaticListDAO;
use App\Util\CommonUtils;
use App\Util\ResponseUtils;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ClientController extends BaseController {

    const ENTITY_SINGULAR = 'client';
    const ENTITY_PLURAL = 'clients';
    const MENU = MenuSection::MenuClients;

    const MENU_DATA = MenuSection::MenuClientData;
    const MENU_PROCESSES = MenuSection::MenuClientProcesses;

    public function getDAO() {
        return new ClientDAO($this->get('pdo'));
    }

    public function getNameForLogs($id) {
        return $this->getDAO()->getClientName($id);
    }

    /**
     * Página de listado de clientes
     */
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $this->prepareList();

        // Carga selectores para filtros
        $clientEntity = StaticListTable::getEntity(StaticListTable::ClientType);
        $clientTypeDAO = new StaticListDAO($this->get('pdo'), 'st_' . $clientEntity);

        $countryEntity = StaticListTable::getEntity(StaticListTable::Country);
        $countryDAO = new StaticListDAO($this->get('pdo'), 'st_' . $countryEntity);

        $data['data'] = [
            'clientTypes' => $clientTypeDAO->getForSelect('id', 'name', 'custom_order'),
            'countries' => $countryDAO->getForSelect('id', 'name'),
        ];

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    /**
     * Prepara el formulario de crear/editar cliente
     */
    public function form(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $data = $this->prepareForm($args);
        $data['menuClient'] = static::MENU_DATA;

        // Obtenemos los valores a mostrar en los desplegables
        $clientEntity = StaticListTable::getEntity(StaticListTable::ClientType);
        $clientTypeDAO = new StaticListDAO($this->get('pdo'), 'st_' . $clientEntity);

        $countryEntity = StaticListTable::getEntity(StaticListTable::Country);
        $countryDAO = new StaticListDAO($this->get('pdo'), 'st_' . $countryEntity);

        $data['data']['clientTypes'] = $clientTypeDAO->getForSelect('id', 'name', 'custom_order');
        $data['data']['countries'] = $countryDAO->getForSelect('id', 'name');

        if (!empty($data['data']['id'])) {
            $data = $this->prepareClientData($data['data']['id'], $data);
        }

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    public function savePreSave($request, $response, $args, &$formData) {
        if (empty($formData['id'])) {
            $this->get('security')->checkAdmin();
        }
        // Convierte los datos personales en JSON
        $formData['information'] = json_encode($formData['information']);
    }

    /**
     * Obtiene los clientes y los devuelve por JSON
     */
    public function selector(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $clientDAO = $this->getDAO();
        $formData = CommonUtils::getSanitizedData($request);
        $searchTerm = !empty($formData['searchTerm']) ? $formData['searchTerm'] : null;
        $id = !empty($formData['id']) ? $formData['id'] : null;
        $result = $clientDAO->getFullForSelect($searchTerm, $id);
        return ResponseUtils::withJson($response, $result);
    }

    private function prepareClientData($id, $data) {
        $clientDAO = new ClientDAO($this->get('pdo'));
        $data['data']['client'] = $clientDAO->getBasicInformation($id);
        if (empty($data['data']['client'])) {
            throw new Exception(__('app.error.file_not_found'), 404);
        }

        $data['breadcumb'][] = trim($data['data']['client']['entity']);
        return $data;
    }

    /**
     * Página de listado de Procesos
     */
    public function processes(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $data['breadcumb'] = [__('app.client.processes')];
        $data['title'] = implode(' > ', $data['breadcumb']);

        // Indica elemento del menú principal activo
        $data['menu'] = static::MENU;
        $data['menuClient'] = static::MENU_PROCESSES;
        $data['entity'] = static::ENTITY_SINGULAR;
        $data['breadcumb'] = [__('app.clients.title'), $data['title']];

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

        // Carga los flash messages para mostrarlos en pantalla
        $data['messages'] = $this->get('flash')->getMessages();

        // Define la vista a utilizar
        $data['view'] = 'client/processes';

        // Javascripts a incluir
        $data['js'] = ['/assets/plugins/custom/datatables/datatables.bundle.js', '/js/datatables.custom.js', '/js/project/client_processes.datatable.js'];
        $data['css'] = ['/assets/plugins/custom/datatables/datatables.bundle.css'];

        $data['data']['id'] = $args['id'];
        $data = $this->prepareClientData($data['data']['id'], $data);

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    /**
     * Obtiene los datos para mostrar la datatable de procesos
     */
    public function processesDatatable(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $processDAO = new ProcessDAO($this->get('pdo'));
        return ResponseUtils::withJson($response, $processDAO->getRemoteDatatable($args['id']));
    }
}
