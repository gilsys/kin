<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Constant\App\MenuSection;
use App\Constant\StaticListTable;
use App\Dao\MarketAreaDAO;
use App\Dao\StaticListDAO;
use App\Dao\MarketDAO;
use App\Exception\AuthException;
use App\Util\ResponseUtils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class MarketController extends BaseController {

    const ENTITY_SINGULAR = 'market';
    const ENTITY_PLURAL = 'markets';
    const MENU = MenuSection::MenuMarket;


    public function getDAO() {
        return new MarketDAO($this->get('pdo'));
    }


    public function getNameForLogs($id) {
        return $this->getDAO()->getSingleField($id, 'name');
    }

    public function load(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $data = $this->getDAO()->getById($args['id']);
        if (empty($data)) {
            throw new AuthException();
        }

        $marketAreaDAO = new MarketAreaDAO($this->get('pdo'));
        $data['market_area_ids'] = $marketAreaDAO->getAreasByMarketId($args['id']);

        return ResponseUtils::withJson($response, $data);
    }

    /**
     * Página de listado de mercados
     */
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $this->prepareList();

        // Carga selectores para filtros
        $areaTypeEntity = StaticListTable::getEntity(StaticListTable::Area);
        $areaTypeDAO = new StaticListDAO($this->get('pdo'), 'st_' . $areaTypeEntity);

        $data['data'] = [
            'areas' => $areaTypeDAO->getForSelect()
        ];

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    /**
     * Prepara el formulario de crear/editar mercados
     */
    public function form(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $data = $this->prepareForm($args);

        // Obtenemos los valores a mostrar en los desplegables
        $areaTypeEntity = StaticListTable::getEntity(StaticListTable::Area);
        $areaTypeDAO = new StaticListDAO($this->get('pdo'), 'st_' . $areaTypeEntity);

        $languageEntity = StaticListTable::getEntity(StaticListTable::Language);
        $languageDAO = new StaticListDAO($this->get('pdo'), 'st_' . $languageEntity);

        $data['data']['areas'] = $areaTypeDAO->getForSelect();
        $data['data']['languages'] = $languageDAO->getForSelect();

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }


    public function deletePreDelete($request, $response, $args, &$formData) {
        $marketAreaDAO = new MarketAreaDAO($this->get('pdo'));
        $marketAreaDAO->clear($formData['id']);
    }

    public function savePreSave($request, $response, $args, &$formData) {
        if (!empty($formData['id'])) {
            $marketAreaDAO = new MarketAreaDAO($this->get('pdo'));
            $marketAreaDAO->clear($formData['id']);
        }
    }

    public function savePersist($request, $response, $args, &$formData) {
        $marketAreaIds = !empty($formData['market_area_ids']) ? $formData['market_area_ids'] : [];
        unset($formData['market_area_ids']);

        parent::savePersist($request, $response, $args, $formData);

        $marketAreaDAO = new MarketAreaDAO($this->get('pdo'));
        foreach ($marketAreaIds as $marketAreaId) {
            $marketAreaDAO->save(['market_id' => $formData['id'], 'area_id' => $marketAreaId]);
        }
    }
   
}
