<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Constant\App\MenuSection;
use App\Constant\StaticListTable;
use App\Dao\StaticListDAO;
use App\Dao\MarketDAO;
use App\Dao\MarketProductDAO;
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

    public function savePersist($request, $response, $args, &$formData) {
        $isNew = empty($formData['id']);

        parent::savePersist($request, $response, $args, $formData);

        if ($isNew) {
            $marketProductDAO = new MarketProductDAO($this->get('pdo'));
            $marketProductDAO->save(['product_id' => $this->get('params')->getParam('EMPTY_PRODUCT'), 'market_id' => $formData['id']]);
        }
    }

    public function deletePreDelete($request, $response, $args, &$formData) {
        $marketProductDAO = new MarketProductDAO($this->get('pdo'));
        $marketProducts = $marketProductDAO->getProductsByMarketId($formData['id']);
        $emptyProductId = $this->get('params')->getParam('EMPTY_PRODUCT');

        if (count($marketProducts) == 1 && $marketProducts[0] == $emptyProductId) {
            $marketProductDAO->deleteByMarketIdProductId($formData['id'], $emptyProductId);
        }
    }
}
