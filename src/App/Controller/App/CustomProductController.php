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
use App\Service\LogService;
use App\Util\CommonUtils;
use App\Util\ResponseUtils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CustomProductController extends BaseController {

    const ENTITY_SINGULAR = 'custom_product';
    const ENTITY_PLURAL = 'custom_products';
    const MENU = MenuSection::MenuCustomProduct;


    public function getDAO() {
        return new ProductDAO($this->get('pdo'));
    }

    public function getNameForLogs($id) {
        return $this->getDAO()->getSingleField($id, 'name');
    }

    /**
     * Página de listado de productos
     */
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $this->prepareList();

        // Carga selectores para filtros
        $data['data'] = [
            'productStatus' => ProductStatus::getAll()
        ];

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    /**
     * Obtiene los datos para mostrar la datatable
     */
    public function datatable(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        return ResponseUtils::withJson($response, $this->getDAO()->getRemoteDatatable(true, $this->get('session')['user']['id']));
    }

    /**
     * Página de listado de productos para seleccionar
     */
    public function listSelect(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $this->get('logger')->addInfo(static::ENTITY_SINGULAR . ' - get select list');

        // Indica elemento del menú principal activo
        $data['menu'] = static::MENU;
        $data['breadcumb'] = [ucfirst(__('app.entity.' . static::ENTITY_PLURAL)), __('app.custom_product.select_title')];
        $data['title'] = implode(' > ', $data['breadcumb']);

        // Define la vista a utilizar
        $data['view'] = static::ENTITY_SINGULAR . '/select';

        // Carga los flash messages para mostrarlos en pantalla
        $data['messages'] = $this->get('flash')->getMessages();
        $data['entity'] = static::ENTITY_SINGULAR;

        // Javascripts a incluir
        $data['js'] = ['/assets/plugins/custom/datatables/datatables.bundle.js', '/js/datatables.custom.js', '/js/project/' . static::ENTITY_SINGULAR . '_select.datatable.js'];
        $data['css'] = ['/assets/plugins/custom/datatables/datatables.bundle.css'];

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    /**
     * Obtiene los datos para mostrar la datatable
     */
    public function datatableSelect(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        return ResponseUtils::withJson($response, $this->getDAO()->getRemoteDatatable(false, null, $this->get('session')['user']['market_id']));
    }

    public function load(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $this->get('security')->checkCustomProductOwner($args['id']);

        $data = $this->getDAO()->getById($args['id']);
        if (empty($data)) {
            throw new AuthException();
        }

        return ResponseUtils::withJson($response, $data);
    }

    /**
     * Prepara el formulario de crear/editar productos
     */
    public function form(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        if (empty($args['id'])) {
            $this->get('security')->checkProductOwner($args['parentProductId']);
        } else {
            $this->get('security')->checkCustomProductOwner($args['id']);
        }

        $data = $this->prepareForm($args);
        if (empty($args['id'])) {
            $data['data']['parent_product_id'] = $args['parentProductId'];
        }

        $cmykBorder = intval($this->get('params')->getParam('CMYK_BORDER')) * 2;
        $data['data']['recommendedDimensions'] = [
            2 => (2480 + $cmykBorder) . 'px x ' . (1754 + $cmykBorder) . 'px',
            3 => (2480 + $cmykBorder) . 'px x ' . (1169 + $cmykBorder) . 'px',
            6 => (1240 + $cmykBorder) . 'px x ' . (1169 + $cmykBorder) . 'px'
        ];

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    public function savePersist($request, $response, $args, &$formData) {
        if (empty($formData['id'])) {
            $this->get('security')->checkProductOwner($formData['parent_product_id']);
        } else {
            $this->get('security')->checkCustomProductOwner($formData['id']);
        }

        $data = [
            'name' => $formData['name'],
            'slug' =>  $formData['slug'],
            'subtitle_custom' =>   $formData['subtitle_custom'],
            'periodicity_custom' => $formData['periodicity_custom']
        ];

        if (empty($formData['id'])) {
            $data['parent_product_id'] = $formData['parent_product_id'];
            $data['creator_user_id'] = $this->get('session')['user']['id'];

            $formData['id'] = $this->getDAO()->saveCustom($data);
            LogService::save($this, 'app.log.action.save', [ucfirst(__('app.entity.' . static::ENTITY_PLURAL)), $this->getNameForLogs($formData['id'])], $this->getDAO()->getTable(), $formData['id']);
        } else {
            $data['id'] = $formData['id'];

            $this->getDAO()->updateCustom($data);
            LogService::save($this, 'app.log.action.update', [ucfirst(__('app.entity.' . static::ENTITY_PLURAL)), $this->getNameForLogs($formData['id'])], $this->getDAO()->getTable(), $formData['id']);
        }

        $this->saveFile($request, $formData['id'], 'image_custom_2', 'image_custom_2', FileType::ProductImage);
        $this->saveFile($request, $formData['id'], 'image_custom_3', 'image_custom_3', FileType::ProductImage);
        $this->saveFile($request, $formData['id'], 'image_custom_6', 'image_custom_6', FileType::ProductImage);

        $this->saveFile($request, $formData['id'], 'logo_custom', 'logo_custom', FileType::ProductImage);

        $this->saveFile($request, $formData['id'], 'photo_custom', 'photo_custom', FileType::ProductImage);
    }

    public function deletePreDelete($request, $response, $args, &$formData) {
        $this->get('security')->checkCustomProductOwner($formData['id']);

        $this->getDAO()->updateSingleField($formData['id'], 'date_deleted', date('Y-m-d H:i:s'));
        return true;
    }

    public function restore(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $formData = CommonUtils::getSanitizedData($request);
        $this->get('security')->checkCustomProductOwner($formData['id']);

        return parent::restore($request, $response, $args);
    }

    public function getRedirectUrlForm($formData, $new) {
        $parentProductId = !empty($formData['id']) ? $this->getDAO()->getSingleField($formData['id'], 'parent_product_id') : ($formData['parent_product_id'] ?? null);

        if (empty($parentProductId)) {
            return $this->getRedirectUrlList($formData);
        }

        $url = '/app/' . static::ENTITY_SINGULAR . '/form/' . $parentProductId;
        if (!$new && !empty($formData['id'])) {
            $url .= '/' . $formData['id'];
        }
        return $url;
    }
}
