<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Constant\App\MenuSection;
use App\Constant\FileType;
use App\Dao\MarketDAO;
use App\Dao\MarketProductDAO;
use App\Dao\ProductDAO;
use App\Exception\AuthException;
use App\Util\ResponseUtils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ProductController extends BaseController {

    const ENTITY_SINGULAR = 'product';
    const ENTITY_PLURAL = 'products';
    const MENU = MenuSection::MenuProduct;


    public function getDAO() {
        return new ProductDAO($this->get('pdo'));
    }


    public function getNameForLogs($id) {
        return $this->getDAO()->getSingleField($id, 'name');
    }

    public function load(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $data = $this->getDAO()->getById($args['id']);
        if (empty($data)) {
            throw new AuthException();
        }

        $marketProductDAO = new MarketProductDAO($this->get('pdo'));
        $data['market_ids'] = $marketProductDAO->getMarketsByProductId($args['id']);

        return ResponseUtils::withJson($response, $data);
    }

    /**
     * Página de listado de productos
     */
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $this->prepareList();

        // Carga selectores para filtros
        $marketDAO = new MarketDAO($this->get('pdo'));

        $data['data'] = [
            'markets' => $marketDAO->getForSelect()
        ];

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    /**
     * Prepara el formulario de crear/editar productos
     */
    public function form(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $data = $this->prepareForm($args);

        // Obtenemos los valores a mostrar en los desplegables
        $marketDAO = new MarketDAO($this->get('pdo'));
        $data['data']['markets'] = $marketDAO->getForSelect();

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    public function deletePreDelete($request, $response, $args, &$formData) {
        $marketProductDAO = new MarketProductDAO($this->get('pdo'));
        $marketProductDAO->clear($formData['id']);
    }

    public function savePreSave($request, $response, $args, &$formData) {
        if (!empty($formData['id'])) {
            $marketProductDAO = new MarketProductDAO($this->get('pdo'));
            $marketProductDAO->clear($formData['id']);
        }
    }

    public function savePersist($request, $response, $args, &$formData) {
        $marketIds = !empty($formData['market_ids']) ? $formData['market_ids'] : [];
        unset($formData['market_ids']);

        parent::savePersist($request, $response, $args, $formData);

        $marketProductDAO = new MarketProductDAO($this->get('pdo'));
        foreach ($marketIds as $marketId) {
            $marketProductDAO->save(['product_id' => $formData['id'], 'market_id' => $marketId]);
        }

        $this->saveFile($request, $formData['id'], 'image_es_2', 'image_es_2', FileType::ProductImage);
        $this->saveFile($request, $formData['id'], 'image_es_3', 'image_es_3', FileType::ProductImage);
        $this->saveFile($request, $formData['id'], 'image_es_6', 'image_es_6', FileType::ProductImage);
        $this->saveFile($request, $formData['id'], 'image_en_2', 'image_en_2', FileType::ProductImage);
        $this->saveFile($request, $formData['id'], 'image_en_3', 'image_en_3', FileType::ProductImage);
        $this->saveFile($request, $formData['id'], 'image_en_6', 'image_en_6', FileType::ProductImage);
        $this->saveFile($request, $formData['id'], 'image_fr_2', 'image_fr_2', FileType::ProductImage);
        $this->saveFile($request, $formData['id'], 'image_fr_3', 'image_fr_3', FileType::ProductImage);
        $this->saveFile($request, $formData['id'], 'image_fr_6', 'image_fr_6', FileType::ProductImage);
    }

    public function image(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $fileId = $this->getDAO()->getSingleField($args['id'], $args['field']);
        if (empty($fileId)) {
            throw new \Exception(__('app.error.file_not_found'), 404);
        }
        return parent::getFileById($response, $fileId, $args['field']);
    }
}
