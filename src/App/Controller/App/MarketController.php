<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Constant\App\MenuSection;
use App\Constant\StaticListTable;
use App\Dao\StaticListDAO;
use App\Dao\MarketDAO;
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

    /**
     * Página de listado de mercados
     */
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $this->prepareList();
        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    /**
     * Prepara el formulario de crear/editar mercados
     */
    public function form(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $data = $this->prepareForm($args);

        // Obtenemos los valores a mostrar en los desplegables
        $languageEntity = StaticListTable::getEntity(StaticListTable::Language);
        $languageDAO = new StaticListDAO($this->get('pdo'), 'st_' . $languageEntity);

        $data['data']['qr_languages'] = $languageDAO->getForSelect('id', 'name', 'custom_order');
        $data['data']['main_languages'] = array_slice($data['data']['qr_languages'], 0, 3);

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }
}
