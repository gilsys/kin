<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Constant\App\MenuSection;
use App\Constant\FileType;
use App\Constant\ProductStatus;
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
        $this->get('security')->checkProductOwner($args['id'], false, false);

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
            'markets' => $marketDAO->getForSelect(),
            'productStatus' => ProductStatus::getAll()
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

        $cmykBorder = intval($this->get('params')->getParam('CMYK_BORDER')) * 2;
        $data['data']['recommendedDimensions'] = [
            2 => (2480 + $cmykBorder) . 'px x ' . (1754 + $cmykBorder) . 'px',
            3 => (2480 + $cmykBorder) . 'px x ' . (1169 + $cmykBorder) . 'px',
            6 => (1240 + $cmykBorder) . 'px x ' . (1169 + $cmykBorder) . 'px'
        ];

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    public function deletePreDelete($request, $response, $args, &$formData) {
        if ($formData['id'] == $this->get('params')->getParam('EMPTY_PRODUCT')) {
            throw new AuthException();
        }

        $this->getDAO()->updateSingleField($formData['id'], 'date_deleted', date('Y-m-d H:i:s'));
        return true;
    }

    public function savePreSave($request, $response, $args, &$formData) {
        if (!empty($formData['id'])) {
            $marketProductDAO = new MarketProductDAO($this->get('pdo'));
            $marketProductDAO->clear($formData['id']);
        }
    }

    public function savePersist($request, $response, $args, &$formData) {
        $isNew = empty($formData['id']);

        $marketIds = !empty($formData['market_ids']) ? $formData['market_ids'] : [];
        unset($formData['market_ids']);

        if ($isNew) {
            $formData['creator_user_id'] = $this->get('session')['user']['id'];
        }

        parent::savePersist($request, $response, $args, $formData);

        if ($formData['id'] == $this->get('params')->getParam('EMPTY_PRODUCT')) {
            $marketDAO = new MarketDAO($this->get('pdo'));
            $marketIds = array_column($marketDAO->getForSelect(), 'id');
        }

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

        $this->saveFile($request, $formData['id'], 'logo_es', 'logo_es', FileType::ProductImage);
        $this->saveFile($request, $formData['id'], 'logo_en', 'logo_en', FileType::ProductImage);
        $this->saveFile($request, $formData['id'], 'logo_fr', 'logo_fr', FileType::ProductImage);

        $this->saveFile($request, $formData['id'], 'photo_es', 'photo_es', FileType::ProductImage);
        $this->saveFile($request, $formData['id'], 'photo_en', 'photo_en', FileType::ProductImage);
        $this->saveFile($request, $formData['id'], 'photo_fr', 'photo_fr', FileType::ProductImage);

        $this->saveFile($request, $formData['id'], 'zip_es', 'zip_es', FileType::ProductFile);
        $this->saveFile($request, $formData['id'], 'zip_en', 'zip_en', FileType::ProductFile);
        $this->saveFile($request, $formData['id'], 'zip_fr', 'zip_fr', FileType::ProductFile);

        if (!$isNew) {
            $this->getDAO()->updateCustomByParentProductId($formData['id']);
        }
    }

    public function image(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $product = $this->getDAO()->getById($args['id']);
        if (empty($product['parent_product_id'])) {
            $this->get('security')->checkProductOwner($product['id'], false, false, true);
        } else {
            $this->get('security')->checkCustomProductOwner($product['id']);
        }

        $fileId = $this->getDAO()->getSingleField($args['id'], $args['field']);
        if (empty($fileId)) {
            throw new \Exception(__('app.error.file_not_found'), 404);
        }
        return parent::getFileById($response, $fileId, $args['field']);
    }
}
