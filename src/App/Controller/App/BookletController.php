<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Constant\App\MenuSection;
use App\Constant\FileType;
use App\Constant\StaticListTable;
use App\Constant\UserProfile;
use App\Dao\BookletDAO;
use App\Dao\BookletLayoutDAO;
use App\Dao\BookletProductDAO;
use App\Dao\MarketDAO;
use App\Dao\ProductDAO;
use App\Dao\StaticListDAO;
use App\Dao\UserDAO;
use App\Exception\AuthException;
use App\Util\CommonUtils;
use App\Util\ResponseUtils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class BookletController extends BaseController {

    const ENTITY_SINGULAR = 'booklet';
    const ENTITY_PLURAL = 'booklets';
    const MENU = MenuSection::MenuBooklet;


    public function getDAO() {
        return new BookletDAO($this->get('pdo'));
    }


    public function getNameForLogs($id) {
        return $this->getDAO()->getSingleField($id, 'name');
    }

    /**
     * Página de listado de booklets
     */
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $this->prepareList();

        // Carga selectores para filtros
        $languageEntity = StaticListTable::getEntity(StaticListTable::Language);
        $languageDAO = new StaticListDAO($this->get('pdo'), 'st_' . $languageEntity);
        $marketDAO = new MarketDAO($this->get('pdo'));
        $userDAO = new UserDAO($this->get('pdo'));

        $data['data'] = [
            'markets' => $marketDAO->getForSelect(),
            'languages' => $languageDAO->getForSelect(),
            'users' => $userDAO->getForSelectFullname()
        ];

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    /**
     * Prepara el formulario de crear/editar booklets
     */
    public function form(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $data = $this->prepareForm($args);

        // Obtenemos los valores a mostrar en los desplegables
        $languageEntity = StaticListTable::getEntity(StaticListTable::Language);
        $languageDAO = new StaticListDAO($this->get('pdo'), 'st_' . $languageEntity);
        $marketDAO = new MarketDAO($this->get('pdo'));
        $bookletLayoutDAO = new BookletLayoutDAO($this->get('pdo'));

        $data['data']['markets'] = $marketDAO->getForSelect();
        $data['data']['languages'] = $languageDAO->getForSelect();

        $data['data']['layouts'] = $bookletLayoutDAO->getForSelect('id', 'name', 'custom_order');

        // Obtener el market del usuario y el idioma asociado a ese market
        $marketId = $this->get('session')['user']['market_id'];

        if (!empty($marketId)) {
            $marketData = $marketDAO->getById($marketId);
            $data['data']['default_main_language'] = $marketData['main_language_id'];
        } else {
            $data['data']['default_main_language'] = null;
        }

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    public function load(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $data = $this->getDAO()->getFullById($args['id']);
        if (empty($data)) {
            throw new AuthException();
        }

        $bookletProductDAO = new BookletProductDAO($this->get('pdo'));
        $data['booklet_products'] = $bookletProductDAO->getByBookletId($args['id']);

        return ResponseUtils::withJson($response, $data);
    }

    public function savePreSave($request, $response, $args, &$formData) {
        // Si es un nuevo booklet
        if (empty($formData['id'])) {
            $formData['creator_user_id'] = $this->get('session')['user']['id'];
        }

        // Si el usuario no es administrador, se asocia el market del usuario (o el que había si ya estaba creado)
        if ($this->get('session')['user']['user_profile_id'] != UserProfile::Administrator) {
            if (empty($formData['id'])) {
                $formData['market_id'] = $this->get('session')['user']['market_id'];
            } else {
                $formData['market_id'] = $this->getDAO()->getSingleField($formData['id'], 'market_id');
            }
        }

        if (!empty($formData['id'])) {
            $bookletProductDAO = new BookletProductDAO($this->get('pdo'));
            $bookletProductDAO->clear($formData['id']);
        }
    }

    public function savePersist($request, $response, $args, &$formData) {
        $bookletProducts = !empty($formData['booklet_product']) ? $formData['booklet_product'] : [];
        unset($formData['booklet_product']);

        parent::savePersist($request, $response, $args, $formData);

        $bookletProductDAO = new BookletProductDAO($this->get('pdo'));
        foreach ($bookletProducts as $page => $pageItems) {
            foreach ($pageItems as $order => $product) {
                $displayMode = key($product);
                $productId = current($product);

                $bookletProductDAO->save(['booklet_id' => $formData['id'], 'product_id' => $productId, 'page' => $page, 'custom_order' => $order, 'display_mode' => $displayMode]);
            }
        }
    }

    public function getProducts(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $formData = CommonUtils::getSanitizedData($request);
        if ($this->get('session')['user']['user_profile_id'] != UserProfile::Administrator) {
            if (empty($formData['id'])) {
                $formData['market_id'] = $this->get('session')['user']['market_id'];
            } else {
                $formData['market_id'] = $this->getDAO()->getSingleField($formData['id'], 'market_id');
            }
        }

        $productDAO = new ProductDAO($this->get('pdo'));
        $products = $productDAO->getByMarketId($formData['market_id']);

        return ResponseUtils::withJson($response, $products);
    }
};
